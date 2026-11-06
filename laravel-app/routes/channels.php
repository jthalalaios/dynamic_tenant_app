<?php

use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatDirectory;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| Authorised at POST /api/v1/broadcasting/auth (see bootstrap/app.php) after
| auth:api + tenant, so the tenant scope is active and {room} only binds
| rooms of the caller's own tenant.
|
*/

// Workspace-wide chat signals (see ChatEvent): rooms changed, mutes
Broadcast::channel('chat.tenant.{tenantId}', function (User $user, int $tenantId) {
    return (int) $user->tenant_id === $tenantId && $user->hasPermission('chat.view');
}, ['guards' => ['api']]);

// A room's messages. Binding $room only resolves rooms the user may enter (audience),
// so members never receive messages of admin-only or other roles' rooms.
Broadcast::channel('chat.feed.{room}', function (User $user, ChatRoom $room) {
    return $user->hasPermission('chat.view');
}, ['guards' => ['api']]);

// Who is in a room right now; members also whisper "typing" here. Binding $room is the
// access check: another workspace's room, or one outside the user's audience, is refused.
Broadcast::channel('chat.room.{room}', function (User $user, ChatRoom $room) {
    if (! $user->hasPermission('chat.view')) {
        return false;
    }

    return app(ChatDirectory::class)->card($user);
}, ['guards' => ['api']]);
