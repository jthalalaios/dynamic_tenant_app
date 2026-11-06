<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One platform-wide "Global" room shared by every organization.
 *
 * It belongs to no tenant (tenant_id NULL, is_global = true). Its messages keep
 * the author's tenant_id, so moderation stays inside each organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
            $table->boolean('is_global')->default(false)->after('is_default');
        });

        // At most one global room, enforced by the database
        DB::statement('CREATE UNIQUE INDEX chat_rooms_one_global ON chat_rooms (is_global) WHERE is_global');

        DB::table('chat_rooms')->insert([
            'tenant_id' => null,
            'name' => 'Global',
            'slug' => 'global',
            'description' => null,
            'icon' => 'globe',
            'is_default' => false,
            'is_global' => true,
            'is_locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Its messages, mutes and read markers go with it (cascading foreign keys)
        DB::table('chat_rooms')->where('is_global', true)->delete();
        DB::statement('DROP INDEX chat_rooms_one_global');

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn('is_global');
            $table->foreignId('tenant_id')->nullable(false)->change();
        });
    }
};
