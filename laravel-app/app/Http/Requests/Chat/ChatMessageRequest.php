<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiRequest;

class ChatMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        // Whitespace-only bodies are trimmed to null by the global middleware and fail `required`
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
