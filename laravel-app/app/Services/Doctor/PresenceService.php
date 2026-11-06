<?php

namespace App\Services\Doctor;

use App\Models\PresenceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tracks whether staff are online (on duty). Online = open session + fresh heartbeat.
 */
class PresenceService
{
    public function current(User $user): ?PresenceSession
    {
        return PresenceSession::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->open()
            ->latest('started_at')
            ->first();
    }

    public function isOnline(User $user): bool
    {
        return (bool) $this->current($user)?->isLive();
    }

    public function goOnline(User $user): PresenceSession
    {
        return DB::transaction(function () use ($user) {
            $session = PresenceSession::query()
                ->withoutGlobalScopes()
                ->where('user_id', $user->getKey())
                ->open()
                ->lockForUpdate()
                ->first();

            if ($session !== null && $session->isLive()) {
                $session->update(['last_heartbeat_at' => now()]);

                return $session;
            }

            // A stale open session is closed at its last heartbeat, not "now", to keep shift history honest
            $session?->update(['ended_at' => $session->last_heartbeat_at]);

            return PresenceSession::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        });
    }

    public function heartbeat(User $user): ?PresenceSession
    {
        $session = $this->current($user);

        if ($session === null || ! $session->isLive()) {
            return null;
        }

        $session->update(['last_heartbeat_at' => now()]);

        return $session;
    }

    public function goOffline(User $user): void
    {
        PresenceSession::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->open()
            ->update(['ended_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Close sessions whose heartbeat stopped (closed tab, lost connection). Returns how many.
     */
    public function closeStale(): int
    {
        return PresenceSession::query()
            ->withoutGlobalScopes()
            ->stale()
            ->update(['ended_at' => DB::raw('last_heartbeat_at'), 'updated_at' => now()]);
    }
}
