# Cách trang web hoạt động

Cập nhật ngày 08/11/2026. Các liên kết “tệp dòng N” bên dưới trỏ đến số dòng thực tế trong mã nguồn tại thời điểm cập nhật. Có thể bấm liên kết hoặc mở tệp và dùng Ctrl+G để đến dòng đó.

Một nút được khai báo trong HTML ở một dòng cụ thể. Khi bấm, trình duyệt chạy hàm JavaScript bắt sự kiện; hàm đó gửi request để PHP xử lý. Các bảng sau mô tả đúng thứ tự này. Số dòng sẽ thay đổi nếu bạn tiếp tục thêm hoặc xóa dòng trong mã nguồn.

Ví dụ đầy đủ nhất là mục **8. Tạo và sửa đơn trong hộp thoại**. Ở đó có đường đi từ thẻ a Tạo đơn, qua AJAX, đến form và từng lệnh SQL ghi database.

## 1. Cách đọc tài liệu và tìm code

Tên tệp trong bảng là đường dẫn tính từ thư mục `order_management`. Dùng Ctrl+F trong tệp để tìm tên hàm, ID hoặc câu SQL được nêu.

| Khu vực nhìn thấy trên web | Tệp xử lý chính |
|---|---|
| Đăng nhập | `login.php`, `config/auth.php` |
| Đăng ký khách hàng | `register.php` |
| Thanh trên và menu trái | `includes/orders-navigation.php` |
| Danh sách, tìm kiếm, lọc và in đơn của quản trị | `index.php` |
| Form tạo đơn | `create.php` |
| Form sửa đơn và lịch sử trạng thái | `edit.php` |
| Các dòng sản phẩm, voucher và COD trong form đơn | `includes/order-fields.php` |
| Tính tiền và kiểm tra form đơn trên trình duyệt | `assets/order-form.js` |
| Ghi đơn mới, cập nhật đơn, xóa đơn | `actions/store.php`, `actions/update.php`, `actions/delete.php` |
| Danh sách khách trong quản trị | `customers.php`, `includes/catalog-list.php` |
| Hộp thoại các đơn của một khách | `actions/customer_orders.php` |
| Thêm, sửa, xóa sản phẩm | `products.php`, `includes/product-form.php` |
| Tạo voucher và kho voucher | `voucher-create.php` |
| Biểu đồ | `reports.php` |
| Hồ sơ và đổi mật khẩu | `profile.php` |
| Đơn và sản phẩm dành cho tài khoản khách | `customer.php` |
| Đăng xuất | `logout.php` |
| AJAX chuyển trang, gửi form, mở form đơn | `assets/app.js` |
| Màu sắc, kích thước, bố cục | `assets/orders-layout.css`, `assets/forms.css` và các khối style riêng của trang |

## 2. Luồng chung từ thao tác đến giao diện

| Bước thực sự chạy | Đi đến đâu trong code | Việc thực hiện |
|---|---|---|
| Bấm một liên kết PHP | [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129) | Listener tìm thẻ a vừa bấm, kiểm tra liên kết nội bộ và gọi preventDefault |
| Bấm một nút submit | [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) | Listener lấy form, tạo FormData và đọc action của form |
| Gửi request | [assets/app.js dòng 76](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:76), sau đó [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85) | GET gửi URL, POST gửi body; fetch gọi đến tệp PHP |
| Đọc kết quả máy chủ | [assets/app.js dòng 91](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:91) | Nhận HTML bằng response.text và phân tích bằng DOMParser |
| Cập nhật trang | [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64), cụ thể [assets/app.js dòng 70](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:70) | Dọn giao diện cũ và thay body bằng HTML vừa nhận |
| Gắn lại hoạt động cho giao diện | [assets/app.js dòng 20](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:20) | Chạy script của trang mới để checkbox, nút, modal, biểu đồ hoạt động |
| Cập nhật thanh địa chỉ | [assets/app.js dòng 72](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:72) | Ghi URL bằng History API |
| Bấm Back hoặc Forward của trình duyệt | [assets/app.js dòng 163](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:163) | Tải lại nội dung ứng với URL bằng AJAX |

Đoạn gửi request thực tế được trích từ mã nguồn.

```javascript
            const response = await fetch(url, {
                method: isPost ? 'POST' : 'GET',
                body: options.body,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                signal: controller.signal
            });
```

Lần đầu mở URL, trình duyệt tải trang HTML do PHP tạo. Các lần bấm liên kết nội bộ và gửi form tiếp theo được `assets/app.js` xử lý.

1. Sự kiện `click` tìm liên kết `<a href="...php">`. Sự kiện `submit` lấy form vừa gửi.
2. `event.preventDefault()` chặn hành vi chuyển trang/gửi form mặc định của trình duyệt.
3. `loadPage()` gọi `fetch()`. GET gửi giá trị trên URL, POST gửi `FormData`.
4. PHP chạy trên máy chủ như trước. Máy chủ kiểm tra session, quyền và dữ liệu, rồi đọc hoặc ghi MySQL.
5. Với thao tác lưu thành công, PHP vẫn có thể trả `Location`. Fetch đi theo chuyển hướng đó và lấy HTML kết quả.
6. JavaScript đọc `response.text()`, dùng `DOMParser` đọc HTML, sau đó `renderPage()` thay phần body đang hiển thị.
7. `updateStyles()` đồng bộ CSS. `runScripts()` chạy phần khởi tạo riêng của trang mới để các nút, biểu đồ và hộp thoại tiếp tục hoạt động.
8. `history.pushState()` hoặc `replaceState()` cập nhật URL. Nút Back/Forward gọi lại AJAX thông qua sự kiện `popstate`.

HTML trả về trong AJAX là HTML do các tệp PHP hiện có tạo. Các form không cần một bản xử lý database riêng dành cho AJAX. Nếu JavaScript bị tắt, liên kết và form vẫn có thể hoạt động theo cách tải trang thông thường.

### Những điểm cần phân biệt

- `prepare()` chuẩn bị SQL, `execute()` thực thi với tham số và `fetchAll()` lấy kết quả.
- PHP tạo HTML trên máy chủ. JavaScript nhận kết quả và đưa lên giao diện.
- AJAX dùng `fetch()` không nhất thiết phải nhận JSON. Lớp chung nhận HTML, một số chức năng chi tiết nhận JSON.
- `app-loading` hiển thị vạch xanh nhỏ ở đầu màn hình trong lúc chờ.
- Đang gửi POST thì lớp chung chặn việc gửi thêm yêu cầu ghi để tránh bấm nút lưu liên tiếp.
- Yêu cầu GET cũ được hủy khi bắt đầu một yêu cầu GET mới.
- Khi mất kết nối, giao diện hiện thông báo lỗi. Dữ liệu form đang nhập được giữ nếu chưa nhận được trang kết quả.

## 3. Đăng nhập

### Lần theo nút Đăng nhập

| Thứ tự | Vị trí thực tế | Dữ liệu và kết quả |
|---|---|---|
| 1 | [login.php dòng 143](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:143) | Form POST có action là login.php |
| 2 | [login.php dòng 178](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:178) | Nút type=submit kích hoạt sự kiện submit của form |
| 3 | [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) → [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85) | AJAX gửi username và password đến login.php |
| 4 | [login.php dòng 27](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:27) | SELECT tài khoản theo username; execute truyền giá trị nhập vào |
| 5 | [login.php dòng 31](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:31) | password_verify đối chiếu mật khẩu với bản băm trong users |
| 6 | [login.php dòng 33](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:33) | Đổi ID session, ghi user_id và user vào session |
| 7 | [login.php dòng 44](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:44) | Location chọn index.php cho admin/nhân viên hoặc customer.php cho khách |
| 8 | [assets/app.js dòng 91](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:91) → [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) | Fetch nhận HTML đích; giao diện chuyển sang trang phù hợp |

```php
            $stmt = $pdo->prepare("SELECT id, username, password, name, email, role, customer_id FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
```

### Các ô tên đăng nhập, mật khẩu và nút Đăng nhập

- HTML nằm trong form `action="login.php"`, `method="POST"`.
- AJAX chung gửi `username` và `password` đến `login.php`.
- PHP tìm tài khoản bằng `SELECT ... FROM users WHERE username = :username`.
- `password_verify()` đối chiếu mật khẩu nhập với mật khẩu băm trong database.
- Đúng mật khẩu và role hợp lệ thì `session_regenerate_id(true)` đổi ID session. PHP lưu `user_id` và thông tin `user` vào `$_SESSION`.
- Admin/nhân viên được chuyển đến `index.php`. Role khách được chuyển đến `customer.php`.
- Fetch nhận HTML trang đích và thay giao diện. Sai thông tin thì giao diện đăng nhập hiện lỗi.

### Liên kết đăng ký

Liên kết đến `register.php` được lớp AJAX chung tải. Tài khoản khách không có quyền tự chọn role quản trị.

Khối tài khoản mặc định dưới form là nội dung hướng dẫn cho dữ liệu mẫu, không phải nút tự đăng nhập.

## 4. Đăng ký khách hàng

### Lần theo nút Tạo tài khoản

| Thứ tự | Vị trí thực tế | Việc chạy |
|---|---|---|
| 1 | [login.php dòng 184](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:184) | Liên kết từ đăng nhập mở register.php bằng AJAX |
| 2 | [register.php dòng 79](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:79) và [register.php dòng 96](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:96) | Form gửi name, phone, email, username, password, confirm_password và csrf_token |
| 3 | [register.php dòng 14](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:14) | Kiểm tra token, định dạng và mật khẩu |
| 4 | [register.php dòng 34](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:34) → [register.php dòng 35](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:35) | Mở transaction; kiểm tra số điện thoại có hồ sơ sẵn |
| 5 | [register.php dòng 40](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:40) | INSERT hồ sơ khách, lấy ID vừa tạo |
| 6 | [register.php dòng 43](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:43) | INSERT tài khoản với role cố định customer và customer_id của bước trước |
| 7 | [register.php dòng 45](F:/Ki1nam4/Lap_trinh_web/order_management/register.php:45) | Commit rồi chuyển đến login.php với thông báo thành công |
| 8 | [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) | AJAX hiển thị form đăng nhập và thông báo |

```php
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, role, customer_id) VALUES (?, ?, ?, ?, 'customer', ?)");
            $stmt->execute([$values['username'], password_hash($password, PASSWORD_DEFAULT), $values['name'], $values['email'] ?: null, $customerId]);
```

Giao diện tại `register.php` gồm họ tên, số điện thoại, email tùy chọn, tên đăng nhập, mật khẩu và xác nhận mật khẩu.

1. Gửi form POST bằng AJAX.
2. PHP kiểm tra CSRF, độ dài, định dạng số điện thoại/tên đăng nhập, email và hai mật khẩu.
3. Bắt đầu transaction.
4. Kiểm tra số điện thoại trong bảng `customers` bằng truy vấn có `FOR UPDATE`.
5. Với hồ sơ mới, tạo một dòng `customers`, rồi tạo dòng `users` với role cố định `customer` và `customer_id` tương ứng.
6. `password_hash()` tạo bản băm mật khẩu. Commit transaction rồi trả về trang đăng nhập với thông báo thành công.
7. Có lỗi thì rollback để không tạo hồ sơ khách mồ côi.

Số điện thoại đã tồn tại được yêu cầu liên hệ quản trị viên. Form đăng ký không tự nhận quyền xem lịch sử đơn cũ chỉ bằng cách nhập một số điện thoại.

## 5. Session và quyền truy cập

Các vị trí kiểm tra quyền có thể tìm trực tiếp tại [config/auth.php dòng 8](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:8), [config/auth.php dòng 24](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:24), [config/auth.php dòng 33](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:33), [config/auth.php dòng 45](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:45) và [config/auth.php dòng 50](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:50).

- session_start ở [config/auth.php dòng 8](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:8) mở session dựa trên ID session trình duyệt gửi.
- requireAuth ở [config/auth.php dòng 24](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:24) yêu cầu có phiên đăng nhập và đọc lại tài khoản bằng SQL tại [config/auth.php dòng 33](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:33).
- requireStaff ở [config/auth.php dòng 50](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:50) dùng canManageOrders tại [config/auth.php dòng 45](F:/Ki1nam4/Lap_trinh_web/order_management/config/auth.php:45) để chỉ cho admin/nhân viên tiếp tục.
- Trang quản trị đơn gọi requireStaff tại [index.php dòng 7](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:7) trước khi truy vấn danh sách.

`config/auth.php` mở session bằng `session_start()`.

- `requireAuth()` kiểm tra `user_id`, đọc lại tài khoản từ database và cập nhật thông tin session. Tài khoản đã xóa hoặc role không hợp lệ phải đăng nhập lại.
- `canManageOrders()` trả về true cho `admin` và `staff`.
- `requireStaff()` dùng cho các trang quản trị và các action ghi dữ liệu. Khách gọi trực tiếp URL này bị trả mã 403.
- Khách được xem sản phẩm, xem đơn có `customer_id` gắn với tài khoản, sửa hồ sơ và đổi mật khẩu của chính tài khoản.
- Quyền được kiểm tra tại máy chủ, không phụ thuộc vào việc menu hoặc nút có đang bị ẩn.

## 6. Thanh trên và menu bên trái

### Bấm menu và tên tài khoản

| Thành phần bạn bấm | Khai báo trong giao diện | Luồng tiếp theo |
|---|---|---|
| Tên tài khoản có mũi tên | [includes/orders-navigation.php dòng 20](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:20), tên lấy tại [includes/orders-navigation.php dòng 22](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:22) | Bootstrap mở danh sách Hồ sơ cá nhân và Đăng xuất |
| Đơn hàng | [includes/orders-navigation.php dòng 40](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:40) | app_click → loadPage → index.php hoặc customer.php theo role |
| Khách hàng | [includes/orders-navigation.php dòng 42](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:42) | app_click → loadPage → customers.php → SELECT customers → template bảng |
| Sản phẩm | [includes/orders-navigation.php dòng 43](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:43) | app_click → loadPage → products.php → SELECT products → template bảng |
| Khuyến mãi | [includes/orders-navigation.php dòng 44](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:44) | app_click → loadPage → voucher-create.php → listVouchers → kho voucher |
| Báo cáo | [includes/orders-navigation.php dòng 45](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:45) | app_click → loadPage → reports.php → truy vấn số liệu → new Chart |
| Hồ sơ cá nhân | [includes/orders-navigation.php dòng 25](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:25) | app_click → loadPage → profile.php → SELECT users bằng ID session |
| Đăng xuất | [includes/orders-navigation.php dòng 27](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:27) → [logout.php dòng 11](F:/Ki1nam4/Lap_trinh_web/order_management/logout.php:11) → [logout.php dòng 16](F:/Ki1nam4/Lap_trinh_web/order_management/logout.php:16) | Xóa user_id/user, hủy session, mở session cho thông báo và chuyển login.php |

Tên **Quản trị viên** hoặc tên khách nằm ở dữ liệu users.name, được hiển thị tại [includes/orders-navigation.php dòng 22](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:22). Đó không phải chuỗi tên cố định viết trong nút.

Code tại `includes/orders-navigation.php`.

| Thành phần | Khi sử dụng |
|---|---|
| Logo Order Management | Mở trang đơn hàng; role khách được đưa về `customer.php` |
| Tên trang trên thanh đầu | Lấy từ `$navigationTitles[$activePage]` |
| Tên người dùng và biểu tượng tài khoản | Lấy `$currentUser['name']`; Bootstrap mở dropdown khi bấm |
| Hồ sơ cá nhân | AJAX tải `profile.php` |
| Đăng xuất | AJAX gọi `logout.php`, xóa session đăng nhập và hiển thị trang đăng nhập |
| Đơn hàng | AJAX tải `index.php` hoặc `customer.php` theo role |
| Khách hàng | AJAX tải `customers.php` |
| Sản phẩm | AJAX tải `products.php` |
| Khuyến mãi | AJAX tải `voucher-create.php` |
| Báo cáo | AJAX tải `reports.php` |
| Nút menu trên màn hình hẹp | Bootstrap mở/đóng thanh điều hướng dạng offcanvas |

Mục đang chọn được xác định bằng `$activePage` và class `orders-nav-current`. Dropdown và offcanvas chỉ đóng/mở giao diện nên không cần truy vấn database.

## 7. Danh sách đơn hàng của quản trị

### Đường đi của các thao tác lọc

| Bạn làm gì | Dòng khai báo điều khiển | PHP thực hiện ở đâu | Cuối cùng hiện ở đâu |
|---|---|---|---|
| Bấm Đang giao hoặc tab trạng thái khác | [index.php dòng 320](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:320) và [index.php dòng 323](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:323) | [index.php dòng 23](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:23) đọc GET, [index.php dòng 63](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:63) thêm điều kiện status | [index.php dòng 106](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:106) lấy kết quả → [index.php dòng 429](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:429) tạo từng dòng |
| Nhập test rồi bấm Tìm kiếm hoặc Enter | [index.php dòng 334](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:334), nút tại [index.php dòng 337](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:337), thuộc [index.php dòng 327](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:327) | [index.php dòng 43](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:43) tạo LIKE và các tham số | [index.php dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:85) ghép SQL → [index.php dòng 106](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:106) → [index.php dòng 429](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:429) |
| Chọn ngày từ lịch | [index.php dòng 357](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:357) | [index.php dòng 68](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:68) thêm DATE(o.created_at) = :filter_date | Chỉ những dòng đúng ngày được đưa vào orders để hiển thị |
| Chọn cách sắp xếp | [index.php dòng 367](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:367) | [index.php dòng 75](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:75) chọn ORDER BY ngày hoặc tổng tiền | Thứ tự vòng foreach thay đổi theo kết quả SQL |
| Bấm Lọc | [index.php dòng 377](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:377) | app_submit gửi toàn bộ #filter-form bằng GET | Server tạo HTML mới, app_render thay giao diện |
| Bấm mũi tên xóa lọc | [index.php dòng 381](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:381) | AJAX gọi URL bỏ điều kiện tìm kiếm/ngày/trạng thái | SQL trả lại danh sách tương ứng với URL |

Ví dụ đang chọn Đang giao và nhập test, trình duyệt gửi một URL dạng sau.

```text
index.php?search=test&status=shipping&date=&sort=created_desc
```

Điều kiện tìm kiếm và điều kiện trạng thái được thêm vào mảng conditions, sau đó ghép bằng AND tại [index.php dòng 72](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:72). Câu SQL được tạo tại [index.php dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:85). Chính execute ở [index.php dòng 106](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:106) thực thi truy vấn, và fetchAll ngay sau đó lấy kết quả.

```php
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
```

Vòng lặp hiển thị bắt đầu tại [index.php dòng 429](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:429). Mỗi phần tử order tạo một thẻ tr với các thẻ td bên trong. HTML của cả bảng trở về qua fetch, sau đó [assets/app.js dòng 70](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:70) thay phần đang hiển thị.

### Checkbox, xem chi tiết, sao chép và in

| Thao tác | Khai báo | Hàm hoặc listener xử lý | Hiển thị kết quả |
|---|---|---|---|
| Tích một ô | [index.php dòng 453](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:453) | [index.php dòng 803](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:803) → [index.php dòng 770](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:770) | selected-count và nhãn in |
| Chọn tất cả ở đầu bảng | [index.php dòng 417](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:417) | [index.php dòng 787](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:787) | checked của từng order-checkbox được đổi |
| Nút Chọn tất cả | [index.php dòng 393](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:393) | [index.php dòng 794](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:794) | Chọn hoặc bỏ chọn các ô đang hiện |
| In danh sách | [index.php dòng 284](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:284) | [index.php dòng 870](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:870) | Điền print-table-body, gọi window.print |
| Bấm mã đơn màu xanh | [index.php dòng 457](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:457) | [index.php dòng 810](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:810) | viewDetailModal nhận dữ liệu từ data-order |
| Bấm con mắt | [index.php dòng 502](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:502) | Cùng [index.php dòng 810](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:810) | Hiện sản phẩm, khách, COD, voucher, ghi chú |
| Bấm tên khách để sao chép | [index.php dòng 466](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:466) | [index.php dòng 738](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:738) | Clipboard được ghi, copyToast hiện thông báo |
| In đơn trong hộp thoại | [index.php dòng 623](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:623) | [index.php dòng 864](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:864) | Hộp thoại in của trình duyệt |

Trong hàm in, checkedBoxes được đọc trước. Có ô chọn thì targetOrders chỉ lấy những ID đó từ allOrdersData. Không có ô chọn thì lấy toàn bộ đơn đang hiện. Code tạo các dòng in trong vòng lặp tại [index.php dòng 895](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:895), rồi gọi in tại [index.php dòng 917](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:917).

### Dữ liệu hiển thị

`index.php` truy vấn `orders` nối `customers`. Sau đó lấy `order_items` và `products` cho những đơn đã tìm được. Kết quả được duyệt bằng `foreach ($orders as $order)` để tạo các dòng bảng.

Các cột hiển thị mã đơn, ngày tạo, khách hàng, trạng thái giao hàng, COD, tổng tiền và thao tác. Checkbox phục vụ chọn đơn để in. Nếu một cột đang có giá trị dấu gạch ngang, đó là giá trị trống hoặc dữ liệu chưa áp dụng cho đơn.

### Các tab trạng thái

Tab Tất cả, Chờ xử lý, Đang giao, Đã giao, Đã hoàn trả và Đã hủy được tạo từ danh sách trạng thái trong `index.php`.

- Tab là liên kết có `search`, `status`, `date`, `sort` trên URL.
- AJAX chung chặn click và tải HTML theo URL đó.
- PHP nhận `$_GET['status']`, kiểm tra danh sách cho phép rồi thêm điều kiện `o.status = :status`.
- Tab đang chọn có class `active`.

### Ô tìm kiếm và nút Tìm kiếm

Ô `name="search"` nằm trong `#filter-form`.

- Nhập rồi bấm nút hoặc Enter sẽ gửi form GET bằng AJAX.
- `$searchKeyword` là từ khóa đã trim.
- `$searchPattern = '%' . $searchKeyword . '%'` tạo mẫu cho `LIKE`.
- Từ khóa số được đối chiếu mã đơn, số điện thoại và tên khách.
- Từ khóa khác tìm tên khách, số điện thoại và sản phẩm thuộc đơn bằng `EXISTS`.
- Các tham số được truyền qua `$params`, không ghép trực tiếp nội dung người dùng vào SQL.

### Trạng thái, Ngày tạo, Sắp xếp và nút Lọc

- Trạng thái gửi giá trị `pending`, `shipping`, `delivered`, `returned` hoặc `cancelled`.
- Ngày tạo là `<input type="date">`. Trình duyệt tự dựng bảng lịch. PHP lọc bằng `DATE(o.created_at) = :filter_date`.
- Sắp xếp quyết định `ORDER BY` ngày tạo hoặc tổng tiền, tăng hoặc giảm.
- Nút Lọc và nút Tìm kiếm gửi cùng form nên gửi toàn bộ giá trị đang có trong các ô.
- `$whereClause` ghép các điều kiện bằng `AND`. Ví dụ đang giao và từ khóa test phải cùng khớp.
- Nút mũi tên vòng tròn mở URL đã bỏ các điều kiện, nhưng giữ `customer_id` nếu đang xem đơn của một khách.

Điểm thực thi cuối cùng nằm ở `$stmt->execute($params)` rồi `$orders = $stmt->fetchAll()`. AJAX nhận bảng mới và thay giao diện.

### Checkbox, Chọn tất cả và số đã chọn

Các checkbox có class `order-checkbox`, value là ID đơn. JavaScript trong `index.php` dùng `.order-checkbox:checked` để đếm các ô đã tích.

- Checkbox ở tiêu đề gán `checked` cho các dòng.
- Nút Chọn tất cả chọn hoặc bỏ chọn toàn bộ dòng đang hiển thị.
- `updateSelectionStatus()` cập nhật số đơn đã chọn và nhãn nút in.
- Các thao tác chọn chỉ thay đổi trạng thái trên trình duyệt.

### Nút In danh sách

JavaScript lấy các ID đang tích và tra trong `allOrdersData`. Nếu chưa tích ô nào thì lấy mọi đơn hiện đang hiển thị.

Code tạo các dòng trong `#print-table-body`, tính tổng ở `#print-total-amount`, hiện `#print-section` rồi gọi `window.print()`. CSS `@media print` quyết định vùng in. Hộp thoại in do trình duyệt cung cấp.

### Bấm mã đơn hoặc biểu tượng con mắt

Cả hai dùng class `btn-view-order`, chứa JSON trong `data-order` và mở `#viewDetailModal`.

Sự kiện `show.bs.modal` đọc JSON rồi điền tên, số điện thoại, email, địa chỉ, COD, ghi chú, voucher và các dòng sản phẩm. Dữ liệu của các đơn trong bảng đã được tải cùng danh sách nên mở chi tiết quản trị không cần thêm request.

Nút In đơn này gọi in của trình duyệt. Nút Chỉnh sửa mở form sửa bằng AJAX. Nút Đóng do Bootstrap xử lý.

### Tên khách và biểu tượng sao chép

JavaScript đọc `data-name`, `data-phone`, gọi Clipboard API và hiện toast thông báo. Đây là thao tác cục bộ.

### Chân bảng và trạng thái trống

Số lượng đơn là `count($orders)`. Tổng ở chân bảng cộng `total_amount` của các đơn đang hiển thị. Không có kết quả thì hiện thông báo trống và liên kết tạo đơn.

## 8. Tạo và sửa đơn trong hộp thoại

### A. Ví dụ đầy đủ khi bấm Tạo đơn

#### Bước 1 — Xác định nút trên giao diện

Nút Tạo đơn ở góc trên bên phải được khai báo tại [index.php dòng 287](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:287).

```php
                <a href="create.php" data-order-form class="btn btn-primary fw-semibold"><i class="bi bi-plus-lg me-1"></i>Tạo đơn</a>
```

href nói rằng dữ liệu form cần lấy từ create.php. data-order-form là dấu hiệu để bộ AJAX biết phải hiển thị trong hộp thoại.

#### Bước 2 — JavaScript nhận click

Listener bắt click nằm tại [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129). Nó tìm thẻ a vừa bấm bằng closest, gọi preventDefault để chặn mở trang theo cách thông thường, rồi gọi loadPage với modal bằng true khi thấy data-order-form.

```javascript
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.target || link.hasAttribute('download') || link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-no-ajax')) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || !url.pathname.endsWith('.php') || link.getAttribute('href').startsWith('#')) return;
        event.preventDefault();
        loadPage(url.href, {modal: link.hasAttribute('data-order-form')});
    });
```

Tới đây mới bắt đầu có request đến máy chủ. Trình duyệt không tự thực thi nội dung PHP chỉ vì thấy href trong thẻ a.

#### Bước 3 — AJAX gọi create.php

Hàm loadPage bắt đầu ở [assets/app.js dòng 76](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:76), và fetch được gọi tại [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85). Với lần mở này không có body nên method là GET, URL là create.php.

#### Bước 4 — create.php chuẩn bị form

- [create.php dòng 15](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:15) kiểm tra quyền nhân viên/quản trị.
- [create.php dòng 31](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:31) lấy danh sách sản phẩm gồm ID, tên, SKU, giá và tồn kho.
- [create.php dòng 32](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:32) lấy danh sách voucher.
- [create.php dòng 104](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:104) tạo form có action là actions/store.php và method POST.
- [create.php dòng 155](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:155) chèn các dòng sản phẩm, voucher và COD từ includes/order-fields.php.

```php
        <form action="actions/store.php" method="POST" id="order-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
```

PHP trả HTML chứa form. Form vẫn chưa ghi đơn vào database tại bước này.

#### Bước 5 — Đưa form vào ô lớn giữa màn hình

Trong [assets/app.js dòng 102](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:102), bộ AJAX nhận ra URL là create.php, lấy nội dung .container và đưa vào .modal-body của orderFormModal. Phần khung modal nằm tại [index.php dòng 547](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:547).

```javascript
                const formContent = page.querySelector('.container');
                if (!formContent || !orderModal) throw new Error('Không thể mở form đơn hàng.');
                orderModal.querySelector('.modal-body').replaceChildren(...[...formContent.children].map(child => document.importNode(child, true)));
                orderModal.querySelector('.modal-title').textContent = path.endsWith('/edit.php') ? 'Cập nhật đơn hàng' : 'Tạo đơn hàng';
```

Sau đó script assets/order-form.js được khởi tạo ở [assets/app.js dòng 106](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:106), Bootstrap mở hộp thoại.

#### Bước 6 — Điền form và tính tiền

| Bạn thay đổi ô nào | Dòng HTML | Hàm được gọi |
|---|---|---|
| Sản phẩm | [includes/order-fields.php dòng 8](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:8) | [assets/order-form.js dòng 5](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:5) đọc data-price và data-stock |
| Số lượng | [includes/order-fields.php dòng 23](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:23) | [assets/order-form.js dòng 25](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:25) tính số lượng nhân giá |
| Đơn giá | [includes/order-fields.php dòng 27](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:27) | Cũng gọi calculateItemTotal |
| Thêm sản phẩm | [includes/order-fields.php dòng 44](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:44) | [assets/order-form.js dòng 75](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:75) sao chép dòng, đổi index name và đặt giá trị mặc định |
| Dấu X của dòng | [includes/order-fields.php dòng 34](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:34) | [assets/order-form.js dòng 89](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:89) xóa dòng và tính lại tổng |
| Voucher | [includes/order-fields.php dòng 59](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:59) | [assets/order-form.js dòng 34](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:34) đọc mức giảm của option và cập nhật tổng |
| Trạng thái và số tiền COD | [includes/order-fields.php dòng 89](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:89), [includes/order-fields.php dòng 97](F:/Ki1nam4/Lap_trinh_web/order_management/includes/order-fields.php:97) | Gửi cùng form để máy chủ kiểm tra khi lưu |

Ví dụ chọn 2 sản phẩm giá 100.000 đồng thì tiền hàng tạm tính là 200.000 đồng. Voucher 10.000 đồng cho tổng tạm tính 190.000 đồng.

#### Bước 7 — Bấm Lưu đơn hàng

Nút nằm tại [create.php dòng 198](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:198) và có type=submit. Điều đó kích hoạt submit của form order-form tại [create.php dòng 104](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:104).

Listener tại [assets/order-form.js dòng 110](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:110) kiểm tra tên khách, số điện thoại, sản phẩm, số lượng và giá. Có lỗi thì preventDefault, đánh dấu ô sai và đưa ô lỗi vào vùng nhìn thấy. Nếu hợp lệ, sự kiện tiếp tục đến listener chung tại [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145).

#### Bước 8 — Gửi POST tới actions/store.php

[assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) đọc form.action, tạo FormData rồi gọi loadPage. [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85) gửi method POST, body là dữ liệu form.

Một phần dữ liệu gửi có dạng sau.

```text
csrf_token=...
customer_name=Nguyen Van A
customer_phone=0901234567
items[0][product_id]=1
items[0][quantity]=2
items[0][unit_price]=100000
voucher_id=
cod_status=pending
cod_amount=100000
status=pending
note=Giao buoi chieu
```

Tên khóa đến từ thuộc tính name của từng input/select/textarea, không phải từ chữ label bên ngoài ô.

#### Bước 9 — store.php kiểm tra lại ở máy chủ

| Nội dung kiểm tra | Vị trí |
|---|---|
| Role được phép tạo đơn | [actions/store.php dòng 15](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:15) |
| Chỉ xử lý POST | [actions/store.php dòng 18](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:18) |
| CSRF có khớp session | [actions/store.php dòng 24](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:24) |
| Nhận các ô khách hàng | [actions/store.php dòng 35](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:35) |
| Tên, điện thoại, email, trạng thái | [actions/store.php dòng 49](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:49) và [actions/store.php dòng 60](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:60) |
| Từng sản phẩm, số lượng, giá | [actions/store.php dòng 72](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:72) |
| Lấy voucher và tính mức giảm thật | [actions/store.php dòng 118](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:118) gọi [config/order-pricing.php dòng 8](F:/Ki1nam4/Lap_trinh_web/order_management/config/order-pricing.php:8) |
| Kiểm tra COD so với tổng sau giảm | [actions/store.php dòng 120](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:120) gọi [config/order-pricing.php dòng 40](F:/Ki1nam4/Lap_trinh_web/order_management/config/order-pricing.php:40) |

Kiểm tra JavaScript phục vụ phản hồi nhanh. Kiểm tra PHP vẫn chạy vì request có thể được gửi mà không đi qua JavaScript.

#### Bước 10 — Bắt đầu ghi database

Transaction bắt đầu tại [actions/store.php dòng 117](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:117). Những lệnh ghi được nhóm lại để commit cùng nhau hoặc rollback khi có lỗi.

| Thứ tự | Vị trí SQL | Câu lệnh và mục đích |
|---|---|---|
| 1 | [actions/store.php dòng 123](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:123) | SELECT khách theo số điện thoại với FOR UPDATE |
| 2a | [actions/store.php dòng 131](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:131) | Nếu đã có khách thì UPDATE tên/email/địa chỉ |
| 2b | [actions/store.php dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:145) | Nếu chưa có khách thì INSERT hồ sơ và lấy customer ID |
| 3 | [actions/store.php dòng 158](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:158) | INSERT đơn vào orders, gồm tổng tiền, voucher, COD, trạng thái và ghi chú |
| 4 | [actions/store.php dòng 174](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:174) | INSERT từng sản phẩm vào order_items |
| 5 | [actions/store.php dòng 178](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:178) | UPDATE products để trừ tồn kho khi áp dụng |
| 6 | [actions/store.php dòng 199](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:199) | INSERT mốc tạo đơn vào order_status_history |

Đây là lệnh tạo bản ghi đơn hàng mới trong code.

```php
    $orderSql = "INSERT INTO orders (customer_id, total_amount, voucher_code, discount_amount, cod_amount, cod_status, status, note)
                 VALUES (:customer_id, :total_amount, :voucher_code, :discount_amount, :cod_amount, :cod_status, :status, :note)";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([
        ':customer_id'     => $customerId,
        ':total_amount'    => $finalTotal,
        ':voucher_code'    => $voucherCode,
        ':discount_amount' => $discountAmount,
        ':cod_amount'      => $codAmount,
        ':cod_status'      => $codStatus,
        ':status'          => $status,
        ':note'            => $note ?: null,
    ]);
    $orderId = (int)$pdo->lastInsertId();
```

execute ngay sau prepare truyền giá trị vào các chỗ có dấu hai chấm. Ví dụ :customer_id được nhận từ ID hồ sơ khách ở bước trước; :total_amount được nhận từ tổng tiền PHP vừa tính.

Lệnh cập nhật tồn kho nằm tại [actions/store.php dòng 178](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:178).

```php
    $stockSql = "UPDATE products SET stock = GREATEST(0, stock - :quantity) WHERE id = :product_id";
    $stockStmt = $pdo->prepare($stockSql);
```

Vì vậy chức năng Tạo đơn dùng INSERT để thêm đơn/chi tiết/lịch sử, đồng thời dùng UPDATE cho hồ sơ khách đã có và tồn kho.

#### Bước 11 — Ghi thành công và hiển thị đơn mới

1. [actions/store.php dòng 208](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:208) commit transaction.
2. [actions/store.php dòng 210](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:210) ghi thông báo thành công vào session.
3. [actions/store.php dòng 211](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:211) trả Location về index.php.
4. Fetch trong [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85) đi theo Location và nhận HTML của index.php.
5. index.php đọc các đơn bằng SQL tại [index.php dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:85), execute tại [index.php dòng 106](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:106).
6. Vòng foreach tại [index.php dòng 429](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:429) tạo HTML bảng có đơn vừa thêm.
7. [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) dọn modal; [assets/app.js dòng 70](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:70) thay body; [assets/app.js dòng 20](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:20) gắn lại các hoạt động cho bảng.

Đó là đường đi để đơn mới xuất hiện trên màn hình sau khi bấm lưu.

#### Bước 12 — Nhập sai hoặc lưu lỗi

- Lỗi kiểm tra dữ liệu được xử lý tại [actions/store.php dòng 107](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:107). PHP lưu errors và old_input rồi trả Location về create.php.
- Lỗi trong transaction đi tới [actions/store.php dòng 216](F:/Ki1nam4/Lap_trinh_web/order_management/actions/store.php:216) để rollback.
- Bộ AJAX thấy trang đích vẫn là create.php nên chạy nhánh modal tại [assets/app.js dòng 102](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:102), hiển thị form và lỗi ngay trong ô lớn.

### B. Bấm bút chì rồi Cập nhật đơn hàng

| Thứ tự | Code thực tế | Nội dung |
|---|---|---|
| 1 | [index.php dòng 509](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:509) | Liên kết mang id của đơn và data-order-form |
| 2 | [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129) → [assets/app.js dòng 76](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:76) | AJAX GET edit.php?id=ID |
| 3 | [edit.php dòng 35](F:/Ki1nam4/Lap_trinh_web/order_management/edit.php:35) và [edit.php dòng 67](F:/Ki1nam4/Lap_trinh_web/order_management/edit.php:67) | SELECT đơn, khách và các dòng sản phẩm để điền form |
| 4 | [edit.php dòng 231](F:/Ki1nam4/Lap_trinh_web/order_management/edit.php:231) | Form POST tới actions/update.php; hidden input id xác định đơn cần sửa |
| 5 | [edit.php dòng 327](F:/Ki1nam4/Lap_trinh_web/order_management/edit.php:327) → [assets/order-form.js dòng 110](F:/Ki1nam4/Lap_trinh_web/order_management/assets/order-form.js:110) → [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) | Bấm cập nhật, kiểm tra phía trình duyệt, gửi POST |
| 6 | [actions/update.php dòng 16](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:16), [actions/update.php dòng 31](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:31), [actions/update.php dòng 79](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:79) | Kiểm tra quyền, token và dữ liệu ở máy chủ |
| 7 | [actions/update.php dòng 124](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:124) → [actions/update.php dòng 127](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:127) | Transaction và SELECT đơn FOR UPDATE để lấy dữ liệu hiện tại |
| 8 | [actions/update.php dòng 144](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:144) | UPDATE thông tin khách của đơn |
| 9 | [actions/update.php dòng 162](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:162) | UPDATE tổng, voucher, COD, trạng thái và ghi chú trong orders |
| 10 | [actions/update.php dòng 186](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:186) → [actions/update.php dòng 190](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:190) | Xóa chi tiết cũ của đơn và INSERT chi tiết mới |
| 11 | [actions/update.php dòng 210](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:210) | INSERT lịch sử nếu đổi trạng thái hoặc có ghi chú trạng thái |
| 12 | [actions/update.php dòng 222](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:222) → [actions/update.php dòng 225](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:225) → [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) | Commit, nhận trang index mới và hiển thị bảng cập nhật |

```php
        UPDATE orders 
        SET total_amount    = :total_amount,
            voucher_code    = :voucher_code,
            discount_amount = :discount_amount,
            cod_amount      = :cod_amount,
            cod_status      = :cod_status,
            status          = :status,
            note            = :note,
            updated_at      = NOW()
        WHERE id = :id
    ";
    $updateStmt = $pdo->prepare($updateOrderSql);
    $updateStmt->execute([
        ':total_amount'    => $finalTotal,
        ':voucher_code'    => $voucherCode,
```

Điều kiện WHERE id = :id ở cuối UPDATE bảo đảm chỉ cập nhật đúng đơn có ID được gửi. Đường xử lý lỗi tại [actions/update.php dòng 230](F:/Ki1nam4/Lap_trinh_web/order_management/actions/update.php:230) rollback và trả lại form edit.php.

### C. Những nút không gửi form

Nút Hủy được khai báo ở [create.php dòng 195](F:/Ki1nam4/Lap_trinh_web/order_management/create.php:195) hoặc [edit.php dòng 324](F:/Ki1nam4/Lap_trinh_web/order_management/edit.php:324). Listener [assets/app.js dòng 131](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:131) đóng modal khi thấy data-close-order-form. Nút X của modal dùng data-bs-dismiss. Hai nút này không gọi store.php/update.php.

### Mở form

- Nút Tạo đơn và nút tạo trong danh sách trống là liên kết `create.php` có `data-order-form`.
- Biểu tượng bút chì và nút Chỉnh sửa chi tiết trỏ `edit.php?id=...` có cùng thuộc tính.
- `loadPage()` tải HTML rồi lấy nội dung `.container` để đưa vào `#orderFormModal`.
- `runScripts(..., true)` khởi tạo `assets/order-form.js` cho form mới.
- Hộp thoại Bootstrap hiện giữa màn hình; nội dung dài có vùng cuộn riêng.
- Nút Hủy có `data-close-order-form`, đóng hộp thoại. Nút X ở đầu hộp thoại cũng đóng nó.

### Thông tin khách

Tên, số điện thoại, email và địa chỉ có các name `customer_name`, `customer_phone`, `customer_email`, `customer_address`. Các giá trị được gửi trong POST khi lưu.

### Dòng sản phẩm

HTML dùng chung tại `includes/order-fields.php`. Mỗi dòng gửi `items[index][product_id]`, `quantity` và `unit_price`.

- Chọn sản phẩm gọi `onProductChange()`, lấy `data-price`, `data-stock` từ option để điền giá và thông tin kho.
- Thay số lượng hoặc giá gọi `calculateItemTotal()`.
- Thêm sản phẩm gọi `addItem()`, sao chép dòng mẫu, đặt lại giá trị và đổi index của name.
- Dấu X gọi `removeItem()`. Chỉ có một dòng thì nút xóa dòng được ẩn.
- `updateGrandTotal()` cộng tiền hàng, đọc mức giảm và tính tổng thanh toán tối thiểu bằng 0.

Các phép tính này chạy ngay trong JavaScript. Khi lưu, máy chủ tính lại số tiền từ dữ liệu nhận được.

### Voucher

Dropdown lấy dữ liệu bằng `listVouchers()` trong `config/order-pricing.php`. Option chứa mức giảm để hiển thị trên ô output, khách quản trị không gõ trực tiếp mức giảm.

Khi lưu, `resolveOrderVoucher()` tra lại mức giảm từ database. Với đơn cũ, lựa chọn đã lưu giữ được thông tin giảm giá kể cả voucher đã bị xóa khỏi kho.

### COD

`cod_status` gồm Không thu COD, Chưa thu, Đã thu. `cod_amount` là số tiền thu hộ.

`readOrderCod()` kiểm tra trạng thái, số tiền và giới hạn không vượt tổng thanh toán. Không thu COD thì lưu số tiền 0.

### Trạng thái, ghi chú và tổng thanh toán

Dropdown trạng thái gửi giá trị nội bộ của đơn. Ghi chú gửi `note`. Khi sửa, `status_note` dùng để ghi thêm nội dung lịch sử trạng thái.

Khối tổng hiển thị tiền hàng, số tiền giảm và tổng sau giảm. Giá trị hiển thị được cập nhật bởi `updateGrandTotal()`.

### Bấm Lưu đơn hàng hoặc Cập nhật đơn hàng

1. Sự kiện submit trong `assets/order-form.js` kiểm tra các trường bắt buộc và dòng sản phẩm. Sai thì đánh dấu `is-invalid` và đưa ô lỗi vào vùng nhìn thấy.
2. Hợp lệ thì sự kiện đến lớp AJAX chung, gửi form POST.
3. Form tạo gửi `actions/store.php`; form sửa gửi `actions/update.php`.
4. Máy chủ kiểm tra quyền, CSRF, khách hàng, sản phẩm, trạng thái, voucher và COD.
5. Tạo đơn dùng transaction để tìm/tạo khách, ghi `orders`, `order_items`, lịch sử trạng thái và trừ kho khi áp dụng.
6. Sửa đơn khóa dòng đơn bằng `FOR UPDATE`, cập nhật khách, tổng tiền, voucher, COD, trạng thái, xóa/ghi lại các dòng chi tiết và ghi lịch sử nếu có thay đổi.
7. Commit thành công thì chuyển hướng đến `index.php`. Fetch lấy HTML đó và cập nhật danh sách, đồng thời đóng hộp thoại.
8. Có lỗi thì rollback và chuyển về form với `old_input`, `errors`. Lớp AJAX phát hiện trang đích vẫn là create/edit nên hiển thị lại ngay trong hộp thoại.

### Lịch sử ở form sửa

`edit.php` đọc `order_status_history` theo ID đơn và sắp theo thời gian. Mỗi mốc hiện trạng thái và ghi chú tương ứng.

## 9. Xóa đơn

| Thứ tự | Dòng chạy | Việc thực hiện |
|---|---|---|
| 1 | [index.php dòng 517](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:517) | Thùng rác mang data-order-id, data-customer và đích deleteModal |
| 2 | [index.php dòng 927](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:927) | Sự kiện mở modal điền mã/tên khách và hidden ID |
| 3 | [index.php dòng 685](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:685) và [index.php dòng 701](F:/Ki1nam4/Lap_trinh_web/order_management/index.php:701) | Xác nhận xóa submit form POST đến actions/delete.php |
| 4 | [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) → [assets/app.js dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:85) | Gửi id và csrf_token trong body |
| 5 | [actions/delete.php dòng 12](F:/Ki1nam4/Lap_trinh_web/order_management/actions/delete.php:12) và [actions/delete.php dòng 26](F:/Ki1nam4/Lap_trinh_web/order_management/actions/delete.php:26) | Kiểm tra method và CSRF |
| 6 | [actions/delete.php dòng 60](F:/Ki1nam4/Lap_trinh_web/order_management/actions/delete.php:60) → [actions/delete.php dòng 63](F:/Ki1nam4/Lap_trinh_web/order_management/actions/delete.php:63) | DELETE FROM orders WHERE id = :id rồi commit |
| 7 | [actions/delete.php dòng 75](F:/Ki1nam4/Lap_trinh_web/order_management/actions/delete.php:75) → [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) | Fetch nhận danh sách mới và cập nhật giao diện |

```php
    $deleteStmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
    $deleteStmt->execute([':id' => $id]);
```

Biểu tượng thùng rác mở `#deleteModal`. Sự kiện `show.bs.modal` điền mã/tên khách và hidden input `#delete-order-id`.

Nút Xác nhận xóa gửi POST đến `actions/delete.php` qua AJAX, kèm ID và CSRF. Máy chủ kiểm tra quyền, xác nhận đơn tồn tại rồi xóa trong transaction. Khóa ngoại CASCADE xóa các dòng chi tiết và lịch sử liên quan. Sau đó danh sách mới và thông báo được đưa lên giao diện.

Hủy bỏ hoặc X chỉ đóng hộp thoại. URL GET đến action xóa trả 405.

## 10. Trang Khách hàng của quản trị

| Bạn làm gì | Luồng theo dòng |
|---|---|
| Mở Khách hàng | [includes/orders-navigation.php dòng 42](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:42) → [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129) → [customers.php dòng 19](F:/Ki1nam4/Lap_trinh_web/order_management/customers.php:19) → [includes/catalog-list.php dòng 50](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:50) |
| Bấm con mắt ở một dòng | [includes/catalog-list.php dòng 61](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:61) → [includes/catalog-list.php dòng 109](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:109) |
| Gửi AJAX lấy đơn | [includes/catalog-list.php dòng 116](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:116) gọi actions/customer_orders.php?customer_id=ID |
| Kiểm tra quyền và lấy dữ liệu | [actions/customer_orders.php dòng 13](F:/Ki1nam4/Lap_trinh_web/order_management/actions/customer_orders.php:13) → [actions/customer_orders.php dòng 31](F:/Ki1nam4/Lap_trinh_web/order_management/actions/customer_orders.php:31) |
| Hiện bảng trong modal | [actions/customer_orders.php dòng 66](F:/Ki1nam4/Lap_trinh_web/order_management/actions/customer_orders.php:66) tạo HTML → [includes/catalog-list.php dòng 121](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:121) gán vào customer-orders-body |

`customers.php` đọc bảng `customers`; `includes/catalog-list.php` hiển thị mã, tên, số điện thoại, email, địa chỉ và con mắt.

Con mắt mở `#customer-orders-modal`. JavaScript gọi `fetch('actions/customer_orders.php?customer_id=...')`. Action kiểm tra quyền quản trị, lấy các đơn đúng customer ID và trả HTML bảng. HTML được đặt vào `#customer-orders-body`.

Hộp thoại hiển thị mã, ngày, sản phẩm, trạng thái, COD và tổng tiền. Khi đổi sang khách khác, request cũ bị hủy bằng AbortController. Đóng hộp thoại cũng hủy yêu cầu đang chờ.

## 11. Trang Sản phẩm

### Thêm sản phẩm

[includes/catalog-list.php dòng 19](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:19) → [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129) → products.php?add=1 → [products.php dòng 108](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:108) tạo mảng mặc định → [includes/catalog-list.php dòng 29](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:29) chèn form tại [includes/product-form.php dòng 7](F:/Ki1nam4/Lap_trinh_web/order_management/includes/product-form.php:7).

Điền xong, nút [includes/product-form.php dòng 32](F:/Ki1nam4/Lap_trinh_web/order_management/includes/product-form.php:32) submit → [assets/app.js dòng 145](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:145) → POST products.php → [products.php dòng 25](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:25) kiểm tra token → [products.php dòng 48](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:48) kiểm tra dữ liệu → [products.php dòng 57](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:57) INSERT → [products.php dòng 80](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:80) chuyển về danh sách → [products.php dòng 96](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:96) SELECT lại → [includes/catalog-list.php dòng 50](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:50) tạo bảng → [assets/app.js dòng 64](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:64) hiển thị.

```php
                    $stmt = $pdo->prepare('INSERT INTO products (name, sku, price, stock, description) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute($values);
```

### Sửa sản phẩm

[includes/catalog-list.php dòng 66](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:66) → AJAX GET products.php?edit=ID → [products.php dòng 97](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:97) tìm dòng theo ID và điền form. Hidden original_stock tại [includes/product-form.php dòng 10](F:/Ki1nam4/Lap_trinh_web/order_management/includes/product-form.php:10) mang số kho lúc mở form.

Lưu form → POST products.php → [products.php dòng 66](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:66) UPDATE có điều kiện id và stock ban đầu. Nếu stock đã thay đổi, nhánh ngay sau UPDATE báo lỗi để người dùng mở lại form.

```php
                    $stmt = $pdo->prepare('UPDATE products SET name = ?, sku = ?, price = ?, stock = ?, description = ? WHERE id = ? AND stock = ?');
                    $stmt->execute([...$values, $id, $originalStock]);
```

### Xóa sản phẩm

[includes/catalog-list.php dòng 70](F:/Ki1nam4/Lap_trinh_web/order_management/includes/catalog-list.php:70) khai báo form và hộp xác nhận → nút submit gửi delete_id → [products.php dòng 35](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:35) DELETE theo ID → [products.php dòng 80](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:80) → SELECT danh sách mới.

[products.php dòng 85](F:/Ki1nam4/Lap_trinh_web/order_management/products.php:85) xử lý mã lỗi 1062 cho SKU trùng và 1451 cho sản phẩm đang được order_items tham chiếu.

`products.php` đọc `products`. Template `includes/catalog-list.php` hiện mã, tên, SKU, giá, tồn kho, mô tả và hai biểu tượng thao tác.

- Thêm sản phẩm mở `products.php?add=1` bằng AJAX. Template chèn `includes/product-form.php`.
- Bút chì mở `products.php?edit=ID`. PHP tìm sản phẩm và điền form.
- Form có tên, SKU tùy chọn, giá, tồn kho và mô tả. Nút Lưu gửi POST bằng AJAX.
- Máy chủ kiểm tra CSRF, tên không rỗng, độ dài, giá không âm và tồn kho là số nguyên không âm.
- SKU trùng bị từ chối bởi unique index.
- Khi sửa, hidden input `original_stock` được đối chiếu với kho hiện tại để tránh ghi đè thay đổi kho vừa phát sinh.
- Nút Hủy mở danh sách qua AJAX.
- Thùng rác dùng xác nhận của trình duyệt, sau đó gửi POST với `delete_id`.
- Sản phẩm đã nằm trong đơn không xóa được do ràng buộc khóa ngoại. Trang hiển thị thông báo tương ứng.
- Sau khi thành công, PHP chuyển về danh sách và AJAX hiện dữ liệu mới cùng thông báo.

## 12. Trang Khuyến mãi

### Lần theo từng nút

| Thành phần | Dòng giao diện | Dòng xử lý sau khi gửi |
|---|---|---|
| Mã và mức giảm | [voucher-create.php dòng 113](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:113), [voucher-create.php dòng 119](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:119) | [voucher-create.php dòng 43](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:43) kiểm tra định dạng và số tiền |
| Lưu voucher | [voucher-create.php dòng 109](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:109) và [voucher-create.php dòng 124](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:124) | app_submit → POST voucher-create.php → [voucher-create.php dòng 49](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:49) |
| Kho voucher | [voucher-create.php dòng 67](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:67) | [config/order-pricing.php dòng 3](F:/Ki1nam4/Lap_trinh_web/order_management/config/order-pricing.php:3) SELECT danh sách → [voucher-create.php dòng 155](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:155) duyệt từng dòng |
| Xóa | [voucher-create.php dòng 160](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:160) | Xác nhận → app_submit gửi delete_voucher → [voucher-create.php dòng 29](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:29) |
| Thông báo thành công | [voucher-create.php dòng 10](F:/Ki1nam4/Lap_trinh_web/order_management/voucher-create.php:10) | Đọc flash từ session, HTML mới được app_render hiển thị |

```php
            $stmt = getDBConnection()->prepare('INSERT INTO vouchers (code, discount_amount) VALUES (:code, :amount)');
            $stmt->execute([':code' => $code, ':amount' => $amount]);
```

Code nằm tại `voucher-create.php`.

| Thành phần | Luồng xử lý |
|---|---|
| Mã voucher | Chuyển chữ thành dạng in hoa, kiểm tra ký tự và độ dài |
| Số tiền giảm | Kiểm tra số nguyên dương trước khi ghi |
| Lưu voucher | POST bằng AJAX, kiểm tra CSRF, INSERT vào `vouchers` |
| Kho voucher | SELECT danh sách bằng `listVouchers()`, hiện mã, mức giảm và số mã |
| Xóa voucher | Hỏi xác nhận rồi POST `delete_voucher`; máy chủ DELETE theo ID |
| Về danh sách đơn hàng | AJAX tải `index.php` |

Voucher trùng mã hoặc có lỗi được báo ngay trên trang. Xóa trong kho không sửa mức giảm đã lưu ở những đơn trước.

## 13. Trang Báo cáo

| Bước | Dòng thực hiện | Kết quả |
|---|---|---|
| Bấm Báo cáo | [includes/orders-navigation.php dòng 45](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:45) → [assets/app.js dòng 129](F:/Ki1nam4/Lap_trinh_web/order_management/assets/app.js:129) | AJAX GET reports.php |
| Đếm theo trạng thái | [reports.php dòng 22](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:22) | Số đơn chờ, đang giao, đã giao, đã hủy, hoàn trả |
| Gom theo ngày | [reports.php dòng 29](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:29) | Ngày, COUNT số đơn và SUM tiền đơn |
| Chuyển mảng PHP sang dữ liệu JavaScript | [reports.php dòng 44](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:44) | chartDates, chartRevenues và chartCounts |
| Tạo vùng vẽ | [reports.php dòng 96](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:96), [reports.php dòng 108](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:108) | Hai canvas có ID riêng |
| Vẽ cột và đường | [reports.php dòng 130](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:130) | new Chart nhận labels và hai datasets |
| Vẽ vành khuyên | [reports.php dòng 213](F:/Ki1nam4/Lap_trinh_web/order_management/reports.php:213) | new Chart với type doughnut |
| Kích thước khung | [assets/orders-layout.css dòng 57](F:/Ki1nam4/Lap_trinh_web/order_management/assets/orders-layout.css:57) | CSS quyết định chiều cao và bố cục |

```php
        SELECT DATE_FORMAT(created_at, '%d/%m/%Y') AS order_date, 
               COUNT(*) AS order_count, 
               SUM(total_amount) AS total_revenue
        FROM orders 
        GROUP BY DATE(created_at) 
        ORDER BY DATE(created_at) ASC 
        LIMIT 10
    ");
```

Ví dụ một ngày có hai đơn 100.000 đồng và 200.000 đồng, COUNT là 2 và SUM là 300.000 đồng. Hai số này trở thành chiều cao cột doanh thu và điểm trên đường số đơn của ngày đó.

`reports.php` thực hiện hai truy vấn.

- `GROUP BY status` và `COUNT(*)` đếm đơn theo trạng thái.
- `GROUP BY DATE(created_at)` đếm đơn và cộng `total_amount` theo ngày.

PHP dùng `array_column()` lấy riêng ngày, doanh thu và số đơn. `json_encode()` đưa các mảng sang JavaScript.

HTML có `canvas#revenueChart` và `canvas#statusChart`. JavaScript gọi `new Chart()` của Chart.js.

- Biểu đồ doanh thu dùng cột xanh ngọc và đường số đơn xanh đậm. Trục trái là tiền, trục phải là số đơn.
- Biểu đồ trạng thái dùng `type: 'doughnut'`. Mảng số lượng quyết định kích thước mỗi phần và mảng màu quyết định màu.
- Khi rê chuột, tooltip hiển thị số tiền/số đơn hoặc tỷ lệ phần trăm.
- Các ô màu và nhãn là chú giải Chart.js. Bấm chú giải có thể ẩn/hiện nhóm dữ liệu tương ứng trong biểu đồ.
- `.report-chart` trong CSS quyết định chiều cao theo màn hình. Hai biểu đồ nằm ngang trên màn hình rộng.
- AJAX tải dữ liệu báo cáo khi mở trang. Khi rời trang, các Chart instance được destroy; mở lại sẽ tạo instance mới.

Truy vấn hiện lấy tối đa 10 ngày theo thứ tự tăng dần. Tổng tiền báo cáo là tổng `total_amount` đang lưu của mọi trạng thái. Đây là cách thống kê hiện có, không phải phép đối soát tiền thực thu.

## 14. Hồ sơ cá nhân

| Thao tác | Form giao diện | Backend và SQL | Trả kết quả |
|---|---|---|---|
| Mở trang | [includes/orders-navigation.php dòng 25](F:/Ki1nam4/Lap_trinh_web/order_management/includes/orders-navigation.php:25) | [profile.php dòng 95](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:95) lấy tài khoản theo ID session | HTML hiện avatar, tên, role và các form |
| Lưu thông tin | [profile.php dòng 213](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:213), action update_profile | [profile.php dòng 39](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:39) UPDATE users SET name, email WHERE id = :id | [profile.php dòng 51](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:51) → AJAX hiện thông báo |
| Cập nhật mật khẩu | [profile.php dòng 244](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:244), action change_password | [profile.php dòng 75](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:75) kiểm tra mật khẩu cũ; [profile.php dòng 78](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:78) băm mật khẩu mới; [profile.php dòng 79](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:79) UPDATE users | Chuyển về profile.php và thay HTML |

```php
                $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id");
                $stmt->execute([
                    ':name'  => $name,
                    ':email' => $email ?: null,
                    ':id'    => $userId,
                ]);

                // Cập nhật lại session
```

ID trong các câu UPDATE được lấy từ session ở [profile.php dòng 13](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:13), không lấy từ một ô cho người dùng nhập. CSRF được kiểm tra tại [profile.php dòng 24](F:/Ki1nam4/Lap_trinh_web/order_management/profile.php:24) trước khi xử lý action.

`profile.php` lấy ID tài khoản từ session.

- Thẻ đầu hiển thị chữ cái tên, họ tên, vai trò, tên đăng nhập và ngày tạo tài khoản.
- Form thông tin có tên đăng nhập chỉ đọc, họ tên và email. Gửi POST với `action=update_profile`.
- PHP kiểm tra CSRF, tên, email rồi UPDATE `users WHERE id = userIdTrongSession`, cập nhật tên/email trong session và trả trang có thông báo.
- Form đổi mật khẩu gửi `action=change_password`, mật khẩu hiện tại, mật khẩu mới và xác nhận.
- PHP kiểm tra đầy đủ trường, độ dài, sự khớp nhau và `password_verify()` với mật khẩu hiện tại. Sau đó `password_hash()` và UPDATE mật khẩu của đúng tài khoản.
- Hai form đều dùng AJAX chung và hiển thị lỗi/thành công trên trang.

## 15. Trang dành cho khách đã đăng nhập

| Bạn làm gì | Đường đi trong code |
|---|---|
| Đăng nhập thành công | [login.php dòng 44](F:/Ki1nam4/Lap_trinh_web/order_management/login.php:44) → customer.php |
| Tải các đơn của mình | [customer.php dòng 8](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:8) lấy customer_id từ tài khoản → [customer.php dòng 63](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:63) SELECT với customer_id → [customer.php dòng 121](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:121) tạo bảng |
| Bấm con mắt | [customer.php dòng 127](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:127) → [customer.php dòng 208](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:208) → [customer.php dòng 216](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:216) POST order_id và lookup_token |
| Kiểm tra chủ đơn | [customer.php dòng 29](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:29) có cả điều kiện mã đơn và customer_id của session |
| Trả chi tiết | [customer.php dòng 47](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:47) trả JSON order và items |
| Hiển thị chi tiết | [customer.php dòng 224](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:224) điền các ô, rồi mở lookup-modal |
| Nhập từ khóa sản phẩm | [customer.php dòng 142](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:142) → [customer.php dòng 254](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:254) → [customer.php dòng 262](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:262) GET ajax=1 |
| Tìm trong database | [customer.php dòng 67](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:67) dùng LIKE với tên và SKU |
| Hiện kết quả sản phẩm | [customer.php dòng 157](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:157) tạo thẻ sản phẩm → [customer.php dòng 264](F:/Ki1nam4/Lap_trinh_web/order_management/customer.php:264) thay shop-results |

```php
            SELECT o.id, o.created_at, o.status, o.total_amount, o.voucher_code, o.discount_amount,
                   o.cod_status, o.cod_amount
            FROM orders o JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :id AND o.customer_id = :customer_id
        ');
```

Vì điều kiện chủ đơn được kiểm tra trong SQL, thay order_id thành một đơn của người khác sẽ không trả dữ liệu đơn đó.

`customer.php` có hai tab Bootstrap. Chuyển tab chỉ thay phần đang hiển thị nên không phát sinh request.

### Đơn hàng đã đặt

Danh sách được lấy bằng `WHERE customer_id = ?`, với giá trị đến từ `$currentUser['customer_id']`. Không dùng customer ID do người dùng tự sửa trên URL.

Bảng hiện mã, ngày, trạng thái, tổng tiền và con mắt. Bấm con mắt sẽ POST tới chính `customer.php`, gồm `order_id` và `lookup_token`.

Máy chủ kiểm tra token và truy vấn đồng thời theo mã đơn và customer ID của session. Có quyền thì trả JSON gồm `order` và `items`. Không khớp thì trả 404.

JavaScript tạo các ô bằng `textContent`, điền sản phẩm, SKU, số lượng, đơn giá, thành tiền, trạng thái, voucher, COD và tổng vào `#lookup-modal`. Khách không có nút sửa/xóa đơn.

### Sản phẩm

Mỗi thẻ hiện tên, SKU, giá, tình trạng còn/hết hàng và mô tả.

Form `#shop-search` chặn submit và gọi GET `customer.php?search=...&ajax=1`. PHP dùng LIKE với tên hoặc SKU rồi chỉ trả HTML kết quả. JavaScript thay `#shop-results`.

Header có Hồ sơ cá nhân và Đăng xuất. Khách không truy cập được các trang quản trị chỉ bằng cách tự nhập URL.

## 16. Những phần chỉ chạy trong trình duyệt

Màu xanh ngọc chính được đặt tại [assets/orders-layout.css dòng 107](F:/Ki1nam4/Lap_trinh_web/order_management/assets/orders-layout.css:107). Màu nền, tab/menu chọn và focus được đặt trong cùng assets/orders-layout.css. [assets/orders-layout.css dòng 115](F:/Ki1nam4/Lap_trinh_web/order_management/assets/orders-layout.css:115) điều khiển nút chính; [assets/orders-layout.css dòng 166](F:/Ki1nam4/Lap_trinh_web/order_management/assets/orders-layout.css:166) điều khiển nút Lưu đơn/Cập nhật đơn và nút hồ sơ. Các link CSS có tham số phiên bản để nhận theme mới.

- Lịch chọn ngày được tạo bởi `input type="date"`.
- Dropdown, tab, modal và offcanvas do Bootstrap đóng/mở.
- Tính tiền tạm, thêm/xóa dòng sản phẩm và chọn checkbox do JavaScript xử lý.
- In dùng `window.print()`.
- Sao chép dùng Clipboard API.
- Chữ gợi ý mờ là CSS `::placeholder`.
- Màu chủ đạo xanh ngọc, nền sáng, đường viền và góc bo được đặt trong CSS.

Các thao tác này không cần request AJAX riêng vì không đọc hoặc ghi dữ liệu từ máy chủ.

## 17. Cách tự kiểm tra AJAX

1. Mở DevTools bằng F12, chọn Network.
2. Lọc theo Fetch/XHR.
3. Bấm một tab trạng thái, gửi tìm kiếm, lưu sản phẩm hoặc lưu đơn.
4. Xem request GET/POST và các trường form trong Payload.
5. Xem Response. Điều hướng chung trả HTML, chi tiết đơn khách trả JSON, danh sách đơn của một khách trả HTML bảng.
6. Quan sát URL và phần nội dung thay đổi. Trang tài liệu gốc không được tải lại cho các thao tác đã được lớp chung chặn.

Lần đầu mở web, bấm tải lại trình duyệt, mở liên kết trong tab mới và hộp thoại in vẫn sử dụng cơ chế thông thường của trình duyệt.

## 18. Khi chạy bằng Docker

Phần Docker được bổ sung ngày 18/11/2026. Các luồng nút, AJAX và SQL phía trên vẫn giữ nguyên.

1. Chạy `docker compose up -d --build --wait` trong thư mục dự án. Docker đọc `compose.yaml` để tạo hai dịch vụ `db` và `web`.
2. Dịch vụ `db` dùng MySQL 8.4. Khi volume `mysql_data` chưa có dữ liệu, entrypoint của image MySQL tự chạy `database.sql` được gắn tại `/docker-entrypoint-initdb.d/01-database.sql`. Nó tạo bảng và thêm dữ liệu mẫu.
3. Healthcheck đăng nhập bằng tài khoản `order_app` và chạy `SELECT id FROM users LIMIT 1`. Chỉ khi truy vấn thành công thì Compose mới khởi động web. Vì vậy web đợi cả MySQL lẫn bảng `users` sẵn sàng.
4. Docker build image web theo `Dockerfile`: PHP 8.3 + Apache, PDO MySQL, mbstring, cấu hình session, mã nguồn và assets. Không cần XAMPP trong container.
5. Mở `http://localhost:8088`. Docker chuyển request từ cổng 8088 của máy đến cổng 80 của Apache trong container web. Request `/` vào `index.php`; chưa đăng nhập thì `requireStaff()` → `requireAuth()` chuyển tới `login.php`.
6. Khi PHP gọi `getDBConnection()` trong `config/database.php`, các hằng `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` lấy từ environment của container. Host `db` là tên dịch vụ MySQL trong mạng Compose, không phải MySQL XAMPP trên máy.
7. Đăng nhập bằng `admin / 123456` trong dữ liệu mẫu. Khách hàng có thể đăng ký mới. Sau đăng nhập, mọi form, request AJAX và câu SQL hoạt động như các mục phía trên; database đích là MySQL Docker.
8. Dữ liệu ghi vào volume `mysql_data`; session PHP nằm trong volume `sessions`. Tạo lại container bằng `docker compose up -d --build --wait` vẫn giữ các volume này. File SQL khởi tạo không chạy lại mỗi lần restart.
9. Mã nguồn được chép vào image lúc build. Sau khi sửa PHP, CSS hoặc JavaScript trên máy, chạy lại lệnh build để cập nhật image. `.dockerignore` giới hạn các tệp được gửi vào build context; `COPY` trong Dockerfile chỉ chép mã phục vụ web.

Hướng dẫn đổi cổng, mật khẩu, dừng/chạy lại và chuyển dữ liệu XAMPP nằm trong mục **Chạy toàn bộ bằng Docker** của `README.md`. Database Docker mới có dữ liệu mẫu; nó không tự chứa những đơn hoặc tài khoản bạn đã tạo trong XAMPP.
