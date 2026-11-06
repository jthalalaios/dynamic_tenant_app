<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Active team members who can use the chat (administrators, or a role granting chat.view);
     * with `?room={id}`, only those who may enter that room. Online status comes from the
     * room presence channel, not from here.
     */
    public function __invoke(Request $request, ChatDirectory $directory): JsonResponse
    {
        $request->validate(['room' => ['nullable', 'integer']]);
        $room = $request->filled('room')
            ? ChatRoom::query()->accessibleBy($request->user())->with('roles:id')->findOrFail($request->integer('room'))
            : null;

        $members = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles->where(fn (Builder $q) => $q
                ->where('is_admin', true)
                ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', 'chat.view'))))
            ->when($room?->audience === ChatRoom::AUDIENCE_ADMINS, fn (Builder $q) => $q
                ->whereHas('roles', fn (Builder $roles) => $roles->where('is_admin', true)))
            ->when($room?->audience === ChatRoom::AUDIENCE_ROLES, fn (Builder $q) => $q
                ->whereHas('roles', fn (Builder $roles) => $roles->where(fn (Builder $r) => $r
                    ->where('is_admin', true)
                    ->orWhereIn('roles.id', $room->roles->pluck('id')))))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $members->map(fn (User $user) => $directory->card($user))->values(),
        ]);
    }
}
