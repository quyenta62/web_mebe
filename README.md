# Facebook Post Monitor (Phase 2)

Ứng dụng Laravel 12 (Blade, Eloquent, MySQL, Queue, Scheduler) quản lý Facebook groups, lưu và tìm kiếm
bài viết do crawler `../crawler_mebe` (Phase 1) thu thập.

> Đang phát triển theo từng phase, xem tiến độ ở [docs/TODO.md](docs/TODO.md).

## Chạy môi trường dev (Docker)

Chạy các lệnh trong thư mục này (`web_mebe/`):

```bash
docker compose up -d                                   # mysql (127.0.0.1:3307) + app (http://localhost:8090)
docker compose run --rm app composer install           # lần đầu, nếu chưa có vendor/
docker compose run --rm app php artisan migrate        # tạo bảng
docker compose run --rm app php artisan test           # tests (database crawler_mebe_test)
docker compose run --rm app ./vendor/bin/pint          # format code
docker compose down                                    # dừng (giữ dữ liệu MySQL)
```

Nếu `.env` chưa có: `cp .env.example .env`, điền `DB_PASSWORD`, rồi
`docker compose run --rm app php artisan key:generate`.

## Database

App dùng MySQL trên Aiven (cấu hình trong `.env`, kết nối TLS có kiểm tra CA qua `MYSQL_ATTR_SSL_CA`).
MySQL trong Docker chỉ dùng cho tests. Chi tiết: [docs/DATABASE.md](docs/DATABASE.md).

## Truy cập

Tool dành cho một người dùng, **không có đăng nhập**: mở http://localhost:8090 là dùng được. Điều này chỉ an
toàn vì `docker-compose.yml` mở port trên `127.0.0.1` (chỉ máy này truy cập được; test `AccessTest` kiểm tra).
Không mở port ra mạng ngoài hay deploy lên server khi chưa thêm lại đăng nhập. Các form vẫn có CSRF token.

## Tài liệu

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): cấu trúc, luồng web và luồng crawl, môi trường dev
- [docs/DATABASE.md](docs/DATABASE.md): bảng, index, collation tiếng Việt
- [docs/TODO.md](docs/TODO.md): tiến độ các phase và câu hỏi mở
- `../crawler_mebe/docs/CRAWLER.md`: thiết kế crawler Facebook (Phase 1)
