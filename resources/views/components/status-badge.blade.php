@props(['status'])

@php
    $class = match($status) {
        'completed' => 'bg-success text-white',
        'running', 'starting', 'crawling', 'processing_results', 'generating_report' => 'bg-primary text-white',
        'queued' => 'bg-info text-white',
        'failed' => 'bg-danger text-white',
        'cancelled' => 'bg-secondary text-white',
        default => 'bg-dark text-white',
    };
@endphp

<span class="glass-badge {{ $class }} text-capitalize font-size-12">
    {{ str_replace('_', ' ', $status) }}
</span>
