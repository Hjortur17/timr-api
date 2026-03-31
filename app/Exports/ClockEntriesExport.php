<?php

namespace App\Exports;

use App\Models\ClockEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClockEntriesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private ?int $employeeId = null,
        private ?string $from = null,
        private ?string $to = null,
    ) {}

    public function collection(): Collection
    {
        $query = ClockEntry::query()
            ->with(['employee', 'shift'])
            ->orderByDesc('clocked_in_at');

        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }

        if ($this->from) {
            $query->where('clocked_in_at', '>=', $this->from);
        }

        if ($this->to) {
            $query->where('clocked_in_at', '<=', now()->parse($this->to)->endOfDay());
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Starfsmaður',
            'Netfang',
            'Vakt',
            'Dagsetning',
            'Inn',
            'Út',
            'Heildartímar',
            'Auka',
        ];
    }

    /**
     * @param  ClockEntry  $entry
     */
    public function map($entry): array
    {
        $totalHours = null;
        if ($entry->clocked_in_at && $entry->clocked_out_at) {
            $totalHours = round($entry->clocked_in_at->diffInMinutes($entry->clocked_out_at) / 60, 2);
        }

        return [
            $entry->employee?->name ?? '',
            $entry->employee?->email ?? '',
            $entry->shift?->title ?? '',
            $entry->clocked_in_at?->format('Y-m-d'),
            $entry->clocked_in_at?->format('H:i'),
            $entry->clocked_out_at?->format('H:i') ?? '',
            $totalHours,
            $entry->shift_id === null ? 'Já' : 'Nei',
        ];
    }
}
