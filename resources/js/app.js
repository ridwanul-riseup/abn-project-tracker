import 'bootstrap';

// Confirmation for destructive actions: <form data-confirm="Are you sure?">
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Copy-to-clipboard: <button data-copy="text">
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }
    event.preventDefault();
    try {
        await navigator.clipboard.writeText(button.getAttribute('data-copy'));
        const original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(() => {
            button.innerHTML = original;
        }, 1500);
    } catch {
        window.prompt('Copy this value:', button.getAttribute('data-copy'));
    }
});

// Auto-submit filter forms when a select changes: <select data-autosubmit>
document.addEventListener('change', (event) => {
    if (event.target.matches('select[data-autosubmit]')) {
        event.target.form.submit();
    }
});

// Markdown editor preview tab.
const previewTab = document.querySelector('[data-markdown-preview]');
if (previewTab) {
    previewTab.addEventListener('shown.bs.tab', async () => {
        const source = document.getElementById(previewTab.dataset.source);
        const target = document.getElementById(previewTab.dataset.target);
        const token = document.querySelector('meta[name="csrf-token"]').content;
        target.textContent = 'Rendering preview…';
        try {
            const response = await fetch(previewTab.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    content: source.value,
                    path: previewTab.dataset.path || null,
                }),
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const data = await response.json();
            // Server returns sanitized HTML (raw HTML escaped, unsafe links removed).
            target.innerHTML = data.html;
        } catch (error) {
            target.textContent = 'Preview failed (' + error.message + ').';
        }
    });
}

// Access rule form: show only resources of the selected scope type.
const scopeType = document.getElementById('scope_type');
const scopeId = document.getElementById('scope_id');
if (scopeType && scopeId) {
    const allOptions = Array.from(scopeId.querySelectorAll('option[data-type]'));
    const refresh = () => {
        const type = scopeType.value;
        scopeId.disabled = type === 'global';
        allOptions.forEach((option) => {
            option.hidden = option.dataset.type !== type;
        });
        const selected = scopeId.selectedOptions[0];
        if (!selected || selected.hidden) {
            const first = allOptions.find((option) => !option.hidden);
            scopeId.value = first ? first.value : '';
        }
    };
    scopeType.addEventListener('change', refresh);
    refresh();
}
