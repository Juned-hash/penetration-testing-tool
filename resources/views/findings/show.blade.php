@extends('layouts.app')

@section('title', 'Finding: ' . $finding->name)
@section('header', 'Finding Details: ' . $finding->name)

@section('content')
<!-- Header & Navigation -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        @php
            $sevClass = match(strtolower($finding->severity)) {
                'critical', 'high' => 'bg-danger',
                'medium' => 'bg-warning text-dark',
                'low' => 'bg-info text-dark',
                default => 'bg-secondary',
            };
        @endphp
        <div class="mb-2">
            <span class="badge {{ $sevClass }} text-uppercase me-2 fs-6">{{ $finding->severity }}</span>
            <span class="badge bg-light text-dark border me-2">Risk: {{ $finding->risk }}</span>
            <span class="badge bg-light text-dark border me-2">Confidence: {{ $finding->confidence }}</span>
            <span class="badge bg-dark text-uppercase me-2">Source: {{ $finding->source }}</span>
        </div>
        <h4 class="fw-bold mb-1">{{ $finding->name }}</h4>
        <span class="font-monospace text-muted">{{ $finding->url }}</span>
    </div>
    <div>
        <a href="{{ route('scans.findings.index', $scan) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Findings List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Main Column: Request Details & Evidence -->
    <div class="col-lg-8">
        <!-- Target Request & Evidence Inspector Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-terminal me-1"></i> Request Details & Evidence</h6>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted w-25">Target URL:</td>
                                <td class="font-monospace fw-semibold text-break">{{ $finding->url }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">HTTP Method:</td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">{{ $finding->method ?? 'GET' }}</span>
                                </td>
                            </tr>
                            @if ($finding->parameter)
                                <tr>
                                    <td class="text-muted">Vulnerable Parameter:</td>
                                    <td>
                                        <code class="text-danger fw-bold">{{ $finding->parameter }}</code>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                @if ($finding->attack)
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">ATTACK PAYLOAD</label>
                        <div class="bg-dark text-danger p-3 rounded font-monospace small text-break">
                            {{ $finding->attack }}
                        </div>
                    </div>
                @endif

                @if ($finding->evidence)
                    <div>
                        <label class="form-label text-muted small fw-semibold">EVIDENCE</label>
                        <div class="bg-light p-3 rounded border font-monospace small text-break">
                            {{ $finding->evidence }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vulnerability Description & Impact -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-file-text me-1"></i> Description & Impact</h6>
            </div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">DESCRIPTION</label>
                    <div class="text-dark small lh-base">
                        {{ $finding->description ?: 'No detailed description provided by scanner.' }}
                    </div>
                </div>

                @if ($finding->impact)
                    <div>
                        <label class="form-label text-muted small fw-semibold">POTENTIAL IMPACT</label>
                        <div class="text-dark small lh-base">
                            {{ $finding->impact }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Remediation Guidance & Solution -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-shield-check me-1"></i> Remediation Guidance</h6>
            </div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">RECOMMENDED SOLUTION</label>
                    <div class="text-dark small lh-base">
                        {{ $finding->solution ?: 'Refer to standard web security best practices for remediation.' }}
                    </div>
                </div>

                @if ($finding->reference)
                    <div>
                        <label class="form-label text-muted small fw-semibold">EXTERNAL REFERENCES</label>
                        <div class="font-monospace small text-break">
                            @foreach (explode("\n", $finding->reference) as $ref)
                                @if (trim($ref))
                                    <div>
                                        <i class="bi bi-link-45deg me-1"></i>
                                        @if (filter_var(trim($ref), FILTER_VALIDATE_URL))
                                            <a href="{{ trim($ref) }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                                {{ trim($ref) }}
                                            </a>
                                        @else
                                            <span>{{ trim($ref) }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Sidebar Column: Classifications & Metadata -->
    <div class="col-lg-4">
        <!-- Security Classifications Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-bookmarks me-1"></i> Classifications</h6>
            </div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">External Plugin ID:</span>
                    <span class="badge bg-light text-dark border font-monospace fs-6">
                        {{ $finding->external_id ?: 'N/A' }}
                    </span>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">CWE Identifier:</span>
                    @if ($finding->cwe_id)
                        <span class="badge bg-dark font-monospace fs-6">CWE-{{ $finding->cwe_id }}</span>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">WASC Identifier:</span>
                    @if ($finding->wasc_id)
                        <span class="badge bg-dark font-monospace fs-6">WASC-{{ $finding->wasc_id }}</span>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </div>

                <div class="mb-0">
                    <span class="text-muted small d-block mb-1">WSTG Identifier:</span>
                    @if ($finding->wstg_id)
                        <span class="badge bg-dark font-monospace fs-6">{{ $finding->wstg_id }}</span>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Finding Status Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-1"></i> Status & Metadata</h6>
            </div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <span class="text-muted small d-block">Status:</span>
                    <span class="badge bg-success text-capitalize fs-6">{{ $finding->status }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-muted small d-block">Discovered Date:</span>
                    <span class="fw-semibold small">{{ $finding->created_at->format('M d, Y H:i:s') }}</span>
                </div>
                <div class="mb-0">
                    <span class="text-muted small d-block">Assessment:</span>
                    <a href="{{ route('scans.show', $scan) }}" class="small fw-semibold text-decoration-none">
                        {{ $scan->name }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
