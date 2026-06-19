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
        Schema::create('profile_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->integer('followers_count');
            $table->integer('following_count');
            $table->integer('posts_count');
            $table->timestampTz('created_at')->useCurrent();
        });

        // Time-series query index (profile_id, created_at DESC)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX profile_snapshots_history_idx ON profile_snapshots (profile_id, created_at DESC)');
        } else {
            Schema::table('profile_snapshots', function (Blueprint $table) {
                $table->index(['profile_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
