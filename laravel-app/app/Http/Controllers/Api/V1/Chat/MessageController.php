<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(private readonly ChatService $chat) {}

    /**
     * History, oldest first. `?before={id}` pages backwards; meta.has_more says whether older messages exist.
     */
    public function index(Request $request, ChatRoom $room): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) ($validated['limit'] ?? self::PAGE_SIZE);
        $messages = $this->chat->history($room, isset($validated['before']) ? (int) $validated['before'] : null, $limit);

        return ChatMessageResource::collection($messages)
            ->additional(['meta' => ['has_more' => $messages->count() === $limit]]);
    }

    public function store(ChatMessageRequest $request, ChatRoom $room): JsonResponse
    {
        $message = $this->chat->send($room, $request->user(), $request->validated('body'));

        return (new ChatMessageResource($message))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, ChatMessage $message): Response
    {
        $this->chat->deleteMessage($message, $request->user());

        return response()->noContent();
    }
}
