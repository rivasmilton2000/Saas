<?php

function getCountryOptions(): array
{
    return [
        'El Salvador' => 'El Salvador',
        'Guatemala' => 'Guatemala',
        'Honduras' => 'Honduras',
        'Nicaragua' => 'Nicaragua',
        'Costa Rica' => 'Costa Rica',
        'Panama' => 'Panama',
        'Mexico' => 'Mexico',
        'Estados Unidos' => 'Estados Unidos',
        'Colombia' => 'Colombia',
        'Republica Dominicana' => 'Republica Dominicana',
        'Otro' => 'Otro',
    ];
}

function normalizeCountryValue($pais, string $fallback = 'El Salvador'): string
{
    $pais = trim((string) $pais);
    if ($pais === '') {
        return $fallback;
    }

    foreach (getCountryOptions() as $label) {
        if (strtolower($label) === strtolower($pais)) {
            return $label;
        }
    }

    return $pais;
}
