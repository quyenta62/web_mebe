<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_groups', function (Blueprint $table) {
            $table->id();
            // Facebook's own group ID (numeric string), not a foreign key.
            $table->string('facebook_group_id', 64)->unique();
            $table->string('name')->nullable();
            $table->string('url', 500);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_groups');
    }
};
