@props(['icon' => 'bi-inbox', 'title' => 'No Data Available', 'message' => 'There are no records to display.'])

<div class="text-center py-5 my-3 glass-panel">
    <div class="mb-3 text-secondary font-size-45">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h5 class="fw-bold font-size-18 text-dark">{{ $title }}</h5>
    <p class="text-secondary mb-3 font-size-14">{{ $message }}</p>
    {{ $slot }}
</div>
