<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftTemplate\GenerateScheduleRequest;
use App\Http\Requests\ShiftTemplate\StoreShiftTemplateRequest;
use App\Http\Requests\ShiftTemplate\UpdateShiftTemplateRequest;
use App\Http\Resources\ShiftTemplateResource;
use App\Models\ShiftTemplate;
use App\Services\ShiftTemplateService;
use Illuminate\Http\JsonResponse;

class ShiftTemplateController extends Controller
{
    public function __construct(private ShiftTemplateService $shiftTemplateService) {}

    public function index(): JsonResponse
    {
        $templates = $this->shiftTemplateService->listForCompany();

        return response()->json([
            'data' => ShiftTemplateResource::collection($templates),
            'message' => 'Success',
        ]);
    }

    public function store(StoreShiftTemplateRequest $request): JsonResponse
    {
        $template = $this->shiftTemplateService->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'data' => new ShiftTemplateResource($template),
            'message' => 'Shift template created successfully.',
        ], 201);
    }

    public function update(UpdateShiftTemplateRequest $request, ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('update', $shiftTemplate);

        $template = $this->shiftTemplateService->update($shiftTemplate, $request->validated());

        return response()->json([
            'data' => new ShiftTemplateResource($template),
            'message' => 'Shift template updated successfully.',
        ]);
    }

    public function destroy(ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('delete', $shiftTemplate);

        $this->shiftTemplateService->delete($shiftTemplate);

        return response()->json([
            'message' => 'Shift template deleted successfully.',
        ]);
    }

    public function generate(GenerateScheduleRequest $request, ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('generate', $shiftTemplate);

        $created = $this->shiftTemplateService->generateSchedule(
            $shiftTemplate,
            $request->validated('start_date'),
            $request->validated('end_date'),
        );

        return response()->json([
            'message' => 'Schedule generated successfully.',
            'assignments_created' => $created,
        ], 201);
    }
}
