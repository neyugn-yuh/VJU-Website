<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/health', HealthController::class)->name('health');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'index']);
Route::get('/sitemap-{group}-{locale}.xml', [SeoController::class, 'sitemap'])->where(['group' => '[a-z]+', 'locale' => '[a-z]{2}']);

Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');

Route::get('/preview/{content}', [PublicController::class, 'preview'])->middleware('auth')->name('preview');
Route::post('/comments/{content}', [CommentController::class, 'store'])->middleware('throttle:comments')->name('comments.store');

// Content bodies hard-link /storage/...; when media lives in a bucket, send those links there.
if (config('cms.media.disk') !== 'public') {
    Route::get('/storage/{path}', fn (string $path) => redirect()->away(Storage::disk(config('cms.media.disk'))->url($path), 301))->where('path', '.*');
}

// Everything else is resolved against content, taxonomy and redirects.
Route::fallback(PublicController::class);
