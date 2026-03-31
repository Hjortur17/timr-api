<?php

namespace App\Http\Controllers\Manager;

use App\Exports\ClockEntriesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClockEntry\ClockEntryIndexRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function clockEntries(ClockEntryIndexRequest $request): BinaryFileResponse
    {
        $export = new ClockEntriesExport(
            employeeId: $request->integer('employee_id') ?: null,
            from: $request->input('from'),
            to: $request->input('to'),
        );

        $filename = 'timaskraning_'.now()->format('Y-m-d').'.xlsx';

        return Excel::download($export, $filename);
    }
}
