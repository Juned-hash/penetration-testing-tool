@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Security Reports')

@section('content')
<div class="glass-card">
    <div class="glass-card-header">
        <h6 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-file-earmark-pdf me-2 text-primary"></i>Generated Assessment Reports</h6>
    </div>
    <div class="card-body p-4">
        @if ($reports->isEmpty())
            <x-empty-state icon="bi-file-earmark-pdf" title="No Reports Available" message="Reports will be generated once security assessments complete.">
            </x-empty-state>
        @else
            <table class="table glass-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Target / Application</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Generated At</th>
                        <th class="text-end">Download</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                        <tr>
                            <td>
                                <a href="{{ route('scans.show', $report->scan) }}" class="text-decoration-none fw-semibold text-dark font-size-14">
                                    {{ $report->scan->name ?? 'Assessment' }}
                                </a>
                            </td>
                            <td><span class="glass-badge bg-dark text-white font-size-12">{{ strtoupper($report->type) }}</span></td>
                            <td>
                                @php
                                    $statusBadge = match($report->status) {
                                        'completed' => 'bg-success text-white',
                                        'generating', 'queued' => 'bg-info text-white',
                                        'failed' => 'bg-danger text-white',
                                        default => 'bg-secondary text-white',
                                    };
                                @endphp
                                <span class="glass-badge {{ $statusBadge }} text-capitalize font-size-12">{{ $report->status }}</span>
                            </td>
                            <td class="text-secondary font-size-12">{{ $report->completed_at ? $report->completed_at->format('M d, Y H:i') : ($report->created_at ? $report->created_at->format('M d, Y H:i') : 'N/A') }}</td>
                            <td class="text-end">
                                @if ($report->status === 'completed')
                                    <a href="{{ route('reports.download', $report) }}" class="app-btn-primary app-btn-sm">
                                        <i class="bi bi-download"></i> Download PDF
                                    </a>
                                @elseif (in_array($report->status, ['queued', 'generating']))
                                    <button class="app-btn-secondary app-btn-sm" disabled>
                                        <i class="bi bi-hourglass-split"></i> Generating...
                                    </button>
                                @else
                                    <span class="text-danger font-size-12 font-monospace" title="{{ $report->error_message }}">Failed</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-3">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
