<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

class PatientController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public static function middleware(): array
    {
        return self::permissionMiddleware('patients');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $patients = Patient::query()
            ->when($request->string('search')->trim()->value(), fn ($q, string $term) => $q->where(
                fn ($q) => $q->whereLike('first_name', "%{$term}%")
                    ->orWhereLike('last_name', "%{$term}%")
                    ->orWhereLike('email', "%{$term}%")
                    ->orWhereLike('phone', "%{$term}%")
            ))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($this->perPage($request));

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request): JsonResponse
    {
        $patient = Patient::query()->create($request->payload());

        return (new PatientResource($patient))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient);
    }

    public function update(PatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->payload());

        return new PatientResource($patient);
    }

    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }
}
