<?php

namespace App\Events\Chat;

/**
 * A member was muted or unmuted; payload: action, mute.
 */
class ChatMuteChanged extends ChatEvent
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(int $tenantId, public readonly string $action, array $item)
    {
        parent::__construct(self::tenantChannel($tenantId), ['action' => $action, 'mute' => $item]);
    }

    public function broadcastAs(): string
    {
        return 'mute.changed';
    }
}
