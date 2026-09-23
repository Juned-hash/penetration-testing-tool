@props(['icon' => 'bi-inbox', 'title' => 'No Data Available', 'message' => 'There are no records to display.'])

<div class="text-center py-5 my-3 bg-white rounded border">
    <div class="mb-3 text-muted display-4">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h5 class="fw-bold">{{ $title }}</h5>
    <p class="text-muted mb-3">{{ $message }}</p>
    {{ $slot }}
</div>
