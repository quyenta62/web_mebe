<?php

use App\Http\Controllers\CrawlRunController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Single-user local tool: no login. The app port is bound to 127.0.0.1 only (docker-compose.yml);
// CSRF protection of the "web" middleware group still applies to every form.

Route::redirect('/', '/groups');

Route::post('/groups/crawl-all', [GroupController::class, 'crawlAll'])->name('groups.crawl-all');
Route::resource('groups', GroupController::class)->except('show');
Route::patch('/groups/{group}/toggle', [GroupController::class, 'toggle'])->name('groups.toggle');
Route::post('/groups/{group}/crawl', [GroupController::class, 'crawl'])->name('groups.crawl');

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

Route::get('/crawl-runs', [CrawlRunController::class, 'index'])->name('crawl-runs.index');
Route::get('/crawl-runs/{crawlRun}', [CrawlRunController::class, 'show'])->name('crawl-runs.show');
