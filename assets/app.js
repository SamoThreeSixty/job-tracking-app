import { createIcons, icons } from 'lucide';

import './styles/app.css';

document.documentElement.classList.add('js-ready');
createIcons({ icons });

const copyButtons = document.querySelectorAll('[data-copy-field]');

const formatDateTime = (value) => {
    if (!value) {
        return '';
    }

    return value.replace('T', ' ');
};

copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        const field = button.closest('.copy-field');
        const input = field?.querySelector('input, textarea');

        if (!input) {
            return;
        }

        const rawValue = input.value ?? '';
        const clipboardText = input.type === 'datetime-local' ? formatDateTime(rawValue) : rawValue.trim();

        try {
            await navigator.clipboard.writeText(clipboardText);
            button.dataset.copied = 'true';

            window.clearTimeout(button.copyResetTimer);
            button.copyResetTimer = window.setTimeout(() => {
                button.removeAttribute('data-copied');
            }, 1400);
        } catch (error) {
            console.error('Unable to copy field value.', error);
        }
    });
});
