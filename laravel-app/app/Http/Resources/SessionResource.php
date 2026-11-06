<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user's bootstrap payload: profile, tenant and effective access.
 *
 * @mixin User
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $acl = $this->acl();

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'timezone' => $this->timezone,
                'locale' => $this->locale,
                'last_login_at' => to_user_timezone($this->last_login_at),
                'email_verified_at' => to_user_timezone($this->email_verified_at),
                // True while the app's features are locked behind email verification
                'must_verify_email' => $this->mustVerifyEmail(),
            ],
            'tenant' => new TenantResource($this->tenant),
            'access' => [
                'is_admin' => $acl['is_admin'],
                'roles' => $acl['roles'],
                'permissions' => $acl['permissions'],
            ],
            'preferences' => [
                'timezone' => user_timezone($this->resource),
                'locale' => app()->getLocale(),
            ],
        ];
    }
}
