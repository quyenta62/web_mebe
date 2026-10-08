# Architecture — Facebook Post Monitor (Phase 2)

## Cấu trúc thư mục

Hai project độc lập, nằm cùng cấp:

```text
/mnt/projects/projects/
├── crawler_mebe/                     # Phase 1: crawler Node.js + TypeScript + Playwright (giữ nguyên)
│   ├── src/, test/, package.json
│   ├── storage/facebook-state.json   # Facebook session (gitignored)
│   ├── output/                       # Output JSON của crawler (gitignored)
│   └── docs/CRAWLER.md
└── web_mebe/                         # Phase 2: Laravel 12 app (Blade, Eloquent, Queue, Scheduler)
    ├── app/, database/, resources/, routes/, tests/ ...
    ├── docker/                       # Dockerfile PHP 8.4, script khởi tạo MySQL
    ├── docker-compose.yml            # Môi trường dev: app (PHP 8.4) + mysql (8.4)
    └── docs/                         # ARCHITECTURE, DATABASE, TODO (+ PRD, API ở Phase 2.8)
```

Laravel gọi crawler qua `CRAWLER_PATH` (mặc định `../crawler_mebe`; trong Docker là `/var/www/crawler`).
Crawler không biết gì về Laravel. Phiên bản Playwright trong Dockerfile (`PLAYWRIGHT_VERSION`) phải khớp
`crawler_mebe/package.json`.

## Luồng web

```text
Browser → Laravel Blade → Controller → Eloquent → MySQL
```

Không có REST API riêng, không SPA. Browser không bao giờ gọi crawler trực tiếp.

### Đăng nhập

Một tài khoản admin duy nhất, khai báo trong `.env` (`ADMIN_USERNAME`, `ADMIN_PASSWORD`), không lưu DB.
`AuthController` so sánh bằng `hash_equals`, giới hạn 5 lần sai/phút/IP, regenerate session khi đăng nhập.
Middleware `admin` (`App\Http\Middleware\EnsureAdmin`) bảo vệ mọi route trừ `/login`. Khi một trong hai
biến rỗng thì không ai đăng nhập được.

### Groups (Phase 2.2)

| Route | Việc |
| --- | --- |
| `GET /groups` | Danh sách (50/trang) |
| `GET /groups/create`, `POST /groups` | Thêm (thêm lại ID đã xoá → khôi phục) |
| `GET /groups/{id}/edit`, `PUT /groups/{id}` | Sửa tên, URL, active (không sửa Group ID) |
| `PATCH /groups/{id}/toggle` | Bật/tắt |
| `DELETE /groups/{id}` | Soft delete, giữ posts và crawl runs |

Validation nằm trong `App\Http\Requests\GroupRequest`; parse/chuẩn hoá URL trong `App\Support\FacebookGroupUrl`.

### Posts (Phase 2.3)

`GET /posts?keyword=pass,+bán&group=<id>&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&page=N`

```text
PostController@index
  → Validator (bộ lọc sai bị bỏ qua + báo lỗi, không redirect)
  → KeywordParser::parse("pass, bán, xe đẩy") = ["pass", "bán", "xe đẩy"]
  → FacebookPost::with('group')
        ->where(facebook_group_id)                       nếu chọn group
        ->postedBetween(from VN 00:00, to+1 ngày VN 00:00) quy đổi sang UTC
        ->containingAnyKeyword(keywords)                 ( content LIKE ? OR content LIKE ? ... )
        ->orderByDesc(posted_at)->orderByDesc(id)->paginate(50)->withQueryString()
```

Mỗi lần tải trang: 1 query đếm, 1 query lấy trang, 1 query eager-load group, 1 query dropdown group.

## Luồng crawl (Phase 2.4)

```text
php artisan facebook:crawl {groupId}
   → GroupCrawlService      tạo crawl_run (pending → running), khoá theo group (Cache::lock)
   → NodeFacebookCrawler    Process: node dist/index.js <groupId> --headless --max-posts N
                            --max-scrolls M --timeout T --output storage/app/crawler/<uuid>.json
   → Playwright → Facebook  (crawler Phase 1, dùng storage/facebook-state.json của crawler)
   → PostImporter           chuẩn hoá, bỏ dòng không hợp lệ, insert bài mới / update bài cũ
   → crawl_run success|failed, facebook_groups.last_crawled_at (chỉ khi success)
```

| Thành phần | File | Trách nhiệm |
| --- | --- | --- |
| `FacebookCrawler` | `app/Services/Crawler/FacebookCrawler.php` | Interface; tests bind `Tests\Fakes\FakeFacebookCrawler` |
| `NodeFacebookCrawler` | `app/Services/Crawler/NodeFacebookCrawler.php` | Chạy crawler bằng `Process` với mảng tham số (không qua shell), Group ID chỉ nhận chữ số; map exit code → mã lỗi; đọc rồi xoá file JSON tạm |
| `PostImporter` | `app/Services/Crawler/PostImporter.php` | `post_id` `"<group>_<post>"` → `facebook_post_id`; bỏ dòng của group khác; URL chỉ giữ `https://…facebook.com/`; `posted_at` → UTC; null không ghi đè giá trị cũ |
| `GroupCrawlService` | `app/Services/Crawler/GroupCrawlService.php` | Vòng đời `crawl_runs`, khoá chống crawl trùng một group, log |
| `facebook:crawl` | `app/Console/Commands/CrawlFacebookGroup.php` | Lệnh Artisan |

Mã lỗi lưu trong `crawl_runs.error_message` (dạng `CODE: message`):

| Mã | Nguồn |
| --- | --- |
| `LOGIN_REQUIRED` | crawler exit 3: chưa đăng nhập, session hết hạn, Facebook hiện dialog đăng nhập |
| `CHECKPOINT` | crawler exit 4: checkpoint / CAPTCHA |
| `GROUP_UNAVAILABLE` | crawler exit 5: group private, bị xoá, không có quyền |
| `TIMEOUT` | crawler exit 6, hoặc process bị kill sau `CRAWLER_TIMEOUT_SECONDS` + 60 s |
| `INVALID_INPUT` | crawler exit 2 |
| `CRAWLER_NOT_BUILT` | không có `dist/index.js` trong `CRAWLER_PATH` |
| `ALREADY_RUNNING` | group đang được crawl ở tiến trình khác |
| `CRAWLER_ERROR` / `UNEXPECTED_ERROR` | lỗi khác |

Khi crawl thất bại nhưng crawler đã đọc được một phần bài, các bài đó vẫn được lưu (`posts_found`,
`posts_created` phản ánh phần đã lưu), còn run vẫn là `failed`.

Cấu hình (`config/crawler.php`, `.env`): `CRAWLER_PATH`, `CRAWLER_NODE_BINARY`, `CRAWLER_MAX_POSTS` (50),
`CRAWLER_MAX_SCROLLS` (15), `CRAWLER_TIMEOUT_SECONDS` (300).

Laravel không đọc file session Facebook; chỉ crawler đọc nó. Session tạo bằng `npm run login` trong
`crawler_mebe` trên máy host (cần cửa sổ browser).

### Crawl history (Phase 2.7)

| Route | Việc |
| --- | --- |
| `GET /crawl-runs` | Danh sách run (50/trang, mới nhất trước, eager-load group) |
| `GET /crawl-runs/{id}` | Chi tiết một run, lỗi đầy đủ (`CrawlRun::errorCode()`, `durationSeconds()`) |

## Queue (Phase 2.5)

```text
/groups nút Crawl ─┐
facebook:crawl-all ┴→ CrawlDispatcher: crawl_run pending + dispatch CrawlFacebookGroupJob (queue "crawler")
                      (bỏ qua group đang pending/running; run kẹt > 180 phút → failed STALE)
queue worker ───────→ LimitCrawlerConcurrency (tối đa CRAWLER_MAX_CONCURRENT browser, mặc định 1)
                   → CrawlFacebookGroupJob → GroupCrawlService (như Phase 2.4)
                      lỗi tạm thời → retry (backoff 60 s, 300 s; tối đa 3 lỗi); lỗi chặn → failed, không retry
                      hết lượt / timeout → failed() → crawl_run failed
```

Một group lỗi không ảnh hưởng group khác: mỗi group là một job riêng. `facebook:crawl {groupId}` vẫn chạy
đồng bộ (không qua queue) để thử nhanh. `DB_QUEUE_RETRY_AFTER` (900) phải lớn hơn timeout của job.

### Crawl theo số bài + thông báo (2026-10-08)

```text
/groups: Crawl ▾ (từng group) hoặc Crawl all ▾ (mọi group active) → 20 | 50 | 100 | 200 | số khác (1–200)
  POST /groups/{id}/crawl max_posts=N   |   POST /groups/crawl-all max_posts=N (lặp qua group active)
    → CrawlDispatcher::queue(group, N): crawl_run (pending, max_posts=N) + job (timeout theo CrawlLimits)
    → CrawlNotifications::watch(session, run)
  Mỗi trang (view composer của layouts.app) → CrawlNotifications::pull(session):
    run chưa xong → dải "Đang chờ/Đang crawl" (+ <meta refresh 10> riêng trang /groups)
    run đã xong   → thông báo một lần: "Crawl xong: X/N bài, Y bài mới" hoặc "Crawl thất bại: <lỗi>"
```

`CrawlLimits::forPosts(N)` là nơi duy nhất quy đổi số bài → `--max-posts`, `--max-scrolls`, `--timeout`.

## Ảnh và giao diện feed

Crawler xuất `image_urls` (link CDN Facebook, có chữ ký, hết hạn). Laravel chỉ lưu link `https://*.fbcdn.net`
và render bằng `<img loading="lazy" referrerpolicy="no-referrer">`; ảnh lỗi tự ẩn (`onerror`, JS duy nhất của
trang). `/posts` hiển thị dạng feed cuộn dọc (`resources/views/posts/_card.blade.php`), vẫn phân trang 50 bài.

## Môi trường dev

Máy dev không có PHP/MySQL và không có quyền sudo, nên dùng Docker:

| Service | Image | Port (chỉ 127.0.0.1) |
| --- | --- | --- |
| `app` | `docker/php/Dockerfile` (php:8.4-cli + pdo_mysql, intl, zip, bcmath, pcntl, Composer, Node.js, Chromium của Playwright 1.63.0) | 8090 → `php artisan serve` |
| `queue` | cùng image với `app` | — (`php artisan queue:work --queue=crawler,default`) |
| `mysql` | `mysql:8.4` | 3307 → 3306 |

Container `app` chạy với UID/GID của user host (`HOST_UID`/`HOST_GID`, mặc định 1000) để file tạo ra
không thuộc root. Thư mục `web_mebe/` được mount vào `/var/www/web`, `../crawler_mebe` vào `/var/www/crawler`. Các lệnh `docker compose` chạy từ thư mục `web_mebe/`.

Đây chỉ là môi trường dev; code Laravel không phụ thuộc Docker. Chạy trên host có PHP 8.4 + MySQL 8 chỉ
cần đổi `DB_HOST`/`DB_PORT` trong `web_mebe/.env`.
