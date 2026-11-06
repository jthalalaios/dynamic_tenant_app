<?php

namespace Tests;

use App\Authorization\PermissionSynchronizer;
use App\Enums\TenantType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tenant with its system roles provisioned (requires synced permissions).
     */
    protected function createTenant(TenantType $type = TenantType::Doctor, array $attributes = []): Tenant
    {
        $sync = app(PermissionSynchronizer::class);
        $sync->syncPermissions();

        $tenant = Tenant::factory()->create(['type' => $type, ...$attributes]);
        $sync->provisionTenantRoles($tenant);

        return $tenant;
    }

    protected function createUser(Tenant $tenant, string ...$roles): User
    {
        return User::factory()->for($tenant)->withRoles(...$roles)->create();
    }

    /**
     * Authenticate on the API guard without issuing a real token.
     */
    protected function actingAsUser(User $user): static
    {
        Passport::actingAs($user, [], 'api');

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Resolving the passport guard needs a key pair, even for Passport::actingAs()
        Passport::loadKeysFrom(self::passportKeyDirectory());
    }

    /**
     * Allow real token issuing (login tests) by creating a personal access client.
     */
    protected function enableTokenIssuing(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal access client', 'users');
    }

    /**
     * Throwaway RSA keys generated once per machine, outside storage/.
     */
    private static function passportKeyDirectory(): string
    {
        $dir = sys_get_temp_dir().'/passport-test-keys';

        if (! is_file("{$dir}/oauth-private.key")) {
            @mkdir($dir, 0700, true);
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            file_put_contents("{$dir}/oauth-private.key", $private);
            file_put_contents("{$dir}/oauth-public.key", openssl_pkey_get_details($key)['key']);
            chmod("{$dir}/oauth-private.key", 0600);
            chmod("{$dir}/oauth-public.key", 0600);
        }

        return $dir;
    }
}
