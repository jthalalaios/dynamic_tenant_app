<?php

namespace App\Http\Resources;

use App\Models\PresenceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a nullable PresenceSession so "offline" is a regular payload, not a 404.
 *
 * @property PresenceSession|null $resource
 */
class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource;
        $online = (bool) $session?->isLive();

        return [
            'online' => $online,
            'started_at' => $online ? to_user_timezone($session->started_at) : null,
            'last_heartbeat_at' => $online ? to_user_timezone($session->last_heartbeat_at) : null,
            'timeout_minutes' => config('tenancy.presence.timeout_minutes'),
        ];
    }
}
