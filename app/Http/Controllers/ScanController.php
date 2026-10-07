<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmAuthorizationRequest;
use App\Http\Requests\StoreScanRequest;
use App\Models\Scan;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(
        protected ScanService $scanService
    ) {}

    public function index(Request $request): View
    {
        $scans = $request->user()
            ->scans()
            ->withCount('findings')
            ->latest()
            ->paginate(10);

        return view('scans.index', compact('scans'));
    }

    public function create(): View
    {
        return view('scans.create');
    }

    public function store(StoreScanRequest $request): RedirectResponse
    {
        $scan = $this->scanService->createScan($request->user(), $request->validated());

        return redirect()
            ->route('scans.show', $scan)
            ->with('success', 'Security assessment configured successfully.');
    }

    public function show(Request $request, Scan $scan): View
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $scan->load([
            'scanConfiguration',
            'scanScopes',
            'authenticationConfiguration',
            'findings',
            'reports',
            'logs' => fn($q) => $q->latest(),
        ]);

        return view('scans.show', compact('scan'));
    }

    public function confirmAuthorization(ConfirmAuthorizationRequest $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->scanService->confirmAuthorization($scan);

        return redirect()
            ->route('scans.show', $scan)
            ->with('success', 'Authorization explicitly confirmed.');
    }

    public function start(Request $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $started = $this->scanService->startAssessment($scan);

        if (!$started) {
            return redirect()
                ->route('scans.show', $scan)
                ->with('error', 'Assessment cannot be started. Ensure authorization is confirmed and assessment is not already running.');
        }

        return redirect()
            ->route('scans.show', $scan)
            ->with('success', 'Assessment job dispatched to queue.');
    }

    public function testAuthentication(Request $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$scan->authenticationConfiguration || $scan->authenticationConfiguration->mode === 'none') {
            return redirect()
                ->route('scans.show', $scan)
                ->with('error', 'No authentication is configured for this assessment.');
        }

        \App\Jobs\TestAuthentication::dispatch($scan)->onQueue('assessments');

        return redirect()
            ->route('scans.show', $scan)
            ->with('success', 'Authentication verification test dispatched to queue. Check execution audit log for progress.');
    }


    public function rerun(Request $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!in_array($scan->status, ['completed', 'failed', 'cancelled'])) {
            return redirect()
                ->route('scans.show', $scan)
                ->with('error', 'Only completed, failed, or cancelled assessments can be rerun.');
        }

        $newScan = $this->scanService->rerunAssessment($request->user(), $scan);

        return redirect()
            ->route('scans.show', $newScan)
            ->with('success', "Assessment rerun initialized as Assessment #{$newScan->id} and queued for execution.");
    }

    public function status(Request $request, Scan $scan): JsonResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $latestReport = $scan->reports()->latest()->first();

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
            'started_at' => $scan->started_at?->toIso8601String(),
            'completed_at' => $scan->completed_at?->toIso8601String(),
            'failure_reason' => $scan->failure_reason,
            'latest_log' => $scan->logs()->latest()->first()?->message,
            'latest_report' => $latestReport ? [
                'id' => $latestReport->id,
                'status' => $latestReport->status,
                'download_url' => $latestReport->status === 'completed' ? route('reports.download', $latestReport) : null,
                'error_message' => $latestReport->error_message,
            ] : null,
        ]);
    }
}
