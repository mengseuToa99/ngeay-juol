<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertySetting;
use App\Models\PropertyUtility;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\UtilityUsage;
use Illuminate\Database\Eloquent\Model;

class Money
{
    /** @var array<int, string> */
    private static array $currencyByProperty = [];

    public static function normalize(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return in_array($currency, ['USD', 'KHR'], true) ? $currency : 'USD';
    }

    public static function symbol(?string $currency): string
    {
        return self::normalize($currency) === 'KHR' ? '៛' : '$';
    }

    public static function decimals(?string $currency): int
    {
        return self::normalize($currency) === 'KHR' ? 0 : 2;
    }

    public static function format(mixed $value, ?string $currency = null, ?int $decimals = null): string
    {
        $currency = self::normalize($currency);
        $decimals ??= self::decimals($currency);

        return self::symbol($currency).number_format((float) $value, $decimals);
    }

    public static function activeCurrency(): string
    {
        return self::forPropertyId(ActiveProperty::id());
    }

    public static function activeSymbol(): string
    {
        return self::symbol(self::activeCurrency());
    }

    public static function activeFormat(mixed $value, ?int $decimals = null): string
    {
        return self::format($value, self::activeCurrency(), $decimals);
    }

    public static function forPropertyId(?int $propertyId): string
    {
        if (! $propertyId) {
            return 'USD';
        }

        if (! array_key_exists($propertyId, self::$currencyByProperty)) {
            self::$currencyByProperty[$propertyId] = self::normalize(
                PropertySetting::query()->where('property_id', $propertyId)->value('currency'),
            );
        }

        return self::$currencyByProperty[$propertyId];
    }

    public static function forRecord(mixed $record): string
    {
        $propertyId = self::propertyIdFor($record);

        if ($propertyId) {
            return self::forPropertyId($propertyId);
        }

        if ($record instanceof Property && $record->relationLoaded('settings')) {
            return self::normalize($record->settings?->currency);
        }

        return self::activeCurrency();
    }

    public static function symbolForRecord(mixed $record): string
    {
        return self::symbol(self::forRecord($record));
    }

    public static function formatForRecord(mixed $value, mixed $record, ?int $decimals = null): string
    {
        return self::format($value, self::forRecord($record), $decimals);
    }

    public static function symbolForUnitId(mixed $unitId): string
    {
        return self::symbol(self::forUnitId($unitId));
    }

    public static function forUnitId(mixed $unitId): string
    {
        if (! $unitId) {
            return self::activeCurrency();
        }

        return self::forPropertyId(Unit::withoutGlobalScopes()->whereKey($unitId)->value('property_id'));
    }

    private static function propertyIdFor(mixed $record): ?int
    {
        if (! $record instanceof Model) {
            return null;
        }

        if ($record instanceof Property) {
            return $record->getKey();
        }

        if ($record instanceof Invoice) {
            return $record->property_id
                ?: $record->property?->getKey()
                ?: $record->rental?->property_id
                ?: $record->rental?->unit?->property_id;
        }

        if ($record instanceof Payment) {
            return self::propertyIdFor($record->invoice);
        }

        if ($record instanceof Rental || $record instanceof Unit || $record instanceof PropertyUtility) {
            return $record->property_id;
        }

        if ($record instanceof UtilityUsage) {
            return $record->propertyUtility?->property_id ?: $record->unit?->property_id;
        }

        if (method_exists($record, 'invoice')) {
            return self::propertyIdFor($record->invoice);
        }

        if (property_exists($record, 'property_id') || isset($record->property_id)) {
            return (int) $record->property_id ?: null;
        }

        return null;
    }
}
