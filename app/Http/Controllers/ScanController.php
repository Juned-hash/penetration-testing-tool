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

    public function cancel(Request $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $cancelled = $this->scanService->cancelAssessment($scan);

        if (!$cancelled) {
            return redirect()
                ->route('scans.show', $scan)
                ->with('error', 'Assessment is already finished or cannot be cancelled.');
        }

        return redirect()
            ->route('scans.show', $scan)
            ->with('success', 'Assessment status set to cancelled.');
    }

    public function status(Request $request, Scan $scan): JsonResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
            'started_at' => $scan->started_at?->toIso8601String(),
            'completed_at' => $scan->completed_at?->toIso8601String(),
            'failure_reason' => $scan->failure_reason,
            'latest_log' => $scan->logs()->latest()->first()?->message,
        ]);
    }
}
