<?php

declare(strict_types=1);

namespace App\Services\Formatters;

/**
 * Renders the array returned by CeidgApiService::findByNip() into HTML.
 *
 * CEIDG response shape mirrors GUS (nip / regon / company_name /
 * address fields), so layout matches GusNoteFormatter for visual
 * consistency in the office UI.
 */
class CeidgNoteFormatter
{
    public static function format(array $data): string
    {
        $rows = [
            ['Nazwa firmy', $data['company_name'] ?? ''],
            ['NIP', $data['nip'] ?? ''],
            ['REGON', $data['regon'] ?? ''],
            ['Typ', 'Osoba fizyczna prowadząca działalność (JDG)'],
            ['Adres', self::formatAddress($data)],
            ['Województwo', $data['province'] ?? ''],
        ];

        $html  = '<div class="ext-note ext-note-ceidg">';
        $html .= '<h4>Dane z CEIDG</h4>';
        $html .= '<table class="ext-note-table">';
        foreach ($rows as [$label, $value]) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $html .= '<tr><th>' . self::e($label) . '</th><td>' . self::e($value) . '</td></tr>';
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

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
