<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A chat room of the current workspace. Who may enter it is its audience:
 *
 * - everyone: every member holding chat.view (the default "Global" room is always this)
 * - admins:   administrators only
 * - roles:    members holding one of the room's roles (see roles())
 *
 * Administrators can enter every room. Route model binding ({room}) only resolves
 * rooms the signed-in user may enter, so a restricted room is a 404 for others,
 * on the API and on the websocket channels alike.
 */
#[Fillable(['tenant_id', 'name', 'slug', 'description', 'icon', 'is_default', 'is_locked', 'audience', 'created_by'])]
class ChatRoom extends Model
{
    use BelongsToTenant;

    public const AUDIENCE_EVERYONE = 'everyone';

    public const AUDIENCE_ADMINS = 'admins';

    public const AUDIENCE_ROLES = 'roles';

    public const AUDIENCES = [self::AUDIENCE_EVERYONE, self::AUDIENCE_ADMINS, self::AUDIENCE_ROLES];

    /** PrimeIcons (without the `pi-` prefix) a room can be shown with */
    public const ICONS = [
        'comments', 'hashtag', 'megaphone', 'users', 'heart', 'briefcase',
        'bolt', 'star', 'calendar', 'shopping-cart', 'flag', 'globe', 'shield',
    ];

    protected $attributes = [
        'audience' => self::AUDIENCE_EVERYONE,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    /**
     * Rooms the user may enter.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();

        $query->where(fn (Builder $q) => $q
            ->where('audience', self::AUDIENCE_EVERYONE)
            ->orWhere(fn (Builder $q) => $q
                ->where('audience', self::AUDIENCE_ROLES)
                ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.id', $roleIds))));
    }

    public function isAccessibleBy(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->accessibleBy($user)->exists();
    }

    /**
     * {room} only binds rooms the signed-in user may enter (API and broadcast channels).
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->resolveRouteBindingQuery($this->newQuery(), $value, $field);
        $user = current_user();

        if ($user !== null) {
            $query->accessibleBy($user);
        }

        return $query->first();
    }

    /**
     * Roles allowed in when the audience is "roles".
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'chat_room_role', 'room_id', 'role_id');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    /**
     * Newest message (highest id), for the room list preview.
     *
     * @return HasOne<ChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'room_id')->latestOfMany();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
