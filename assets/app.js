import { createIcons, icons } from 'lucide';

import './styles/app.css';

document.documentElement.classList.add('js-ready');
createIcons({ icons });

const copyButtons = document.querySelectorAll('[data-copy-field]');
const editableBlocks = document.querySelectorAll('[data-editable-block]');
const expandableBlocks = document.querySelectorAll('[data-block-card]');
const ticketLoaderButton = document.querySelector('[data-load-tickets]');

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

editableBlocks.forEach((form) => {
    const editButton = form.querySelector('[data-edit-toggle]');

    if (!editButton) {
        return;
    }

    editButton.addEventListener('click', () => {
        form.dataset.editing = 'true';

        const fields = form.querySelectorAll('[data-readonly-field]');
        fields.forEach((field) => {
            const mode = field.dataset.readonlyField;

            if (mode === 'disabled') {
                field.disabled = false;
            } else {
                field.readOnly = false;
            }
        });

        const firstField = form.querySelector('input[name="ticket"]');
        firstField?.focus();
        firstField?.select();
    });
});

expandableBlocks.forEach((blockCard) => {
    const toggle = blockCard.querySelector('[data-expand-toggle]');

    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isExpanded = blockCard.dataset.expanded === 'true';
        blockCard.dataset.expanded = isExpanded ? 'false' : 'true';
        toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
    });
});

if (ticketLoaderButton) {
    const ticketStatus = document.querySelector('[data-ticket-status]');
    const ticketSelect = document.querySelector('[data-ticket-select]');
    const ticketModal = document.querySelector('[data-ticket-modal]');
    const closeTicketModalButton = document.querySelector('[data-close-ticket-modal]');
    const ticketSourceInput = document.querySelector('[data-ticket-source]');
    const ticketInput = document.querySelector('input[name="ticket"]');
    const jobInput = document.querySelector('input[name="job_number"]');
    const descriptionInput = document.querySelector('textarea[name="description"]');

    const setStatus = (message, isError = false) => {
        if (!ticketStatus) {
            return;
        }

        ticketStatus.textContent = message;
        ticketStatus.dataset.state = isError ? 'error' : 'default';
    };

    const closeTicketModal = () => {
        if (!ticketModal) {
            return;
        }

        ticketModal.hidden = true;
        document.body.classList.remove('modal-open');
    };

    ticketLoaderButton.addEventListener('click', () => {
        if (ticketModal) {
            ticketModal.hidden = false;
            document.body.classList.add('modal-open');
        }

        ticketSourceInput?.focus();
        setStatus('Paste the page source and import it to save or update tickets.');
    });

    closeTicketModalButton?.addEventListener('click', closeTicketModal);

    ticketModal?.addEventListener('click', (event) => {
        if (event.target === ticketModal) {
            closeTicketModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ticketModal && !ticketModal.hidden) {
            closeTicketModal();
        }
    });

    ticketSelect?.addEventListener('change', () => {
        const option = ticketSelect.selectedOptions[0];

        if (!option || !option.dataset.ticket) {
            return;
        }

        if (ticketInput) {
            ticketInput.value = option.dataset.ticket;
        }

        if (jobInput) {
            jobInput.value = option.dataset.jobNumber || '';
        }

        if (descriptionInput && !descriptionInput.value.trim()) {
            descriptionInput.value = option.dataset.description || '';
        }

        setStatus('Ticket details copied into the form.');
    });
}
