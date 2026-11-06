<?php

namespace App\Http\Resources;

use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatRoom */
class ChatRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'is_default' => $this->is_default,
            'is_locked' => $this->is_locked,
            // everyone | admins | roles
            'audience' => $this->audience,
            'roles' => $this->whenLoaded('roles', fn ($roles) => $roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug])->values()),
            // Viewer specific; only present on the room list
            'unread_count' => $this->whenHas('unread_count', fn ($count) => (int) $count),
            // null when the room has no messages yet
            'last_message' => $this->whenLoaded('latestMessage', fn ($message) => new ChatMessageResource($message)),
            'created_at' => to_user_timezone($this->created_at),
        ];
    }
}
