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

// Access rule / role assignment forms: show only resources of the selected scope type.
document.querySelectorAll('select[data-scope-target]').forEach((scopeType) => {
    const scopeId = document.getElementById(scopeType.dataset.scopeTarget);
    if (!scopeId) {
        return;
    }
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
});

// Application site URLs + nested credentials (create/edit application form).
const siteUrlRoot = document.querySelector('[data-nested-site-urls]');
if (siteUrlRoot) {
    const urlList = siteUrlRoot.querySelector('[data-site-url-list]');
    const urlTemplate = document.getElementById('hub-site-url-template');
    const credTemplate = document.getElementById('hub-credential-template');
    let nextUrlIndex = urlList.querySelectorAll('[data-site-url-row]').length;

    const reindex = () => {
        urlList.querySelectorAll('[data-site-url-row]').forEach((row, urlIndex) => {
            row.querySelectorAll('[name^="site_urls["]').forEach((input) => {
                input.name = input.name.replace(/site_urls\[\d+]/, `site_urls[${urlIndex}]`);
            });
            row.querySelectorAll('[data-credential-row]').forEach((credRow, credIndex) => {
                credRow.querySelectorAll('[name*="[credentials]["]').forEach((input) => {
                    input.name = input.name.replace(
                        /site_urls\[\d+]\[credentials\]\[\d+]/,
                        `site_urls[${urlIndex}][credentials][${credIndex}]`,
                    );
                });
            });
        });
        nextUrlIndex = urlList.querySelectorAll('[data-site-url-row]').length;
    };

    siteUrlRoot.addEventListener('click', (event) => {
        const addUrl = event.target.closest('[data-add-site-url]');
        if (addUrl) {
            event.preventDefault();
            const html = urlTemplate.innerHTML.replaceAll('__URL__', String(nextUrlIndex));
            urlList.insertAdjacentHTML('beforeend', html);
            nextUrlIndex += 1;
            return;
        }

        const removeUrl = event.target.closest('[data-remove-site-url]');
        if (removeUrl) {
            event.preventDefault();
            const message = removeUrl.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                return;
            }
            removeUrl.closest('[data-site-url-row]').remove();
            reindex();
            return;
        }

        const addCred = event.target.closest('[data-add-credential]');
        if (addCred) {
            event.preventDefault();
            const urlRow = addCred.closest('[data-site-url-row]');
            const urlIndex = Array.from(urlList.querySelectorAll('[data-site-url-row]')).indexOf(urlRow);
            const credList = urlRow.querySelector('[data-credential-list]');
            const credIndex = credList.querySelectorAll('[data-credential-row]').length;
            const html = credTemplate.innerHTML
                .replaceAll('__URL__', String(urlIndex))
                .replaceAll('__CRED__', String(credIndex));
            credList.insertAdjacentHTML('beforeend', html);
            return;
        }

        const removeCred = event.target.closest('[data-remove-credential]');
        if (removeCred) {
            event.preventDefault();
            const message = removeCred.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                return;
            }
            removeCred.closest('[data-credential-row]').remove();
            reindex();
            return;
        }

        const toggle = event.target.closest('[data-password-toggle]');
        if (toggle) {
            event.preventDefault();
            const input = toggle.parentElement.querySelector('input[type="password"], input[type="text"]');
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
    });
}
