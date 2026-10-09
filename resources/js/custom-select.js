export function initCustomSelects() {
    const selects = document.querySelectorAll('select:not(.custom-select-initialized)');

    selects.forEach(select => {
        select.classList.add('custom-select-initialized');

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';
        if (select.classList.contains('form-select-sm') || select.classList.contains('glass-select-sm')) {
            wrapper.classList.add('custom-select-sm');
        }

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        // Create trigger
        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';
        
        const triggerText = document.createElement('span');
        const selectedOpt = select.options[select.selectedIndex] || select.options[0];
        triggerText.textContent = selectedOpt ? selectedOpt.textContent : '';

        const arrow = document.createElement('i');
        arrow.className = 'bi bi-chevron-down arrow-icon';

        trigger.appendChild(triggerText);
        trigger.appendChild(arrow);
        wrapper.appendChild(trigger);

        // Create options menu
        const optionsMenu = document.createElement('div');
        optionsMenu.className = 'custom-select-options';

        function buildOptions() {
            optionsMenu.innerHTML = '';
            Array.from(select.options).forEach((opt, idx) => {
                const item = document.createElement('div');
                item.className = 'custom-option';
                if (idx === select.selectedIndex) {
                    item.classList.add('selected');
                }
                item.textContent = opt.textContent;
                item.setAttribute('data-value', opt.value);

                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    select.selectedIndex = idx;
                    triggerText.textContent = opt.textContent;

                    optionsMenu.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                    item.classList.add('selected');

                    wrapper.classList.remove('open');
                    const card = wrapper.closest('.glass-card, .glass-panel, .card');
                    if (card) card.classList.remove('select-open');

                    // Dispatch change event to trigger inline or framework listeners
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });

                optionsMenu.appendChild(item);
            });
        }

        buildOptions();
        wrapper.appendChild(optionsMenu);

        // Toggle dropdown open/close
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.custom-select-wrapper.open').forEach(other => {
                if (other !== wrapper) {
                    other.classList.remove('open');
                    const c = other.closest('.glass-card, .glass-panel, .card');
                    if (c) c.classList.remove('select-open');
                }
            });
            const isOpen = wrapper.classList.toggle('open');
            const card = wrapper.closest('.glass-card, .glass-panel, .card');
            if (card) {
                if (isOpen) {
                    card.classList.add('select-open');
                } else {
                    card.classList.remove('select-open');
                }
            }
        });

        // Sync if select value changes programmatically
        select.addEventListener('change', () => {
            const currentOpt = select.options[select.selectedIndex];
            if (currentOpt) {
                triggerText.textContent = currentOpt.textContent;
                optionsMenu.querySelectorAll('.custom-option').forEach((o, i) => {
                    o.classList.toggle('selected', i === select.selectedIndex);
                });
            }
        });
    });
}

// Global outside click handler to close open custom selects
if (typeof window !== 'undefined') {
    window.addEventListener('click', () => {
        document.querySelectorAll('.custom-select-wrapper.open').forEach(wrapper => {
            wrapper.classList.remove('open');
            const card = wrapper.closest('.glass-card, .glass-panel, .card');
            if (card) card.classList.remove('select-open');
        });
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initCustomSelects);
}
