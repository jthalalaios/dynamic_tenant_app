<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\SessionResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\IssuedToken;
use App\Services\Auth\MemberRegistrar;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TenantContext $tenantContext,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->tokenResponse($this->auth->login($request->validated('email'), $request->validated('password')));
    }

    public function register(RegisterRequest $request, MemberRegistrar $registrar): JsonResponse
    {
        $registrar->register($request->validated());

        return $this->tokenResponse(
            $this->auth->login($request->validated('email'), $request->validated('password')),
            Response::HTTP_CREATED,
        );
    }

    public function me(Request $request): SessionResource
    {
        return new SessionResource($request->user()->load('tenant'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->user());

        return response()->noContent();
    }

    private function tokenResponse(IssuedToken $issued, int $status = Response::HTTP_OK): JsonResponse
    {
        // Login runs outside the tenant middleware; bind it so the session payload resolves tenant defaults
        $this->tenantContext->set($issued->user->tenant);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued->token->accessToken,
            'expires_in' => $issued->token->expiresIn,
            'session' => new SessionResource($issued->user),
        ], $status);
    }
}
