<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ModulePricing
{
    public const CATALOG = ['VTS' => ['name' => 'Vehicle Tracking System', 'monthly' => 250000, 'yearly' => 2500000], 'VAS' => ['name' => 'Visitor Authorization System', 'monthly' => 2500000, 'yearly' => 20000000], 'PAS' => ['name' => 'Pickup Authorization System', 'monthly' => 2500000, 'yearly' => 20000000], 'PBS' => ['name' => 'Panic Button System', 'monthly' => 2500000, 'yearly' => 20000000]];
    public static function catalog(): array
    {
        return collect(self::CATALOG)->map(fn($plan, $code) => ['code' => $code, 'name' => $plan['name'], 'monthly' => $plan['monthly'] / 100, 'yearly' => $plan['yearly'] / 100])->values()->all();
    }
    public static function resolve(array $selections): array
    {
        if (!$selections) throw ValidationException::withMessages(['modules' => 'Select at least one module.']);
        $items = [];
        foreach ($selections as $selection) {
            $code = strtoupper($selection['code'] ?? '');
            $cycle = $selection['cycle'] ?? '';
            if (!isset(self::CATALOG[$code]) || !in_array($cycle, ['monthly', 'yearly'], true)) throw ValidationException::withMessages(['modules' => 'One or more module selections are invalid.']);
            $items[] = ['code' => $code, 'name' => self::CATALOG[$code]['name'], 'cycle' => $cycle, 'amount_kobo' => self::CATALOG[$code][$cycle]];
        }
        return $items;
    }
}
