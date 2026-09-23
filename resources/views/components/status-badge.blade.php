@props(['status'])

@php
    $class = match($status) {
        'completed' => 'bg-success',
        'running', 'starting', 'crawling', 'processing_results', 'generating_report' => 'bg-primary',
        'queued' => 'bg-info text-dark',
        'failed' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        default => 'bg-dark',
    };
@endphp

<span class="badge {{ $class }} text-capitalize">
    {{ str_replace('_', ' ', $status) }}
</span>
