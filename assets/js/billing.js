document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-billing-form]');
    if (!form) {
        return;
    }

    const tbody = form.querySelector('[data-billing-rows]');
    const addButton = form.querySelector('[data-billing-add]');
    const template = document.getElementById('billing-row-template');
    const grandTotalEl = form.querySelector('[data-billing-grand-total]');
    const paidInput = form.querySelector('[data-paid-input]');

    function formatEuro(amount) {
        return (Number(amount) || 0).toFixed(2) + ' €';
    }

    function recalculate() {
        let grandTotal = 0;
        const rows = tbody ? tbody.querySelectorAll('[data-billing-row]') : [];
        rows.forEach(function (row) {
            const qtyInput = row.querySelector('[data-qty-input]');
            const priceInput = row.querySelector('[data-price-input]');
            const rowTotalEl = row.querySelector('[data-row-total]');
            const qty = Math.max(1, parseInt(qtyInput ? qtyInput.value : '1', 10) || 1);
            const price = Math.max(0, parseFloat(priceInput ? priceInput.value : '0') || 0);
            const sub = qty * price;
            grandTotal += sub;
            if (rowTotalEl) {
                rowTotalEl.textContent = formatEuro(sub);
            }
        });
        if (grandTotalEl) {
            grandTotalEl.textContent = formatEuro(grandTotal);
        }
        if (paidInput) {
            paidInput.max = grandTotal.toFixed(2);
        }
    }

    function bindRow(row) {
        const serviceSelect = row.querySelector('[data-service-select]');
        const descInput = row.querySelector('[data-desc-input]');
        const qtyInput = row.querySelector('[data-qty-input]');
        const priceInput = row.querySelector('[data-price-input]');
        const removeBtn = row.querySelector('[data-billing-remove]');

        if (serviceSelect) {
            serviceSelect.addEventListener('change', function () {
                const option = serviceSelect.options[serviceSelect.selectedIndex];
                if (!option) {
                    return;
                }
                const price = option.getAttribute('data-price');
                const name = option.getAttribute('data-name');
                if (name && descInput) {
                    descInput.value = name;
                }
                if (price && priceInput) {
                    priceInput.value = price;
                }
                recalculate();
            });
        }

        [qtyInput, priceInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', recalculate);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const rows = tbody ? tbody.querySelectorAll('[data-billing-row]') : [];
                if (rows.length <= 1) {
                    return;
                }
                row.remove();
                recalculate();
            });
        }
    }

    if (tbody) {
        tbody.querySelectorAll('[data-billing-row]').forEach(bindRow);
    }

    if (addButton && template && tbody) {
        addButton.addEventListener('click', function () {
            const clone = template.content.firstElementChild.cloneNode(true);
            tbody.appendChild(clone);
            bindRow(clone);
            recalculate();
        });
    }

    recalculate();
});
