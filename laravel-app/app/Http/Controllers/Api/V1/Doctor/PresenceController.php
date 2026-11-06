<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PresenceResource;
use App\Models\PresenceSession;
use App\Models\User;
use App\Services\Doctor\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presence) {}

    public function show(Request $request): PresenceResource
    {
        return new PresenceResource($this->presence->current($request->user()));
    }

    public function online(Request $request): JsonResponse
    {
        // 200 even when a new session row was created: the client toggles a state, it does not create a resource
        return (new PresenceResource($this->presence->goOnline($request->user())))->response()->setStatusCode(200);
    }

    public function heartbeat(Request $request): PresenceResource
    {
        return new PresenceResource($this->presence->heartbeat($request->user()));
    }

    public function offline(Request $request): PresenceResource
    {
        $this->presence->goOffline($request->user());

        return new PresenceResource(null);
    }

    /**
     * Staff currently online in this clinic.
     */
    public function team(): JsonResponse
    {
        $sessions = PresenceSession::query()
            ->open()
            ->where('last_heartbeat_at', '>=', now()->subMinutes(config('tenancy.presence.timeout_minutes')))
            ->with('user:id,name')
            ->get();

        return response()->json([
            'data' => $sessions->map(fn (PresenceSession $s) => [
                'user' => $s->user instanceof User ? ['id' => $s->user->id, 'name' => $s->user->name] : null,
                'since' => to_user_timezone($s->started_at),
            ])->values(),
        ]);
    }
}
