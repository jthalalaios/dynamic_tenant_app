<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatMuteChanged;
use App\Exceptions\BusinessRuleException;
use App\Http\Resources\ChatMuteResource;
use App\Models\ChatMessage;
use App\Models\ChatMute;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chat moderation rules, in one place:
 *
 * - Administrators may moderate every member, other administrators included.
 * - Members holding `chat.mute` may moderate everyone except administrators.
 * - Everyone else may only chat. Nobody moderates themselves.
 *
 * The same rule decides who may delete someone else's message.
 */
class ChatModeration
{
    public function __construct(
        private readonly ChatDirectory $directory,
        private readonly ChatBroadcaster $broadcaster,
    ) {}

    public function canModerate(User $actor, ?User $target): bool
    {
        if ($target === null || $actor->is($target) || (int) $actor->tenant_id !== (int) $target->tenant_id) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->hasPermission('chat.mute') && ! $this->directory->isAdmin($target->id);
    }

    public function canDeleteMessage(User $actor, ChatMessage $message): bool
    {
        if ($message->user_id !== null && (int) $message->user_id === (int) $actor->id) {
            return true;
        }

        // Messages of a removed account: the workspace's moderators may clean them up
        if ($message->user === null) {
            return (int) $message->tenant_id === (int) $actor->tenant_id
                && ($actor->isAdmin() || $actor->hasPermission('chat.mute'));
        }

        return $this->canModerate($actor, $message->user);
    }

    /**
     * Mute (or re-mute, replacing the previous terms) a member in one room or everywhere.
     */
    public function mute(User $actor, User $target, ?ChatRoom $room, ?int $minutes, ?string $reason): ChatMute
    {
        $this->authorize($actor, $target);

        $mute = DB::transaction(fn () => ChatMute::query()->updateOrCreate(
            ['user_id' => $target->id, 'room_id' => $room?->id],
            [
                'muted_by' => $actor->id,
                'reason' => $reason,
                'expires_at' => $minutes !== null ? now()->addMinutes($minutes) : null,
            ],
        ));

        $mute->load(['user', 'mutedBy']);
        $this->broadcaster->send(new ChatMuteChanged($mute->tenant_id, 'muted', (new ChatMuteResource($mute))->resolve()));

        return $mute;
    }

    public function unmute(User $actor, ChatMute $mute): void
    {
        $mute->loadMissing(['user', 'mutedBy']);
        $this->authorize($actor, $mute->user);

        $payload = (new ChatMuteResource($mute))->resolve();
        $mute->delete();

        $this->broadcaster->send(new ChatMuteChanged($mute->tenant_id, 'unmuted', $payload));
    }

    /**
     * The mute silencing a member in a room, if any. A permanent mute wins over
     * a timed one, otherwise the one that ends last.
     */
    public function activeMuteFor(User $user, ChatRoom $room): ?ChatMute
    {
        return ChatMute::query()
            ->active()
            ->covering($room->id)
            ->where('user_id', $user->id)
            ->get()
            ->sortByDesc(fn (ChatMute $m) => $m->expires_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * @return Collection<int, ChatMute>
     */
    public function activeMutes(): Collection
    {
        return ChatMute::query()
            ->active()
            ->with(['user', 'mutedBy'])
            ->orderBy('created_at')
            ->get();
    }

    private function authorize(User $actor, ?User $target): void
    {
        if (! $this->canModerate($actor, $target)) {
            throw new BusinessRuleException(__('messages.chat_cannot_moderate'), 'chat_cannot_moderate', 403);
        }
    }
}
