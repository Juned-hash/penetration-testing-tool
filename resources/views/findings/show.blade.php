@extends('layouts.app')

@section('title', 'Finding: ' . $finding->name)
@section('header', 'Finding Details: ' . $finding->name)

@section('content')
<!-- Header & Navigation -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        @php
            $sevClass = match(strtolower($finding->severity)) {
                'critical', 'high' => 'badge-high',
                'medium' => 'badge-medium',
                'low' => 'badge-low',
                default => 'badge-info',
            };
        @endphp
        <div class="mb-2">
            <span class="glass-badge {{ $sevClass }} text-uppercase me-2 font-size-12">{{ $finding->severity }}</span>
            <span class="glass-badge bg-white text-dark me-2 font-size-12">Risk: {{ $finding->risk }}</span>
            <span class="glass-badge bg-white text-dark me-2 font-size-12">Confidence: {{ $finding->confidence }}</span>
            <span class="glass-badge bg-dark text-white text-uppercase me-2 font-size-12">Source: {{ $finding->source }}</span>
        </div>
        <h4 class="fw-bold mb-1 font-size-26 text-dark">{{ $finding->name }}</h4>
        <span class="font-monospace text-secondary font-size-14">{{ $finding->url }}</span>
    </div>
    <div>
        <a href="{{ route('scans.findings.index', $scan) }}" class="app-btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Findings
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Main Column: Request Details & Evidence -->
    <div class="col-lg-8">
        <!-- Target Request & Evidence Inspector Card -->
        <div class="glass-card mb-4">
            <div class="glass-card-header">
                <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-terminal me-2 text-primary"></i>Request Details & Evidence</h6>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive mb-3">
                    <table class="table glass-table align-middle">
                        <tbody>
                            <tr>
                                <td class="text-secondary w-25 font-size-14">Target URL:</td>
                                <td class="font-monospace fw-semibold text-break font-size-14 text-dark">{{ $finding->url }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary font-size-14">HTTP Method:</td>
                                <td>
                                    <span class="glass-badge bg-white text-dark font-monospace font-size-12">{{ $finding->method ?? 'GET' }}</span>
                                </td>
                            </tr>
                            @if ($finding->parameter)
                                <tr>
                                    <td class="text-secondary font-size-14">Vulnerable Parameter:</td>
                                    <td>
                                        <code class="text-danger fw-bold font-size-14">{{ $finding->parameter }}</code>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                @if ($finding->attack)
                    <div class="mb-3">
                        <label class="form-label text-secondary font-size-12 fw-semibold">ATTACK PAYLOAD</label>
                        <div class="bg-dark text-danger p-3 rounded font-monospace font-size-12 text-break">
                            {{ $finding->attack }}
                        </div>
                    </div>
                @endif

                @if ($finding->evidence)
                    <div>
                        <label class="form-label text-secondary font-size-12 fw-semibold">EVIDENCE</label>
                        <div class="glass-panel font-monospace font-size-12 text-break">
                            {{ $finding->evidence }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Vulnerability Description & Impact -->
        <div class="glass-card mb-4">
            <div class="glass-card-header">
                <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Description & Impact</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label text-secondary font-size-12 fw-semibold">DESCRIPTION</label>
                    <div class="text-dark font-size-14 lh-base">
                        {{ $finding->description ?: 'No detailed description provided by scanner.' }}
                    </div>
                </div>

                @if ($finding->impact)
                    <div>
                        <label class="form-label text-secondary font-size-12 fw-semibold">POTENTIAL IMPACT</label>
                        <div class="text-dark font-size-14 lh-base">
                            {{ $finding->impact }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Remediation Guidance & Solution -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>Remediation Guidance</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label text-secondary font-size-12 fw-semibold">RECOMMENDED SOLUTION</label>
                    <div class="text-dark font-size-14 lh-base">
                        {{ $finding->solution ?: 'Refer to standard web security best practices for remediation.' }}
                    </div>
                </div>

                @if ($finding->reference)
                    <div>
                        <label class="form-label text-secondary font-size-12 fw-semibold">EXTERNAL REFERENCES</label>
                        <div class="font-monospace font-size-12 text-break">
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
        <div class="glass-card mb-4">
            <div class="glass-card-header">
                <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-bookmarks me-2 text-primary"></i>Classifications</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="text-secondary font-size-12 d-block mb-1">External Plugin ID:</span>
                    <span class="glass-badge bg-white text-dark font-monospace font-size-14">
                        {{ $finding->external_id ?: 'N/A' }}
                    </span>
                </div>

                <div class="mb-3">
                    <span class="text-secondary font-size-12 d-block mb-1">CWE Identifier:</span>
                    @if ($finding->cwe_id)
                        <span class="glass-badge bg-dark text-white font-monospace font-size-14">CWE-{{ $finding->cwe_id }}</span>
                    @else
                        <span class="text-secondary font-size-12">N/A</span>
                    @endif
                </div>

                <div class="mb-3">
                    <span class="text-secondary font-size-12 d-block mb-1">WASC Identifier:</span>
                    @if ($finding->wasc_id)
                        <span class="glass-badge bg-dark text-white font-monospace font-size-14">WASC-{{ $finding->wasc_id }}</span>
                    @else
                        <span class="text-secondary font-size-12">N/A</span>
                    @endif
                </div>

                <div class="mb-0">
                    <span class="text-secondary font-size-12 d-block mb-1">WSTG Identifier:</span>
                    @if ($finding->wstg_id)
                        <span class="glass-badge bg-dark text-white font-monospace font-size-14">{{ $finding->wstg_id }}</span>
                    @else
                        <span class="text-secondary font-size-12">N/A</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Finding Status Card -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Status & Metadata</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="text-secondary font-size-12 d-block">Status:</span>
                    <span class="glass-badge bg-success text-white text-capitalize font-size-14">{{ $finding->status }}</span>
                </div>
                <div class="mb-3">
                    <span class="text-secondary font-size-12 d-block">Discovered Date:</span>
                    <span class="fw-semibold font-size-14 text-dark">{{ $finding->created_at->format('M d, Y H:i:s') }}</span>
                </div>
                <div class="mb-0">
                    <span class="text-secondary font-size-12 d-block">Assessment:</span>
                    <a href="{{ route('scans.show', $scan) }}" class="font-size-14 fw-semibold text-decoration-none text-dark">
                        {{ $scan->name }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
