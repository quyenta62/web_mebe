<?php

namespace App\Http\Controllers;

use App\Models\CrawlRun;
use Illuminate\View\View;

class CrawlRunController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): View
    {
        $runs = CrawlRun::query()
            ->with('group')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return view('crawl-runs.index', compact('runs'));
    }

    public function show(CrawlRun $crawlRun): View
    {
        $crawlRun->load('group');

        return view('crawl-runs.show', ['run' => $crawlRun]);
    }
}
