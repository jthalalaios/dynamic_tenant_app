<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatEvent;
use Throwable;

/**
 * Pushes chat events to Reverb right away (ShouldBroadcastNow) without letting
 * a websocket outage fail the request: the change is already saved, and clients
 * resync from the API when they reconnect.
 */
class ChatBroadcaster
{
    public function send(ChatEvent $event, bool $toOthers = true): void
    {
        try {
            $pending = broadcast($event);

            // The sender already has the result from the HTTP response (X-Socket-ID)
            if ($toOthers) {
                $pending->toOthers();
            }

            // PendingBroadcast dispatches in its destructor: release it inside the try
            unset($pending);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
