<?php

declare(strict_types=1);

namespace Prometheus\Models;

final class Control
{
    public const STATUS_DRAFT = 'bozza';
    public const STATUS_VALIDATED = 'validato';
    public const STATUS_ANNULLED = 'annullato';
}
