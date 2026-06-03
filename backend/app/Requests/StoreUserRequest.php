<?php

declare(strict_types=1);

namespace Prometheus\Requests;

use Prometheus\Models\User;

final class StoreUserRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required'],
            'surname'  => ['required'],
            'email'    => ['required', 'email'],
            'username' => ['required'],
            'password' => ['required', 'min:8'],
            'role'     => ['required', 'in:' . implode(',', User::ROLES)],
        ];
    }
}
