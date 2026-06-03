<?php

declare(strict_types=1);

namespace Prometheus\Requests;

final class UpdateUserRequest
{
    public function rules(): array
    {
        return [
            'name'    => ['required'],
            'surname' => ['required'],
            'email'   => ['required', 'email'],
            'role'    => ['required', 'in:amministratore,responsabile_ufficio,operatore,lettore'],
        ];
    }
}
