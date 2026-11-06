<?php

use App\Enums\TenantType;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatMuteChanged;
use App\Events\Chat\ChatRoomChanged;
use App\Models\ChatMessage;
use App\Models\ChatMute;
use App\Models\ChatRoom;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

/*
| Clinic roles (config/permissions.php):
|   admin        → every permission, may moderate everyone (admins included), enters every room
|   doctor       → chat.view + chat.mute: moderates everyone except admins; enters the Doctors room
|   receptionist → chat.view only: can only chat
|   member       → chat.view only (self-registered users)
*/

beforeEach(function () {
    Event::fake([ChatMessageSent::class, ChatMessageDeleted::class, ChatMuteChanged::class, ChatRoomChanged::class]);

    $this->clinic = $this->createTenant(TenantType::Doctor);
    $this->admin = $this->createUser($this->clinic, 'admin');
    $this->moderator = $this->createUser($this->clinic, 'doctor');
    $this->member = $this->createUser($this->clinic, 'receptionist');

    // First listing provisions the default rooms
    $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')->assertOk();
    $room = fn (string $slug) => ChatRoom::query()->withoutGlobalScopes()->where('tenant_id', $this->clinic->id)->where('slug', $slug)->firstOrFail();
    $this->general = $room('general');
    $this->announcements = $room('announcements');
    $this->adminsRoom = $room('admins');
    $this->doctorsRoom = $room('doctors');
});

function postMessage($test, $user, ChatRoom $room, string $body = 'Hello')
{
    Passport::actingAs($user, [], 'api');

    return $test->postJson("/api/v1/chat/rooms/{$room->id}/messages", ['body' => $body]);
}

function mute($test, $actor, $target, array $extra = [])
{
    Passport::actingAs($actor, [], 'api');

    return $test->postJson('/api/v1/chat/mutes', ['user_id' => $target->id, ...$extra]);
}

function authorizeChannel($test, $user, string $channel)
{
    Passport::actingAs($user, [], 'api');

    return $test->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);
}

function roomSlugs($test, $user): array
{
    Passport::actingAs($user, [], 'api');

    return collect($test->getJson('/api/v1/chat/rooms')->assertOk()->json('data'))->pluck('slug')->all();
}

it('provisions a Global room for everyone plus admin and role rooms', function () {
    $this->actingAsUser($this->admin)->getJson('/api/v1/chat/rooms')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'general')
        ->assertJsonPath('data.0.name', 'Global')
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonPath('data.0.audience', 'everyone')
        ->assertJsonPath('meta.mutes', []);

    expect($this->announcements->is_locked)->toBeTrue()
        ->and($this->adminsRoom->audience)->toBe('admins')
        ->and($this->doctorsRoom->audience)->toBe('roles')
        ->and($this->doctorsRoom->roles()->pluck('slug')->all())->toBe(['doctor']);
});

it('shows each member only the rooms of their audience', function () {
    expect(roomSlugs($this, $this->admin))->toEqualCanonicalizing(['general', 'announcements', 'admins', 'doctors'])
        ->and(roomSlugs($this, $this->moderator))->toEqualCanonicalizing(['general', 'announcements', 'doctors'])
        ->and(roomSlugs($this, $this->member))->toEqualCanonicalizing(['general', 'announcements']);
});

it('hides restricted rooms everywhere, not just in the list', function () {
    // Reading, posting and the members list of a room outside your audience are all 404s
    $this->actingAsUser($this->member)->getJson("/api/v1/chat/rooms/{$this->doctorsRoom->id}/messages")->assertNotFound();
    postMessage($this, $this->member, $this->doctorsRoom)->assertNotFound();
    postMessage($this, $this->moderator, $this->adminsRoom)->assertNotFound();
    $this->actingAsUser($this->member)->getJson("/api/v1/chat/members?room={$this->adminsRoom->id}")->assertNotFound();

    // ...and their messages cannot be targeted by id either
    $secret = postMessage($this, $this->admin, $this->adminsRoom, 'admins only')->assertCreated()->json('data.id');
    $this->actingAsUser($this->moderator)->deleteJson("/api/v1/chat/messages/{$secret}")->assertNotFound();

    // Doctors and admins share the Doctors room
    postMessage($this, $this->moderator, $this->doctorsRoom, 'for doctors')->assertCreated();
    $this->actingAsUser($this->admin)->getJson("/api/v1/chat/rooms/{$this->doctorsRoom->id}/messages")
        ->assertJsonPath('data.0.body', 'for doctors');
});

it('lists a room’s members by its audience', function () {
    $ids = fn ($room) => collect($this->actingAsUser($this->admin)->getJson("/api/v1/chat/members?room={$room->id}")->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids($this->general))->toBe(collect([$this->admin->id, $this->moderator->id, $this->member->id])->sort()->values()->all())
        ->and($ids($this->doctorsRoom))->toBe(collect([$this->admin->id, $this->moderator->id])->sort()->values()->all())
        ->and($ids($this->adminsRoom))->toBe([$this->admin->id]);
});

it('lets every chat member post in the Global room and broadcasts on the room channel', function () {
    postMessage($this, $this->member, $this->general, '  Good morning  ')
        ->assertCreated()
        ->assertJsonPath('data.body', 'Good morning')
        ->assertJsonPath('data.user.id', $this->member->id)
        ->assertJsonPath('data.user.is_admin', false);

    Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $e) => $e->payload['body'] === 'Good morning'
        && $e->broadcastOn()[0]->name === "private-chat.feed.{$this->general->id}");

    postMessage($this, $this->member, $this->general, '   ')->assertJsonValidationErrors('body');
});

it('blocks users without chat.view', function () {
    $outsider = $this->createUser($this->clinic);

    $this->actingAsUser($outsider)->getJson('/api/v1/chat/rooms')->assertForbidden();
    postMessage($this, $outsider, $this->general)->assertForbidden();
});

it('only lets administrators post in a locked room', function () {
    postMessage($this, $this->member, $this->announcements)->assertForbidden()->assertJsonPath('code', 'chat_room_locked');
    postMessage($this, $this->moderator, $this->announcements)->assertForbidden()->assertJsonPath('code', 'chat_room_locked');
    postMessage($this, $this->admin, $this->announcements)->assertCreated();
});

it('lets a moderator mute a member in one room only', function () {
    mute($this, $this->moderator, $this->member, ['room_id' => $this->general->id, 'duration_minutes' => 60, 'reason' => 'Spam'])
        ->assertCreated()
        ->assertJsonPath('data.user.id', $this->member->id)
        ->assertJsonPath('data.room_id', $this->general->id)
        ->assertJsonPath('data.reason', 'Spam')
        ->assertJsonPath('data.muted_by.id', $this->moderator->id);

    Event::assertDispatched(ChatMuteChanged::class, fn (ChatMuteChanged $e) => $e->action === 'muted'
        && $e->broadcastOn()[0]->name === "private-chat.tenant.{$this->clinic->id}");

    postMessage($this, $this->member, $this->general)->assertForbidden()->assertJsonPath('code', 'chat_muted');

    // Other rooms are unaffected
    $frontDesk = ChatRoom::query()->withoutGlobalScopes()->create(['tenant_id' => $this->clinic->id, 'name' => 'Front desk', 'slug' => 'front-desk']);
    postMessage($this, $this->member, $frontDesk)->assertCreated();
});

it('cannot scope a mute to a room the moderator cannot enter', function () {
    mute($this, $this->moderator, $this->member, ['room_id' => $this->adminsRoom->id])->assertNotFound();
});

it('mutes everywhere when no room is given, until lifted', function () {
    mute($this, $this->moderator, $this->member)->assertCreated()->assertJsonPath('data.room_id', null)->assertJsonPath('data.expires_at', null);

    postMessage($this, $this->member, $this->general)->assertForbidden()->assertJsonPath('code', 'chat_muted');

    $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')->assertJsonCount(1, 'meta.mutes');
});

it('lets timed mutes expire on their own', function () {
    mute($this, $this->moderator, $this->member, ['duration_minutes' => 15])->assertCreated();
    postMessage($this, $this->member, $this->general)->assertForbidden();

    $this->travel(16)->minutes();

    postMessage($this, $this->member, $this->general)->assertCreated();
    $this->actingAsUser($this->member)->getJson('/api/v1/chat/mutes')->assertJsonCount(0, 'data');
});

it('only accepts the offered mute durations', function () {
    mute($this, $this->moderator, $this->member, ['duration_minutes' => 7])->assertJsonValidationErrors('duration_minutes');
});

it('re-muting replaces the previous terms instead of stacking', function () {
    mute($this, $this->moderator, $this->member, ['duration_minutes' => 15])->assertCreated();
    mute($this, $this->moderator, $this->member)->assertCreated()->assertJsonPath('data.expires_at', null);

    expect(ChatMute::query()->withoutGlobalScopes()->where('user_id', $this->member->id)->count())->toBe(1);
});

it('never lets moderators without admin rights mute administrators', function () {
    mute($this, $this->moderator, $this->admin)->assertForbidden()->assertJsonPath('code', 'chat_cannot_moderate');

    expect(ChatMute::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('lets administrators mute everyone, other administrators and moderators included', function () {
    $otherAdmin = $this->createUser($this->clinic, 'admin');

    mute($this, $this->admin, $this->moderator)->assertCreated();
    mute($this, $this->admin, $otherAdmin)->assertCreated();
    mute($this, $this->admin, $this->member)->assertCreated();

    postMessage($this, $otherAdmin, $this->general)->assertForbidden()->assertJsonPath('code', 'chat_muted');
});

it('lets members without chat.mute only chat', function () {
    mute($this, $this->member, $this->moderator)->assertForbidden();
});

it('refuses self mutes and members of another workspace', function () {
    mute($this, $this->admin, $this->admin)->assertForbidden()->assertJsonPath('code', 'chat_cannot_moderate');

    $shop = $this->createTenant(TenantType::Marketplace);
    $stranger = $this->createUser($shop, 'sales');
    mute($this, $this->admin, $stranger)->assertJsonValidationErrors('user_id');
});

it('applies the same hierarchy to lifting mutes', function () {
    $adminMute = mute($this, $this->admin, $this->createUser($this->clinic, 'admin'))->json('data.id');
    $memberMute = mute($this, $this->admin, $this->member)->json('data.id');

    // A moderator cannot lift an administrator's mute (they could not have set it either)
    $this->actingAsUser($this->moderator)->deleteJson("/api/v1/chat/mutes/{$adminMute}")->assertForbidden();

    $this->actingAsUser($this->moderator)->deleteJson("/api/v1/chat/mutes/{$memberMute}")->assertNoContent();
    Event::assertDispatched(ChatMuteChanged::class, fn (ChatMuteChanged $e) => $e->action === 'unmuted' && $e->payload['mute']['id'] === $memberMute);

    postMessage($this, $this->member, $this->general)->assertCreated();
});

it('applies the moderation hierarchy to deleting messages', function () {
    $mine = postMessage($this, $this->member, $this->general)->json('data.id');
    $moderators = postMessage($this, $this->moderator, $this->general)->json('data.id');
    $admins = postMessage($this, $this->admin, $this->general)->json('data.id');

    // Members delete only their own messages
    $this->actingAsUser($this->member)->deleteJson("/api/v1/chat/messages/{$moderators}")->assertForbidden();
    $this->actingAsUser($this->member)->deleteJson("/api/v1/chat/messages/{$mine}")->assertNoContent();

    // Moderators cannot touch administrators' messages
    $this->actingAsUser($this->moderator)->deleteJson("/api/v1/chat/messages/{$admins}")->assertForbidden();

    // Administrators can remove anyone's
    $this->actingAsUser($this->admin)->deleteJson("/api/v1/chat/messages/{$moderators}")->assertNoContent();

    Event::assertDispatched(ChatMessageDeleted::class, 2);
    expect(ChatMessage::withTrashed()->withoutGlobalScopes()->find($moderators)->deleted_by)->toBe($this->admin->id);

    $this->actingAsUser($this->member)->getJson("/api/v1/chat/rooms/{$this->general->id}/messages")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $admins);
});

describe('room management', function () {
    beforeEach(function () {
        // A non-admin room manager who is also a doctor
        $keeperRole = Role::query()->withoutGlobalScopes()->create(['tenant_id' => $this->clinic->id, 'name' => 'Room keeper', 'slug' => 'room-keeper']);
        $keeperRole->permissions()->sync(Permission::query()->whereIn('name', ['chat.view', 'chat.manage'])->pluck('id'));
        $this->keeper = $this->createUser($this->clinic, 'room-keeper', 'doctor');
        $this->doctorRoleId = Role::query()->withoutGlobalScopes()->where('tenant_id', $this->clinic->id)->where('slug', 'doctor')->value('id');
        $this->receptionRoleId = Role::query()->withoutGlobalScopes()->where('tenant_id', $this->clinic->id)->where('slug', 'receptionist')->value('id');
    });

    it('is limited to chat.manage holders and signals the workspace without leaking names', function () {
        $this->actingAsUser($this->member)->postJson('/api/v1/chat/rooms', ['name' => 'Lounge'])->assertForbidden();

        $roomId = $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Night shift', 'icon' => 'bolt', 'description' => 'After hours'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'night-shift')
            ->assertJsonPath('data.audience', 'everyone')
            ->json('data.id');

        Event::assertDispatched(ChatRoomChanged::class, fn (ChatRoomChanged $e) => $e->action === 'created'
            && $e->payload['room'] === ['id' => $roomId]
            && $e->broadcastOn()[0]->name === "private-chat.tenant.{$this->clinic->id}");

        $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Night shift'])->assertJsonValidationErrors('name');
        $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Bad icon', 'icon' => 'skull'])->assertJsonValidationErrors('icon');
    });

    it('lets administrators open rooms to admins only or to any roles', function () {
        $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Board', 'audience' => 'admins'])
            ->assertCreated()->assertJsonPath('data.audience', 'admins');

        $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Front office', 'audience' => 'roles', 'role_ids' => [$this->receptionRoleId]])
            ->assertCreated()
            ->assertJsonPath('data.roles.0.slug', 'receptionist');

        expect(roomSlugs($this, $this->member))->toContain('front-office')->not->toContain('board')
            ->and(roomSlugs($this, $this->moderator))->not->toContain('front-office')->not->toContain('board');

        $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Empty', 'audience' => 'roles', 'role_ids' => []])
            ->assertJsonPath('code', 'chat_audience_roles_required');
    });

    it('lets non-admin managers open rooms only to everyone or to their own roles', function () {
        $this->actingAsUser($this->keeper)->postJson('/api/v1/chat/rooms', ['name' => 'Doctors lounge', 'audience' => 'roles', 'role_ids' => [$this->doctorRoleId]])
            ->assertCreated();
        $this->actingAsUser($this->keeper)->postJson('/api/v1/chat/rooms', ['name' => 'Open lounge'])->assertCreated();

        $this->actingAsUser($this->keeper)->postJson('/api/v1/chat/rooms', ['name' => 'Board', 'audience' => 'admins'])
            ->assertForbidden()->assertJsonPath('code', 'chat_audience_forbidden');
        $this->actingAsUser($this->keeper)->postJson('/api/v1/chat/rooms', ['name' => 'Desk', 'audience' => 'roles', 'role_ids' => [$this->receptionRoleId]])
            ->assertForbidden()->assertJsonPath('code', 'chat_audience_forbidden');
    });

    it('lets non-admin managers rename but not lock rooms', function () {
        $roomId = $this->actingAsUser($this->admin)->postJson('/api/v1/chat/rooms', ['name' => 'Night shift'])->json('data.id');

        $this->actingAsUser($this->keeper)->patchJson("/api/v1/chat/rooms/{$roomId}", ['name' => 'Night owls'])->assertOk()->assertJsonPath('data.name', 'Night owls');
        $this->actingAsUser($this->keeper)->patchJson("/api/v1/chat/rooms/{$roomId}", ['is_locked' => true])->assertForbidden()->assertJsonPath('code', 'chat_admin_only');
        $this->actingAsUser($this->admin)->patchJson("/api/v1/chat/rooms/{$roomId}", ['is_locked' => true])->assertOk()->assertJsonPath('data.is_locked', true);

        // Narrowing the audience takes the room away from those no longer in it
        $this->actingAsUser($this->admin)->patchJson("/api/v1/chat/rooms/{$roomId}", ['audience' => 'admins'])->assertOk();
        expect(roomSlugs($this, $this->member))->not->toContain('night-shift');

        $this->actingAsUser($this->admin)->deleteJson("/api/v1/chat/rooms/{$roomId}")->assertNoContent();
        Event::assertDispatched(ChatRoomChanged::class, fn (ChatRoomChanged $e) => $e->action === 'deleted' && $e->payload['room']['id'] === $roomId);
    });

    it('keeps the Global room open to everyone and undeletable', function () {
        $this->actingAsUser($this->admin)->patchJson("/api/v1/chat/rooms/{$this->general->id}", ['audience' => 'admins'])
            ->assertUnprocessable()->assertJsonPath('code', 'chat_default_room_audience');
        $this->actingAsUser($this->admin)->deleteJson("/api/v1/chat/rooms/{$this->general->id}")
            ->assertUnprocessable()->assertJsonPath('code', 'chat_default_room');
    });

    it('gives room managers the workspace roles to pick from', function () {
        $roles = collect($this->actingAsUser($this->keeper)->getJson('/api/v1/chat/rooms')->json('meta.roles'));

        expect($roles->firstWhere('slug', 'doctor')['assignable'])->toBeTrue()
            ->and($roles->firstWhere('slug', 'receptionist')['assignable'])->toBeFalse();
        $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')->assertJsonPath('meta.roles', []);
    });
});

it('tracks unread messages per member', function () {
    postMessage($this, $this->moderator, $this->general, 'One');
    $last = postMessage($this, $this->moderator, $this->general, 'Two')->json('data.id');

    $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')
        ->assertJsonPath('data.0.unread_count', 2)
        ->assertJsonPath('data.0.last_message.body', 'Two');

    // The author's own messages never count as unread
    $this->actingAsUser($this->moderator)->getJson('/api/v1/chat/rooms')->assertJsonPath('data.0.unread_count', 0);

    $this->actingAsUser($this->member)->postJson("/api/v1/chat/rooms/{$this->general->id}/read", ['message_id' => $last])->assertNoContent();
    $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')->assertJsonPath('data.0.unread_count', 0);

    // The marker never moves backwards
    $this->actingAsUser($this->member)->postJson("/api/v1/chat/rooms/{$this->general->id}/read", ['message_id' => 1])->assertNoContent();
    $this->actingAsUser($this->member)->getJson('/api/v1/chat/rooms')->assertJsonPath('data.0.unread_count', 0);
});

it('pages history backwards, oldest first', function () {
    $ids = collect(range(1, 5))->map(fn ($i) => postMessage($this, $this->member, $this->general, "m{$i}")->json('data.id'));

    $this->actingAsUser($this->member)->getJson("/api/v1/chat/rooms/{$this->general->id}/messages?limit=2")
        ->assertJsonPath('data.*.body', ['m4', 'm5'])
        ->assertJsonPath('meta.has_more', true);

    $this->actingAsUser($this->member)->getJson("/api/v1/chat/rooms/{$this->general->id}/messages?limit=2&before={$ids[1]}")
        ->assertJsonPath('data.*.body', ['m1'])
        ->assertJsonPath('meta.has_more', false);
});

it('keeps rooms, messages and mutes inside their workspace', function () {
    $shop = $this->createTenant(TenantType::Marketplace);
    $shopAdmin = $this->createUser($shop, 'admin');

    $this->actingAsUser($shopAdmin)->getJson("/api/v1/chat/rooms/{$this->general->id}/messages")->assertNotFound();
    postMessage($this, $shopAdmin, $this->general)->assertNotFound();
    expect(roomSlugs($this, $shopAdmin))->toEqualCanonicalizing(['general', 'announcements', 'admins', 'managers']);
});

it('lists the members who can chat, flagging administrators', function () {
    $this->createUser($this->clinic); // no role: cannot chat

    $members = $this->actingAsUser($this->member)->getJson('/api/v1/chat/members')->assertOk()->json('data');

    expect(collect($members)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->admin->id, $this->moderator->id, $this->member->id])->sort()->values()->all())
        ->and(collect($members)->firstWhere('id', $this->admin->id)['is_admin'])->toBeTrue();
});

describe('websocket channel authorization', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => '1',
                'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            ],
        ]);
        // Channels registered at boot went to the "null" broadcaster; register them on reverb
        require base_path('routes/channels.php');
    });

    it('lets chat members join their own workspace channel only', function () {
        authorizeChannel($this, $this->member, "private-chat.tenant.{$this->clinic->id}")->assertOk()->assertJsonStructure(['auth']);

        $shop = $this->createTenant(TenantType::Marketplace);
        authorizeChannel($this, $this->member, "private-chat.tenant.{$shop->id}")->assertForbidden();
        authorizeChannel($this, $this->createUser($this->clinic), "private-chat.tenant.{$this->clinic->id}")->assertForbidden();
    });

    it('only lets a room’s audience receive its messages', function () {
        authorizeChannel($this, $this->member, "private-chat.feed.{$this->general->id}")->assertOk();
        authorizeChannel($this, $this->member, "private-chat.feed.{$this->doctorsRoom->id}")->assertForbidden();
        authorizeChannel($this, $this->moderator, "private-chat.feed.{$this->doctorsRoom->id}")->assertOk();
        authorizeChannel($this, $this->moderator, "private-chat.feed.{$this->adminsRoom->id}")->assertForbidden();
        authorizeChannel($this, $this->admin, "private-chat.feed.{$this->adminsRoom->id}")->assertOk();
    });

    it('shares the member card on room presence channels within the audience', function () {
        $data = authorizeChannel($this, $this->admin, "presence-chat.room.{$this->general->id}")->assertOk()->json('channel_data');

        expect(json_decode($data, true))->toMatchArray([
            'user_id' => $this->admin->id,
            'user_info' => ['id' => $this->admin->id, 'name' => $this->admin->name, 'is_admin' => true],
        ]);

        authorizeChannel($this, $this->member, "presence-chat.room.{$this->adminsRoom->id}")->assertForbidden();

        $shopRoom = ChatRoom::query()->withoutGlobalScopes()->create(['tenant_id' => $this->createTenant(TenantType::Marketplace)->id, 'name' => 'X', 'slug' => 'x']);
        authorizeChannel($this, $this->admin, "presence-chat.room.{$shopRoom->id}")->assertForbidden();
    });

    it('refuses guests', function () {
        // The shared beforeEach signed a member in
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-chat.tenant.{$this->clinic->id}"])->assertUnauthorized();
    });
});
