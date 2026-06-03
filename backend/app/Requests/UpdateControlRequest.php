<?php

declare(strict_types=1);

namespace Prometheus\Requests;

use Prometheus\Models\Control;

final class UpdateControlRequest
{
    public function rules(): array
    {
        return [
            'control_date'        => ['required', 'date'],
            'control_time'        => ['required'],
            'has_event'           => ['required', 'boolean'],
            'business_name'       => ['required'],
            'business_location'   => ['required'],
            'primary_category_id' => ['required', 'integer'],
            'outcome'             => ['required', 'in:' . implode(',', Control::OUTCOMES)],
            'change_reason'       => ['required'],
        ];
    }
}
