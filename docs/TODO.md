# TODO — Phase 2: Laravel Facebook Post Monitor

Trạng thái: ✅ xong · ⏳ đang chờ xác nhận · ⬜ chưa làm

## Phase 2.1 — Laravel setup ✅ (2026-10-07, đã xác nhận)

- [x] Môi trường dev Docker: PHP 8.4 + MySQL 8.4 (`docker-compose.yml`, `docker/`)
- [x] Laravel 12, cấu hình MySQL (`utf8mb4_0900_as_ci`)
- [x] Database test riêng `crawler_mebe_test` cho PHPUnit
- [x] Migrations: `facebook_groups`, `facebook_posts`, `crawl_runs` + unique/index
- [x] Models + relationships: `FacebookGroup`, `FacebookPost`, `CrawlRun`, enum `CrawlRunStatus`
- [x] Factories cho 3 model
- [x] Tests schema/relationships (8 test), Pint, rollback/migrate, `EXPLAIN`
- [x] Sửa `.gitignore` của crawler để không ignore nhầm thư mục `storage/` của Laravel
- [x] Docs: `DATABASE.md`, `ARCHITECTURE.md`, `TODO.md`
- [x] Tách Laravel ra project riêng `/mnt/projects/projects/web_mebe`, cùng cấp với `crawler_mebe` (Docker + docs Phase 2 đi theo)

## Phase 2.2 — Group management ✅ (2026-10-07, đã xác nhận)

- [x] Đăng nhập admin bằng `ADMIN_USERNAME` / `ADMIN_PASSWORD` trong `.env` (không lưu DB), giới hạn
      5 lần sai/phút/IP, regenerate session khi đăng nhập, đăng xuất huỷ session
- [x] Middleware `admin` bảo vệ mọi trang; đổi username trong `.env` làm session cũ hết hiệu lực
- [x] `/groups`: danh sách (50/trang, số posts mỗi group), thêm, sửa, bật/tắt, xoá (có xác nhận)
- [x] Validate Group ID (5–25 chữ số), URL (chỉ host facebook.com, path `/groups/<id|tên>`), URL số phải
      khớp Group ID; URL được chuẩn hoá về `https://www.facebook.com/groups/<key>/`
- [x] Group ID không sửa được sau khi tạo (posts và lịch sử gắn với ID này)
- [x] Xoá group = soft delete, giữ posts và crawl runs; thêm lại cùng Group ID → khôi phục group cũ
- [x] Migration: `deleted_at` + khoá ngoại `RESTRICT` (DB chặn xoá cứng group còn dữ liệu)
- [x] Layout Blade (Bootstrap 5 CDN + SRI): Groups | Posts | Crawl Runs (2 mục sau chưa bật)
- [x] Tests: 29 test mới (đăng nhập, CRUD, bật/tắt, validation 11 trường hợp, soft delete, khôi phục, escape HTML)
- [x] Kiểm tra giao diện thật bằng Playwright (đăng nhập sai/đúng, validation, thêm, sửa, tắt, xoá, đăng xuất)
- [x] Nút "Crawl" thủ công trên `/groups` (làm ở Phase 2.5)

> Thứ tự đổi theo yêu cầu (2026-10-07): làm 2.4 trước 2.3 để có dữ liệu thật cho trang Posts.

## Phase 2.4 — Crawler integration ✅ (2026-10-07, đã xác nhận)

- [x] `FacebookCrawler` interface + `NodeFacebookCrawler` (Process, mảng tham số, không shell)
- [x] `PostImporter`: chuẩn hoá, bỏ dòng không hợp lệ / của group khác, dedupe trong batch + unique index,
      update bài cũ nhưng không ghi đè bằng null
- [x] `GroupCrawlService`: crawl_run pending → running → success/failed, `last_crawled_at` khi success,
      `Cache::lock` chống crawl trùng một group, log lỗi
- [x] `php artisan facebook:crawl {groupId}`
- [x] Docker: image có Node.js + Chromium (Playwright 1.63.0), mount `../crawler_mebe` → `/var/www/crawler`
- [x] Config `CRAWLER_PATH`, `CRAWLER_MAX_POSTS`, `CRAWLER_MAX_SCROLLS`, `CRAWLER_TIMEOUT_SECONDS`
- [x] Tests với crawler giả + `Process::fake` (18 test mới), không test nào truy cập Facebook
- [x] Chạy thật trong Docker (headless, session của crawler) với 2 group thật:
      `684937728619320` → 43 bài (53 s), chạy lại → 43 thấy / 2 mới, không trùng;
      `194239421495722` → 48 bài; không có session → run `failed` `LOGIN_REQUIRED`, có log
- [x] Tests không ghi vào `storage/logs/laravel.log` (`LOG_CHANNEL=null` trong `phpunit.xml`)

## Phase 2.3 — Posts ✅ (2026-10-07, đã xác nhận)

- [x] `/posts`: 50 bài/trang, mới nhất trước (bài không có ngày ở cuối), "Hiển thị 1–50 / N posts",
      phân trang giữ nguyên bộ lọc, nút "Open Facebook" (`rel="noopener noreferrer"`)
- [x] Filter: Keyword, Group (All Groups + group đã xoá), Từ ngày / Đến ngày (theo ngày giờ Việt Nam), Search, Reset
- [x] Keyword: `KeywordParser` tách theo dấu phẩy, gộp khoảng trắng, bỏ rỗng/trùng, tối đa 20;
      `FacebookPost::containingAnyKeyword()` = `( content LIKE ? OR ... )`, escape `%` `_` `\`, binding
- [x] Filter sai (ngày sai định dạng, khoảng ngày ngược, group không tồn tại) bị bỏ qua và báo trên trang
- [x] Nội dung escape khi render, bài dài rút gọn 400 ký tự + "Xem đầy đủ" (`<details>`, không JS)
- [x] Tests: 35 test mới (parser, ví dụ A/B/C/D, hoa/thường, có dấu, phrase, nhiều keyword, rỗng,
      ký tự đặc biệt, phân trang, group, ngày biên, XSS, số query cố định)
- [x] Kiểm tra trên 93 bài thật + trình duyệt (Playwright)

## Phase 2.5 — Queue ✅ (2026-10-07, chờ xác nhận)

- [x] `CrawlFacebookGroupJob` (queue `crawler`): timeout = `CRAWLER_TIMEOUT_SECONDS` + 120, `failOnTimeout`,
      `maxExceptions` 3, backoff 60 s / 300 s, `retryUntil` 2 giờ, `failed()` ghi run `failed`
- [x] Chỉ retry lỗi tạm thời (`TIMEOUT`, `CRAWLER_ERROR`, `UNEXPECTED_ERROR`); `LOGIN_REQUIRED`, `CHECKPOINT`,
      `GROUP_UNAVAILABLE`… không retry (tránh gọi Facebook liên tục khi tài khoản bị chặn)
- [x] Giới hạn đồng thời: middleware `LimitCrawlerConcurrency` (`CRAWLER_MAX_CONCURRENT`, mặc định 1),
      job chưa có slot quay lại hàng đợi sau 30 s
- [x] `CrawlDispatcher`: tạo run `pending` + dispatch; bỏ qua group đang chờ/đang crawl; run kẹt quá
      `CRAWLER_STALE_AFTER_MINUTES` (180) → `failed` `STALE`
- [x] `php artisan facebook:crawl-all`; nút **Crawl** trên `/groups` + badge Đang chờ / Đang crawl / Lỗi
- [x] `DB_QUEUE_RETRY_AFTER=900` (lớn hơn timeout job, tránh chạy trùng); service `queue` trong docker-compose
- [x] Tests: 17 test mới; chạy thật: `crawl-all` 2 group chạy lần lượt (#5: 46/35, #6: 48/11), nút Crawl (#7)

### Bổ sung theo yêu cầu (2026-10-07)

- [x] Crawler lấy link ảnh (`image_urls`, tối đa 10/bài, chỉ `https://*.fbcdn.net`); cột JSON
      `facebook_posts.image_urls`; crawl lại làm mới link, không xoá link cũ khi lần sau không có ảnh
- [x] `/posts` dạng feed cuộn dọc (card: group, tác giả, thời gian, nội dung, ảnh bên dưới; lưới tối đa 4 ảnh,
      "+N"; ảnh lazy-load, `referrerpolicy="no-referrer"`, ảnh hỏng/hết hạn tự ẩn)
- [ ] Link ảnh Facebook hết hạn (tham số `oe`): bài không được crawl lại sẽ mất ảnh. Nếu cần giữ ảnh lâu dài
      phải tải ảnh về server (chưa làm, cần quyết định: dung lượng, bản quyền/dữ liệu cá nhân)

## Nút Crawl chọn số bài + thông báo ✅ (2026-10-08, thay cho Phase 2.6, chờ xác nhận)

- [x] `/groups`: **Crawl ▾** (thẻ `<details>`, không JS) → 20 / 50 / 100 / 200 bài hoặc nhập số (1–200)
- [x] `CrawlLimits`: số scroll = max(`CRAWLER_MAX_SCROLLS`, bài/3 + 5) ≤ 100; timeout = max(`CRAWLER_TIMEOUT_SECONDS`,
      60 + scroll × 8) ≤ 1800 s (200 bài: 71 scroll, 628 s); job timeout và khoá tính theo đó
- [x] Cột `crawl_runs.max_posts`; `php artisan facebook:crawl {groupId} --posts=N`
- [x] Thông báo: `CrawlNotifications` nhớ các run đã bấm (session); đang chạy → dải "Đang chờ/Đang crawl",
      `/groups` tự tải lại mỗi 10 s (`<meta refresh>`); xong → "Crawl xong: lấy được X/N bài, Y bài mới" + link
      "Xem bài", lỗi → "Crawl thất bại: <mã lỗi>"; mỗi thông báo hiện một lần
- [x] `DB_QUEUE_RETRY_AFTER=2100` (lớn hơn job dài nhất)
- [x] Tests: 15 test mới (128 tổng); chạy thật: chọn 100 bài → 97/100, 97 bài mới, thông báo sau 141 s
- [x] Nút **Crawl all (N group active)** trên `/groups` (cùng menu chọn số bài, partial `groups/_crawl-picker`):
      đưa mọi group active vào hàng đợi, bỏ qua group đang chờ/đang crawl, mỗi group có thông báo riêng;
      `facebook:crawl-all --posts=N`. Tests: 6 test mới (134 tổng). Chạy thật (người dùng bấm, 20 bài):
      run #9 20/20 (15 mới, 21 s), run #10 20/20 (18 mới, 10 s), chạy lần lượt

## Phase 2.6 — Scheduler ⏸ (hoãn theo yêu cầu 2026-10-08)

- [ ] Crawl active groups theo interval cấu hình bằng env; service `scheduler`

## Phase 2.7 — Crawl history ⬜

- [ ] `/crawl-runs` + trang chi tiết lỗi

## Phase 2.8 — Testing + cleanup ⬜

- [ ] Review tests, validation, security, indexes, performance
- [ ] Docs: `PRD.md`, `API.md`, cập nhật `README.md`; cập nhật `crawler_mebe/docs/CRAWLER.md` phần tích hợp Laravel

## Quyết định đã chốt (2026-10-07)

1. **Tìm kiếm phân biệt dấu**: giữ, "bán" không khớp "ban"/"bạn".
2. **Đăng nhập**: một tài khoản admin khai báo trong `.env` (`ADMIN_USERNAME`, `ADMIN_PASSWORD`).
3. **Xoá group**: không xoá posts và lịch sử crawl (soft delete).

## Ghi chú vận hành

- Queue worker nạp code một lần: sau khi sửa code, chạy `docker compose restart queue`.
- `php artisan serve` đọc lại `.env` và bỏ qua biến môi trường cùng tên đặt từ bên ngoài, nên tài khoản admin
  phải được khai báo trong `.env` (không truyền bằng `-e` khi dùng `artisan serve`).
