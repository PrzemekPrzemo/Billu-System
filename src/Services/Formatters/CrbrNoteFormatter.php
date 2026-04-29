<?php

declare(strict_types=1);

namespace App\Services\Formatters;

/**
 * Renders a CRBR (Central Register of Beneficial Owners) response.
 *
 * Privacy: PESEL is ALWAYS masked in the rendered HTML (last 4 digits
 * visible). Raw response sits in raw_json — only office_admin can fetch
 * it via the controller, and AuditLog::log() redacts PESEL before
 * persisting CRBR-related events anyway.
 */
class CrbrNoteFormatter
{
    public static function format(array $response): string
    {
        $beneficiaries = $response['beneficiaries']
                      ?? $response['beneficjenciRzeczywisci']
                      ?? [];

        $html  = '<div class="ext-note ext-note-crbr">';
        $html .= '<h4>Beneficjenci rzeczywiści (CRBR)</h4>';

        if (empty($beneficiaries)) {
            $html .= '<p>Brak zgłoszonych beneficjentów rzeczywistych.</p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<table class="ext-note-table"><thead><tr>'
              .  '<th>Imię i nazwisko</th>'
              .  '<th>Obywatelstwo</th>'
              .  '<th>PESEL</th>'
              .  '<th>Charakter / udział</th>'
              .  '</tr></thead><tbody>';

        foreach ($beneficiaries as $b) {
            $name = trim(((string) ($b['firstName'] ?? $b['imie'] ?? '')) . ' ' .
                        ((string) ($b['lastName']  ?? $b['nazwisko'] ?? '')));
            $citizen = (string) ($b['citizenship'] ?? $b['obywatelstwo'] ?? '');
            $pesel = self::maskPesel((string) ($b['pesel'] ?? ''));
            $role  = self::roleLabel($b);

            $html .= '<tr>'
                  .  '<td>' . self::e($name) . '</td>'
                  .  '<td>' . self::e($citizen) . '</td>'
                  .  '<td>' . self::e($pesel) . '</td>'
                  .  '<td>' . self::e($role) . '</td>'
                  .  '</tr>';
        }
        $html .= '</tbody></table>';

        $reportedAt = (string) ($response['reportedAt'] ?? $response['dataZgloszenia'] ?? '');
        if ($reportedAt !== '') {
            $html .= '<p class="ext-note-meta">Data zgłoszenia: '
                  .  self::e($reportedAt) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function roleLabel(array $b): string
    {
        $parts = [];
        $nature = (string) ($b['natureOfControl'] ?? $b['charakterBeneficjenta'] ?? '');
        if ($nature !== '') {
            $parts[] = $nature;
        }
        $share = $b['ownershipPercent'] ?? $b['udzial'] ?? null;
        if ($share !== null && $share !== '') {
            $parts[] = number_format((float) $share, 2, ',', ' ') . '%';
        }
        return implode(' • ', $parts);
    }

    private static function maskPesel(string $pesel): string
    {
        $pesel = preg_replace('/[^0-9]/', '', $pesel);
        if (strlen($pesel) !== 11) {
            return $pesel === '' ? '' : '***';
        }
        return str_repeat('*', 7) . substr($pesel, -4);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
