# Hệ Thống Quản Lý Đơn Hàng (Order Management System)

Dự án website Quản lý đơn hàng viết bằng **PHP thuần & MySQL (PDO)** kết hợp **Bootstrap 5**, giao diện thiết kế theo phong cách tối giản, màu basic trang nhã, bảo mật cao.

Chi tiết đợt sửa AJAX và giao diện nằm trong [THAY_DOI.md](THAY_DOI.md). Luồng của từng nút, ô nhập và trang được mô tả trong [HUONG_DAN_HOAT_DONG.md](HUONG_DAN_HOAT_DONG.md).

---

## Đối chiếu yêu cầu TH3

| Yêu cầu | Cách thực hiện trong web |
|---|---|
| Thêm, sửa, xóa | Quản lý sản phẩm tại `products.php`, có kiểm tra dữ liệu, SKU trùng, CSRF và sản phẩm đã nằm trong đơn hàng |
| AJAX | Điều hướng, tìm/lọc đơn, các form đăng nhập/đăng ký, sản phẩm, đơn hàng, voucher và hồ sơ dùng `assets/app.js`. Chi tiết đơn khách và tìm sản phẩm dùng fetch riêng |
| Khách hàng | Đăng ký tại `register.php`, đăng nhập tại `login.php`, xem đơn của mình và sản phẩm tại `customer.php` |
| Quản trị viên | Đăng nhập để quản lý đơn hàng, sản phẩm, voucher, xem khách hàng và báo cáo |
| Một module thiết kế chi tiết | Chọn module Sản phẩm, mô tả bên dưới |
| Một font sans-serif | Inter, khai báo dự phòng bằng `sans-serif` |

### Module Sản phẩm

- Dữ liệu sử dụng bảng `products`, gồm mã, tên, SKU, giá bán, tồn kho và mô tả.
- Khách truy cập `customer.php`, nhập tên hoặc SKU và bấm Tìm kiếm. Trình duyệt gửi AJAX, máy chủ truy vấn bằng tham số và trả phần kết quả để cập nhật ngay trên trang.
- Quản trị viên mở Sản phẩm, thêm sản phẩm hoặc chọn biểu tượng sửa, xóa. Thông tin được kiểm tra trên máy chủ trước khi cập nhật database.
- Khi thêm hoặc sửa, tên bắt buộc, SKU duy nhất khi được nhập, giá và tồn kho không âm. Khi sửa tồn kho, hệ thống kiểm tra dữ liệu đã thay đổi bởi thao tác khác.
- Khi xóa, giao diện yêu cầu xác nhận. Cơ sở dữ liệu từ chối xóa sản phẩm đã thuộc một đơn hàng để giữ lịch sử.
- AJAX ở Admin được xử lý bởi `loadPage()` trong `assets/app.js`. GET mở form/danh sách, POST kèm `FormData` để lưu hoặc xóa.
- Sau khi máy chủ kiểm tra quyền, CSRF và dữ liệu, JavaScript nhận HTML trang kết quả, cập nhật nội dung và khởi tạo giao diện.
- AJAX xem đơn khách gọi `actions/customer_orders.php` rồi điền HTML trả về vào hộp thoại. Trang `customer.php` dùng POST để lấy JSON chi tiết đơn của tài khoản và GET để tìm sản phẩm.
- Các trang quản trị kiểm tra quyền ở máy chủ. Trang khách hàng yêu cầu đăng nhập và chỉ trả đơn có `customer_id` gắn với tài khoản.
- Đăng ký mới tạo hồ sơ khách và tài khoản trong cùng giao dịch. Số điện thoại đã có hồ sơ cần quản trị viên xác minh và liên kết tài khoản.
- Tài khoản mẫu trên máy đang phát triển là `khach_demo`, mật khẩu `KhachDemo123!`. Đây là tài khoản đã tạo trong database cục bộ.

## Chạy toàn bộ bằng Docker

Yêu cầu: Docker Desktop đang chạy ở chế độ Linux containers, có Docker Compose. Không cần cài PHP, Apache hoặc MySQL trên máy chủ.

Mở terminal tại thư mục `order_management` và chạy:

```powershell
docker compose up -d --build --wait
```

Truy cập **http://localhost:8088**. Tài khoản mẫu: **admin / 123456**. Khách hàng có thể tạo tài khoản qua nút Đăng ký. Tài khoản `khach_demo` trong database XAMPP cũ không tự xuất hiện trong Docker.

### Cấu trúc và cấu hình

- `Dockerfile`: đóng gói mã nguồn vào PHP 8.3 + Apache, cài `pdo_mysql` và `mbstring`. Chỉ chép mã chạy web và assets; không đưa SQL, tài liệu hay `.env` vào web root.
- `compose.yaml`: chạy `web` và MySQL 8.4 (`db`). Web đợi database import xong và healthcheck thành công mới khởi động. Chỉ mở cổng web trên máy hiện tại; không mở cổng MySQL ra ngoài.
- `database.sql`: tự import đúng một lần khi volume MySQL còn trống. Bao gồm schema và dữ liệu mẫu, dùng tên database cố định `order_management`.
- `config/database.php`: đọc biến môi trường do Compose truyền (`DB_HOST=db`, `DB_USER=order_app`, ...). Khi không có biến môi trường, vẫn dùng cấu hình XAMPP cũ.
- Volume `mysql_data` giữ database; volume `sessions` giữ phiên đăng nhập khi tạo lại container.

Mặc định cổng web là `8088`; mật khẩu DB cục bộ là `order_local_password`, root là `root_local_password`. Muốn đổi, tạo `.env` **trước lần chạy đầu**:

```powershell
Copy-Item .env.example .env
```

Sửa `APP_PORT`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` trong `.env`, rồi chạy Compose. Đổi `APP_PORT` nếu cổng 8088 đang được ứng dụng khác sử dụng. Đây là cấu hình chạy thử trên máy cá nhân; đổi mật khẩu mẫu trước khi triển khai cho người khác truy cập. Biến khởi tạo mật khẩu MySQL chỉ có tác dụng khi volume dữ liệu trống; thay `.env` sau đó không tự đổi mật khẩu trong DB.

### Các lệnh thường dùng

```powershell
# Xem trạng thái và log
docker compose ps
docker compose logs --tail=100 web db

# Dừng; database và phiên đăng nhập vẫn được giữ
docker compose down

# Chạy lại hoặc cập nhật sau khi sửa mã nguồn
docker compose up -d --build --wait

# Mở MySQL, nhập DB_PASSWORD khi được hỏi
docker compose exec db mysql -uorder_app -p order_management
```

Không bind mount mã nguồn, nên sửa file PHP/JS/CSS cần build lại bằng lệnh trên. Trình duyệt vẫn cần Internet để tải Bootstrap, icon, font và Chart.js từ CDN.

### Sao lưu và chuyển dữ liệu đang dùng

Docker dùng database **riêng**, không tự chuyển database hiện tại trong XAMPP. Muốn giữ dữ liệu hiện có, xuất database XAMPP bằng phpMyAdmin sang một file SQL rồi import vào MySQL Docker. Sao lưu trước khi thay dữ liệu; bản dump phải chứa toàn bộ schema hiện tại.

Sao lưu database Docker (lệnh dùng được trong PowerShell, tránh lỗi encoding khi redirect SQL trực tiếp trên Windows):

```powershell
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --no-tablespaces --single-transaction --set-gtid-purged=OFF -u"$MYSQL_USER" "$MYSQL_DATABASE" > /tmp/order-management-backup.sql'
docker compose cp db:/tmp/order-management-backup.sql ./order-management-backup.sql
```

Khôi phục từ bản sao lưu hoặc bản xuất XAMPP tương thích:

```powershell
docker compose cp ./order-management-backup.sql db:/tmp/order-management-backup.sql
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" < /tmp/order-management-backup.sql'
```

Không import lại `database.sql` vào DB đang dùng: tệp này có `DROP TABLE` và sẽ thay dữ liệu bằng dữ liệu mẫu. Tương tự, `docker compose down -v` **xóa cả database và session trong volume**; chỉ dùng khi chủ động muốn cài mới hoàn toàn.

Tài liệu nền tảng: [PHP Official Image](https://hub.docker.com/_/php), [MySQL Official Image](https://hub.docker.com/_/mysql), [Compose startup order](https://docs.docker.com/compose/how-tos/startup-order/).

## 🌟 Tính Năng Chính

1. **Bảo mật & Đăng nhập (Auth - `login.php`, `logout.php`, `config/auth.php`)**:
   - Xác thực đăng nhập qua bảng `users`, mã hóa mật khẩu bằng hàm `password_hash()` (Bcrypt).
   - Bảo vệ toàn bộ các trang quản lý; nếu chưa đăng nhập sẽ tự động chuyển hướng về `login.php`.
   - **Tài khoản mặc định**: Tên đăng nhập `admin` &bull; Mật khẩu `123456`.

2. **Hồ sơ cá nhân & Đổi mật khẩu (`profile.php`)**:
   - Xem thông tin tài khoản và ngày tham gia.
   - Cập nhật Họ và tên, Email.
   - Đổi mật khẩu an toàn (yêu cầu xác thực mật khẩu hiện tại và tối thiểu 6 ký tự).

3. **Quản lý danh sách đơn hàng & Biểu đồ thống kê (Read - `index.php`)**:
   - **Biểu đồ thống kê đơn hàng (Chart.js)**:
     - Biểu đồ cột/đường: Thống kê doanh thu và số lượng đơn hàng theo ngày.
     - Biểu đồ tròn (Doughnut): Phân tích tỷ lệ phần trăm các trạng thái đơn hàng (Chờ xử lý, Đang giao, Đã giao, Hủy).
     - Biểu đồ luôn hiển thị tại trang Báo cáo riêng.
   - **Sao chép nhanh thông tin khách hàng**: Click trực tiếp vào Tên / SĐT khách hàng trên bảng để tự động copy vào bộ nhớ tạm (`Tên - SĐT`) kèm Toast thông báo.
   - **Lọc thông minh**: Tìm kiếm theo Mã đơn (#ID), Tên KH, Số điện thoại, Tên sản phẩm; lọc theo Trạng thái; lọc theo **Ngày**, **Tháng (YYYY-MM)**, **Năm**; và **Sắp xếp** (Mới nhất, Cũ nhất, Tiền cao ➜ thấp, Tiền thấp ➜ cao).
   - **Modal xem chi tiết**: Xem đầy đủ thông tin khách hàng, từng dòng sản phẩm (SKU, Đơn giá, Số lượng, Thành tiền), Tổng tiền, Ghi chú.

4. **In danh sách & In hóa đơn bán hàng**:
   - **In danh sách tổng hợp**: Hỗ trợ chọn checkbox từng đơn hàng hoặc chọn toàn bộ để in phiếu tổng hợp danh sách đơn hàng ra giấy A4/PDF.
   - **In hóa đơn lẻ**: In trực tiếp từ Modal chi tiết đơn hàng.

5. **Thêm mới đơn hàng (Create - `create.php` & `actions/store.php`)**:
   - **Mặc định nhập trực tiếp thông tin khách hàng** (Tên *, SĐT *, Email, Địa chỉ) - đã bỏ dropdown rườm rà. Hệ thống tự động kiểm tra theo SĐT để liên kết hoặc tạo khách hàng mới.
   - Thêm nhiều dòng sản phẩm động, tự lấy đơn giá và hiển thị tồn kho.
   - Tự động tính thành tiền và tổng tiền bằng JavaScript thời gian thực.
   - Sử dụng **Database Transaction** lưu đồng thời: `customers`, `orders`, `order_items`, `order_status_history` và tự động trừ kho `products.stock`.

6. **Chỉnh sửa đơn hàng (Update - `edit.php` & `actions/update.php`)**:
   - Chỉnh sửa trực tiếp thông tin khách hàng, sửa số lượng/đơn giá các mặt hàng.
   - Thay đổi trạng thái đơn hàng và ghi log lịch sử.
   - **Timeline lịch sử trạng thái**: Theo dõi tiến trình xử lý đơn hàng theo thời gian thực.

7. **Xóa đơn hàng an toàn (Delete - `actions/delete.php`)**:
   - Modal xác nhận xóa, bảo vệ bằng **CSRF Token** và ràng buộc `ON DELETE CASCADE`.

---

## 🗄️ Cấu Trúc Cơ Sở Dữ Liệu

1. **`users`**: Tài khoản người dùng (`id`, `username`, `password`, `name`, `email`, `role`, `created_at`, `updated_at`).
2. **`customers`**: Thông tin khách hàng (`id`, `name`, `phone`, `email`, `address`, `created_at`, `updated_at`).
3. **`products`**: Danh mục sản phẩm (`id`, `name`, `sku`, `price`, `stock`, `description`, `created_at`, `updated_at`).
4. **`orders`**: Thông tin đơn hàng (`id`, `customer_id`, `total_amount`, `status`, `note`, `created_at`, `updated_at`).
4. **`order_items`**: Chi tiết sản phẩm trong đơn (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `created_at`).
5. **`order_status_history`**: Lịch sử chuyển đổi trạng thái (`id`, `order_id`, `old_status`, `new_status`, `note`, `changed_at`).
6. **`vouchers`**: Mã voucher duy nhất và số tiền giảm cố định (`id`, `code`, `discount_amount`).

---

## 🚀 Hướng Dẫn Cài Đặt & Sử Dụng

### 1. Khởi động Web Server & MySQL
- Mở **XAMPP Control Panel** và bấm **Start** cả 2 dịch vụ: **Apache** và **MySQL**.

### 2. Import Cơ sở dữ liệu (Nếu cài mới)
- Mở trình duyệt truy cập: `http://localhost/phpmyadmin/`
- Tạo database `order_management` với Collation `utf8mb4_unicode_ci`.
- Import file `database.sql` trong thư mục dự án.
- Chỉ import `database.sql` khi cài mới. File này xóa và tạo lại các bảng, nên import vào cơ sở dữ liệu đang dùng sẽ làm mất dữ liệu hiện có.

### 3. Đăng nhập hệ thống
- Mở trình duyệt và truy cập:
  `http://localhost/order_management/` hoặc `http://localhost:8080/`
- Đăng nhập với tài khoản:
  - **Tên đăng nhập**: `admin`
  - **Mật khẩu**: `123456`
