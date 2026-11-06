<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiRequest;
use App\Models\ChatRoom;
use Illuminate\Validation\Rule;

class ChatRoomRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:60', Rule::unique('chat_rooms', 'name')->where('tenant_id', tenant_id())->ignore($this->route('room'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'icon' => ['sometimes', 'string', Rule::in(ChatRoom::ICONS)],
            // Administrators only, enforced by ChatService
            'is_locked' => ['sometimes', 'boolean'],
            // Who may enter: everyone, administrators only, or the listed roles (rules in ChatService)
            'audience' => ['sometimes', 'string', Rule::in(ChatRoom::AUDIENCES)],
            'role_ids' => ['sometimes', 'array', 'max:20'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('tenant_id', tenant_id())],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('is_locked', $data)) {
            $data['is_locked'] = $this->boolean('is_locked');
        }

        return $data;
    }
}
