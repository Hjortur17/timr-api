<?php

namespace App\Http\Controllers\Auth;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationPreference\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isManager = $user->isManager();

        $types = $isManager
            ? NotificationType::cases()
            : NotificationType::employeeTypes();

        $saved = $user->notificationPreferences()->get()->keyBy(
            fn (NotificationPreference $p) => $p->notification_type->value
        );

        $preferences = collect($types)->values()->map(function (NotificationType $type) use ($saved) {
            $pref = $saved->get($type->value);
            $availableChannels = $type->availableChannels();

            return [
                'notification_type' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'mandatory' => $type->isMandatory(),
                'manager_only' => $type->isManagerOnly(),
                'available_channels' => $availableChannels,
                'channel_push' => $pref ? $pref->channel_push : in_array('push', $availableChannels),
                'channel_email' => $pref ? $pref->channel_email : in_array('email', $availableChannels),
                'channel_in_app' => $pref ? $pref->channel_in_app : in_array('in_app', $availableChannels),
                'has_timing_config' => $type->hasTimingConfig(),
                'timing_options' => $type->timingOptions(),
                'timing_value' => $pref?->timing_value,
            ];
        });

        return response()->json([
            'data' => $preferences,
            'pause_all' => (bool) $user->notifications_paused,
            'quiet_hours_start' => $user->quiet_hours_start,
            'quiet_hours_end' => $user->quiet_hours_end,
            'message' => 'Success',
        ]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->input('preferences', []) as $item) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notification_type' => $item['notification_type']],
                [
                    'channel_push' => $item['channel_push'],
                    'channel_email' => $item['channel_email'],
                    'channel_in_app' => $item['channel_in_app'],
                    'timing_value' => $item['timing_value'] ?? null,
                ],
            );
        }

        $userUpdates = [];

        if ($request->has('pause_all')) {
            $userUpdates['notifications_paused'] = $request->boolean('pause_all');
        }

        if ($request->has('quiet_hours_start')) {
            $userUpdates['quiet_hours_start'] = $request->input('quiet_hours_start');
        }

        if ($request->has('quiet_hours_end')) {
            $userUpdates['quiet_hours_end'] = $request->input('quiet_hours_end');
        }

        if (! empty($userUpdates)) {
            $user->update($userUpdates);
        }

        return response()->json([
            'message' => 'Stillingar uppfærðar.',
        ]);
    }
}
