@extends('layouts.app')

@section('title', 'Assessments')
@section('header', 'Security Assessments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-secondary mb-0 font-size-14">Manage and monitor authorized web application security assessments.</p>
    <a href="{{ route('scans.create') }}" class="app-btn-primary">
        <i class="bi bi-plus-circle"></i> New Assessment
    </a>
</div>

<div class="glass-card">
    <div class="table-responsive">
        @if ($scans->isEmpty())
            <div class="p-4">
                <x-empty-state icon="bi-search" title="No Assessments Configured" message="You have not created any security assessments yet.">
                    <a href="{{ route('scans.create') }}" class="app-btn-primary app-btn-sm mt-2">
                        <i class="bi bi-plus-circle"></i> Create Assessment
                    </a>
                </x-empty-state>
            </div>
        @else
            <table class="table glass-table align-middle">
                <thead>
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
                            <td class="fw-bold font-size-14 text-dark">{{ $scan->name }}</td>
                            <td>
                                <a href="{{ route('scans.show', $scan) }}" class="text-decoration-none text-dark font-size-14">
                                    {{ $scan->target_url }}
                                </a>
                            </td>
                            <td><span class="glass-badge bg-white text-dark font-size-12">{{ ucfirst($scan->environment) }}</span></td>
                            <td><x-status-badge :status="$scan->status" /></td>
                            <td><span class="badge bg-secondary font-size-12">{{ $scan->findings_count }}</span></td>
                            <td class="text-secondary font-size-12">{{ $scan->created_at->format('M d, Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('scans.show', $scan) }}" class="app-btn-secondary app-btn-sm">
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
