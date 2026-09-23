@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Security Assessment Dashboard')

@section('content')
<!-- Assessment Status Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase">Total</div>
                <h3 class="fw-bold mb-0 text-dark">{{ $total_scans }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase">Queued</div>
                <h3 class="fw-bold mb-0 text-info">{{ $queued_scans }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase">Running / Active</div>
                <h3 class="fw-bold mb-0 text-primary">{{ $running_scans }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase">Completed</div>
                <h3 class="fw-bold mb-0 text-success">{{ $completed_scans }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small fw-semibold text-uppercase">Failed</div>
                <h3 class="fw-bold mb-0 text-danger">{{ $failed_scans }}</h3>
            </div>
        </div>
    </div>
</div>

<!-- Findings Severity Summary -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0">Findings Summary by Severity</h6>
    </div>
    <div class="card-body pt-0">
        <div class="row g-2">
            <div class="col-md-2">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="badge badge-critical mb-1">Critical</div>
                    <div class="fs-4 fw-bold text-dark">{{ $findings_by_severity['Critical'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="badge badge-high mb-1">High</div>
                    <div class="fs-4 fw-bold text-dark">{{ $findings_by_severity['High'] }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="badge badge-medium mb-1">Medium</div>
                    <div class="fs-4 fw-bold text-dark">{{ $findings_by_severity['Medium'] }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="badge badge-low mb-1">Low</div>
                    <div class="fs-4 fw-bold text-dark">{{ $findings_by_severity['Low'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="p-3 rounded border text-center bg-light">
                    <div class="badge badge-info mb-1">Informational</div>
                    <div class="fs-4 fw-bold text-dark">{{ $findings_by_severity['Informational'] }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Assessments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-0">
        <h6 class="fw-bold mb-0">Recent Security Assessments</h6>
        <a href="{{ route('scans.index') }}" class="btn btn-sm btn-outline-primary">View All Assessments</a>
    </div>
    <div class="table-responsive">
        @if ($recent_scans->isEmpty())
            <x-empty-state icon="bi-shield-slash" title="No Assessments Configured" message="You have not created any security assessments yet.">
                <a href="{{ route('scans.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Start New Assessment
                </a>
            </x-empty-state>
        @else
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Target URL</th>
                        <th>Application</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>Findings</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent_scans as $scan)
                        <tr>
                            <td>
                                <a href="{{ route('scans.show', $scan) }}" class="fw-semibold text-decoration-none text-dark">
                                    {{ $scan->target_url }}
                                </a>
                            </td>
                            <td>{{ $scan->name }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ ucfirst($scan->environment) }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$scan->status" />
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $scan->findings_count }}</span>
                            </td>
                            <td class="text-muted small">{{ $scan->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('scans.show', $scan) }}" class="btn btn-sm btn-light border">
                                    View Detail
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
