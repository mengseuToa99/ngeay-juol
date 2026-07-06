<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class ExchangeRateService
{
    /**
     * @return array{rate: float, date: string, source: string}
     */
    public function fetchUsdToKhr(): array
    {
        $endpoint = (string) config('services.exchange_rate.nbc_endpoint', 'https://www.nbc.gov.kh/api/exRate.php');
        $timeout = (int) config('services.exchange_rate.timeout', 10);

        $response = Http::timeout($timeout)->get($endpoint);

        if ($response->failed()) {
            throw new RuntimeException('Unable to fetch the exchange rate from NBC.');
        }

        return $this->parseNbcXml($response->body());
    }

    /**
     * @return array{rate: float, date: string, source: string}
     */
    public function parseNbcXml(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('NBC returned an invalid exchange-rate response.');
        }

        foreach ($xml->ex as $entry) {
            if ((string) $entry->key !== 'USD/KHR') {
                continue;
            }

            $average = (float) $entry->average;
            $unit = max(1.0, (float) $entry->unit);
            $rate = round($average / $unit, 4);

            if ($rate <= 0) {
                throw new RuntimeException('NBC returned an invalid USD to KHR rate.');
            }

            return [
                'rate' => $rate,
                'date' => $this->parseNbcDate((string) $entry->date)->toDateString(),
                'source' => 'NBC',
            ];
        }

        throw new RuntimeException('NBC did not return a USD to KHR rate.');
    }

    private function parseNbcDate(string $date): CarbonImmutable
    {
        $date = trim($date);

        foreach (['m/d/Y', 'd/m/Y', 'Y-m-d'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $date);

            if ($parsed !== false) {
                return $parsed->startOfDay();
            }
        }

        return CarbonImmutable::today();
    }
}
