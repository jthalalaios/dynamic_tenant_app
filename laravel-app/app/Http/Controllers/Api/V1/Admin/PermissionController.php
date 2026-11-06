<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Permissions this tenant may grant, grouped for a role editor matrix.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->whereIn('scope', tenant()->type->permissionScopes())
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $permissions->groupBy('group')->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => PermissionResource::collection($items),
            ])->values(),
        ]);
    }
}
