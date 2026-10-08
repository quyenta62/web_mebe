<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a group must keep its posts and crawl history: groups are soft-deleted,
 * and hard-deleting a group that still has posts or runs is refused by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->softDeletes();
        });

        foreach (['facebook_posts', 'crawl_runs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['facebook_group_id']);
                $table->foreign('facebook_group_id')->references('id')->on('facebook_groups')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['facebook_posts', 'crawl_runs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['facebook_group_id']);
                $table->foreign('facebook_group_id')->references('id')->on('facebook_groups')->cascadeOnDelete();
            });
        }

        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
