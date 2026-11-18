<div class="section-title"><i class="bi bi-box-seam me-2"></i>Sản phẩm trong đơn hàng</div>
<div id="order-items-container">
    <?php foreach ($formItems as $idx => $item): ?>
    <div class="item-row">
        <div class="row g-2 align-items-start">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Sản phẩm <span class="text-danger">*</span></label>
                <select class="form-select item-product" name="items[<?= $idx ?>][product_id]" onchange="onProductChange(this)" required>
                    <option value="">-- Chọn sản phẩm --</option>
                    <?php foreach ($products as $prod): ?>
                    <option value="<?= $prod['id'] ?>"
                            data-price="<?= $prod['price'] ?>"
                            data-stock="<?= $prod['stock'] ?>"
                            <?= ((string)($item['product_id'] ?? '') === (string)$prod['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="stock-info text-muted small mt-1"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Số lượng <span class="text-danger">*</span></label>
                <input type="number" class="form-control item-quantity" name="items[<?= $idx ?>][quantity]" min="1" max="9999" value="<?= (int)($item['quantity'] ?? 1) ?>" oninput="calculateItemTotal(this)" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Đơn giá (₫) <span class="text-danger">*</span></label>
                <input type="number" class="form-control item-price" name="items[<?= $idx ?>][unit_price]" min="0" step="0.01" value="<?= (float)($item['unit_price'] ?? 0) ?>" oninput="calculateItemTotal(this)" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Thành tiền</label>
                <div class="item-subtotal d-flex align-items-center fw-semibold text-primary">0₫</div>
            </div>
            <div class="col-md-1 text-end item-actions">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" onclick="removeItem(this)" title="Xóa dòng này">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="mb-4">
    <button type="button" class="btn-add-item" onclick="addItem()" id="btn-add-item">
        <i class="bi bi-plus-circle me-1"></i>Thêm sản phẩm
    </button>
</div>

<?php
$savedCode = $order['voucher_code'] ?? '';
$savedDiscount = $order['discount_amount'] ?? 0;
$hasSavedVoucher = $savedCode !== '' || (float)$savedDiscount > 0;
$selectedVoucher = $oldInput['voucher_id'] ?? ($hasSavedVoucher ? 'saved' : '');
?>
<div class="section-title"><i class="bi bi-ticket-perforated me-2"></i>Mã giảm giá</div>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="voucher_id" class="form-label small fw-semibold">Mã voucher</label>
        <select class="form-select" id="voucher_id" name="voucher_id" onchange="updateGrandTotal()">
            <option value="">Không áp dụng voucher</option>
            <?php if ($hasSavedVoucher): ?>
                <option value="saved" data-discount="<?= (float)$savedDiscount ?>" <?= $selectedVoucher === 'saved' ? 'selected' : '' ?>>
                    <?= htmlspecialchars($savedCode === '' ? 'Giảm giá' : $savedCode, ENT_QUOTES, 'UTF-8') ?> (đã lưu trên đơn)
                </option>
            <?php endif; ?>
            <?php foreach ($vouchers as $voucher): ?>
                <option value="<?= (int)$voucher['id'] ?>" data-discount="<?= (float)$voucher['discount_amount'] ?>"
                        <?= $selectedVoucher === (string)$voucher['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($voucher['code'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text"><a href="voucher-create.php" target="_blank" rel="noopener">Tạo voucher mới</a></div>
    </div>
    <div class="col-md-6">
        <label for="voucher_amount" class="form-label small fw-semibold">Số tiền giảm giá (₫)</label>
        <output id="voucher_amount" for="voucher_id" class="form-control bg-light fw-semibold d-block">0₫</output>
    </div>
</div>

<?php
$selectedCod = $oldInput['cod_status'] ?? $order['cod_status'] ?? 'none';
$codAmount = $oldInput['cod_amount'] ?? $order['cod_amount'] ?? '';
?>
<div class="section-title"><i class="bi bi-cash-coin me-2"></i>Thu hộ khi giao hàng (COD)</div>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="cod_status" class="form-label small fw-semibold">Trạng thái COD</label>
        <select class="form-select" id="cod_status" name="cod_status">
            <?php foreach (ORDER_COD_LABELS as $value => $label): ?>
                <option value="<?= $value ?>" <?= $selectedCod === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="cod_amount" class="form-label small fw-semibold">Số tiền COD (₫)</label>
        <input type="number" id="cod_amount" name="cod_amount" class="form-control" min="0" max="9999999999999.99"
               step="0.01" value="<?= htmlspecialchars(is_string($codAmount) ? $codAmount : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nhập số tiền thu hộ">
        <div class="form-text">Chỉ áp dụng khi chọn Chưa thu hoặc Đã thu.</div>
    </div>
</div>
