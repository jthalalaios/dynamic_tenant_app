<?php

namespace Database\Seeders;

use App\Authorization\PermissionSynchronizer;
use App\Enums\AppointmentReason;
use App\Enums\AppointmentStatus;
use App\Enums\TenantType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Marketplace\OrderService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo clinic + demo shop. Every account's password is "password".
 */
class DemoTenantSeeder extends Seeder
{
    public function __construct(
        private readonly PermissionSynchronizer $synchronizer,
        private readonly TenantContext $context,
    ) {}

    public function run(): void
    {
        $clinic = $this->tenant('Acme Clinic', 'acme-clinic', TenantType::Doctor, 'Europe/Athens');
        $clinicUsers = [
            // A doctor who is also an administrator, a doctor who is not, and front desk staff
            $this->user($clinic, 'Dr. Alice Admin', 'admin@clinic.test', ['admin', 'doctor']),
            $this->user($clinic, 'Dr. Bob House', 'doctor@clinic.test', ['doctor']),
            $this->user($clinic, 'Rita Reception', 'reception@clinic.test', ['receptionist']),
        ];

        $this->context->runAs($clinic, fn () => $this->seedClinic($clinicUsers[1]));

        $shop = $this->tenant('Acme Market', 'acme-market', TenantType::Marketplace, 'Europe/Athens');
        $this->user($shop, 'Mia Admin', 'admin@market.test', ['admin']);
        $manager = $this->user($shop, 'Max Manager', 'manager@market.test', ['manager']);
        $this->user($shop, 'Sam Sales', 'sales@market.test', ['sales']);

        $this->context->runAs($shop, fn () => $this->seedShop($manager));

        $this->call(ChatDemoSeeder::class);
    }

    private function tenant(string $name, string $slug, TenantType $type, string $timezone): Tenant
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'type' => $type, 'timezone' => $timezone, 'locale' => 'en'],
        );

        $this->synchronizer->provisionTenantRoles($tenant);

        return $tenant;
    }

    /**
     * @param  list<string>  $roles
     */
    private function user(Tenant $tenant, string $name, string $email, array $roles): User
    {
        $user = User::query()->withoutGlobalScopes()->firstOrCreate(
            ['email' => $email],
            ['tenant_id' => $tenant->id, 'name' => $name, 'password' => 'password', 'email_verified_at' => now()],
        );

        $user->syncRoles(
            Role::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('slug', $roles)->pluck('id')->all()
        );

        return $user;
    }

    private function seedClinic(User $doctor): void
    {
        if (Patient::query()->exists()) {
            return;
        }

        $patients = Patient::factory()->count(12)->create(['tenant_id' => $this->context->id()]);
        $startOfDay = now('Europe/Athens')->setTime(9, 0);
        $reasons = AppointmentReason::cases();

        foreach ($patients->take(8)->values() as $i => $patient) {
            Appointment::factory()->create([
                'tenant_id' => $this->context->id(),
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => $startOfDay->copy()->addMinutes(30 * $i)->utc(),
                'reason' => $reasons[$i % count($reasons)],
                'status' => $i < 2 ? AppointmentStatus::Completed : AppointmentStatus::Scheduled,
            ]);
        }
    }

    private function seedShop(User $manager): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $catalogue = [
            ['AUR-001', 'Aurora Headphones', 'audio', 12900, 24],
            ['PUL-002', 'Pulse Smartwatch', 'wearables', 19900, 6],
            ['NIM-003', 'Nimbus Desk Lamp', 'home', 4900, 41],
            ['VOY-004', 'Voyager Backpack', 'accessories', 8900, 0],
            ['ECH-005', 'Echo Bluetooth Speaker', 'audio', 7900, 15],
            ['ORB-006', 'Orbit Fitness Band', 'wearables', 5900, 32],
        ];

        $products = collect($catalogue)->map(fn (array $p) => Product::query()->create([
            'sku' => $p[0], 'name' => $p[1], 'category' => $p[2], 'price_cents' => $p[3], 'stock' => $p[4], 'currency' => 'EUR',
        ]));

        app(OrderService::class)->place([
            'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com',
            'items' => [
                ['product_id' => $products[0]->id, 'quantity' => 1],
                ['product_id' => $products[2]->id, 'quantity' => 2],
            ],
        ], $manager);
    }
}
