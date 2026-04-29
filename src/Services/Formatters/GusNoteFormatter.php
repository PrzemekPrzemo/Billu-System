<?php

declare(strict_types=1);

namespace App\Services\Formatters;

/**
 * Renders the array returned by GusApiService::findByNip() into a
 * human-readable HTML block for storage in client_external_notes.
 *
 * All output is escaped (htmlspecialchars / ENT_QUOTES) — the resulting
 * blob is rendered into the office UI with a raw `echo` so an attacker
 * controlling a NIP value must not be able to inject HTML.
 */
class GusNoteFormatter
{
    public static function format(array $data): string
    {
        $nip          = self::e($data['nip'] ?? '');
        $regon        = self::e($data['regon'] ?? '');
        $companyName  = self::e($data['company_name'] ?? '');
        $type         = self::typeLabel((string) ($data['type'] ?? ''));
        $address      = self::e(self::formatAddress($data));

        $rows = [
            ['Nazwa', $companyName],
            ['NIP', $nip],
            ['REGON', $regon],
            ['Typ', self::e($type)],
            ['Adres', $address],
            ['Województwo', self::e((string) ($data['province'] ?? ''))],
        ];

        $html  = '<div class="ext-note ext-note-gus">';
        $html .= '<h4>Dane z GUS (REGON BIR1)</h4>';
        $html .= '<table class="ext-note-table">';
        foreach ($rows as [$label, $value]) {
            if ($value === '') {
                continue;
            }
            $html .= '<tr><th>' . self::e($label) . '</th><td>' . $value . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
        return $html;
    }

    private static function formatAddress(array $d): string
    {
        $parts = [];
        $street = (string) ($d['street'] ?? '');
        if ($street !== '') {
            $num = (string) ($d['building_no'] ?? '');
            if (!empty($d['apartment_no'])) {
                $num .= '/' . $d['apartment_no'];
            }
            $parts[] = "ul. {$street} {$num}";
        }
        $postal = (string) ($d['postal_code'] ?? '');
        $city   = (string) ($d['city'] ?? '');
        if ($postal !== '' || $city !== '') {
            $parts[] = trim("{$postal} {$city}");
        }
        return implode(', ', $parts);
    }

    private static function typeLabel(string $code): string
    {
        return match ($code) {
            'P'     => 'Osoba prawna',
            'F'     => 'Osoba fizyczna prowadząca działalność',
            'LP'    => 'Jednostka lokalna osoby prawnej',
            'LF'    => 'Jednostka lokalna osoby fizycznej',
            default => $code !== '' ? $code : '—',
        };
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
