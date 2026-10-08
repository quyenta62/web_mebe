<?php

namespace App\Http\Controllers;

use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use App\Support\KeywordParser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PostController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $validator = Validator::make($request->query(), [
            'keyword' => ['nullable', 'string', 'max:500'],
            'group' => ['nullable', 'integer', 'exists:facebook_groups,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ], [
            'date_from.date_format' => 'Từ ngày không hợp lệ (định dạng YYYY-MM-DD).',
            'date_to.date_format' => 'Đến ngày không hợp lệ (định dạng YYYY-MM-DD).',
            'date_to.after_or_equal' => '"Đến ngày" phải sau hoặc bằng "Từ ngày".',
            'group.exists' => 'Group không tồn tại.',
        ]);
        // A filter page never redirects: invalid fields are ignored and reported.
        $filters = $validator->valid();
        $errors = $validator->errors();

        $keywords = KeywordParser::parse($filters['keyword'] ?? null);
        if (count($keywords) > KeywordParser::MAX_KEYWORDS) {
            $errors->add('keyword', 'Chỉ dùng '.KeywordParser::MAX_KEYWORDS.' keyword đầu tiên.');
            $keywords = array_slice($keywords, 0, KeywordParser::MAX_KEYWORDS);
        }

        $timezone = config('monitor.display_timezone');
        $from = isset($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay() : null;
        $toExclusive = isset($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'], $timezone)->addDay()->startOfDay() : null;

        $posts = FacebookPost::query()
            ->with('group')
            ->when($filters['group'] ?? null, fn ($query, $groupId) => $query->where('facebook_group_id', $groupId))
            ->postedBetween($from, $toExclusive)
            ->containingAnyKeyword($keywords)
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('posts.index', [
            'posts' => $posts,
            'keywords' => $keywords,
            'groups' => FacebookGroup::withTrashed()->orderBy('name')->get(['id', 'name', 'facebook_group_id', 'deleted_at']),
            'filters' => $request->only(['keyword', 'group', 'date_from', 'date_to']),
            'filterErrors' => $errors,
        ]);
    }
}
