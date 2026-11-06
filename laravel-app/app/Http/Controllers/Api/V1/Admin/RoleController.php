<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Runtime management of tenant roles and the permissions they grant.
 */
class RoleController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public function __construct(private readonly RoleService $roles) {}

    public static function middleware(): array
    {
        return self::permissionMiddleware('roles');
    }

    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->payload());

        return (new RoleResource($role->load('permissions:id,name')->loadCount('users')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions:id,name')->loadCount('users'));
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $role = $this->roles->update($role, $request->payload());

        return new RoleResource($role->load('permissions:id,name')->loadCount('users'));
    }

    public function destroy(Role $role): Response
    {
        $this->roles->delete($role);

        return response()->noContent();
    }
}
