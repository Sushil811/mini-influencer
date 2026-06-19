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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS profiles_username_lower_idx');
            DB::statement('CREATE UNIQUE INDEX profiles_username_platform_lower_idx ON profiles (LOWER(username), platform)');
        } else {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->unique(['username', 'platform']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS profiles_username_platform_lower_idx');
            DB::statement('CREATE UNIQUE INDEX profiles_username_lower_idx ON profiles (LOWER(username))');
        } else {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropUnique(['username', 'platform']);
                $table->unique(['username']);
            });
        }
    }
};
