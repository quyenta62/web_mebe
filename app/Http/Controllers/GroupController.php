<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroupRequest;
use App\Models\FacebookGroup;
use App\Services\Crawler\CrawlDispatcher;
use App\Services\Crawler\CrawlLimits;
use App\Support\CrawlNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        $groups = FacebookGroup::query()
            ->withCount('posts')
            ->with('latestCrawlRun')
            ->orderByDesc('id')
            ->paginate(50);

        $activeCount = FacebookGroup::active()->count();

        return view('groups.index', compact('groups', 'activeCount'));
    }

    public function create(): View
    {
        return view('groups.create', ['group' => new FacebookGroup(['is_active' => true])]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $data = $request->groupData();

        // Re-adding a deleted group restores it, so its old posts and history come back with it.
        $group = FacebookGroup::onlyTrashed()->where('facebook_group_id', $data['facebook_group_id'])->first();
        if ($group) {
            $group->restore();
            $group->update($data);
            $message = "Đã khôi phục group {$group->facebook_group_id} (kèm posts và lịch sử crawl cũ).";
        } else {
            $group = FacebookGroup::create($data);
            $message = "Đã thêm group {$group->facebook_group_id}.";
        }

        return redirect()->route('groups.index')->with('status', $message);
    }

    public function edit(FacebookGroup $group): View
    {
        return view('groups.edit', compact('group'));
    }

    public function update(GroupRequest $request, FacebookGroup $group): RedirectResponse
    {
        $group->update($request->groupData());

        return redirect()->route('groups.index')->with('status', "Đã cập nhật group {$group->facebook_group_id}.");
    }

    public function toggle(FacebookGroup $group): RedirectResponse
    {
        $group->update(['is_active' => ! $group->is_active]);
        $state = $group->is_active ? 'bật' : 'tắt';

        return redirect()->route('groups.index')->with('status', "Đã {$state} group {$group->facebook_group_id}.");
    }

    /** Queues a crawl; the browser never runs the crawler itself. */
    public function crawl(Request $request, FacebookGroup $group, CrawlDispatcher $dispatcher): RedirectResponse
    {
        $run = $dispatcher->queue($group, $this->validatedPostCount($request));
        if ($run === null) {
            return redirect()->route('groups.index')
                ->with('status', "Group {$group->facebook_group_id} đang chờ hoặc đang được crawl.");
        }

        CrawlNotifications::watch($request->session(), $run);

        return redirect()->route('groups.index')->with(
            'status',
            "Đã bắt đầu crawl {$run->max_posts} bài mới nhất của group {$group->facebook_group_id}. Sẽ có thông báo khi xong."
        );
    }

    /** Queues every active group; the queue worker crawls them one at a time. */
    public function crawlAll(Request $request, CrawlDispatcher $dispatcher): RedirectResponse
    {
        $maxPosts = $this->validatedPostCount($request);
        $groups = FacebookGroup::active()->orderBy('id')->get();
        if ($groups->isEmpty()) {
            return redirect()->route('groups.index')->with('status', 'Không có group active nào để crawl.');
        }

        $queued = 0;
        foreach ($groups as $group) {
            $run = $dispatcher->queue($group, $maxPosts);
            if ($run !== null) {
                CrawlNotifications::watch($request->session(), $run);
                $queued++;
            }
        }

        $skipped = $groups->count() - $queued;
        $message = "Đã bắt đầu crawl {$maxPosts} bài mới nhất cho {$queued}/{$groups->count()} group active"
            .($skipped > 0 ? " (bỏ qua {$skipped} group đang chờ/đang crawl)" : '')
            .'. Các group chạy lần lượt; mỗi group có thông báo khi xong.';

        return redirect()->route('groups.index')->with('status', $message);
    }

    private function validatedPostCount(Request $request): int
    {
        $validated = $request->validate([
            'max_posts' => ['required', 'integer', 'min:'.CrawlLimits::MIN_POSTS, 'max:'.CrawlLimits::MAX_POSTS],
        ], [
            'max_posts.*' => 'Số bài cần crawl phải từ '.CrawlLimits::MIN_POSTS.' đến '.CrawlLimits::MAX_POSTS.'.',
        ]);

        return (int) $validated['max_posts'];
    }

    public function destroy(FacebookGroup $group): RedirectResponse
    {
        // Soft delete: posts and crawl runs are kept.
        $group->delete();

        return redirect()->route('groups.index')
            ->with('status', "Đã xoá group {$group->facebook_group_id}. Posts và lịch sử crawl vẫn được giữ.");
    }
}
