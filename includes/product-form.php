<?php
$productValue = static fn(string $field): string => htmlspecialchars((string)($productForm[$field] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="card shadow-sm mb-3" aria-labelledby="product-form-title">
    <div class="card-body">
        <h2 class="h5 mb-3" id="product-form-title"><?= $productForm['id'] === '' ? 'Thêm sản phẩm' : 'Sửa sản phẩm' ?></h2>
        <form method="post" action="products.php" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" value="<?= $productValue('id') ?>">
            <input type="hidden" name="original_stock" value="<?= $productValue('original_stock') ?>">
            <div class="col-md-8">
                <label for="product-name" class="form-label">Tên sản phẩm</label>
                <input id="product-name" name="name" class="form-control" maxlength="255" value="<?= $productValue('name') ?>" required>
            </div>
            <div class="col-md-4">
                <label for="product-sku" class="form-label">SKU</label>
                <input id="product-sku" name="sku" class="form-control" maxlength="50" value="<?= $productValue('sku') ?>">
            </div>
            <div class="col-sm-6">
                <label for="product-price" class="form-label">Giá bán (₫)</label>
                <input id="product-price" name="price" type="number" class="form-control" min="0" max="9999999999999.99" step="0.01" value="<?= $productValue('price') ?>" required>
            </div>
            <div class="col-sm-6">
                <label for="product-stock" class="form-label">Tồn kho</label>
                <input id="product-stock" name="stock" type="number" class="form-control" min="0" max="2147483647" step="1" value="<?= $productValue('stock') ?>" required>
            </div>
            <div class="col-12">
                <label for="product-description" class="form-label">Mô tả</label>
                <textarea id="product-description" name="description" class="form-control" rows="3"><?= $productValue('description') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Lưu sản phẩm</button>
                <a class="btn btn-outline-secondary" href="products.php">Hủy</a>
            </div>
        </form>
    </div>
</section>
