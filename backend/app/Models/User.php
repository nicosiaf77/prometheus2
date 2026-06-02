<?php

declare(strict_types=1);

namespace Prometheus\Models;

final class User
{
    public const ROLE_ADMIN = 'amministratore';
    public const ROLE_MANAGER = 'responsabile_ufficio';
    public const ROLE_OPERATOR = 'operatore';
    public const ROLE_READER = 'lettore';
}
