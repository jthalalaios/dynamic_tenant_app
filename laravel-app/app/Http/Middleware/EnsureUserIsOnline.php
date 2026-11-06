<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use App\Services\Doctor\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects the request unless the user currently has a live presence session.
 */
class EnsureUserIsOnline
{
    public function __construct(private readonly PresenceService $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->presence->isOnline($request->user())) {
            throw new BusinessRuleException(__('messages.must_be_online'), 'user_offline');
        }

        return $next($request);
    }
}
