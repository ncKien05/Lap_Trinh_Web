# Tổng hợp thay đổi

Ngày thực hiện 08/11/2026.

Mã nguồn tiếp tục dùng cấu trúc PHP hiện có. File này tập hợp toàn bộ thay đổi của đợt chỉnh AJAX và giao diện. Hướng dẫn từng phần giao diện nằm trong `HUONG_DAN_HOAT_DONG.md`.

## 1. Phạm vi AJAX sau thay đổi

| Thao tác | Trước đợt sửa | Sau đợt sửa |
|---|---|---|
| Chuyển mục bằng menu trái | Mở trang thông thường | Fetch HTML rồi cập nhật giao diện |
| Tab trạng thái đơn | Tải lại trang | GET bằng AJAX, cập nhật bảng và URL |
| Tìm kiếm, lọc ngày/trạng thái, sắp xếp đơn | Gửi form tải lại trang | AJAX gửi GET từ cùng form |
| Xóa bộ lọc | Mở lại trang | AJAX mở URL đã bỏ điều kiện |
| Mở form tạo đơn | Iframe trong modal | AJAX tải form vào modal chung |
| Mở form sửa đơn | Chuyển sang trang sửa | AJAX mở cùng modal chung |
| Lưu đơn mới, cập nhật đơn | Submit và tải trang đích | AJAX POST, hiện danh sách sau lưu |
| Báo lỗi nhập liệu trong modal đơn | Tải lại nội dung iframe hoặc trang sửa | Giữ modal, hiện form và lỗi từ máy chủ |
| Xóa đơn | GET tới action | POST có CSRF qua AJAX |
| Thêm, sửa, xóa sản phẩm | Bộ AJAX riêng của module | Dùng lớp AJAX chung |
| Tạo và xóa voucher | Submit tải lại trang | AJAX POST, cập nhật thông báo và kho |
| Sửa hồ sơ, đổi mật khẩu | Submit tải lại trang | AJAX POST, nhận trang thông báo |
| Đăng nhập, đăng ký, đăng xuất | Chuyển trang thông thường | AJAX xử lý và hiển thị trang đích |
| Mở Báo cáo | Chuyển trang thông thường | AJAX tải trang và khởi tạo biểu đồ |
| Khách xem đơn của mình | AJAX riêng | Tiếp tục dùng JSON và modal |
| Khách tìm sản phẩm | AJAX riêng | Tiếp tục cập nhật phần kết quả |
| Admin xem đơn của một khách | AJAX riêng | Tiếp tục cập nhật nội dung hộp thoại |

Các thao tác in, mở lịch, chọn checkbox, đóng/mở tab và tính tiền tạm dùng chức năng của trình duyệt/JavaScript vì không cần đọc ghi dữ liệu máy chủ.

## 2. Lớp AJAX chung

Thêm `assets/app.js`.

- Chặn liên kết PHP cùng website và submit form bằng sự kiện chung.
- GET ghép dữ liệu vào URL, POST dùng FormData, bao gồm name/value của nút submit.
- Nhận HTML do PHP hiện có trả về, bao gồm trang cuối sau chuyển hướng.
- Thay nội dung body và tiêu đề, cập nhật CSS, chạy lại phần khởi tạo trang.
- Tải Bootstrap/Chart.js một lần trong vòng đời tài liệu; khởi tạo lại script form đơn khi mở form mới.
- Ghi lịch sử URL và xử lý Back/Forward.
- Hủy GET cũ khi có yêu cầu đọc mới, chặn gửi POST liên tiếp khi đang lưu.
- Hiển thị vạch trạng thái chờ và thông báo nếu yêu cầu thất bại.
- Dọn modal, backdrop, offcanvas và biểu đồ khi chuyển giao diện để tránh lớp phủ hoặc instance cũ.
- Giữ cách dùng form/liên kết thông thường khi JavaScript bị tắt.

## 3. Tệp đã sửa

| Tệp | Nội dung |
|---|---|
| `assets/app.js` | Thêm bộ xử lý AJAX chung và tải form đơn vào modal |
| `assets/order-form.js` | Đưa biến/hàm vào phạm vi riêng, khởi tạo khi script chạy và xuất đúng các hàm dùng bởi onchange/onclick của form |
| `assets/orders-layout.css` | Đồng bộ bảng màu, trạng thái chọn, đường viền, góc bo, trạng thái chờ và vùng cuộn modal |
| `index.php` | Liên kết tạo/sửa mở modal bằng AJAX; dùng một modal form chung; form xác nhận xóa chuyển POST; khởi tạo lại script sau khi tải nội dung; bỏ cột thanh toán không có dữ liệu |
| `create.php` | Gỡ nhánh dành riêng cho iframe và tham số modal; thêm liên kết Hủy dùng trong modal; nạp controller/CSS chung |
| `edit.php` | Hủy đóng modal khi được mở trong modal; nạp controller/CSS chung |
| `actions/store.php` | Gỡ HTML postMessage trả cho iframe; dùng luồng trả về form/danh sách thống nhất |
| `actions/delete.php` | Chỉ nhận POST; nhận CSRF trong body; GET trả 405 |
| `products.php` | Gỡ phân nhánh response AJAX riêng, dùng chung luồng kiểm tra/lưu và trang kết quả |
| `includes/catalog-list.php` | Gỡ bộ fetch sản phẩm và response JSON chứa HTML của module; nạp controller chung; giữ AJAX hộp thoại đơn khách |
| `includes/product-form.php` | Bỏ thuộc tính điều hướng dành riêng cho bộ AJAX sản phẩm cũ |
| `reports.php` | Khởi tạo biểu đồ khi script được chèn; nạp controller chung |
| `login.php` | Nạp controller chung và CSS; gỡ màu nút cũ để dùng bảng màu chung |
| `register.php` | Nạp controller chung cho đăng ký và liên kết đăng nhập |
| `profile.php` | Nạp controller chung cho cả hai form và điều hướng |
| `voucher-create.php` | Nạp controller chung cho tạo/xóa voucher và điều hướng |
| `customer.php` | Nạp controller chung; ngăn response chi tiết cũ cập nhật giao diện sau khi đã rời trang |
| `README.md` | Cập nhật phần mô tả AJAX và liên kết đến hai file tài liệu |
| `THAY_DOI.md` | Ghi nội dung của đợt sửa |
| `HUONG_DAN_HOAT_DONG.md` | Mô tả thành phần giao diện, request, tệp PHP/JS, SQL và cách kết quả được hiển thị |

## 4. Mã cũ đã gỡ

- Iframe `createOrderFrame`, nhánh `?modal=1`, hidden input modal và biến `$inModal`.
- Giao tiếp postMessage giữa iframe và trang đơn.
- Lệnh reload toàn trang sau khi iframe tạo đơn thành công.
- Bộ `updateProducts()`, cờ request và listener riêng của module Sản phẩm.
- Nhánh `$isAjax` và JSON chứa HTML dành riêng cho CRUD Sản phẩm.
- Thuộc tính `data-product-link` đã được thay bằng cơ chế bắt liên kết chung.
- URL xóa đơn bằng GET chứa token.
- Cột Thanh toán chỉ hiển thị dấu gạch ngang.

## 5. Giao diện

- Dùng xanh ngọc làm màu chính, nền trắng pha mint rất nhạt và chữ đậm.
- Trạng thái menu/tab chọn dùng nền mint nhạt.
- Thống nhất góc bo và đường viền, bóng đổ nhẹ cho card.
- Ô nhập có viền và vùng sáng khi focus.
- Hàng bảng có màu hover nhẹ.
- Form tạo/sửa đơn hiển thị giữa màn hình và cuộn trong modal.
- Tiếp tục sử dụng font Inter, Bootstrap và CSS thuần.

## 6. Kiểm tra

- Kiểm tra cú pháp PHP và JavaScript.
- Chạy kiểm thử DOM kết hợp HTTP thật tới website cục bộ.
- Luồng quản trị đã đi qua 26 request, gồm lọc/tìm kiếm, thêm/sửa/xóa sản phẩm, tạo/sửa/xóa đơn, lỗi nhập liệu trong modal, voucher, hồ sơ, modal đơn khách và chuyển Báo cáo.
- Luồng tài khoản đi qua thêm 17 request, gồm sai thông tin đăng nhập, đăng ký, đăng nhập khách, đổi mật khẩu tài khoản thử, đăng xuất, xem chi tiết đơn, tìm sản phẩm và Back/Forward.
- Kiểm tra biểu đồ được khởi tạo và dọn instance khi chuyển trang bằng Chart stub trong môi trường DOM.
- Kiểm tra lỗi mạng không tự xóa dữ liệu đang hiển thị.
- Tạo dữ liệu thử có tên/mã riêng để xác minh các luồng ghi; dọn bản ghi thử sau kiểm tra.

Kết nối điều khiển trình duyệt trực tiếp không khả dụng. Chrome không xác định được URL đủ chắc chắn để công cụ tiếp tục. Vì vậy chưa có kiểm tra trực quan đầy đủ bằng thao tác trên Chrome; kiểm thử DOM không thay cho việc đối chiếu bố cục bằng mắt trên mọi kích thước màn hình.

## 7. Cách triển khai

Chép các tệp mã nguồn đã sửa cùng `assets/app.js`. Không cần thay đổi schema database cho đợt chỉnh AJAX này. Khi mở lại trang đã dùng trước đó, tải mới trang một lần để trình duyệt nhận controller và CSS mới.

## 8. Bổ sung theme xanh ngọc và hướng dẫn theo số dòng

- Đổi màu chính thành xanh ngọc `#0f766e`, màu hover `#115e59`, nền chọn màu mint nhạt và đường viền xanh xám.
- Đồng bộ nút chính, nút lưu form đơn/hồ sơ, tab/menu, checkbox, focus và vạch chờ AJAX.
- Đổi cột doanh thu và đường số đơn trong Báo cáo sang bảng màu xanh ngọc.
- Cập nhật các điểm nhấn màu trong CSS của trang và thêm phiên bản `20261108-teal` vào đường dẫn CSS để nhận theme mới.
- Mở rộng `HUONG_DAN_HOAT_DONG.md` với 283 liên kết tới các dòng code được đối chiếu trực tiếp.
- Bổ sung luồng Tạo đơn từng bước, từ thẻ a ở index.php dòng 287 qua app.js, create.php, kiểm tra form, store.php, từng câu SQL, commit và hiển thị lại danh sách.
- Bổ sung các luồng tương tự cho sửa/xóa đơn, lọc/tìm kiếm, chọn/in, sản phẩm, khách hàng, voucher, báo cáo, hồ sơ, đăng ký và đăng nhập.
- Khi rà code, khôi phục kiểm tra quyền nhân viên tại đầu index.php và cho logo điều hướng đúng trang theo role. Sửa thẻ đóng của nút xác nhận xóa cho khớp button.

## 9. Đóng gói Docker — 18/11/2026

- Thêm `Dockerfile`, `compose.yaml`, `.dockerignore`, `.env.example` và `.gitignore`; giữ cấu trúc hiện tại, không tạo thêm thư mục triển khai.
- Web dùng PHP 8.3 + Apache với extension PDO MySQL và mbstring. Database dùng MySQL 8.4, tự import `database.sql` khi volume còn trống.
- Thay các hằng kết nối trong `config/database.php` bằng biến môi trường có giá trị dự phòng XAMPP; giữ nguyên số dòng để các liên kết hướng dẫn vẫn đúng.
- Tách dữ liệu MySQL và session thành volume, thêm healthcheck và thứ tự khởi động. Chỉ public cổng web trên localhost; tài khoản ứng dụng dùng quyền trên database riêng.
- Không chép tài liệu, SQL và cấu hình môi trường vào image web; chặn truy cập HTTP trực tiếp vào `config` và `includes`.
- Bổ sung hướng dẫn chạy, đổi cổng, build lại, sao lưu/khôi phục và phân biệt database Docker với database XAMPP trong `README.md`.
- Đã build và chạy hai container thực tế, đều ở trạng thái healthy. Dùng cổng `8088` vì cổng `8080` của máy đang được sử dụng.
- Đã qua 31 kiểm tra HTTP/PDO: đăng nhập mẫu, các trang quản trị, asset, chặn truy cập tệp nội bộ, CRUD sản phẩm, tạo/xóa đơn có COD, trừ kho, đăng ký/đăng nhập khách, kiểm tra quyền và giữ dữ liệu/session sau restart.
- Đã lint toàn bộ PHP trong image, kiểm tra Apache và chạy thử lệnh sao lưu MySQL. Dữ liệu thử đã được dọn; database XAMPP không bị thay đổi.
