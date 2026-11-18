(() => {
const orderItems = document.getElementById('order-items-container');
let nextItemIndex = orderItems.children.length;

function onProductChange(selectEl, resetPrice = true) {
    const row = selectEl.closest('.item-row');
    const stockInfo  = row.querySelector('.stock-info');
    const option = selectEl.options[selectEl.selectedIndex];

    if (resetPrice) {
        row.querySelector('.item-price').value = option?.dataset.price ?? 0;
    }

    if (option && option.dataset.price) {
        const stock = parseInt(option.dataset.stock) || 0;
        stockInfo.innerHTML = stock <= 0 
            ? '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Hết hàng trong kho</span>' 
            : `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Còn ${stock} trong kho</span>`;
    } else {
        stockInfo.innerHTML = '';
    }
    calculateItemTotal(selectEl);
}

function calculateItemTotal(el) {
    const row = el.closest('.item-row');
    const qty   = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const subtotal = qty * price;
    row.querySelector('.item-subtotal').textContent = formatVND(subtotal);
    updateGrandTotal();
}

function updateGrandTotal() {
    const rows = document.querySelectorAll('.item-row');
    let subtotal = 0;
    let details = [];

    rows.forEach(function(row) {
        const qty   = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const itemTotal = qty * price;
        subtotal += itemTotal;
        if (itemTotal > 0) {
            const productSelect = row.querySelector('.item-product');
            const selectedOpt = productSelect.options[productSelect.selectedIndex];
            const productName = selectedOpt ? selectedOpt.text.trim() : '—';
            details.push(qty + ' × ' + productName);
        }
    });

    const voucher = document.getElementById('voucher_id').selectedOptions[0];
    const discount = Number(voucher?.dataset.discount) || 0;
    document.getElementById('voucher_amount').textContent = formatVND(discount);
    const grandTotal = Math.max(0, subtotal - discount);

    const subtotalDisplay = document.getElementById('subtotal-display');
    if (subtotalDisplay) subtotalDisplay.textContent = formatVND(subtotal);

    const discountRow = document.getElementById('voucher-discount-row');
    const discountDisplay = document.getElementById('discount-display');
    if (discountRow && discountDisplay) {
        if (discount > 0) {
            discountRow.style.display = 'block';
            discountDisplay.textContent = formatVND(discount);
        } else {
            discountRow.style.display = 'none';
        }
    }

    document.getElementById('total-display').textContent = formatVND(grandTotal);
    document.getElementById('calc-detail').textContent = details.length > 0 ? details.join(' + ') : 'Chưa có sản phẩm';
}

function addItem() {
    const row = orderItems.firstElementChild.cloneNode(true);
    for (const field of row.querySelectorAll('[name]')) {
        field.name = field.name.replace(/^items\[\d+\]/, 'items[' + nextItemIndex + ']');
        field.classList.remove('is-invalid');
    }
    row.querySelector('.item-product').value = '';
    row.querySelector('.item-quantity').value = '1';
    orderItems.append(row);
    nextItemIndex++;
    onProductChange(row.querySelector('.item-product'));
    updateRemoveButtons();
}

function removeItem(btn) {
    const row = btn.closest('.item-row');
    row.remove();
    updateRemoveButtons();
    updateGrandTotal();
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(function(row) {
        const btn = row.querySelector('.btn-remove-item');
        if (btn) {
            btn.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
        }
    });
}

function formatVND(n) {
    return Number(n).toLocaleString('vi-VN') + '₫';
}

document.getElementById('order-form').addEventListener('submit', function(e) {
    let valid = true;
    const nameEl = document.getElementById('customer_name');
    const phoneEl = document.getElementById('customer_phone');

    if (!nameEl.value.trim()) {
        nameEl.classList.add('is-invalid');
        valid = false;
    } else {
        nameEl.classList.remove('is-invalid');
    }

    if (!phoneEl.value.trim() || !/^[0-9]{9,11}$/.test(phoneEl.value.trim())) {
        phoneEl.classList.add('is-invalid');
        valid = false;
    } else {
        phoneEl.classList.remove('is-invalid');
    }

    const rows = document.querySelectorAll('.item-row');
    let hasValidProduct = false;

    rows.forEach(function(row) {
        const pSelect = row.querySelector('.item-product');
        const qtyInput = row.querySelector('.item-quantity');
        const priceInput = row.querySelector('.item-price');

        if (!pSelect.value) {
            pSelect.classList.add('is-invalid');
            valid = false;
        } else {
            pSelect.classList.remove('is-invalid');
            hasValidProduct = true;
        }

        if (parseFloat(qtyInput.value) <= 0) {
            qtyInput.classList.add('is-invalid');
            valid = false;
        } else {
            qtyInput.classList.remove('is-invalid');
        }

        if (parseFloat(priceInput.value) < 0) {
            priceInput.classList.add('is-invalid');
            valid = false;
        } else {
            priceInput.classList.remove('is-invalid');
        }
    });

    if (!hasValidProduct) {
        alert('Vui lòng chọn ít nhất một sản phẩm cho đơn hàng.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        document.querySelector('.is-invalid')?.scrollIntoView({block: 'center', behavior: 'smooth'});
    }
});

(() => {
    updateRemoveButtons();
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(function(row) {
        onProductChange(row.querySelector('.item-product'), false);
    });
    updateGrandTotal();
})();

Object.assign(window, {onProductChange, calculateItemTotal, updateGrandTotal, addItem, removeItem});
})();
