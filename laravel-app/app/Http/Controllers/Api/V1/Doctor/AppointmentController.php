<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\AppointmentRequest;
use App\Http\Requests\Doctor\AppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\Doctor\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public function __construct(private readonly AppointmentService $appointments) {}

    public static function middleware(): array
    {
        return [
            ...self::permissionMiddleware('appointments', ['status' => 'update']),
            new Middleware(['permission:appointments.check-in', 'online'], only: ['checkIn']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'patient_id' => ['nullable', 'integer'],
        ]);

        $appointments = Appointment::query()
            ->with(['patient', 'doctor:id,name', 'checkedInBy:id,name'])
            ->when($request->date('date', 'Y-m-d', user_timezone()), function ($q, Carbon $day) {
                // A calendar day in the viewer's timezone, translated to a UTC range
                $q->whereBetween('scheduled_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()]);
            })
            ->when($request->enum('status', AppointmentStatus::class), fn ($q, AppointmentStatus $s) => $q->where('status', $s))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->orderBy('scheduled_at')
            ->paginate($this->perPage($request));

        return AppointmentResource::collection($appointments);
    }

    public function store(AppointmentRequest $request): JsonResponse
    {
        $appointment = Appointment::query()->create($request->payload());

        return (new AppointmentResource($appointment->load(['patient', 'doctor:id,name'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load(['patient', 'doctor:id,name', 'checkedInBy:id,name']));
    }

    public function update(AppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        $appointment->update($request->payload());

        return new AppointmentResource($appointment->load(['patient', 'doctor:id,name', 'checkedInBy:id,name']));
    }

    public function destroy(Appointment $appointment): Response
    {
        $appointment->delete();

        return response()->noContent();
    }

    /**
     * Check the patient in. Requires appointments.check-in and an online presence session.
     */
    public function checkIn(Request $request, Appointment $appointment): AppointmentResource
    {
        $appointment = $this->appointments->checkIn($appointment, $request->user());

        return new AppointmentResource($appointment->load(['patient', 'doctor:id,name', 'checkedInBy:id,name']));
    }

    public function status(AppointmentStatusRequest $request, Appointment $appointment): AppointmentResource
    {
        $appointment = $this->appointments->transition($appointment, $request->status(), $request->user());

        return new AppointmentResource($appointment->load(['patient', 'doctor:id,name', 'checkedInBy:id,name']));
    }
}
