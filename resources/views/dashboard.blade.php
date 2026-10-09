@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Security Assessment Dashboard')

@section('content')
<!-- Assessment Status Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="glass-card h-100 p-3">
            <div class="text-secondary font-size-12 fw-semibold text-uppercase">Total</div>
            <h3 class="fw-bold mb-0 text-dark font-size-32">{{ $total_scans }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="glass-card h-100 p-3">
            <div class="text-secondary font-size-12 fw-semibold text-uppercase">Queued</div>
            <h3 class="fw-bold mb-0 text-info font-size-32">{{ $queued_scans }}</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card h-100 p-3">
            <div class="text-secondary font-size-12 fw-semibold text-uppercase">Running / Active</div>
            <h3 class="fw-bold mb-0 text-primary font-size-32">{{ $running_scans }}</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card h-100 p-3">
            <div class="text-secondary font-size-12 fw-semibold text-uppercase">Completed</div>
            <h3 class="fw-bold mb-0 text-success font-size-32">{{ $completed_scans }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="glass-card h-100 p-3">
            <div class="text-secondary font-size-12 fw-semibold text-uppercase">Failed</div>
            <h3 class="fw-bold mb-0 text-danger font-size-32">{{ $failed_scans }}</h3>
        </div>
    </div>
</div>

<!-- Findings Severity Summary -->
<div class="glass-card mb-4">
    <div class="glass-card-header">
        <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-shield-exclamation me-2 text-primary"></i>Findings Summary by Severity</h6>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-2">
                <div class="glass-panel text-center">
                    <div class="badge badge-critical mb-2">Critical</div>
                    <div class="font-size-26 fw-bold text-dark">{{ $findings_by_severity['Critical'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="glass-panel text-center">
                    <div class="badge badge-high mb-2">High</div>
                    <div class="font-size-26 fw-bold text-dark">{{ $findings_by_severity['High'] }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-panel text-center">
                    <div class="badge badge-medium mb-2">Medium</div>
                    <div class="font-size-26 fw-bold text-dark">{{ $findings_by_severity['Medium'] }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-panel text-center">
                    <div class="badge badge-low mb-2">Low</div>
                    <div class="font-size-26 fw-bold text-dark">{{ $findings_by_severity['Low'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="glass-panel text-center">
                    <div class="badge badge-info mb-2">Informational</div>
                    <div class="font-size-26 fw-bold text-dark">{{ $findings_by_severity['Informational'] }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Assessments Table -->
<div class="glass-card">
    <div class="glass-card-header d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Security Assessments</h6>
        <a href="{{ route('scans.index') }}" class="app-btn-secondary app-btn-sm">View All Assessments</a>
    </div>
    <div class="table-responsive">
        @if ($recent_scans->isEmpty())
            <div class="p-4">
                <x-empty-state icon="bi-shield-slash" title="No Assessments Configured" message="You have not created any security assessments yet.">
                    <a href="{{ route('scans.create') }}" class="app-btn-primary app-btn-sm mt-2">
                        <i class="bi bi-plus-circle"></i> Start New Assessment
                    </a>
                </x-empty-state>
            </div>
        @else
            <table class="table glass-table align-middle">
                <thead>
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
                                <a href="{{ route('scans.show', $scan) }}" class="fw-semibold text-decoration-none text-dark font-size-14">
                                    {{ $scan->target_url }}
                                </a>
                            </td>
                            <td>{{ $scan->name }}</td>
                            <td>
                                <span class="glass-badge bg-white text-dark">{{ ucfirst($scan->environment) }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$scan->status" />
                            </td>
                            <td>
                                <span class="badge bg-secondary font-size-12">{{ $scan->findings_count }}</span>
                            </td>
                            <td class="text-secondary font-size-12">{{ $scan->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('scans.show', $scan) }}" class="app-btn-secondary app-btn-sm">
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
