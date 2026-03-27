<?php

namespace App\Http\Controllers\Employee;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEmployee;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Return all notification preferences for the authenticated employee.
     * For any type that has no saved preference, a default enabled record is returned.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 404);

        $saved = $employee->notificationPreferences()->get()->keyBy('type');

        $preferences = collect(NotificationType::cases())->map(function (NotificationType $type) use ($saved) {
            $pref = $saved->get($type->value);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'enabled' => $pref ? $pref->enabled : true,
            ];
        });

        return response()->json([
            'data' => $preferences,
            'message' => 'Success',
        ]);
    }

    /**
     * Update (upsert) notification preferences for the authenticated employee.
     *
     * Expects: { preferences: [{ type: "shift_published", enabled: true }, ...] }
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string', 'in:'.implode(',', array_column(NotificationType::cases(), 'value'))],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        $employee = $request->user()->employee;

        abort_unless($employee, 404);

        foreach ($request->input('preferences') as $item) {
            NotificationPreference::updateOrCreate(
                ['employee_id' => $employee->id, 'type' => $item['type']],
                ['enabled' => $item['enabled']],
            );
        }

        return response()->json([
            'message' => 'Stillingar uppfærðar.',
        ]);
    }
}
