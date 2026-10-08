<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            // List of photo URLs on Facebook's CDN (signed, they expire; refreshed on re-crawl).
            $table->json('image_urls')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->dropColumn('image_urls');
        });
    }
};
