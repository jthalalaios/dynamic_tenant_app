<?php

namespace App\Events\Chat;

/**
 * A message was removed; payload: id, room_id, deleted_by.
 */
class ChatMessageDeleted extends ChatEvent
{
    public function broadcastAs(): string
    {
        return 'message.deleted';
    }
}
