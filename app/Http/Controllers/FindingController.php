<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Scan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FindingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of findings for the given assessment scan.
     *
     * @param Request $request
     * @param Scan $scan
     * @return View
     */
    public function index(Request $request, Scan $scan): View
    {
        $this->authorize('view', $scan);

        $query = $scan->findings();

        // Severity filtering
        if ($request->filled('severity')) {
            $query->where('severity', strtolower($request->severity));
        }

        // Confidence filtering
        if ($request->filled('confidence')) {
            $query->where('confidence', $request->confidence);
        }

        // Status filtering
        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }

        // Sorting
        $sortField = $request->input('sort', 'id');
        $sortDirection = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'name', 'severity', 'risk', 'confidence', 'status', 'url', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        $findings = $query->paginate(15)->withQueryString();

        return view('findings.index', [
            'scan' => $scan,
            'findings' => $findings,
            'filters' => [
                'severity' => $request->input('severity'),
                'confidence' => $request->input('confidence'),
                'status' => $request->input('status'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Display the details of a specific finding.
     *
     * @param Request $request
     * @param Scan $scan
     * @param Finding $finding
     * @return View
     */
    public function show(Request $request, Scan $scan, Finding $finding): View
    {
        $this->authorize('view', $scan);

        if ($finding->scan_id !== $scan->id) {
            abort(404, 'Finding not found for this assessment.');
        }

        return view('findings.show', [
            'scan' => $scan,
            'finding' => $finding,
        ]);
    }
}
