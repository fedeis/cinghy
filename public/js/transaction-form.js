function toggleSign(btn) {
    const input = btn.parentNode.querySelector('input[name="amounts[]"]');
    let val = input.value.trim();
    if (val === '') {
        input.value = '-';
    } else if (val === '-') {
        input.value = '';
    } else {
        if (val.startsWith('-')) {
            input.value = val.substring(1);
        } else {
            input.value = '-' + val;
        }
    }
    input.dispatchEvent(new Event('input'));

    input.focus();
    const len = input.value.length;
    input.setSelectionRange(len, len);
}

function focusNextField(currentInput) {
    const form = currentInput.form;
    if (!form) return;

    const elements = Array.from(form.elements);
    const index = elements.indexOf(currentInput);

    for (let i = index + 1; i < elements.length; i++) {
        const el = elements[i];
        if (el.tagName === 'INPUT' && el.type !== 'hidden' && !el.disabled && !el.readOnly) {
            el.focus();
            if (el.type === 'text' || el.type === 'date') {
                el.select();
            }
            break;
        }
    }
}

function checkNewRow(input) {
    const container = document.getElementById('postings-container');
    const rows = container.getElementsByClassName('posting-row');
    const lastRow = rows[rows.length - 1];
    const accountInput = lastRow.querySelector('input[name="accounts[]"]');

    if (input === accountInput && input.value.trim() !== '') {
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = '');

        const newAccInput = newRow.querySelector('input[name="accounts[]"]');
        if (typeof initAccountAutocomplete === 'function') {
            initAccountAutocomplete(newAccInput);
        }

        const toggle = newRow.querySelector('.sign-toggle');
        if (toggle && /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
            toggle.style.display = 'block';
        }

        const note = container.querySelector('p');
        container.insertBefore(newRow, note);
    }
}

function parseCurrencyJS(str) {
    if (!str || str.trim() === '') return NaN;
    const settings = window.APP_SETTINGS || {};
    const dec = settings.decimal_sep || '.';
    const thousands = settings.thousands_sep || '';

    // Remove symbol, thousands sep, and spaces
    let clean = str.replace(settings.currency_symbol || 'EUR', '')
        .replace(thousands, '')
        .replace(/\s/g, '');

    // Normalize decimal separator to '.'
    clean = clean.replace(dec, '.');

    return parseFloat(clean);
}

function formatCurrencyJS(value) {
    if (isNaN(value)) return '';
    const settings = window.APP_SETTINGS || {};
    const dec = settings.decimal_sep || '.';
    const thousands = settings.thousands_sep || '';
    const sym = settings.currency_symbol || 'EUR';
    const pos = settings.currency_position || 'after';
    const spacing = settings.currency_spacing ? ' ' : '';

    const parts = Math.abs(value).toFixed(2).split('.');
    if (thousands) {
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    }
    const formatted = parts.join(dec);
    const sign = value < 0 ? '-' : '';

    if (pos === 'before') {
        return sign + sym + spacing + formatted;
    } else {
        return sign + formatted + spacing + sym;
    }
}

function formatInput(input) {
    const val = parseCurrencyJS(input.value);
    if (!isNaN(val)) {
        input.value = formatCurrencyJS(val);
    }
    updateBalancingSuggestion();
}

function updateBalancingSuggestion() {
    const container = document.getElementById('postings-container');
    if (!container) return;
    const rows = container.getElementsByClassName('posting-row');

    let total = 0;
    let emptyAmounts = [];

    for (let row of rows) {
        const acc = row.querySelector('input[name="accounts[]"]').value.trim();
        const amtInput = row.querySelector('input[name="amounts[]"]');
        const amtVal = amtInput.value.trim();

        if (acc === '') {
            amtInput.placeholder = formatCurrencyJS(0);
            continue;
        }

        if (amtVal === '') {
            emptyAmounts.push(amtInput);
        } else {
            const num = parseCurrencyJS(amtVal);
            if (!isNaN(num)) {
                total += num;
            }
        }
    }

    if (emptyAmounts.length === 1) {
        const suggestion = formatCurrencyJS(-total);
        emptyAmounts[0].placeholder = suggestion;
    } else {
        const zeroFormatted = formatCurrencyJS(0);
        emptyAmounts.forEach(i => i.placeholder = zeroFormatted);
    }
}

// Global iOS detection helper
window.isIOS = function () {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
};

document.addEventListener('DOMContentLoaded', function () {
    if (window.isIOS()) {
        document.querySelectorAll('.sign-toggle').forEach(el => el.style.display = 'block');
    }
});
