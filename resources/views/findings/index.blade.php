@extends('layouts.app')

@section('title', 'Findings: ' . $scan->name)
@section('header', 'Assessment Findings: ' . $scan->name)

@section('content')
<!-- Header & Navigation -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1 font-size-26 text-dark">Assessment Findings</h4>
        <span class="font-monospace text-secondary me-2 font-size-14">{{ $scan->target_url }}</span>
        <span class="glass-badge bg-white text-dark me-2 font-size-12">{{ ucfirst($scan->environment) }}</span>
        <x-status-badge :status="$scan->status" />
    </div>
    <div>
        <a href="{{ route('scans.show', $scan) }}" class="app-btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Assessment Review
        </a>
    </div>
</div>

<!-- Filter & Sort Toolbar -->
<div class="glass-card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('scans.findings.index', $scan) }}" class="row g-2 align-items-center">
            <!-- Severity Filter -->
            <div class="col-md-2">
                <label class="form-label text-secondary font-size-12 fw-semibold mb-1">Severity</label>
                <select name="severity" class="form-select glass-select font-size-14" onchange="this.form.submit()">
                    <option value="">All Severities</option>
                    <option value="high" {{ ($filters['severity'] ?? '') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ ($filters['severity'] ?? '') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ ($filters['severity'] ?? '') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="info" {{ ($filters['severity'] ?? '') === 'info' ? 'selected' : '' }}>Informational</option>
                </select>
            </div>

            <!-- Confidence Filter -->
            <div class="col-md-2">
                <label class="form-label text-secondary font-size-12 fw-semibold mb-1">Confidence</label>
                <select name="confidence" class="form-select glass-select font-size-14" onchange="this.form.submit()">
                    <option value="">All Confidence</option>
                    <option value="High" {{ ($filters['confidence'] ?? '') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Medium" {{ ($filters['confidence'] ?? '') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="Low" {{ ($filters['confidence'] ?? '') === 'Low' ? 'selected' : '' }}>Low</option>
                    <option value="False Positive" {{ ($filters['confidence'] ?? '') === 'False Positive' ? 'selected' : '' }}>False Positive</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2">
                <label class="form-label text-secondary font-size-12 fw-semibold mb-1">Status</label>
                <select name="status" class="form-select glass-select font-size-14" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="confirmed" {{ ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="resolved" {{ ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="false_positive" {{ ($filters['status'] ?? '') === 'false_positive' ? 'selected' : '' }}>False Positive</option>
                </select>
            </div>

            <!-- Sort By -->
            <div class="col-md-2">
                <label class="form-label text-secondary font-size-12 fw-semibold mb-1">Sort By</label>
                <select name="sort" class="form-select glass-select font-size-14" onchange="this.form.submit()">
                    <option value="id" {{ ($filters['sort'] ?? '') === 'id' ? 'selected' : '' }}>Default (ID)</option>
                    <option value="name" {{ ($filters['sort'] ?? '') === 'name' ? 'selected' : '' }}>Name</option>
                    <option value="severity" {{ ($filters['sort'] ?? '') === 'severity' ? 'selected' : '' }}>Severity</option>
                    <option value="confidence" {{ ($filters['sort'] ?? '') === 'confidence' ? 'selected' : '' }}>Confidence</option>
                </select>
            </div>

            <!-- Sort Direction -->
            <div class="col-md-2">
                <label class="form-label text-secondary font-size-12 fw-semibold mb-1">Order</label>
                <select name="direction" class="form-select glass-select font-size-14" onchange="this.form.submit()">
                    <option value="desc" {{ ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' }}>Descending</option>
                    <option value="asc" {{ ($filters['direction'] ?? '') === 'asc' ? 'selected' : '' }}>Ascending</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="col-md-2 d-flex align-items-end pt-3 pt-md-0">
                <a href="{{ route('scans.findings.index', $scan) }}" class="app-btn-secondary app-btn-sm w-100">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Findings List Card -->
<div class="glass-card">
    <div class="glass-card-header d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-shield-exclamation me-2 text-primary"></i>Discovered Vulnerabilities ({{ $findings->total() }})</h6>
    </div>
    <div class="card-body p-0">
        @if ($findings->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-shield-check text-secondary font-size-45 mb-2 d-block"></i>
                <h6 class="fw-bold text-secondary mb-1 font-size-18">No Findings Found</h6>
                <p class="text-secondary font-size-14 mb-0">No vulnerability findings match your current filter parameters.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table glass-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 120px;">SEVERITY</th>
                            <th>VULNERABILITY & TARGET URL</th>
                            <th>CONFIDENCE</th>
                            <th>CWE / WASC</th>
                            <th class="pe-4 text-end">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($findings as $finding)
                            @php
                                $sevClass = match(strtolower($finding->severity)) {
                                    'critical', 'high' => 'badge-high',
                                    'medium' => 'badge-medium',
                                    'low' => 'badge-low',
                                    default => 'badge-info',
                                };
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="glass-badge {{ $sevClass }} text-uppercase font-size-12">
                                        {{ $finding->severity }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-1 font-size-14">{{ $finding->name }}</div>
                                    <div class="font-monospace font-size-12 text-secondary">
                                        @if ($finding->method)
                                            <span class="glass-badge bg-white text-dark me-1 font-size-12">{{ $finding->method }}</span>
                                        @endif
                                        {{ $finding->url }}
                                        @if ($finding->parameter)
                                            <span class="text-danger font-monospace">[{{ $finding->parameter }}]</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="glass-badge bg-white text-dark font-size-12">
                                        {{ $finding->confidence }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-monospace font-size-12">
                                        @if ($finding->cwe_id)
                                            <span class="glass-badge bg-white text-dark me-1 font-size-12">CWE-{{ $finding->cwe_id }}</span>
                                        @endif
                                        @if ($finding->wasc_id)
                                            <span class="glass-badge bg-white text-dark font-size-12">WASC-{{ $finding->wasc_id }}</span>
                                        @endif
                                        @if (!$finding->cwe_id && !$finding->wasc_id)
                                            <span class="text-secondary font-size-12">N/A</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('scans.findings.show', [$scan, $finding]) }}" class="app-btn-secondary app-btn-sm">
                                        View Detail <i class="bi bi-chevron-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-top">
                {{ $findings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
