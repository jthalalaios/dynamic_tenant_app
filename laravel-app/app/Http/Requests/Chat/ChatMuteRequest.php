<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiRequest;
use App\Models\ChatMute;
use Illuminate\Validation\Rule;

class ChatMuteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', tenant_id())],
            // Omitted / null = mute in every room
            'room_id' => ['nullable', 'integer', Rule::exists('chat_rooms', 'id')->where('tenant_id', tenant_id())],
            // Omitted / null = until someone lifts it
            'duration_minutes' => ['nullable', 'integer', Rule::in(ChatMute::DURATIONS)],
            'reason' => ['nullable', 'string', 'max:240'],
        ];
    }
}
