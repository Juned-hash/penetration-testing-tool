@extends('layouts.app')

@section('title', $scan->name)
@section('header', 'Assessment Review: ' . $scan->name)

@section('content')
<!-- Header & Back Button -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ $scan->name }}</h4>
        <span class="font-monospace text-muted me-2">{{ $scan->target_url }}</span>
        <span class="badge bg-light text-dark border me-2">{{ ucfirst($scan->environment) }}</span>
        <x-status-badge :status="$scan->status" />
    </div>
    <div>
        <a href="{{ route('scans.findings.index', $scan) }}" class="btn btn-primary btn-sm me-2 fw-semibold">
            <i class="bi bi-shield-exclamation me-1"></i> View Findings ({{ $scan->findings->count() }})
        </a>
        <a href="{{ route('scans.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Assessments
        </a>
    </div>
</div>

<!-- Production Environment Warning Alert -->
@if ($scan->environment === 'production')
    <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
            <div>
                <h6 class="fw-bold mb-1">PRODUCTION ENVIRONMENT WARNING</h6>
                <div class="small">This assessment targets a <strong>Production</strong> environment. Active security testing should only be executed against explicitly approved production targets during authorized maintenance windows. Ensure all approvals are documented before proceeding.</div>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <!-- Review Column -->
    <div class="col-lg-8">
        <!-- Assessment Review Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Pre-Execution Assessment Review</h6>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted w-25">Target URL:</td>
                                <td class="font-monospace fw-semibold">{{ $scan->target_url }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Environment:</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst($scan->environment) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td><x-status-badge :status="$scan->status" /></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Authentication Mode:</td>
                                <td>
                                    <span class="badge bg-dark text-uppercase">
                                        {{ $scan->authenticationConfiguration->mode ?? 'none' }}
                                    </span>
                                    @if ($scan->authenticationConfiguration && $scan->authenticationConfiguration->mode !== 'none')
                                        <div class="mt-1 small font-monospace text-muted">
                                            Credentials: <span class="badge bg-light text-secondary border">•••••••• [ENCRYPTED SECRET]</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if ($scan->authenticationConfiguration && $scan->authenticationConfiguration->mode !== 'none')
                                @php
                                    $authLog = $scan->logs->firstWhere('phase', 'zap_auth_test_completed') ?? $scan->logs->firstWhere('phase', 'zap_auth_diagnostics');
                                    $authStatus = 'AUTHENTICATION_UNKNOWN';
                                    $authBadge = 'bg-warning text-dark';
                                    if ($authLog) {
                                        $rawMsg = $authLog->message;
                                        if (str_starts_with(trim($rawMsg), '{')) {
                                            $decoded = json_decode($rawMsg, true);
                                            $rawMsg = $decoded['log_message'] ?? $rawMsg;
                                        }
                                        if (str_contains($rawMsg, 'Status: AUTHENTICATED') || str_contains($rawMsg, 'Status: SUCCESS')) {
                                            $authStatus = 'AUTHENTICATED';
                                            $authBadge = 'bg-success';
                                        } elseif (str_contains($rawMsg, 'Status: AUTHENTICATION_FAILED') || str_contains($rawMsg, 'Status: FAILED')) {
                                            $authStatus = 'AUTHENTICATION_FAILED';
                                            $authBadge = 'bg-danger';
                                        } elseif (str_contains($rawMsg, 'Status: NOT_CONFIGURED') || str_contains($rawMsg, 'Status: NONE')) {
                                            $authStatus = 'NOT_CONFIGURED';
                                            $authBadge = 'bg-secondary';
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="text-muted align-top">ZAP Auth Status:</td>
                                    <td>
                                        @if ($authLog)
                                            <span class="badge {{ $authBadge }} fs-6 mb-2">{{ $authStatus }}</span>
                                            <div class="bg-light p-3 rounded border font-monospace small">
                                                <div class="mb-2 text-dark fw-bold border-bottom pb-1">
                                                    <i class="bi bi-shield-check me-1"></i> OWASP ZAP Authentication Diagnostics
                                                </div>
                                                @php
                                                    $msg = $authLog->message;
                                                    if (str_starts_with(trim($msg), '{')) {
                                                        $decoded = json_decode($msg, true);
                                                        $msg = $decoded['log_message'] ?? $msg;
                                                    }
                                                    preg_match('/Username Field:\s*([^\|]+)/i', $msg, $uMatch);
                                                    preg_match('/Password Field:\s*([^\|]+)/i', $msg, $pMatch);
                                                    preg_match('/Login Attempt:\s*([^\|]+)/i', $msg, $lMatch);
                                                    preg_match('/Successful Logins:\s*([^\|]+)/i', $msg, $slMatch);
                                                    preg_match('/Failed Logins:\s*([^\|]+)/i', $msg, $flMatch);
                                                    preg_match('/Session Management:\s*([^\|]+)/i', $msg, $smMatch);
                                                    preg_match('/Verification:\s*([^\|]+)/i', $msg, $vMatch);
                                                    preg_match('/Failure Reason:\s*([^\|]+)/i', $msg, $frMatch);
                                                    preg_match('/Message:\s*(.+)$/i', $msg, $mMatch);
                                                @endphp
                                                <div class="row g-2 mb-2 text-dark">
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Username Field:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($uMatch[1] ?? 'NOT IDENTIFIED') }}</span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Password Field:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($pMatch[1] ?? 'NOT IDENTIFIED') }}</span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Login Attempt:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($lMatch[1] ?? 'NO') }}</span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Successful / Failed Logins:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($slMatch[1] ?? '0') }} / {{ trim($flMatch[1] ?? '0') }}</span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Session Management:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($smMatch[1] ?? 'NOT IDENTIFIED') }}</span>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted">Verification:</span>
                                                        <span class="fw-semibold text-dark">{{ trim($vMatch[1] ?? 'NOT IDENTIFIED') }}</span>
                                                    </div>
                                                </div>
                                                @if (!empty($frMatch[1]) && trim($frMatch[1]) !== 'None')
                                                    <div class="mt-2 pt-2 border-top text-danger">
                                                        <strong>Failure Reason:</strong> {{ trim($frMatch[1]) }}
                                                    </div>
                                                @endif
                                                @if (!empty($mMatch[1]))
                                                    <div class="mt-1 text-muted small border-top pt-1">
                                                        {{ trim($mMatch[1]) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge bg-secondary">NOT EXECUTED / UNKNOWN</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Testing Scope & Exclusions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Testing Scope & Exclusions</h6>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">INCLUDED SCOPE</label>
                        <div class="bg-light p-2 rounded border font-monospace small">
                            @forelse ($scan->scanScopes->where('type', 'include') as $scope)
                                <div><i class="bi bi-check-circle-fill text-success me-1"></i> {{ $scope->path }}</div>
                            @empty
                                <div class="text-muted">/* (Entire Target Domain)</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">EXCLUDED PATHS</label>
                        <div class="bg-light p-2 rounded border font-monospace small">
                            @forelse ($scan->scanScopes->where('type', 'exclude') as $scope)
                                <div><i class="bi bi-x-circle-fill text-danger me-1"></i> {{ $scope->path }}</div>
                            @empty
                                <div class="text-muted">No paths excluded.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Execution Audit Logs Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Execution Audit Logs</h6>
            </div>
            <div class="card-body pt-0">
                @if ($scan->logs->isEmpty())
                    <div class="text-muted small">No log events recorded yet.</div>
                @else
                    <div class="bg-dark text-light p-3 rounded font-monospace small" style="max-height: 250px; overflow-y: auto;">
                        @foreach ($scan->logs as $log)
                            <div class="mb-1">
                                <span class="text-white">[{{ $log->created_at->format('H:i:s') }}]</span>
                                <span class="badge bg-secondary me-1">{{ strtoupper($log->phase) }}</span>
                                <span>{{ $log->message }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Pre-Execution Authorization & Execution Column -->
    <div class="col-lg-4">
        <!-- Authorization & Execution Control Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock me-1"></i> Control Panel</h6>
            </div>
            <div class="card-body pt-0">
                @if ($scan->authorization_confirmed_at)
                    <div class="alert alert-success border-success small mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i> Authorization explicitly confirmed on<br>
                        <strong>{{ $scan->authorization_confirmed_at->format('M d, Y H:i:s') }}</strong>
                    </div>

                    @if ($scan->authenticationConfiguration && $scan->authenticationConfiguration->mode !== 'none')
                        <form method="POST" action="{{ route('scans.test-authentication', $scan) }}" class="mb-3" onsubmit="return confirm('Run dedicated ZAP Authentication Verification Test?\n\nThis will execute a lightweight authentication check without launching a full vulnerability scan.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-info w-100 fw-bold py-2">
                                <i class="bi bi-key-fill me-1"></i> Verify Authentication
                            </button>
                        </form>
                    @endif

                    @if ($scan->status === 'draft')
                        <form method="POST" action="{{ route('scans.start', $scan) }}">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 fw-bold py-2 mb-2">
                                <i class="bi bi-play-fill me-1"></i> Dispatch Job to Queue
                            </button>
                        </form>
                    @elseif (in_array($scan->status, ['queued', 'starting', 'running', 'crawling', 'passive_scanning', 'active_scanning', 'processing_results', 'generating_report']))
                        <div class="alert alert-primary border-primary small mb-3">
                            <i class="bi bi-arrow-repeat me-1 spin"></i> Assessment job actively processing...
                        </div>
                    @else
                        <div class="badge bg-secondary w-100 py-2 fs-6 mb-3">
                            Status: {{ ucfirst($scan->status) }}
                        </div>
                        @if ($scan->status === 'completed')
                            @php
                                $latestReport = $scan->reports()->latest()->first();
                            @endphp

                            @if ($latestReport && in_array($latestReport->status, ['queued', 'generating']))
                                <div class="alert alert-info border-info small mb-3" id="pdf-status-alert">
                                    <i class="bi bi-arrow-repeat spin me-1"></i> PDF report is being generated in the background...
                                </div>
                                <button type="button" class="btn btn-secondary w-100 fw-bold py-2 mb-2" disabled>
                                    <i class="bi bi-hourglass-split me-1"></i> PDF Generating...
                                </button>
                            @elseif ($latestReport && $latestReport->status === 'completed')
                                <a href="{{ route('reports.download', $latestReport) }}" class="btn btn-success w-100 fw-bold py-2 mb-2">
                                    <i class="bi bi-download me-1"></i> Download PDF Report
                                </a>
                                <form method="POST" action="{{ route('scans.reports.pdf', $scan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Regenerate PDF
                                    </button>
                                </form>
                            @elseif ($latestReport && $latestReport->status === 'failed')
                                <div class="alert alert-danger border-danger small mb-3">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> PDF Generation Failed:<br>
                                    <span class="small font-monospace">{{ $latestReport->error_message }}</span>
                                </div>
                                <form method="POST" action="{{ route('scans.reports.pdf', $scan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger w-100 fw-bold py-2 mb-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Retry PDF Report
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('scans.reports.pdf', $scan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary w-100 fw-bold py-2 mb-2">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Generate PDF Report
                                    </button>
                                </form>
                            @endif
                        @endif

                        @if (in_array($scan->status, ['completed', 'failed', 'cancelled']))
                            <form method="POST" action="{{ route('scans.rerun', $scan) }}" onsubmit="return confirm('Rerun this assessment?\n\nA new assessment will be created using the same configuration. The existing assessment and its findings will remain unchanged.');">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                    <i class="bi bi-arrow-repeat me-1"></i> Rerun Assessment
                                </button>
                            </form>
                        @endif
                    @endif
                @else
                    <div class="alert alert-danger border-danger small mb-3">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i> <strong>Authorization Required:</strong> Active testing cannot start without explicit user authorization confirmation.
                    </div>

                    <form method="POST" action="{{ route('scans.confirm-authorization', $scan) }}">
                        @csrf
                        <div class="form-check mb-3 bg-light p-3 rounded border">
                            <input type="checkbox" class="form-check-input ms-0 me-2" id="authorization_confirmed" name="authorization_confirmed" value="1" required>
                            <label class="form-check-label small fw-semibold" for="authorization_confirmed">
                                I confirm that I am authorized to perform security testing against this target and that the target is within the approved assessment scope.
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                            Confirm Authorization
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($scan->parentScan)
            <!-- Parent Assessment Link Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-diagram-2 me-1"></i> Lineage</h6>
                </div>
                <div class="card-body pt-0 small">
                    <div class="text-muted mb-1">Rerun of parent assessment:</div>
                    <a href="{{ route('scans.show', $scan->parentScan) }}" class="fw-bold text-decoration-none d-block">
                        <i class="bi bi-arrow-return-right me-1"></i> Assessment #{{ $scan->parentScan->id }} ({{ $scan->parentScan->name }})
                    </a>
                </div>
            </div>
        @endif

        @if ($scan->reruns->isNotEmpty())
            <!-- Child Reruns History Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i> Rerun History ({{ $scan->reruns->count() }})</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="list-group list-group-flush small">
                        @foreach ($scan->reruns as $rerun)
                            <a href="{{ route('scans.show', $rerun) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between px-0">
                                <div>
                                    <span class="fw-semibold">Assessment #{{ $rerun->id }}</span>
                                    <div class="text-muted small">{{ $rerun->created_at->format('M d, Y H:i') }}</div>
                                </div>
                                <x-status-badge :status="$rerun->status" />
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Scan Metadata Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Assessment Timestamps</h6>
            </div>
            <div class="card-body pt-0">
                <div class="mb-2">
                    <span class="text-muted small d-block">Created:</span>
                    <span class="fw-semibold small">{{ $scan->created_at->format('M d, Y H:i') }}</span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-block">Started:</span>
                    <span class="fw-semibold small">{{ $scan->started_at ? $scan->started_at->format('M d, Y H:i') : 'Not Started' }}</span>
                </div>
                <div class="mb-0">
                    <span class="text-muted small d-block">Completed:</span>
                    <span class="fw-semibold small">{{ $scan->completed_at ? $scan->completed_at->format('M d, Y H:i') : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($scan->status === 'completed' && isset($latestReport) && in_array($latestReport->status, ['queued', 'generating']))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const interval = setInterval(function () {
            fetch("{{ route('scans.status', $scan) }}")
                .then(res => res.json())
                .then(data => {
                    if (data.latest_report && ['completed', 'failed'].includes(data.latest_report.status)) {
                        clearInterval(interval);
                        window.location.reload();
                    }
                })
                .catch(() => {});
        }, 4000);
    });
</script>
@endif
@endsection
