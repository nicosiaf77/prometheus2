<?php

declare(strict_types=1);

namespace Prometheus\Services;

final class AuditActions
{
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED = 'LOGIN_FAILED';
    public const LOGIN_THROTTLED = 'LOGIN_THROTTLED';
    public const LOGOUT = 'LOGOUT';
    public const EVENT_CREATED = 'EVENT_CREATED';
    public const AGENT_CREATED = 'AGENT_CREATED';
    public const AGENT_UPDATED = 'AGENT_UPDATED';
    public const CONTROL_CREATED = 'CONTROL_CREATED';
    public const CONTROL_UPDATED = 'CONTROL_UPDATED';
    public const CONTROL_VALIDATED = 'CONTROL_VALIDATED';
    public const CONTROL_ANNULLED = 'CONTROL_ANNULLED';
    public const CONTROL_VIEWED = 'CONTROL_VIEWED';
    public const REPORT_EXPORTED = 'REPORT_EXPORTED';
    public const BACKUP_CREATED = 'BACKUP_CREATED';
    public const INTEGRITY_CHECK = 'INTEGRITY_CHECK';
    public const USER_CREATED = 'USER_CREATED';
    public const USER_UPDATED = 'USER_UPDATED';
}
