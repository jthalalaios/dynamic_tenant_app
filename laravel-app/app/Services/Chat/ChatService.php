<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatEvent;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatRoomChanged;
use App\Exceptions\BusinessRuleException;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rooms, messages and read markers of the current workspace's live chat.
 *
 * Realtime: messages go to the room's own channel (`private-chat.feed.{room}`), which
 * only members who may enter the room can join, so a restricted room never leaks.
 * Room and mute changes go to the workspace channel as signals to resync.
 */
class ChatService
{
    /**
     * Rooms every workspace starts with: a Global room for everyone, an announcements
     * room only administrators post in, a private room for administrators, and a room
     * per role listed for the workspace's theme.
     *
     * @var array<string, array{name: string, description: string, icon: string, is_default?: bool, is_locked?: bool, audience: string, roles?: list<string>}>
     */
    private const DEFAULT_ROOMS = [
        'general' => ['name' => 'chat_default_room_name', 'description' => 'chat_default_room_description', 'icon' => 'globe', 'is_default' => true, 'audience' => ChatRoom::AUDIENCE_EVERYONE],
        'announcements' => ['name' => 'chat_announcements_room_name', 'description' => 'chat_announcements_room_description', 'icon' => 'megaphone', 'is_locked' => true, 'audience' => ChatRoom::AUDIENCE_EVERYONE],
        'admins' => ['name' => 'chat_admins_room_name', 'description' => 'chat_admins_room_description', 'icon' => 'shield', 'audience' => ChatRoom::AUDIENCE_ADMINS],
    ];

    /** Role rooms per theme: slug => [lang keys, icon, role slug] */
    private const ROLE_ROOMS = [
        'doctor' => ['doctors' => ['name' => 'chat_doctors_room_name', 'description' => 'chat_doctors_room_description', 'icon' => 'heart', 'role' => 'doctor']],
        'marketplace' => ['managers' => ['name' => 'chat_managers_room_name', 'description' => 'chat_managers_room_description', 'icon' => 'briefcase', 'role' => 'manager']],
    ];

    public function __construct(
        private readonly ChatModeration $moderation,
        private readonly ChatBroadcaster $broadcaster,
    ) {}

    /**
     * Lazily set up a workspace that has no rooms at all yet.
     */
    public function ensureDefaultRooms(): void
    {
        if (! ChatRoom::query()->exists()) {
            $this->provisionDefaultRooms();
        }
    }

    /**
     * Create the current workspace's default rooms that do not exist yet (by slug).
     * Idempotent and safe under concurrent requests.
     */
    public function provisionDefaultRooms(): void
    {
        $tenant = tenant();
        if ($tenant === null) {
            return;
        }

        // Named in the organisation's language, not in that of whoever opens the chat first
        $locale = $tenant->locale;
        $definitions = self::DEFAULT_ROOMS;

        foreach (self::ROLE_ROOMS[$tenant->type->value] ?? [] as $slug => $roleRoom) {
            $definitions[$slug] = [...$roleRoom, 'audience' => ChatRoom::AUDIENCE_ROLES, 'roles' => [$roleRoom['role']]];
        }

        foreach ($definitions as $slug => $definition) {
            $roles = isset($definition['roles'])
                ? Role::query()->whereIn('slug', $definition['roles'])->pluck('id')->all()
                : [];

            // A role room whose role does not exist in this workspace would be empty: skip it
            if (isset($definition['roles']) && $roles === []) {
                continue;
            }

            $room = ChatRoom::query()->createOrFirst(['tenant_id' => $tenant->id, 'slug' => $slug], [
                'name' => __("messages.{$definition['name']}", [], $locale),
                'description' => __("messages.{$definition['description']}", [], $locale),
                'icon' => $definition['icon'],
                'is_default' => $definition['is_default'] ?? false,
                'is_locked' => $definition['is_locked'] ?? false,
                'audience' => $definition['audience'],
            ]);

            if ($room->wasRecentlyCreated && $roles !== []) {
                $room->roles()->sync($roles);
            }
        }
    }

    /**
     * Rooms the viewer may enter, with their unread count and newest message.
     *
     * @return Collection<int, ChatRoom>
     */
    public function roomsFor(User $viewer): Collection
    {
        return ChatRoom::query()
            ->accessibleBy($viewer)
            ->withCount(['messages as unread_count' => fn (Builder $q) => $this->unread($q, $viewer)])
            ->with(['latestMessage.user', 'roles:id,name,slug'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, description?: ?string, icon?: string, is_locked?: bool, audience?: string, role_ids?: list<int>}  $data
     */
    public function createRoom(array $data, User $actor): ChatRoom
    {
        $this->guardLock($data, $actor);
        [$audience, $roleIds] = $this->resolveAudience($data, $actor);

        $room = DB::transaction(function () use ($data, $actor, $audience, $roleIds) {
            $room = ChatRoom::query()->create([
                ...collect($data)->except(['audience', 'role_ids'])->all(),
                'audience' => $audience,
                'slug' => $this->uniqueSlug($data['name']),
                'created_by' => $actor->id,
            ]);
            $room->roles()->sync($roleIds);

            return $room;
        });

        $this->signalRoom($room, 'created');

        return $room->refresh()->load('roles:id,name,slug');
    }

    /**
     * @param  array{name?: string, description?: ?string, icon?: string, is_locked?: bool, audience?: string, role_ids?: list<int>}  $data
     */
    public function updateRoom(ChatRoom $room, array $data, User $actor): ChatRoom
    {
        $this->guardLock($data, $actor, $room);
        $changesAudience = array_key_exists('audience', $data) || array_key_exists('role_ids', $data);

        if ($changesAudience && $room->is_default) {
            throw new BusinessRuleException(__('messages.chat_default_room_audience'), 'chat_default_room_audience', 422);
        }

        DB::transaction(function () use ($room, $data, $actor, $changesAudience) {
            $room->update(collect($data)->except(['audience', 'role_ids'])->all());

            if ($changesAudience) {
                [$audience, $roleIds] = $this->resolveAudience(
                    ['audience' => $data['audience'] ?? $room->audience, 'role_ids' => $data['role_ids'] ?? $room->roles()->pluck('roles.id')->all()],
                    $actor,
                );
                $room->update(['audience' => $audience]);
                $room->roles()->sync($roleIds);
            }
        });

        $this->signalRoom($room, 'updated');

        return $room->refresh()->load('roles:id,name,slug');
    }

    public function deleteRoom(ChatRoom $room): void
    {
        if ($room->is_default) {
            throw new BusinessRuleException(__('messages.chat_default_room'), 'chat_default_room', 422);
        }

        $room->delete();
        $this->signalRoom($room, 'deleted');
    }

    /**
     * A page of history, oldest first, ending just before `$beforeId` (or at the newest message).
     *
     * @return Collection<int, ChatMessage>
     */
    public function history(ChatRoom $room, ?int $beforeId, int $limit): Collection
    {
        return $room->messages()
            ->with('user')
            ->when($beforeId, fn (Builder $q, int $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function send(ChatRoom $room, User $author, string $body): ChatMessage
    {
        if ($room->is_locked && ! $author->isAdmin()) {
            throw new BusinessRuleException(__('messages.chat_room_locked'), 'chat_room_locked', 403);
        }

        $mute = $this->moderation->activeMuteFor($author, $room);
        if ($mute !== null) {
            $message = $mute->expires_at === null
                ? __('messages.chat_muted')
                : __('messages.chat_muted_until', ['time' => $mute->expires_at->copy()->setTimezone(user_timezone($author))->format('Y-m-d H:i')]);

            throw new BusinessRuleException($message, 'chat_muted', 403);
        }

        $message = DB::transaction(function () use ($room, $author, $body) {
            $message = $room->messages()->create([
                'tenant_id' => $room->tenant_id,
                'user_id' => $author->id,
                'body' => $body,
            ]);

            // Your own message never counts as unread
            $this->markRead($room, $author, $message->id);

            return $message;
        });

        $message->setRelation('user', $author);
        $this->broadcaster->send(new ChatMessageSent(ChatEvent::roomChannel($room->id), (new ChatMessageResource($message))->resolve()));

        return $message;
    }

    public function deleteMessage(ChatMessage $message, User $actor): void
    {
        $message->loadMissing('user');

        if (! $this->moderation->canDeleteMessage($actor, $message)) {
            throw new BusinessRuleException(__('messages.chat_cannot_moderate'), 'chat_cannot_moderate', 403);
        }

        DB::transaction(function () use ($message, $actor) {
            $message->update(['deleted_by' => $actor->id]);
            $message->delete();
        });

        $this->broadcaster->send(new ChatMessageDeleted(ChatEvent::roomChannel($message->room_id), [
            'id' => $message->id,
            'room_id' => $message->room_id,
            'deleted_by' => $actor->id,
        ]));
    }

    /**
     * Move the viewer's read marker forward (never backwards) to a message, or to the newest one.
     */
    public function markRead(ChatRoom $room, User $viewer, ?int $messageId = null): void
    {
        $messageId ??= (int) $room->messages()->max('id');

        if ($messageId <= 0) {
            return;
        }

        $current = (int) DB::table('chat_room_reads')
            ->where('room_id', $room->id)
            ->where('user_id', $viewer->id)
            ->value('last_read_message_id');

        if ($messageId > $current) {
            DB::table('chat_room_reads')->upsert(
                [['room_id' => $room->id, 'user_id' => $viewer->id, 'last_read_message_id' => $messageId, 'updated_at' => now()]],
                ['room_id', 'user_id'],
                ['last_read_message_id', 'updated_at'],
            );
        }
    }

    /**
     * Who a room is for, validated against what the actor may grant:
     *
     * - administrators may pick any audience;
     * - others may open a room to everyone, or to roles they hold themselves
     *   (never "administrators only", never a room they would be locked out of).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: list<int>}
     */
    private function resolveAudience(array $data, User $actor): array
    {
        $audience = $data['audience'] ?? ChatRoom::AUDIENCE_EVERYONE;
        $roleIds = $audience === ChatRoom::AUDIENCE_ROLES ? array_values(array_unique(array_map('intval', $data['role_ids'] ?? []))) : [];

        if ($audience === ChatRoom::AUDIENCE_ROLES && $roleIds === []) {
            throw new BusinessRuleException(__('messages.chat_audience_roles_required'), 'chat_audience_roles_required', 422);
        }

        if (! $actor->isAdmin()) {
            $ownRoleIds = $actor->roles()->pluck('roles.id')->all();
            $allowed = $audience === ChatRoom::AUDIENCE_EVERYONE
                || ($audience === ChatRoom::AUDIENCE_ROLES && array_intersect($roleIds, $ownRoleIds) !== []);

            if (! $allowed) {
                throw new BusinessRuleException(__('messages.chat_audience_forbidden'), 'chat_audience_forbidden', 403);
            }
        }

        return [$audience, $roleIds];
    }

    /**
     * Rooms changed: tell the workspace to resync its room list. Only the id goes
     * out, so a restricted room's name never reaches members who cannot see it.
     */
    private function signalRoom(ChatRoom $room, string $action): void
    {
        $this->broadcaster->send(new ChatRoomChanged($room->tenant_id, $action, ['id' => $room->id]));
    }

    /**
     * Messages of others newer than the viewer's read marker.
     *
     * @param  Builder<ChatMessage>  $query
     */
    private function unread(Builder $query, User $viewer): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('chat_messages.user_id')->orWhere('chat_messages.user_id', '!=', $viewer->id))
            ->whereRaw(
                'chat_messages.id > coalesce((select r.last_read_message_id from chat_room_reads r where r.room_id = chat_messages.room_id and r.user_id = ?), 0)',
                [$viewer->id],
            );
    }

    /**
     * Locking a room silences everyone but administrators, so only they may toggle it.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardLock(array $data, User $actor, ?ChatRoom $room = null): void
    {
        if (! array_key_exists('is_locked', $data) || $actor->isAdmin()) {
            return;
        }

        $changes = $room === null ? (bool) $data['is_locked'] : (bool) $data['is_locked'] !== $room->is_locked;

        if ($changes) {
            throw new BusinessRuleException(__('messages.chat_admin_only'), 'chat_admin_only', 403);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'room';
        $slug = $base;
        $n = 2;

        while (ChatRoom::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
