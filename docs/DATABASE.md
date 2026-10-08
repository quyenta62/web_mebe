# Database — Facebook Post Monitor (Phase 2)

MySQL 8.4, InnoDB, charset `utf8mb4`, collation mặc định `utf8mb4_0900_as_ci`.

## Môi trường (từ 2026-10-08)

| Dùng cho | Server | Database |
| --- | --- | --- |
| App + queue (dữ liệu thật) | Aiven MySQL 8.4 (managed), TLS bắt buộc | `crawler_mebe` |
| PHPUnit | MySQL 8.4 trong Docker (`mysql`, 127.0.0.1:3307) | `crawler_mebe_test` |

- Kết nối Aiven cấu hình trong `.env` (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) và
  `MYSQL_ATTR_SSL_CA` = đường dẫn CA của project Aiven (đang để ở `storage/app/private/aiven-ca.pem`,
  gitignored; tải lại ở Aiven Console → service → CA certificate). Có CA thì Laravel bật luôn
  `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` (`config/database.php`).
- Aiven bật `sql_require_primary_key` (mọi bảng của project đều có primary key) và `sql_mode` mặc định có
  `ANSI_QUOTES`; Laravel tự đặt lại `sql_mode` cho mỗi kết nối (`strict => true`), không cần đổi gì.
- `phpunit.xml` ghim `DB_HOST=mysql` / `DB_DATABASE=crawler_mebe_test`; `tests/TestCase.php` từ chối chạy
  nếu kết nối khác (RefreshDatabase xoá mọi bảng).
- Database `crawler_mebe` trong Docker local là bản dữ liệu cũ (trước khi chuyển), không còn được app dùng.
- Độ trễ Aiven ~50 ms/truy vấn + ~0,3 s mở kết nối TLS: mỗi trang ~0,7–1,1 s.
- Sessions, cache (khoá crawl) và queue jobs cũng nằm trên Aiven, nên nhiều máy dùng chung database vẫn
  chia sẻ khoá chống crawl trùng.
Migrations nằm ở `database/migrations/` (trong project `web_mebe`).

## Collation và tìm kiếm tiếng Việt

`utf8mb4_0900_as_ci` = **c**ase-**i**nsensitive (không phân biệt hoa thường) và **a**ccent-**s**ensitive
(phân biệt dấu). Cột `facebook_posts.content` được đặt collation này tường minh vì keyword search chạy
`LIKE` trên cột đó.

| Keyword | Khớp | Không khớp |
| --- | --- | --- |
| `BÁN` | "Bán xe đẩy" | "bạn ơi", "ban công" |
| `đồ cho bé` | "ĐỒ CHO BÉ" | — |
| `ban` | "ban công" | "Bán xe đẩy" |

Nếu dùng `utf8mb4_unicode_ci` (mặc định của Laravel) thì "bán" sẽ khớp cả "bạn" và "ban", gây nhiều
kết quả sai. Hệ quả của lựa chọn này: người dùng phải gõ đúng dấu ("bán", không phải "ban"). Đây là
quyết định cần xác nhận trước Phase 2.3.

## Bảng

### `facebook_groups`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `facebook_group_id` | varchar(64), **unique** | ID group của Facebook (chuỗi số), không phải khoá ngoại |
| `name` | varchar(255) null | |
| `url` | varchar(500) | |
| `is_active` | boolean, default true, index | `crawl-all` chỉ lấy group active |
| `last_crawled_at` | timestamp null | |
| `created_at`, `updated_at` | timestamp | |
| `deleted_at` | timestamp null | Soft delete (Phase 2.2) |

### `facebook_posts`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `facebook_group_id` | bigint unsigned, FK → `facebook_groups.id`, `ON DELETE RESTRICT` | Tên theo convention Laravel cho model `FacebookGroup` |
| `facebook_post_id` | varchar(64) | ID bài viết của Facebook (chuỗi số) |
| `author_name` | varchar(255) null | |
| `content` | mediumtext null, `utf8mb4_0900_as_ci` | Bài Facebook tối đa ~63k ký tự → vượt 64KB của `TEXT` khi là UTF-8 |
| `image_urls` | json null | Danh sách link ảnh `https://*.fbcdn.net` (≤ 10). Link có chữ ký, hết hạn; crawl lại sẽ làm mới |
| `post_url` | varchar(500) null | |
| `posted_at` | timestamp null, index | Lưu UTC |
| `created_at`, `updated_at` | timestamp | |

Index:

- `UNIQUE (facebook_group_id, facebook_post_id)`: chống trùng bài giữa các lần crawl.
- `(facebook_group_id, posted_at)`: lọc theo group + sắp xếp mới nhất (đã kiểm tra bằng `EXPLAIN`:
  dùng index, backward index scan, không filesort).
- `(posted_at)`: lọc theo ngày / sắp xếp khi không lọc group.

### `crawl_runs`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `facebook_group_id` | bigint unsigned, FK → `facebook_groups.id`, `ON DELETE RESTRICT` | |
| `status` | varchar(20), default `pending`, index | `pending`, `running`, `success`, `failed` (`App\Enums\CrawlRunStatus`) |
| `max_posts` | smallint unsigned null | Số bài mới nhất được yêu cầu (null = mặc định `CRAWLER_MAX_POSTS`) |
| `posts_found` | int unsigned, default 0 | |
| `posts_created` | int unsigned, default 0 | |
| `error_message` | text null | |
| `started_at`, `finished_at` | timestamp null | |
| `created_at`, `updated_at` | timestamp | |

## Quan hệ (Eloquent)

```text
FacebookGroup hasMany FacebookPost   ($group->posts)
FacebookGroup hasMany CrawlRun       ($group->crawlRuns)
FacebookPost  belongsTo FacebookGroup ($post->group)
CrawlRun      belongsTo FacebookGroup ($run->group)
```

Scope: `FacebookGroup::active()`. `FacebookPost::group()` và `CrawlRun::group()` dùng `withTrashed()` để
vẫn hiện tên group đã xoá.

## Xoá group

Xoá group là **soft delete** (`deleted_at`): group biến khỏi `/groups` và không được crawl nữa, còn posts
và crawl runs được giữ nguyên. Khoá ngoại `ON DELETE RESTRICT` chặn việc xoá cứng một group còn posts
hoặc crawl runs. Thêm lại cùng Facebook Group ID sẽ khôi phục group cũ, nên posts cũ gắn lại và chống
trùng vẫn hoạt động.

## Thời gian

Laravel chạy timezone `UTC`; mọi timestamp lưu UTC. Hiển thị và lọc theo ngày sẽ quy đổi sang
`Asia/Ho_Chi_Minh` (Phase 2.3).

## Database cho test

PHPUnit dùng database MySQL riêng `crawler_mebe_test` (tạo bởi `docker/mysql/init/01-test-database.sql`),
không dùng SQLite: `LIKE` của SQLite chỉ không phân biệt hoa thường với ký tự ASCII nên không kiểm thử
được tiếng Việt.

## Hiệu năng tìm kiếm keyword (Phase 2.3)

Tìm keyword dùng `content LIKE '%…%'` (OR giữa các keyword), luôn qua binding. `LIKE` có `%` ở đầu không
dùng được index, nên MySQL quét toàn bảng (`EXPLAIN`: `type=ALL`) rồi sắp xếp. Lọc group/ngày và sắp xếp
khi không có keyword vẫn dùng index.

Với vài chục nghìn bài, cách này vẫn đủ nhanh. **Đề xuất khi dữ liệu lớn (chưa triển khai, cần xác nhận
vì thay đổi ngữ nghĩa tìm kiếm):** thêm `FULLTEXT(content)` và dùng
`MATCH(content) AGAINST('"xe đẩy" pass bán' IN BOOLEAN MODE)`. Khác biệt: FULLTEXT khớp theo **từ**,
không theo chuỗi con ("pass" không còn khớp "passs"), có độ dài từ tối thiểu (`innodb_ft_min_token_size`,
mặc định 3, nên "xe" bị bỏ qua nếu đứng riêng) và stopword; độ phân biệt dấu theo collation của cột.
