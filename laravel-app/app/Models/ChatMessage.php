<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'room_id', 'user_id', 'body', 'deleted_by'])]
class ChatMessage extends Model
{
    use BelongsToTenant, SoftDeletes;

    /**
     * {message} only binds messages of rooms the signed-in user may enter.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->resolveRouteBindingQuery($this->newQuery(), $value, $field);
        $user = current_user();

        if ($user !== null) {
            $query->whereHas('room', fn ($rooms) => $rooms->accessibleBy($user));
        }

        return $query->first();
    }

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
