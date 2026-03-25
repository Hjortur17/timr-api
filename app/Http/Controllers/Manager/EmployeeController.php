<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Mail\EmployeeInvite;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::query()->get();

        return response()->json([
            'data' => EmployeeResource::collection($employees),
            'message' => 'Success',
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create([
            'company_id' => $request->user()->company_id,
            ...$request->validated(),
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee),
            'message' => 'Starfsmanni bætt við.',
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json([
            'data' => new EmployeeResource($employee),
            'message' => 'Starfsmaður uppfærður.',
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json([
            'message' => 'Starfsmanni eytt.',
        ]);
    }

    public function sendInvite(Employee $employee): JsonResponse
    {
        $employee->update([
            'invite_token' => Str::uuid()->toString(),
            'invite_sent_at' => now(),
        ]);

        Mail::to($employee->email)->send(new EmployeeInvite($employee));

        return response()->json([
            'message' => 'Hlekkur sendur.',
        ]);
    }
}
