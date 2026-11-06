<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PresenceSession;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Today's (viewer-local) clinic overview and visitor queue.
     */
    public function __invoke(): JsonResponse
    {
        $now = now(user_timezone());
        $range = [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()];

        $today = Appointment::query()
            ->with(['patient', 'doctor:id,name', 'checkedInBy:id,name'])
            ->whereBetween('scheduled_at', $range)
            ->orderBy('scheduled_at')
            ->get();

        $count = fn (AppointmentStatus ...$statuses) => $today->whereIn('status', $statuses)->count();

        return response()->json([
            'data' => [
                'stats' => [
                    'scheduled' => $today->count(),
                    'waiting' => $count(AppointmentStatus::Scheduled),
                    'checked_in' => $count(AppointmentStatus::CheckedIn, AppointmentStatus::InProgress),
                    'completed' => $count(AppointmentStatus::Completed),
                    'patients' => Patient::query()->count(),
                    'staff_online' => PresenceSession::query()
                        ->open()
                        ->where('last_heartbeat_at', '>=', now()->subMinutes(config('tenancy.presence.timeout_minutes')))
                        ->count(),
                ],
                'queue' => AppointmentResource::collection($today),
            ],
        ]);
    }
}
