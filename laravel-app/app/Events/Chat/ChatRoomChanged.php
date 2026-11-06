<?php

namespace App\Events\Chat;

/**
 * A room was created, updated or deleted; payload: action, room (id only: members re-fetch the rooms they may see).
 */
class ChatRoomChanged extends ChatEvent
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(int $tenantId, public readonly string $action, array $item)
    {
        parent::__construct(self::tenantChannel($tenantId), ['action' => $action, 'room' => $item]);
    }

    public function broadcastAs(): string
    {
        return 'room.changed';
    }
}
