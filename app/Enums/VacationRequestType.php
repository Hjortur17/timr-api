<?php

namespace App\Enums;

enum VacationRequestType: string
{
    case Holiday = 'holiday';
    case UnpaidLeave = 'unpaid_leave';
    case SickLeave = 'sick_leave';
    case ParentalLeave = 'parental_leave';
    case Compassionate = 'compassionate';

    /**
     * Whether this type consumes the employee's vacation balance.
     */
    public function isDeductible(): bool
    {
        return $this === self::Holiday;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
