<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatMuteRequest;
use App\Http\Resources\ChatMuteResource;
use App\Models\ChatMute;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Who may mute whom is decided by ChatModeration (admins: everyone; chat.mute: everyone but admins).
 */
class MuteController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ChatModeration $moderation) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:chat.mute', only: ['store', 'destroy'])];
    }

    public function index(): AnonymousResourceCollection
    {
        return ChatMuteResource::collection($this->moderation->activeMutes());
    }

    public function store(ChatMuteRequest $request): JsonResponse
    {
        $data = $request->validated();

        $mute = $this->moderation->mute(
            $request->user(),
            User::query()->findOrFail($data['user_id']),
            isset($data['room_id']) ? ChatRoom::query()->accessibleBy($request->user())->findOrFail($data['room_id']) : null,
            isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            $data['reason'] ?? null,
        );

        return (new ChatMuteResource($mute))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, ChatMute $mute): Response
    {
        $this->moderation->unmute($request->user(), $mute);

        return response()->noContent();
    }
}
