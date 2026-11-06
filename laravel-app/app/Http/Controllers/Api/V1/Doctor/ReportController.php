<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Appointment breakdown by status and reason over the last N days.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = max(1, min($request->integer('days', 30), 365));
        $from = now(user_timezone())->subDays($days - 1)->startOfDay()->utc();

        $base = Appointment::query()->where('scheduled_at', '>=', $from);

        $breakdown = fn (string $column) => (clone $base)
            ->selectRaw("{$column} as label, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total]);

        return response()->json([
            'data' => [
                'days' => $days,
                'total' => (clone $base)->count(),
                'by_status' => $breakdown('status'),
                'by_reason' => $breakdown('reason'),
            ],
        ]);
    }
}
