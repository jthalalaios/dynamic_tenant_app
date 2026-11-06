<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member silenced in one room (room_id) or in every room (room_id NULL),
 * until expires_at or, when that is NULL, until someone lifts it.
 */
#[Fillable(['tenant_id', 'user_id', 'room_id', 'muted_by', 'reason', 'expires_at'])]
class ChatMute extends Model
{
    use BelongsToTenant;

    /** Mute lengths a moderator can pick, in minutes (15m, 1h, 24h, 7d); null = until lifted */
    public const DURATIONS = [15, 60, 1440, 10080];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Mutes that silence a user in the given room: room specific or chat wide.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCovering(Builder $query, int $roomId): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('room_id')->orWhere('room_id', $roomId));
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mutedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_by');
    }

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }
}
