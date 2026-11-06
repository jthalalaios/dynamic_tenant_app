<?php

namespace App\Enums;

enum TenantType: string
{
    case Doctor = 'doctor';
    case Marketplace = 'marketplace';

    /**
     * Permission scopes a tenant of this type may grant.
     *
     * @return list<string>
     */
    public function permissionScopes(): array
    {
        return ['common', $this->value];
    }
}
