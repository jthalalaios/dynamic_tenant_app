<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

final readonly class IssuedToken
{
    public function __construct(
        public User $user,
        public PersonalAccessTokenResult $token,
    ) {}
}
