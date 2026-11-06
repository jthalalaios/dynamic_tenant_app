<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

class UserController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public function __construct(private readonly UserService $users) {}

    public static function middleware(): array
    {
        return self::permissionMiddleware('users');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->trim()->value(), fn ($q, string $term) => $q->where(
                fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%")
            ))
            ->when($request->filled('role_id'), fn ($q) => $q->whereHas('roles', fn ($q) => $q->whereKey($request->integer('role_id'))))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->payload());

        return (new UserResource($user->load('roles')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $user = $this->users->update($user, $request->user(), $request->payload());

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->users->delete($user, $request->user());

        return response()->noContent();
    }
}
