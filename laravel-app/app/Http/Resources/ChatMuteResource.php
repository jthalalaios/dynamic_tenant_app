<?php

namespace App\Http\Resources;

use App\Models\ChatMute;
use App\Services\Chat\ChatDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMute */
class ChatMuteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $directory = app(ChatDirectory::class);

        return [
            'id' => $this->id,
            'user' => $directory->card($this->user),
            // null = muted in every room
            'room_id' => $this->room_id,
            'reason' => $this->reason,
            // null = until someone lifts it
            'expires_at' => to_user_timezone($this->expires_at),
            'muted_by' => $directory->card($this->mutedBy),
            'created_at' => to_user_timezone($this->created_at),
        ];
    }
}
