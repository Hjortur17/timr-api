<?php

namespace App\Http\Controllers\Employee;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Return all employee notification preferences for the authenticated user.
     * Backward-compatible: returns the simplified { type, label, enabled } shape.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $saved = $user->notificationPreferences()->get()->keyBy(
            fn (NotificationPreference $p) => $p->notification_type->value
        );

        $preferences = collect(NotificationType::employeeTypes())->values()->map(function (NotificationType $type) use ($saved) {
            $pref = $saved->get($type->value);
            $enabled = $pref
                ? ($pref->channel_push || $pref->channel_email || $pref->channel_in_app)
                : true;

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'enabled' => $enabled,
            ];
        });

        return response()->json([
            'data' => $preferences,
            'message' => 'Success',
        ]);
    }

    /**
     * Update notification preferences for the authenticated user.
     * Backward-compatible: accepts { preferences: [{ type, enabled }] }.
     */
    public function update(Request $request): JsonResponse
    {
        $validTypes = implode(',', array_column(NotificationType::cases(), 'value'));

        $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string', "in:{$validTypes}"],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        foreach ($request->input('preferences') as $item) {
            $enabled = $item['enabled'];

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notification_type' => $item['type']],
                [
                    'channel_push' => $enabled,
                    'channel_email' => $enabled,
                    'channel_in_app' => $enabled,
                ],
            );
        }

        return response()->json([
            'message' => 'Stillingar uppfærðar.',
        ]);
    }
}
