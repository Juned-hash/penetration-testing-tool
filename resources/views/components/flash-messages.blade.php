@if (session('success') || session('error') || session('warning') || $errors->any())
<div id="toast-notification-container" class="toast-notification-container">
    @if (session('success'))
        <div class="app-toast app-toast-success" role="alert" data-autodismiss="true" data-duration="4500">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success font-size-18 me-1"></i>
                <span class="font-size-14 text-dark fw-medium">{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close ms-2 font-size-12" onclick="dismissToast(this.closest('.app-toast'))" aria-label="Close"></button>
            <div class="toast-progress"><div class="toast-progress-bar"></div></div>
        </div>
    @endif

    @if (session('error'))
        <div class="app-toast app-toast-error" role="alert" data-autodismiss="true" data-duration="5500">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger font-size-18 me-1"></i>
                <span class="font-size-14 text-dark fw-medium">{{ session('error') }}</span>
            </div>
            <button type="button" class="btn-close ms-2 font-size-12" onclick="dismissToast(this.closest('.app-toast'))" aria-label="Close"></button>
            <div class="toast-progress"><div class="toast-progress-bar"></div></div>
        </div>
    @endif

    @if (session('warning'))
        <div class="app-toast app-toast-warning" role="alert" data-autodismiss="true" data-duration="5000">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill text-warning font-size-18 me-1"></i>
                <span class="font-size-14 text-dark fw-medium">{{ session('warning') }}</span>
            </div>
            <button type="button" class="btn-close ms-2 font-size-12" onclick="dismissToast(this.closest('.app-toast'))" aria-label="Close"></button>
            <div class="toast-progress"><div class="toast-progress-bar"></div></div>
        </div>
    @endif

    @if ($errors->any())
        <div class="app-toast app-toast-error" role="alert" data-autodismiss="true" data-duration="6000">
            <div class="d-flex flex-column gap-1">
                <div class="d-flex align-items-center gap-2 fw-bold text-danger font-size-14">
                    <i class="bi bi-x-circle-fill font-size-18 me-1"></i> Please fix the following errors:
                </div>
                <ul class="mb-0 ps-4 font-size-14 text-dark">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-2 align-self-start font-size-12" onclick="dismissToast(this.closest('.app-toast'))" aria-label="Close"></button>
            <div class="toast-progress"><div class="toast-progress-bar"></div></div>
        </div>
    @endif
</div>

<script>
    if (typeof dismissToast !== 'function') {
        function dismissToast(toastEl) {
            if (!toastEl || toastEl.classList.contains('hide-toast')) return;
            toastEl.classList.add('hide-toast');
            setTimeout(() => {
                toastEl.remove();
            }, 450);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const toasts = document.querySelectorAll('.app-toast[data-autodismiss="true"]');
        toasts.forEach(toast => {
            let duration = parseInt(toast.getAttribute('data-duration') || '4500', 10);
            let startTime = Date.now();
            let timerId = null;

            function startTimer() {
                startTime = Date.now();
                timerId = setTimeout(() => {
                    dismissToast(toast);
                }, duration);
            }

            function pauseTimer() {
                if (timerId) {
                    clearTimeout(timerId);
                    timerId = null;
                    const elapsed = Date.now() - startTime;
                    duration = Math.max(0, duration - elapsed);
                }
            }

            toast.addEventListener('mouseenter', pauseTimer);
            toast.addEventListener('mouseleave', function () {
                if (duration > 0) {
                    startTimer();
                } else {
                    dismissToast(toast);
                }
            });

            startTimer();
        });
    });
</script>
@endif
