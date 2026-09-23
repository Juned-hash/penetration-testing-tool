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
                            <td>{{ $report->scan->name ?? 'Assessment' }}</td>
                            <td><span class="badge bg-dark">{{ strtoupper($report->type) }}</span></td>
                            <td class="text-muted small">{{ $report->generated_at ? $report->generated_at->format('M d, Y H:i') : 'N/A' }}</td>
                            <td class="text-end">
                                <a href="{{ route('reports.download', $report) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="bi bi-download me-1"></i> Download PDF
                                </a>
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
