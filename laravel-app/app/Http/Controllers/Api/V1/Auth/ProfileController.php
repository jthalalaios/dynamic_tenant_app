<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\SessionResource;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): SessionResource
    {
        $user = $request->user();
        $user->update($request->payload());

        if ($user->wasChanged('locale') && $user->locale !== null) {
            app()->setLocale($user->locale);
        }

        return new SessionResource($user->load('tenant'));
    }
}
