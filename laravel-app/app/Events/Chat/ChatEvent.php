<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Base for every chat event (authorised in routes/channels.php):
 *
 * - `private-chat.feed.{room}`: a room's messages; only members who may enter the
 *   room can join, so restricted rooms (admins, roles) never leak.
 * - `private-chat.tenant.{id}`: workspace-wide signals (rooms changed, mutes).
 *
 * The payload is resolved by the caller while the request (tenant scope,
 * viewer timezone) is still active, so the event never touches the database.
 */
abstract class ChatEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    /**
     * @param  string  $channel  without the `private-` prefix
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $channel,
        public readonly array $payload,
    ) {}

    public static function tenantChannel(int $tenantId): string
    {
        return "chat.tenant.{$tenantId}";
    }

    public static function roomChannel(int $roomId): string
    {
        return "chat.feed.{$roomId}";
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    abstract public function broadcastAs(): string;
}
