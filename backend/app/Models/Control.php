<?php

declare(strict_types=1);

namespace Prometheus\Models;

final class Control
{
    public const STATUS_DRAFT     = 'bozza';
    public const STATUS_VALIDATED = 'validato';
    public const STATUS_ANNULLED  = 'annullato';

    public const OUTCOME_POSITIVE      = 'positivo';
    public const OUTCOME_NEGATIVE      = 'negativo';
    public const OUTCOME_INVESTIGATION = 'in_accertamento';

    public const OUTCOMES = [
        self::OUTCOME_POSITIVE,
        self::OUTCOME_NEGATIVE,
        self::OUTCOME_INVESTIGATION,
    ];
}
