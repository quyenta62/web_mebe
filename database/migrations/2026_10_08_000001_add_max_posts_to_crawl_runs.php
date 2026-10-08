<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            // Number of most recent posts requested for this crawl (null = default).
            $table->unsignedSmallInteger('max_posts')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->dropColumn('max_posts');
        });
    }
};
