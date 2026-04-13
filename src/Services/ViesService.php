<?php

namespace App\Services;

class ViesService
{
    private const WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    public static function checkVat(string $countryCode, string $vatNumber): array
    {
        $vatNumber = preg_replace('/[^0-9A-Za-z]/', '', $vatNumber);
        $countryCode = strtoupper(trim($countryCode));

        if (strlen($countryCode) !== 2) {
            return ['valid' => false, 'error' => 'Invalid country code'];
        }

        try {
            $client = new \SoapClient(self::WSDL_URL, [
                'connection_timeout' => 10,
                'default_socket_timeout' => 15,
                'exceptions' => true,
            ]);

            $result = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber,
            ]);

            return [
                'valid' => (bool)$result->valid,
                'country_code' => $result->countryCode ?? $countryCode,
                'vat_number' => $result->vatNumber ?? $vatNumber,
                'name' => trim($result->name ?? ''),
                'address' => trim($result->address ?? ''),
                'request_date' => $result->requestDate ?? date('Y-m-d'),
                'error' => null,
            ];
        } catch (\SoapFault $e) {
            $faultCode = $e->faultstring ?? $e->getMessage();

            $errorMessages = [
                'INVALID_INPUT' => 'Invalid input data',
                'SERVICE_UNAVAILABLE' => 'VIES service temporarily unavailable',
                'MS_UNAVAILABLE' => 'Member state service unavailable',
                'TIMEOUT' => 'Request timeout',
                'MS_MAX_CONCURRENT_REQ' => 'Too many requests, try again later',
                'GLOBAL_MAX_CONCURRENT_REQ' => 'Too many requests globally, try again later',
            ];

            return [
                'valid' => false,
                'error' => $errorMessages[$faultCode] ?? $faultCode,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    public static function checkPolishNip(string $nip): array
    {
        return self::checkVat('PL', $nip);
    }
}
