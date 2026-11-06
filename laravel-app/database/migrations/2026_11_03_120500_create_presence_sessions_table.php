<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A user is "online" while they have an open session with a recent heartbeat
        Schema::create('presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['tenant_id', 'ended_at']);
        });

        // At most one open session per user, enforced by the database where partial indexes exist
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX presence_sessions_one_open_per_user ON presence_sessions (user_id) WHERE ended_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_sessions');
    }
};
