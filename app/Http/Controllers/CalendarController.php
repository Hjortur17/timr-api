<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Services\ShiftService;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends Controller
{
    public function __construct(private ShiftService $shiftService) {}

    public function show(string $token): Response
    {
        $employee = Employee::withoutGlobalScope('company')
            ->where('calendar_token', $token)
            ->firstOrFail();

        $assignments = EmployeeShift::withoutGlobalScope('company')
            ->with(['shift' => fn ($q) => $q->withoutGlobalScope('company')->withTrashed()])
            ->where('published_employee_id', $employee->id)
            ->where('published', true)
            ->oldest('published_date')
            ->get();

        $content = $this->shiftService->renderIcal($assignments);

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
        ]);
    }
}
