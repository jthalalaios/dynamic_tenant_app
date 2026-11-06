<?php

use App\Authorization\PermissionCache;
use App\Authorization\PermissionRegistry;
use App\Authorization\PermissionSynchronizer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->string('description', 240)->nullable();
            $table->string('icon', 32)->default('comments');
            // The tenant's default room can be edited but never deleted
            $table->boolean('is_default')->default(false);
            // Locked room: only administrators may post ("mute everyone")
            $table->boolean('is_locked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // History is paged backwards by id within a room
            $table->index(['room_id', 'id']);
        });

        // room_id NULL = muted in every room of the tenant; expires_at NULL = until lifted
        Schema::create('chat_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('muted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 240)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'room_id']);
            $table->index(['tenant_id', 'expires_at']);
        });

        // Per user read marker, drives the unread badges
        Schema::create('chat_room_reads', function (Blueprint $table) {
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['room_id', 'user_id']);
        });

        $this->grantChatPermissionsToExistingRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_reads');
        Schema::dropIfExists('chat_mutes');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_rooms');
    }

    /**
     * Existing tenants' system roles get the chat.* grants config/permissions.php
     * gives them. Additive only, so permissions an administrator customised on a
     * system role are kept (unlike `permissions:sync --roles`, which resets them).
     */
    private function grantChatPermissionsToExistingRoles(): void
    {
        app(PermissionSynchronizer::class)->syncPermissions();

        $registry = app(PermissionRegistry::class);
        $chatIds = Permission::query()->where('name', 'like', 'chat.%')->pluck('id', 'name');

        Tenant::query()->each(function (Tenant $tenant) use ($registry, $chatIds) {
            foreach ($registry->defaultRoles($tenant->type) as $slug => $definition) {
                $grant = collect($definition['permissions'])
                    ->filter(fn (string $name) => str_starts_with($name, 'chat.'))
                    ->map(fn (string $name) => $chatIds[$name] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if ($grant === []) {
                    continue;
                }

                Role::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('slug', $slug)
                    ->where('is_system', true)
                    ->first()
                    ?->permissions()
                    ->syncWithoutDetaching($grant);
            }

            app(PermissionCache::class)->flushTenant($tenant->id);
        });
    }
};
