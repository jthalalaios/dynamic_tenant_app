<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo rooms and a short conversation for the demo clinic and shop.
 * Idempotent, and safe to run on its own: `php artisan db:seed --class=ChatDemoSeeder`.
 *
 * Messages are written directly (not via ChatService::send) so seeding never
 * needs the websocket server.
 */
class ChatDemoSeeder extends Seeder
{
    /** tenant slug => [extra room, conversation as [author email, room slug, text]] */
    private const DEMO = [
        'acme-clinic' => [
            'room' => ['slug' => 'front-desk', 'name' => 'Front desk', 'icon' => 'calendar', 'description' => 'Arrivals, delays and walk-ins.'],
            'messages' => [
                ['admin@clinic.test', 'announcements', 'Welcome to the clinic chat! Please keep patient details out of the open rooms.'],
                ['reception@clinic.test', 'general', 'Good morning everyone ☀️'],
                ['doctor@clinic.test', 'general', 'Morning! I am running ten minutes late after rounds.'],
                ['reception@clinic.test', 'front-desk', 'Two walk-ins waiting, I have put them on the list.'],
                ['admin@clinic.test', 'general', 'Thanks both, coffee is on me today.'],
                ['doctor@clinic.test', 'doctors', 'Can someone cover my 15:00 follow-up?'],
                ['admin@clinic.test', 'doctors', 'I will take it, Bob.'],
                ['admin@clinic.test', 'admins', 'Reminder: review the new roles before Friday.'],
            ],
        ],
        'acme-market' => [
            'room' => ['slug' => 'sales-floor', 'name' => 'Sales floor', 'icon' => 'shopping-cart', 'description' => 'Orders, stock and customer questions.'],
            'messages' => [
                ['admin@market.test', 'announcements', 'Flash sale starts Friday at 10:00. Stock up on headphones!'],
                ['sales@market.test', 'general', 'Hi team 👋'],
                ['manager@market.test', 'sales-floor', 'The Voyager backpack is out of stock, please offer the Aurora bundle instead.'],
                ['sales@market.test', 'sales-floor', 'Got it, thanks Max.'],
                ['manager@market.test', 'managers', 'Weekly stock count moves to Thursday.'],
                ['admin@market.test', 'admins', 'Quarterly numbers are in, looking good.'],
            ],
        ],
    ];

    public function __construct(
        private readonly ChatService $chat,
        private readonly TenantContext $context,
    ) {}

    public function run(): void
    {
        foreach (self::DEMO as $slug => $demo) {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if ($tenant !== null) {
                $this->context->runAs($tenant, fn () => $this->seedTenant($tenant, $demo));
            }
        }
    }

    /**
     * @param  array{room: array<string, string>, messages: list<array{0: string, 1: string, 2: string}>}  $demo
     */
    private function seedTenant(Tenant $tenant, array $demo): void
    {
        // Global, Announcements, Admins and the theme's role rooms (idempotent, by slug)
        $this->chat->provisionDefaultRooms();
        ChatRoom::query()->firstOrCreate(['tenant_id' => $tenant->id, 'slug' => $demo['room']['slug']], $demo['room']);

        // Only this organization's own messages: the global room is visible to everyone
        if (ChatMessage::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $rooms = ChatRoom::query()->pluck('id', 'slug');
        $users = User::query()->pluck('id', 'email');

        foreach ($demo['messages'] as $i => [$email, $room, $body]) {
            if (! isset($users[$email], $rooms[$room])) {
                continue;
            }

            // forceCreate: created_at is not mass assignable, but the demo backdates the conversation
            ChatMessage::query()->forceCreate([
                'tenant_id' => $tenant->id,
                'room_id' => $rooms[$room],
                'user_id' => $users[$email],
                'body' => $body,
                'created_at' => now()->subMinutes((count($demo['messages']) - $i) * 7),
            ]);
        }
    }
}
