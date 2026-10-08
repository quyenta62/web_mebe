<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_group_id')->constrained('facebook_groups')->cascadeOnDelete();
            // pending | running | success | failed (App\Enums\CrawlRunStatus)
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('posts_found')->default(0);
            $table->unsignedInteger('posts_created')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
    }
};
