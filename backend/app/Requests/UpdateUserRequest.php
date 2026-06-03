<?php

declare(strict_types=1);

namespace Prometheus\Requests;

use Prometheus\Models\User;

final class UpdateUserRequest
{
    public function rules(): array
    {
        return [
            'name'    => ['required'],
            'surname' => ['required'],
            'email'   => ['required', 'email'],
            'role'    => ['required', 'in:' . implode(',', User::ROLES)],
        ];
    }
}
