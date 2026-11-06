<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat rooms are scoped by audience inside each workspace (everyone / admins /
 * roles) instead of one cross-workspace room: the clinic and the shop each have
 * their own "Global" room (their default room).
 */
return new class extends Migration
{
    public function up(): void
    {
        // The cross-workspace room goes, with its messages, mutes and read markers (cascade)
        DB::table('chat_rooms')->where('is_global', true)->delete();
        DB::statement('DROP INDEX chat_rooms_one_global');

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
            // everyone | admins | roles (see chat_room_role)
            $table->string('audience', 16)->default('everyone')->after('is_locked');
        });

        Schema::create('chat_room_role', function (Blueprint $table) {
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['room_id', 'role_id']);
        });

        // Each workspace's default room becomes its "Global" room
        DB::table('chat_rooms')
            ->where('is_default', true)
            ->whereIn('name', ['General', 'Γενικά'])
            ->update(['name' => 'Global', 'icon' => 'globe', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('chat_rooms')
            ->where('is_default', true)
            ->where('name', 'Global')
            ->update(['name' => 'General', 'icon' => 'hashtag', 'updated_at' => now()]);

        Schema::dropIfExists('chat_room_role');

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn('audience');
        });

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
            $table->boolean('is_global')->default(false)->after('is_default');
        });

        DB::statement('CREATE UNIQUE INDEX chat_rooms_one_global ON chat_rooms (is_global) WHERE is_global');
    }
};
