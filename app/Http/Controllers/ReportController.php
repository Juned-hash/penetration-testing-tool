<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Scan;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of reports for the authenticated user's assessments.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $reports = Report::with('scan')
            ->whereHas('scan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->paginate(10);

        return view('reports.index', compact('reports'));
    }

    /**
     * Trigger PDF report generation for an assessment scan.
     *
     * @param Request $request
     * @param Scan $scan
     * @param ReportService $reportService
     * @return RedirectResponse
     */
    public function generatePdf(Request $request, Scan $scan, ReportService $reportService): RedirectResponse
    {
        $this->authorize('update', $scan);

        $reportService->generateReport($scan, 'pdf');

        return redirect()
            ->back()
            ->with('success', 'PDF security assessment report generated successfully.');
    }

    /**
     * Download a generated report file securely.
     *
     * @param Request $request
     * @param Report $report
     * @return BinaryFileResponse
     */
    public function download(Request $request, Report $report): BinaryFileResponse
    {
        if ($report->scan->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to assessment report.');
        }

        $fullPath = storage_path('app/' . $report->file_path);
        if (!File::exists($fullPath)) {
            // Fallback check if path is absolute
            if (File::exists($report->file_path)) {
                $fullPath = $report->file_path;
            } else {
                abort(404, 'Report file was not found on disk.');
            }
        }

        $downloadFilename = 'Security_Assessment_Report_Scan_' . $report->scan_id . '.' . $report->type;

        return response()->download($fullPath, $downloadFilename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
