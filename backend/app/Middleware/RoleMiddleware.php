<?php

declare(strict_types=1);

namespace Prometheus\Middleware;

final class RoleMiddleware
{
    public function allows(string $role, array $allowedRoles): bool
    {
        return in_array($role, $allowedRoles, true);
    }
}
