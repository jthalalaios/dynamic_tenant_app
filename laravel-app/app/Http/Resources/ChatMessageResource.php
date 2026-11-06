<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Services\Chat\ChatDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'body' => $this->body,
            // null once the author's account was removed
            'user' => app(ChatDirectory::class)->card($this->user),
            'created_at' => to_user_timezone($this->created_at),
        ];
    }
}
