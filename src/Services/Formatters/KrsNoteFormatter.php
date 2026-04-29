<?php

declare(strict_types=1);

namespace App\Services\Formatters;

/**
 * Renders a KRS Open Data API response (OdpisAktualny / OdpisPelny)
 * into HTML for storage in client_external_notes.formatted_html.
 *
 * KRS responses are deeply nested (odpis.dane.dzial1...dzial6) and
 * shape varies with podmiot type (sp. z o.o., S.A., spółka komandytowa,
 * fundacja, ...). The formatter degrades gracefully — missing sections
 * are simply skipped, so an unexpected shape never crashes rendering.
 *
 * PESEL of board members is masked (last 4 digits visible). The raw
 * value stays in raw_json for AML/KYC obligations.
 */
class KrsNoteFormatter
{
    public static function format(array $response): string
    {
        $odpis = $response['odpis'] ?? $response;
        $dane  = $odpis['dane']  ?? [];

        $html  = '<div class="ext-note ext-note-krs">';
        $html .= '<h4>Dane z KRS (' . self::e((string) ($odpis['rodzaj'] ?? 'odpis')) . ')</h4>';

        $html .= self::renderDzial1($dane['dzial1'] ?? []);
        $html .= self::renderDzial2($dane['dzial2'] ?? []);
        $html .= self::renderDzial3($dane['dzial3'] ?? []);
        $html .= self::renderStatus($odpis);

        $html .= '</div>';
        return $html;
    }

    /** Dział 1: dane podstawowe (nazwa, KRS, REGON, NIP, siedziba). */
    private static function renderDzial1(array $d): string
    {
        if (empty($d)) {
            return '';
        }
        $danePodm = $d['danePodmiotu']    ?? [];
        $siedziba = $d['siedzibaIAdres']  ?? [];
        $kapital  = $d['kapital']         ?? [];

        $rows = [
            ['Nazwa', $danePodm['nazwa'] ?? ''],
            ['Forma prawna', $danePodm['formaPrawna'] ?? ''],
            ['KRS', $danePodm['identyfikatory']['krs'] ?? ''],
            ['REGON', $danePodm['identyfikatory']['regon'] ?? ''],
            ['NIP', $danePodm['identyfikatory']['nip'] ?? ''],
            ['Siedziba', self::joinAddr($siedziba['adres'] ?? [])],
            ['Kapitał zakładowy', self::money($kapital['wysokoscKapitaluZakladowego'] ?? null)],
            ['Kapitał wpłacony', self::money($kapital['kapitalWplacony'] ?? null)],
        ];

        return self::section('Dział 1 — Dane podstawowe', $rows);
    }

    /** Dział 2: organy reprezentacji + zarząd. */
    private static function renderDzial2(array $d): string
    {
        if (empty($d)) {
            return '';
        }
        $html = '';
        $organ = $d['organReprezentacji']['nazwaOrganu']  ?? '';
        $sposob = $d['organReprezentacji']['sposobReprezentacji'] ?? '';
        if ($organ !== '' || $sposob !== '') {
            $html .= self::section('Dział 2 — Reprezentacja', [
                ['Organ', $organ],
                ['Sposób reprezentacji', $sposob],
            ]);
        }

        $sklad = $d['organReprezentacji']['skladOsobowy']['sklad'] ?? [];
        if (!empty($sklad) && is_array($sklad)) {
            $html .= '<h5>Zarząd</h5>';
            $html .= '<table class="ext-note-table"><thead><tr>'
                  .  '<th>Imię i nazwisko</th><th>Funkcja</th><th>PESEL</th>'
                  .  '</tr></thead><tbody>';
            foreach ($sklad as $row) {
                $imie     = trim(($row['imiePierwsze'] ?? '') . ' ' . ($row['nazwisko'] ?? ''));
                $funkcja  = $row['funkcjaWOrganie'] ?? '';
                $pesel    = self::maskPesel((string) ($row['pesel'] ?? ''));
                $html .= '<tr><td>' . self::e($imie) . '</td>'
                      .  '<td>' . self::e($funkcja) . '</td>'
                      .  '<td>' . self::e($pesel) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        return $html;
    }

    /** Dział 3: prokurenci, oddziały (jeśli są). */
    private static function renderDzial3(array $d): string
    {
        if (empty($d)) {
            return '';
        }
        $prokurenci = $d['prokurenci']['prokurent'] ?? [];
        if (empty($prokurenci)) {
            return '';
        }
        $html = '<h5>Prokurenci</h5><ul>';
        foreach ($prokurenci as $p) {
            $name = trim(($p['imiePierwsze'] ?? '') . ' ' . ($p['nazwisko'] ?? ''));
            if ($name !== '') {
                $html .= '<li>' . self::e($name) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    private static function renderStatus(array $odpis): string
    {
        $status = (string) ($odpis['status'] ?? $odpis['naglowekP']['stanZUpadlosci'] ?? '');
        if ($status === '') {
            return '';
        }
        return '<p class="ext-note-status">Status: <strong>' . self::e($status) . '</strong></p>';
    }

    private static function section(string $title, array $rows): string
    {
        $rows = array_values(array_filter($rows, fn($r) => trim((string) $r[1]) !== ''));
        if (empty($rows)) {
            return '';
        }
        $html = '<h5>' . self::e($title) . '</h5>';
        $html .= '<table class="ext-note-table">';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th>' . self::e((string) $label) . '</th>'
                  .  '<td>' . self::e((string) $value) . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private static function joinAddr(array $a): string
    {
        if (empty($a)) {
            return '';
        }
        $parts = [];
        $street = (string) ($a['ulica'] ?? '');
        if ($street !== '') {
            $num = (string) ($a['nrDomu'] ?? '');
            if (!empty($a['nrLokalu'])) {
                $num .= '/' . $a['nrLokalu'];
            }
            $parts[] = "ul. {$street} {$num}";
        }
        $postal = (string) ($a['kodPocztowy'] ?? '');
        $city   = (string) ($a['miejscowosc'] ?? '');
        if ($postal !== '' || $city !== '') {
            $parts[] = trim("{$postal} {$city}");
        }
        return implode(', ', $parts);
    }

    private static function money(?array $v): string
    {
        if (!is_array($v)) {
            return '';
        }
        $kwota = $v['wartosc'] ?? $v['kwota'] ?? null;
        $waluta = $v['waluta'] ?? 'PLN';
        if ($kwota === null) {
            return '';
        }
        return number_format((float) $kwota, 2, ',', ' ') . ' ' . $waluta;
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
