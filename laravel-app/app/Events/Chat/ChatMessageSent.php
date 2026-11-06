<?php

namespace App\Events\Chat;

/**
 * A message was posted; payload is the message resource.
 */
class ChatMessageSent extends ChatEvent
{
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
