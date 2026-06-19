<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('platform')->default('instagram');
            $table->integer('followers_count')->default(0);
            $table->integer('following_count')->default(0);
            $table->integer('posts_count')->default(0);
            $table->text('bio')->nullable();
            $table->text('profile_picture_url')->nullable();
            $table->string('status')->default('pending'); // pending, fetching, fetched, failed
            $table->text('error_message')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampsTz();
        });

        // Enforce unique lower-case username at DB level
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX profiles_username_lower_idx ON profiles (LOWER(username))');
            DB::statement('CREATE INDEX profiles_status_refreshed_idx ON profiles (status, last_refreshed_at DESC) INCLUDE (username)');
        } else {
            // SQLite does not support lower() in indexes directly or INCLUDE clause in this way, so simple index
            Schema::table('profiles', function (Blueprint $table) {
                $table->unique('username');
                $table->index(['status', 'last_refreshed_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
