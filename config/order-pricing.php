<?php

function listVouchers(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, discount_amount FROM vouchers ORDER BY code')->fetchAll();
}

function resolveOrderVoucher(PDO $pdo, $selection, array $currentOrder = []): array
{
    if ($selection === '') {
        return [null, 0];
    }

    $savedCode = $currentOrder['voucher_code'] ?? '';
    $savedDiscount = $currentOrder['discount_amount'] ?? 0;
    if ($selection === 'saved' && ($savedCode !== '' || (float)$savedDiscount > 0)) {
        return [$currentOrder['voucher_code'] ?? null, $savedDiscount];
    }

    if (!is_string($selection) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $selection)) {
        throw new InvalidArgumentException('Voucher được chọn không hợp lệ.');
    }

    $stmt = $pdo->prepare('SELECT code, discount_amount FROM vouchers WHERE id = :id');
    $stmt->execute([':id' => $selection]);
    $voucher = $stmt->fetch();
    if (!$voucher) {
        throw new InvalidArgumentException('Voucher được chọn không tồn tại.');
    }

    return [$voucher['code'], $voucher['discount_amount']];
}

const ORDER_COD_LABELS = [
    'none' => 'Không thu COD',
    'pending' => 'Chưa thu',
    'collected' => 'Đã thu',
];

function readOrderCod(array $input, float $finalTotal): array
{
    $status = $input['cod_status'] ?? 'none';
    if (!is_string($status) || !isset(ORDER_COD_LABELS[$status])) {
        throw new InvalidArgumentException('Trạng thái COD không hợp lệ.');
    }
    if ($status === 'none') {
        return ['none', '0.00'];
    }

    $amount = $input['cod_amount'] ?? '';
    if (!is_string($amount) || !preg_match('/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/', $amount) || (float)$amount <= 0) {
        throw new InvalidArgumentException('Vui lòng nhập số tiền COD lớn hơn 0.');
    }
    if ((float)$amount > round($finalTotal, 2)) {
        throw new InvalidArgumentException('Số tiền COD không được vượt quá tổng thanh toán sau giảm giá.');
    }
    return [$status, $amount];
}

function formatOrderCod(array $order): string
{
    $status = $order['cod_status'];
    if ($status === 'none') {
        return ORDER_COD_LABELS[$status];
    }
    $amount = (float)$order['cod_amount'];
    return ORDER_COD_LABELS[$status] . ' · ' . number_format($amount, floor($amount) === $amount ? 0 : 2, ',', '.') . ' ₫';
}
