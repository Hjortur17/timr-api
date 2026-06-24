<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\StoreLocationRequest;
use App\Http\Requests\Manager\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = Location::query()->get();

        return response()->json([
            'data' => LocationResource::collection($locations),
            'message' => 'Success',
        ]);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'data' => new LocationResource($location),
            'message' => 'Location created successfully.',
        ], 201);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());

        return response()->json([
            'data' => new LocationResource($location->fresh()),
            'message' => 'Location updated successfully.',
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully.',
        ]);
    }
}
