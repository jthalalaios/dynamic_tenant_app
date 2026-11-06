<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatRoomRequest;
use App\Http\Resources\ChatMuteResource;
use App\Http\Resources\ChatRoomResource;
use App\Models\ChatRoom;
use App\Models\Role;
use App\Services\Chat\ChatModeration;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoomController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ChatModeration $moderation,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:chat.manage', only: ['store', 'update', 'destroy'])];
    }

    /**
     * Rooms the viewer may enter with their unread counts, every active mute (meta.mutes)
     * and, for room managers, the workspace's roles to pick an audience from (meta.roles).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->chat->ensureDefaultRooms();
        $user = $request->user();

        return ChatRoomResource::collection($this->chat->roomsFor($user))
            ->additional(['meta' => [
                'mutes' => ChatMuteResource::collection($this->moderation->activeMutes()),
                'roles' => $user->hasPermission('chat.manage')
                    ? Role::query()->orderByDesc('is_admin')->orderBy('name')->get(['id', 'name', 'slug', 'is_admin'])
                        ->map(fn (Role $role) => [
                            'id' => $role->id, 'name' => $role->name, 'slug' => $role->slug, 'is_admin' => $role->is_admin,
                            // Non-admins may only open rooms to roles they hold themselves
                            'assignable' => $user->isAdmin() || $user->hasRole($role->slug),
                        ])->values()
                    : [],
            ]]);
    }

    public function store(ChatRoomRequest $request): JsonResponse
    {
        $room = $this->chat->createRoom($request->payload(), $request->user());

        return (new ChatRoomResource($room))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ChatRoomRequest $request, ChatRoom $room): ChatRoomResource
    {
        return new ChatRoomResource($this->chat->updateRoom($room, $request->payload(), $request->user()));
    }

    public function destroy(ChatRoom $room): Response
    {
        $this->chat->deleteRoom($room);

        return response()->noContent();
    }

    /**
     * Mark the room read up to a message (default: the newest one).
     */
    public function read(Request $request, ChatRoom $room): Response
    {
        $validated = $request->validate(['message_id' => ['nullable', 'integer', 'min:1']]);

        $this->chat->markRead($room, $request->user(), $validated['message_id'] ?? null);

        return response()->noContent();
    }
}
