<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'started_at', 'last_heartbeat_at', 'ended_at'])]
class PresenceSession extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /**
     * Open sessions whose heartbeat is older than the presence timeout.
     *
     * @param  Builder<self>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->whereNull('ended_at')
            ->where('last_heartbeat_at', '<', now()->subMinutes(config('tenancy.presence.timeout_minutes')));
    }

    public function isLive(): bool
    {
        return $this->ended_at === null
            && $this->last_heartbeat_at->gte(now()->subMinutes(config('tenancy.presence.timeout_minutes')));
    }
}
