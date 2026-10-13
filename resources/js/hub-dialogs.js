import Swal from 'sweetalert2';

const buttonClasses = {
    confirmButton: 'btn btn-danger mx-1',
    cancelButton: 'btn btn-outline-secondary mx-1',
    denyButton: 'btn btn-outline-secondary mx-1',
};

/**
 * Replacement for window.confirm — used by data-confirm on forms and buttons.
 */
export async function hubConfirm(message, { confirmText = 'Yes, continue', cancelText = 'Cancel' } = {}) {
    const result = await Swal.fire({
        title: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        focusCancel: true,
        buttonsStyling: false,
        customClass: buttonClasses,
    });

    return result.isConfirmed;
}

/**
 * Fallback when clipboard API is unavailable.
 */
export async function hubCopyPrompt(value) {
    await Swal.fire({
        title: 'Copy this value',
        input: 'text',
        inputValue: value,
        inputAttributes: { readonly: 'readonly', spellcheck: 'false' },
        showConfirmButton: true,
        confirmButtonText: 'Close',
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-primary' },
    });
}
