<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TicketReportExport;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\Reports\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function excel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $report = ReportPeriod::fromRequest($request);
        $tz = config('helpdesk.working_hours.timezone');
        $name = sprintf('it-report-%s_%s.xlsx', $report->from->setTimezone($tz)->toDateString(), $report->to->setTimezone($tz)->toDateString());

        return Excel::download(new TicketReportExport($report), $name);
    }

    public function print(Request $request): View
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $report = ReportPeriod::fromRequest($request);
        $tz = config('helpdesk.working_hours.timezone');

        return view('reports.print', [
            'period' => $report->from->setTimezone($tz)->toDateString().' → '.$report->to->setTimezone($tz)->toDateString(),
            'building' => $report->buildingId ? Building::find($report->buildingId)?->name_ar : null,
            'organization' => config('helpdesk.organization_name'),
            'summary' => $report->summary(),
            'sections' => [
                __('By problem type') => $report->byCategory(),
                __('By building') => $report->byBuilding(),
                __('By technician') => $report->byTechnician(),
            ],
            'rooms' => $report->topRooms(),
            'assets' => $report->topAssets(),
        ]);
    }
}
