@extends('layouts.app')

@section('title', 'Assessments')
@section('header', 'Security Assessments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage and monitor authorized web application security assessments.</p>
    <a href="{{ route('scans.create') }}" class="btn btn-primary fw-semibold">
        <i class="bi bi-plus-circle me-1"></i> New Assessment
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        @if ($scans->isEmpty())
            <x-empty-state icon="bi-search" title="No Assessments Configured" message="You have not created any security assessments yet.">
                <a href="{{ route('scans.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Create Assessment
                </a>
            </x-empty-state>
        @else
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application Name</th>
                        <th>Target URL</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>Findings</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scans as $scan)
                        <tr>
                            <td class="fw-bold">{{ $scan->name }}</td>
                            <td>
                                <a href="{{ route('scans.show', $scan) }}" class="text-decoration-none text-dark font-monospace small">
                                    {{ $scan->target_url }}
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ ucfirst($scan->environment) }}</span></td>
                            <td><x-status-badge :status="$scan->status" /></td>
                            <td><span class="badge bg-secondary">{{ $scan->findings_count }}</span></td>
                            <td class="text-muted small">{{ $scan->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('scans.show', $scan) }}" class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($scans->hasPages())
                <div class="p-3 border-top">
                    {{ $scans->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
