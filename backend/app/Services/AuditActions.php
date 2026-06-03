<?php

declare(strict_types=1);

namespace Prometheus\Services;

final class AuditActions
{
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED = 'LOGIN_FAILED';
    public const LOGOUT = 'LOGOUT';
}
