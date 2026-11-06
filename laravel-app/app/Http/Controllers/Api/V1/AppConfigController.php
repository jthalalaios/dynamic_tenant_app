<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Tenancy\Themes;
use App\Tenancy\Workspaces;
use Illuminate\Http\JsonResponse;

/**
 * Public bootstrap info for frontends: which themes this backend serves and
 * which auth features are on. A multi frontend uses it to only offer the
 * enabled themes; a standalone one to confirm its theme is served.
 */
class AppConfigController extends Controller
{
    public function __invoke(Themes $themes, Workspaces $workspaces): JsonResponse
    {
        // The workspace each theme's sign-ups join, by display name ("You will join Acme Clinic")
        $names = collect($themes->enabled())->mapWithKeys(fn (TenantType $type) => [
            $type->value => Tenant::query()->where('slug', $workspaces->slugFor($type))->value('name')
                ?? config("tenancy.workspaces.{$type->value}.name"),
        ]);

        return response()->json([
            'themes' => array_map(fn (TenantType $type) => $type->value, $themes->enabled()),
            'workspaces' => $names,
            'theme' => $themes->requested()?->value,
            'locales' => config('tenancy.locales'),
            'features' => [
                'registration' => true,
                'email_verification' => (bool) config('tenancy.auth.verify_email'),
            ],
        ]);
    }
}
