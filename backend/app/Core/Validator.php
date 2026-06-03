<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $error = $this->validateRule((string) $field, $value, (string) $rule);

                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    private function validateRule(string $field, mixed $value, string $rule): ?string
    {
        if ($rule === 'required' && $this->blank($value)) {
            return "Il campo {$field} e obbligatorio.";
        }

        if ($this->blank($value)) {
            return null;
        }

        if ($rule === 'date' && !$this->date((string) $value)) {
            return "Il campo {$field} deve essere una data valida YYYY-MM-DD.";
        }

        if ($rule === 'integer' && !ctype_digit((string) $value)) {
            return "Il campo {$field} deve essere un intero.";
        }

        if ($rule === 'boolean' && !in_array((string) $value, ['0', '1'], true)) {
            return "Il campo {$field} deve essere 0 oppure 1.";
        }

        if (str_starts_with($rule, 'in:')) {
            $accepted = explode(',', substr($rule, 3));

            if (!in_array((string) $value, $accepted, true)) {
                return "Il campo {$field} deve essere uno tra: " . implode(', ', $accepted) . '.';
            }
        }

        if ($rule === 'email' && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
            return "Il campo {$field} deve essere una email valida.";
        }

        if (str_starts_with($rule, 'min:') && mb_strlen((string) $value) < (int) substr($rule, 4)) {
            return "Il campo {$field} e troppo corto.";
        }

        return null;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    private function date(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
