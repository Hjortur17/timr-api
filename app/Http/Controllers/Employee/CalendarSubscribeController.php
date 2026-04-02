<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarSubscribeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $employee->calendar_token) {
            $employee->update(['calendar_token' => Str::uuid()->toString()]);
        }

        return response()->json([
            'data' => [
                'url' => url("/api/calendar/{$employee->calendar_token}"),
            ],
        ]);
    }
}
