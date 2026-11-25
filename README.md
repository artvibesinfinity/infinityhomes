# infinityhomes — WordPress + admin kiểu MatBao WS

WordPress chạy bằng Docker, cùng plugin với site thật, kèm plugin admin `mu-plugins/infinity-admin`
(menu, Dashboard, Website Settings, Statistical, Email logs, Customer List, Auto Publish từ file Word).

## Chạy

1. Cài Docker Desktop.
2. `cp .env.example .env` rồi đặt mật khẩu admin trong `.env`.
3. Đặt file export (WordPress → Tools → Export → All content) vào `data/content.xml`.
4. Chạy:

```bash
./setup.sh
./import.sh
```

Mở http://localhost:8080/wp-admin

Kiểm tra nhanh plugin admin:

```bash
docker compose run --rm cli wp eval-file /data/test/check.php
```

## Ghi chú

- Theme tạm là Storefront. Cài Flatsome (theme gốc của site) để layout trang hiển thị đúng.
- `import.sh` chạy lại được nhiều lần, không tạo bản trùng (khớp theo ID gốc).
- Đơn hàng, cài đặt WooCommerce và Rank Math không có trong file export.
