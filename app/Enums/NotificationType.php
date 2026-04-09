<?php

namespace App\Enums;

enum NotificationType: string
{
    // Employee mandatory
    case ForgotClockOutReminder = 'forgot_clock_out_reminder';
    case ForgotClockInReminder = 'forgot_clock_in_reminder';
    case OvertimeAlert = 'overtime_alert';
    case TimesheetSubmissionDeadline = 'timesheet_submission_deadline';
    case ScheduleChangeAlert = 'schedule_change_alert';

    // Employee configurable
    case ShiftStartReminder = 'shift_start_reminder';
    case ShiftPublished = 'shift_published';
    case BreakTimeReminder = 'break_time_reminder';
    case OvertimeWarning = 'overtime_warning';
    case WeeklyTimesheetSummary = 'weekly_timesheet_summary';
    case OpenShiftAvailable = 'open_shift_available';
    case VacationBalanceReminder = 'vacation_balance_reminder';
    case ShiftSwapRequest = 'shift_swap_request';
    case VacationRequestResponse = 'vacation_request_response';

    // Manager mandatory
    case OvertimeEscalation = 'overtime_escalation';
    case ForgotClockOutEscalation = 'forgot_clock_out_escalation';
    case TimesheetApprovalDeadline = 'timesheet_approval_deadline';

    // Manager configurable
    case UnapprovedTimesheetsAlert = 'unapproved_timesheets_alert';
    case UnusualActivityAlert = 'unusual_activity_alert';

    public function label(): string
    {
        return match ($this) {
            self::ForgotClockOutReminder => 'Gleymdist að stimpla út',
            self::ForgotClockInReminder => 'Gleymdist að stimpla inn',
            self::OvertimeAlert => 'Yfirvinna (100%)',
            self::TimesheetSubmissionDeadline => 'Frestur til að skila tímaskýrslu',
            self::ScheduleChangeAlert => 'Vakt breytt eða eytt',
            self::ShiftStartReminder => 'Áminning fyrir vakt',
            self::ShiftPublished => 'Vaktir birtar',
            self::BreakTimeReminder => 'Áminning um hlé',
            self::OvertimeWarning => 'Yfirvinnuviðvörun (90%)',
            self::WeeklyTimesheetSummary => 'Vikuyfirsýn tímaskýrslu',
            self::OpenShiftAvailable => 'Laus vakt í boði',
            self::VacationBalanceReminder => 'Orlofsjöfnuður',
            self::ShiftSwapRequest => 'Beiðni um vaktaskipti',
            self::VacationRequestResponse => 'Svar við orlofsbeiðni',
            self::OvertimeEscalation => 'Yfirvinna starfsmanns (100%)',
            self::ForgotClockOutEscalation => 'Starfsmaður gleymdist að stimpla út',
            self::TimesheetApprovalDeadline => 'Frestur til að samþykkja tímaskýrslur',
            self::UnapprovedTimesheetsAlert => 'Ósamþykktar tímaskýrslur',
            self::UnusualActivityAlert => 'Óvenjuleg virkni',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ForgotClockOutReminder => 'Fáðu tilkynningu ef þú gleymir að stimpla þig út úr vakt.',
            self::ForgotClockInReminder => 'Fáðu tilkynningu ef þú gleymir að stimpla þig inn í vakt.',
            self::OvertimeAlert => 'Tilkynning þegar þú nærð 100% af leyfilegri yfirvinnu.',
            self::TimesheetSubmissionDeadline => 'Áminning um að skila tímaskýrslu fyrir frest.',
            self::ScheduleChangeAlert => 'Tilkynning þegar vöktum þínum er breytt eða þeim eytt.',
            self::ShiftStartReminder => 'Áminning áður en vaktin þín hefst.',
            self::ShiftPublished => 'Tilkynning þegar nýjar vaktir eru birtar.',
            self::BreakTimeReminder => 'Áminning um að taka hlé á vakt.',
            self::OvertimeWarning => 'Viðvörun þegar þú nærð 90% af leyfilegri yfirvinnu.',
            self::WeeklyTimesheetSummary => 'Vikuleg samantekt á tímaskráningu.',
            self::OpenShiftAvailable => 'Tilkynning þegar laus vakt er í boði.',
            self::VacationBalanceReminder => 'Áminning um orlofsjöfnuð.',
            self::ShiftSwapRequest => 'Tilkynning þegar einhver biður um vaktaskipti.',
            self::VacationRequestResponse => 'Tilkynning þegar svarað er orlofsbeiðni.',
            self::OvertimeEscalation => 'Tilkynning þegar starfsmaður nær 100% yfirvinnu.',
            self::ForgotClockOutEscalation => 'Tilkynning þegar starfsmaður gleymir að stimpla út.',
            self::TimesheetApprovalDeadline => 'Áminning um að samþykkja tímaskýrslur fyrir frest.',
            self::UnapprovedTimesheetsAlert => 'Tilkynning um ósamþykktar tímaskýrslur.',
            self::UnusualActivityAlert => 'Tilkynning um óvenjulega virkni í stimplunum.',
        };
    }

    public function isMandatory(): bool
    {
        return in_array($this, [
            self::ForgotClockOutReminder,
            self::ForgotClockInReminder,
            self::OvertimeAlert,
            self::TimesheetSubmissionDeadline,
            self::ScheduleChangeAlert,
            self::OvertimeEscalation,
            self::ForgotClockOutEscalation,
            self::TimesheetApprovalDeadline,
        ]);
    }

    public function isManagerOnly(): bool
    {
        return in_array($this, [
            self::OvertimeEscalation,
            self::ForgotClockOutEscalation,
            self::TimesheetApprovalDeadline,
            self::UnapprovedTimesheetsAlert,
            self::UnusualActivityAlert,
        ]);
    }

    /**
     * @return array<string>
     */
    public function availableChannels(): array
    {
        return match ($this) {
            self::ForgotClockOutReminder,
            self::ForgotClockInReminder,
            self::OvertimeAlert,
            self::TimesheetSubmissionDeadline,
            self::ScheduleChangeAlert,
            self::OvertimeEscalation,
            self::ForgotClockOutEscalation,
            self::TimesheetApprovalDeadline => ['push', 'email', 'in_app'],

            self::ShiftStartReminder,
            self::ShiftSwapRequest,
            self::VacationRequestResponse,
            self::ShiftPublished => ['push', 'email'],

            self::BreakTimeReminder => ['push'],

            self::OvertimeWarning,
            self::OpenShiftAvailable => ['push', 'in_app'],

            self::WeeklyTimesheetSummary,
            self::VacationBalanceReminder => ['email', 'in_app'],

            self::UnapprovedTimesheetsAlert,
            self::UnusualActivityAlert => ['push', 'email', 'in_app'],
        };
    }

    public function hasTimingConfig(): bool
    {
        return in_array($this, [
            self::ShiftStartReminder,
            self::WeeklyTimesheetSummary,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function timingOptions(): ?array
    {
        return match ($this) {
            self::ShiftStartReminder => ['minutes_before' => [15, 30, 60]],
            self::WeeklyTimesheetSummary => ['days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']],
            default => null,
        };
    }

    /**
     * @return array<self>
     */
    public static function employeeTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => ! $type->isManagerOnly());
    }

    /**
     * @return array<self>
     */
    public static function managerTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isManagerOnly());
    }

    /**
     * @return array<self>
     */
    public static function mandatoryTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isMandatory());
    }
}
