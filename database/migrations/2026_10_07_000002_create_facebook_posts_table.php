<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();
            // Foreign key to facebook_groups.id (Laravel convention for FacebookGroup).
            $table->foreignId('facebook_group_id')->constrained('facebook_groups')->cascadeOnDelete();
            $table->string('facebook_post_id', 64);
            $table->string('author_name')->nullable();
            // Keyword search runs LIKE on this column: case-insensitive, accent-sensitive.
            $table->mediumText('content')->collation('utf8mb4_0900_as_ci')->nullable();
            $table->string('post_url', 500)->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();

            // One row per Facebook post within a group: prevents duplicates across crawls.
            $table->unique(['facebook_group_id', 'facebook_post_id']);
            // Group filter + newest-first listing.
            $table->index(['facebook_group_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
