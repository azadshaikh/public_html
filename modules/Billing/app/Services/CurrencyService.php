<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

class CurrencyService
{
    /**
     * Supported currencies with their details.
     *
     * @var array<string, array{name: string, symbol: string, decimals: int, symbol_position: string}>
     */
    protected array $currencies = [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'symbol_position' => 'before'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'symbol_position' => 'before'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'symbol_position' => 'before'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'decimals' => 2, 'symbol_position' => 'before'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'decimals' => 2, 'symbol_position' => 'before'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2, 'symbol_position' => 'before'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0, 'symbol_position' => 'before'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'decimals' => 2, 'symbol_position' => 'before'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'decimals' => 2, 'symbol_position' => 'before'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'decimals' => 2, 'symbol_position' => 'after'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimals' => 2, 'symbol_position' => 'before'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimals' => 2, 'symbol_position' => 'before'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimals' => 2, 'symbol_position' => 'before'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$', 'decimals' => 2, 'symbol_position' => 'before'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'decimals' => 2, 'symbol_position' => 'before'],
    ];

    /**
     * Exchange rates relative to USD.
     * In production, these would be fetched from an API or database.
     *
     * @var array<string, float>
     */
    protected array $exchangeRates = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'CAD' => 1.36,
        'AUD' => 1.53,
        'INR' => 83.12,
        'JPY' => 149.50,
        'CNY' => 7.24,
        'CHF' => 0.88,
        'SEK' => 10.45,
        'NZD' => 1.64,
        'SGD' => 1.34,
        'HKD' => 7.82,
        'MXN' => 17.15,
        'BRL' => 4.97,
    ];

    /**
     * Get default currency code.
     */
    public function getDefaultCurrency(): string
    {
        return config('billing.default_currency', 'USD');
    }

    /**
     * Get all supported currencies.
     *
     * @return array<string, array{name: string, symbol: string, decimals: int, symbol_position: string}>
     */
    public function getSupportedCurrencies(): array
    {
        return $this->currencies;
    }

    /**
     * Get currency options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCurrencyOptions(): array
    {
        return collect($this->currencies)
            ->map(fn ($details, string $code): array => [
                'value' => $code,
                'label' => sprintf('%s - %s', $code, $details['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * Check if a currency is supported.
     */
    public function isSupportedCurrency(string $currency): bool
    {
        return isset($this->currencies[strtoupper($currency)]);
    }

    /**
     * Get currency details.
     *
     * @return array{name: string, symbol: string, decimals: int, symbol_position: string}|null
     */
    public function getCurrencyDetails(string $currency): ?array
    {
        return $this->currencies[strtoupper($currency)] ?? null;
    }

    /**
     * Format an amount for display.
     */
    public function format(float $amount, string $currency = 'USD'): string
    {
        $currency = strtoupper($currency);
        $details = $this->getCurrencyDetails($currency);

        if (! $details) {
            return number_format($amount, 2).' '.$currency;
        }

        $formatted = number_format(abs($amount), $details['decimals']);
        $symbol = $details['symbol'];

        $result = $details['symbol_position'] === 'before'
            ? $symbol.$formatted
            : $formatted.' '.$symbol;

        return $amount < 0 ? '-'.$result : $result;
    }

    /**
     * Get the exchange rate for a currency.
     */
    public function getExchangeRate(string $currency): float
    {
        return $this->exchangeRates[strtoupper($currency)] ?? 1.0;
    }

    /**
     * Convert an amount between currencies.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Convert to USD first (base currency)
        $amountInUsd = $amount / $this->getExchangeRate($fromCurrency);

        // Then convert to target currency
        $converted = $amountInUsd * $this->getExchangeRate($toCurrency);

        // Round to appropriate decimals
        $decimals = $this->getCurrencyDetails($toCurrency)['decimals'] ?? 2;

        return round($converted, $decimals);
    }

    /**
     * Get conversion info for display.
     *
     * @return array{
     *     original_amount: float,
     *     original_currency: string,
     *     converted_amount: float,
     *     converted_currency: string,
     *     exchange_rate: float,
     *     formatted_original: string,
     *     formatted_converted: string
     * }
     */
    public function getConversionInfo(float $amount, string $fromCurrency, string $toCurrency): array
    {
        $converted = $this->convert($amount, $fromCurrency, $toCurrency);
        $rate = $this->getExchangeRate($toCurrency) / $this->getExchangeRate($fromCurrency);

        return [
            'original_amount' => $amount,
            'original_currency' => $fromCurrency,
            'converted_amount' => $converted,
            'converted_currency' => $toCurrency,
            'exchange_rate' => round($rate, 6),
            'formatted_original' => $this->format($amount, $fromCurrency),
            'formatted_converted' => $this->format($converted, $toCurrency),
        ];
    }

    /**
     * Update exchange rates (in production, this would fetch from an API).
     *
     * @param  array<string, float>  $rates
     */
    public function updateExchangeRates(array $rates): void
    {
        foreach ($rates as $currency => $rate) {
            if (isset($this->exchangeRates[$currency])) {
                $this->exchangeRates[$currency] = $rate;
            }
        }
    }

    /**
     * Get all current exchange rates.
     *
     * @return array<string, float>
     */
    public function getAllExchangeRates(): array
    {
        return $this->exchangeRates;
    }

    /**
     * Calculate subtotal in base currency (USD).
     */
    public function toBaseCurrency(float $amount, string $fromCurrency): float
    {
        return $this->convert($amount, $fromCurrency, 'USD');
    }

    /**
     * Calculate amount from base currency (USD) to target.
     */
    public function fromBaseCurrency(float $amountInUsd, string $toCurrency): float
    {
        return $this->convert($amountInUsd, 'USD', $toCurrency);
    }
}
