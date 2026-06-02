<?php

declare(strict_types=1);

namespace Prometheus\Requests;

final class StoreControlRequest
{
    public function rules(): array
    {
        return [
            'control_date' => ['required', 'date'],
            'control_time' => ['required'],
            'has_event' => ['required', 'boolean'],
            'business_name' => ['required'],
            'business_location' => ['required'],
            'primary_category_id' => ['required', 'integer'],
            'outcome' => ['required', 'in:positivo,negativo,in_accertamento'],
        ];
    }
}
