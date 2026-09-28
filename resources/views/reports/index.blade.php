@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Security Reports')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0">Generated Assessment Reports</h6>
    </div>
    <div class="card-body">
        @if ($reports->isEmpty())
            <x-empty-state icon="bi-file-earmark-pdf" title="No Reports Available" message="Reports will be generated once security assessments complete.">
            </x-empty-state>
        @else
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Target / Application</th>
                        <th>Type</th>
                        <th>Generated At</th>
                        <th class="text-end">Download</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                        <tr>
                            <td>
                                <a href="{{ route('scans.show', $report->scan) }}" class="text-decoration-none fw-semibold">
                                    {{ $report->scan->name ?? 'Assessment' }}
                                </a>
                            </td>
                            <td><span class="badge bg-dark">{{ strtoupper($report->type) }}</span></td>
                            <td>
                                @php
                                    $statusBadge = match($report->status) {
                                        'completed' => 'bg-success',
                                        'generating', 'queued' => 'bg-info text-dark',
                                        'failed' => 'bg-danger',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }} text-capitalize">{{ $report->status }}</span>
                            </td>
                            <td class="text-muted small">{{ $report->completed_at ? $report->completed_at->format('M d, Y H:i') : ($report->created_at ? $report->created_at->format('M d, Y H:i') : 'N/A') }}</td>
                            <td class="text-end">
                                @if ($report->status === 'completed')
                                    <a href="{{ route('reports.download', $report) }}" class="btn btn-sm btn-outline-success fw-semibold">
                                        <i class="bi bi-download me-1"></i> Download PDF
                                    </a>
                                @elseif (in_array($report->status, ['queued', 'generating']))
                                    <button class="btn btn-sm btn-outline-secondary fw-semibold" disabled>
                                        <i class="bi bi-hourglass-split me-1"></i> Generating...
                                    </button>
                                @else
                                    <span class="text-danger small font-monospace" title="{{ $report->error_message }}">Failed</span>
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
