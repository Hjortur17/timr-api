<?php

namespace App\Http\Requests\NotificationPreference;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $validTypes = implode(',', array_column(NotificationType::cases(), 'value'));

        return [
            'preferences' => ['present', 'array'],
            'preferences.*.notification_type' => ['required', 'string', "in:{$validTypes}"],
            'preferences.*.channel_push' => ['required', 'boolean'],
            'preferences.*.channel_email' => ['required', 'boolean'],
            'preferences.*.channel_in_app' => ['required', 'boolean'],
            'preferences.*.timing_value' => ['nullable', 'array'],
            'pause_all' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['nullable', 'string', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'string', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $preferences = $this->input('preferences', []);

                foreach ($preferences as $index => $pref) {
                    $type = NotificationType::tryFrom($pref['notification_type'] ?? '');

                    if (! $type) {
                        continue;
                    }

                    if ($type->isMandatory()) {
                        $push = $pref['channel_push'] ?? false;
                        $email = $pref['channel_email'] ?? false;
                        $inApp = $pref['channel_in_app'] ?? false;

                        if (! $push && ! $email && ! $inApp) {
                            $validator->errors()->add(
                                "preferences.{$index}.notification_type",
                                'Ekki er hægt að slökkva á öllum rásum fyrir nauðsynlegar tilkynningar.',
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferences.*.notification_type.in' => 'Ógild tilkynningategund.',
        ];
    }
}
