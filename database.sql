-- Hệ thống Quản lý Đơn hàng
-- Database Schema & Sample Data

-- Tạo database nếu chưa tồn tại
CREATE DATABASE IF NOT EXISTS `order_management`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Chọn database để sử dụng
USE `order_management`;

-- Xóa bảng cũ theo thứ tự phụ thuộc (bảng con trước, bảng cha sau)
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `vouchers`;

CREATE TABLE `vouchers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `discount_amount` DECIMAL(15, 2) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_vouchers_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BẢNG 1: users - Tài khoản người dùng hệ thống
-- =============================================
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã người dùng (khóa chính)',
    `username` VARCHAR(50) NOT NULL COMMENT 'Tên đăng nhập',
    `password` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã băm (Bcrypt)',
    `name` VARCHAR(100) NOT NULL COMMENT 'Họ và tên',
    `email` VARCHAR(100) NULL COMMENT 'Email',
    `role` VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT 'Vai trò (admin / staff / customer)',
    `customer_id` INT(11) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày tạo',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ngày cập nhật',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_users_username` (`username`),
    UNIQUE KEY `idx_users_customer` (`customer_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu thông tin tài khoản người dùng';

-- =============================================
-- BẢNG 2: customers - Thông tin khách hàng
-- =============================================
CREATE TABLE `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã khách hàng (khóa chính)',
    `name` VARCHAR(150) NOT NULL COMMENT 'Tên khách hàng',
    `phone` VARCHAR(20)  NOT NULL COMMENT 'Số điện thoại',
    `email` VARCHAR(100) NULL COMMENT 'Email khách hàng',
    `address` TEXT NULL COMMENT 'Địa chỉ khách hàng',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày tạo',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ngày cập nhật',
    PRIMARY KEY (`id`),
    INDEX `idx_customer_name`  (`name`),
    INDEX `idx_customer_phone` (`phone`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu thông tin khách hàng';

ALTER TABLE `users`
    ADD CONSTRAINT `fk_user_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT;

-- =============================================
-- BẢNG 3: products - Thông tin sản phẩm
-- =============================================
CREATE TABLE `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã sản phẩm (khóa chính)',
    `name` VARCHAR(255) NOT NULL COMMENT 'Tên sản phẩm',
    `sku` VARCHAR(50) NULL COMMENT 'Mã SKU sản phẩm',
    `price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Giá bán mặc định (VNĐ)',
    `stock` INT(11) NOT NULL DEFAULT 0 COMMENT 'Số lượng tồn kho',
    `description` TEXT NULL COMMENT 'Mô tả sản phẩm',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày tạo',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ngày cập nhật',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_product_sku` (`sku`),
    INDEX `idx_product_name`      (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu thông tin sản phẩm';

-- =============================================
-- BẢNG 4: orders - Đơn hàng (header)
-- =============================================
CREATE TABLE `orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã đơn hàng (khóa chính)',
    `customer_id` INT(11) NOT NULL COMMENT 'FK → customers.id',
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền thanh toán sau giảm giá (VNĐ)',
    `voucher_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Mã voucher giảm giá (nếu có)',
    `discount_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền giảm giá từ voucher (VNĐ)',
    `cod_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `cod_status` ENUM('none', 'pending', 'collected') NOT NULL DEFAULT 'none',
    `status` ENUM(
                'pending',    -- Chờ xử lý
                'shipping',   -- Đang giao
                'delivered',  -- Đã giao
                'cancelled',  -- Hủy
                'returned'    -- Hoàn trả
            ) NOT NULL DEFAULT 'pending' COMMENT 'Trạng thái đơn hàng',
    `note` TEXT NULL COMMENT 'Ghi chú đơn hàng',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày tạo đơn',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ngày cập nhật',
    PRIMARY KEY (`id`),
    INDEX `idx_order_status`     (`status`),
    INDEX `idx_order_created`    (`created_at`),
    INDEX `idx_order_customer`   (`customer_id`),
    CONSTRAINT `fk_order_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu thông tin đơn hàng (header)';

-- =============================================
-- BẢNG 5: order_items - Chi tiết đơn hàng
-- =============================================
CREATE TABLE `order_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã chi tiết (khóa chính)',
    `order_id` INT(11) NOT NULL COMMENT 'FK → orders.id',
    `product_id` INT(11) NOT NULL COMMENT 'FK → products.id',
    `quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Số lượng sản phẩm',
    `unit_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Đơn giá tại thời điểm mua (VNĐ)',
    `total_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền = quantity × unit_price',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày tạo',
    PRIMARY KEY (`id`),
    INDEX `idx_item_order`   (`order_id`),
    INDEX `idx_item_product` (`product_id`),
    CONSTRAINT `fk_item_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_item_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu chi tiết từng sản phẩm trong đơn hàng';

-- =============================================
-- BẢNG 6: order_status_history - Lịch sử trạng thái
-- =============================================
CREATE TABLE `order_status_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã lịch sử (khóa chính)',
    `order_id` INT(11) NOT NULL COMMENT 'FK → orders.id',
    `old_status` ENUM(
                     'pending', 'shipping', 'delivered', 'cancelled', 'returned'
                 ) NULL COMMENT 'Trạng thái cũ (NULL nếu vừa tạo)',
    `new_status` ENUM(
                     'pending', 'shipping', 'delivered', 'cancelled', 'returned'
                 ) NOT NULL COMMENT 'Trạng thái mới',
    `note` VARCHAR(255) NULL COMMENT 'Ghi chú thay đổi',
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời điểm thay đổi',
    PRIMARY KEY (`id`),
    INDEX `idx_history_order`   (`order_id`),
    INDEX `idx_history_changed` (`changed_at`),
    CONSTRAINT `fk_history_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Bảng lưu lịch sử thay đổi trạng thái đơn hàng';

-- =============================================
-- Dữ liệu mẫu (Sample Data)
-- =============================================

-- ── Người dùng quản trị mẫu (Mật khẩu: 123456) ──
INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`) VALUES
    ('admin', '$2y$10$yOBi4PPLe8EIObuNFSTeZuBbXmF93BJn9pOMF7UUNV.WONWWzEwnW', 'Quản trị viên', 'admin@ordermanager.vn', 'admin');

-- ── Khách hàng mẫu ──
INSERT INTO `customers` (`name`, `phone`, `email`, `address`) VALUES
    ('Nguyễn Văn An',   '0901234567', 'an.nguyen@email.com',   '123 Lê Lợi, Q.1, TP.HCM'),
    ('Trần Thị Bình',   '0912345678', 'binh.tran@email.com',   '456 Trần Hưng Đạo, Q.5, TP.HCM'),
    ('Lê Minh Châu',    '0923456789', NULL,                    '789 Nguyễn Huệ, Q.1, TP.HCM'),
    ('Phạm Thị Dung',   '0934567890', 'dung.pham@email.com',   NULL),
    ('Hoàng Văn Em',    '0945678901', NULL,                    '321 Lý Tự Trọng, Q.3, TP.HCM');

-- ── Sản phẩm mẫu ──
INSERT INTO `products` (`name`, `sku`, `price`, `stock`, `description`) VALUES
    ('Laptop Dell XPS 15', 'DELL-XPS15', 28990000.00, 15, 'Laptop Dell XPS 15 inch, Intel Core i7, 16GB RAM, 512GB SSD'),
    ('Chuột không dây Logitech', 'LOGI-M720', 590000.00, 50, 'Chuột không dây Logitech M720 Triathlon'),
    ('Bàn phím cơ Keychron K2', 'KEY-K2V2', 2150000.00, 30, 'Bàn phím cơ Keychron K2 Version 2, Gateron Brown'),
    ('Màn hình LG 27 inch', 'LG-27UL850', 6500000.00, 10, 'Màn hình LG 27UL850 4K UHD IPS'),
    ('Tai nghe Sony WH-1000XM5', 'SONY-XM5', 8200000.00, 20, 'Tai nghe chụp tai Sony WH-1000XM5 chống ồn'),
    ('SSD Samsung 1TB', 'SAM-870-1T', 2300000.00, 40, 'SSD Samsung 870 EVO 1TB SATA III'),
    ('Webcam Logitech C920', 'LOGI-C920', 1850000.00, 25, 'Webcam Logitech C920 HD Pro'),
    ('USB Hub 7 cổng Ugreen', 'UG-HUB7', 320000.00, 60, 'USB Hub 7 cổng Ugreen USB 3.0');

-- ── Đơn hàng mẫu ──
INSERT INTO `orders` (`customer_id`, `total_amount`, `status`, `note`) VALUES
    (1, 28990000.00, 'pending', 'Giao hàng trước 5h chiều'),
    (2, 1770000.00, 'shipping', NULL),
    (3, 4300000.00, 'delivered', 'Đã giao ngày 10/10'),
    (4, 6500000.00, 'cancelled', 'Khách hủy do đổi ý'),
    (1, 8200000.00, 'pending', NULL),
    (5, 4600000.00, 'shipping', 'Giao nhanh'),
    (3, 1850000.00, 'delivered', NULL),
    (2, 1280000.00, 'pending', NULL);

-- ── Chi tiết đơn hàng mẫu ──
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
    (1, 1, 1, 28990000.00, 28990000.00), -- Đơn 1: Laptop Dell XPS 15
    (2, 2, 3, 590000.00, 1770000.00), -- Đơn 2: 3x Chuột Logitech
    (3, 3, 2, 2150000.00, 4300000.00), -- Đơn 3: 2x Bàn phím Keychron
    (4, 4, 1, 6500000.00, 6500000.00), -- Đơn 4: Màn hình LG
    (5, 5, 1, 8200000.00, 8200000.00), -- Đơn 5: Tai nghe Sony
    (6, 6, 2, 2300000.00, 4600000.00), -- Đơn 6: 2x SSD Samsung
    (7, 7, 1, 1850000.00, 1850000.00), -- Đơn 7: Webcam Logitech
    (8, 8, 4, 320000.00, 1280000.00); -- Đơn 8: 4x USB Hub Ugreen

-- ── Lịch sử trạng thái mẫu ──
INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `note`) VALUES
    (1, NULL, 'pending', 'Đơn hàng mới tạo'),
    (2, NULL, 'pending', 'Đơn hàng mới tạo'),
    (2, 'pending', 'shipping', 'Đã chuyển cho đơn vị vận chuyển'),
    (3, NULL, 'pending', 'Đơn hàng mới tạo'),
    (3, 'pending', 'shipping', 'Đã chuyển cho đơn vị vận chuyển'),
    (3, 'shipping', 'delivered', 'Đã giao thành công ngày 10/10'),
    (4, NULL, 'pending', 'Đơn hàng mới tạo'),
    (4, 'pending', 'cancelled', 'Khách hàng yêu cầu hủy đơn'),
    (5, NULL, 'pending', 'Đơn hàng mới tạo'),
    (6, NULL, 'pending', 'Đơn hàng mới tạo'),
    (6, 'pending', 'shipping', 'Đã chuyển cho đơn vị vận chuyển'),
    (7, NULL, 'pending', 'Đơn hàng mới tạo'),
    (7, 'pending', 'shipping', 'Đã chuyển cho đơn vị vận chuyển'),
    (7, 'shipping', 'delivered', 'Giao thành công'),
    (8, NULL, 'pending', 'Đơn hàng mới tạo');
