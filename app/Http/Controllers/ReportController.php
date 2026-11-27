<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Reports\ReportService;
use App\Reports\XlsxWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index', ['reports' => collect(ReportService::REPORTS)->groupBy(fn ($r) => $r[3], true)]);
    }

    public function show(Request $request, string $type, ReportService $service)
    {
        abort_unless(isset(ReportService::REPORTS[$type]), 404);

        $request->validate([
            'date' => ['nullable', 'date'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'upto' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'], 'session_id' => ['nullable', 'integer'], 'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'], 'format' => ['nullable', 'in:xlsx,pdf'],
        ]);

        $report = $service->build($type, $request);
        $file = Str::slug($report->title.' '.$report->subtitle);

        if ($request->format === 'xlsx') {
            return response(XlsxWriter::fromReport($report, Setting::get('school_name')), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$file.'.xlsx"',
            ]);
        }

        if ($request->format === 'pdf') {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('reports.pdf', compact('report'))->render(), 'UTF-8');
            $pdf->setPaper('A4', count($report->columns) > 7 ? 'landscape' : 'portrait');
            $pdf->render();

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$file.'.pdf"',
            ]);
        }

        return view('reports.show', [
            'type' => $type,
            'meta' => ReportService::REPORTS[$type],
            'report' => $report,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::ordered()->with('sections')->get(),
            'feeHeads' => FeeHead::orderBy('name')->get(),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
            'currentSession' => AcademicSession::current(),
        ]);
    }
}
