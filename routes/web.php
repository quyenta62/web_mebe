<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/groups');

    Route::post('/groups/crawl-all', [GroupController::class, 'crawlAll'])->name('groups.crawl-all');
    Route::resource('groups', GroupController::class)->except('show');
    Route::patch('/groups/{group}/toggle', [GroupController::class, 'toggle'])->name('groups.toggle');
    Route::post('/groups/{group}/crawl', [GroupController::class, 'crawl'])->name('groups.crawl');

    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
});
