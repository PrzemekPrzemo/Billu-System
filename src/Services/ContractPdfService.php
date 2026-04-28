<?php

declare(strict_types=1);

namespace App\Services;

use mikehaertl\pdftk\Pdf;

/**
 * Thin wrapper over pdftk for the two operations the Contracts module
 * needs: list AcroForm fields and fill them in.
 *
 * pdftk binary path is configured in config/contracts.php (PDFTK_PATH env).
 * If pdftk is missing, every method either throws RuntimeException or
 * returns an empty array — callers must surface the failure to the office
 * admin instead of silently failing.
 */
final class ContractPdfService
{
    /** @return list<array{name:string,type:string,label:string,required:bool,default:string}> */
    public static function parseFields(string $pdfPath): array
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            throw new \RuntimeException('PDF not readable: ' . $pdfPath);
        }
        $pdftk = self::pdftkPath();

        $cmd = sprintf(
            '%s %s dump_data_fields_utf8 2>&1',
            escapeshellcmd($pdftk),
            escapeshellarg($pdfPath)
        );
        $output = shell_exec($cmd);
        if ($output === null || $output === '') {
            // Either pdftk not installed, or no fields. Re-check distinction:
            if (!self::isPdftkAvailable()) {
                throw new \RuntimeException('pdftk binary not found at ' . $pdftk);
            }
            return [];
        }

        return self::parseDumpDataFields($output);
    }

    /**
     * Fill an AcroForm PDF with values keyed by field name.
     * @param array<string,scalar|null> $values
     */
    public static function fillForm(string $templatePath, array $values, string $outputPath): bool
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template missing: ' . $templatePath);
        }
        $outDir = dirname($outputPath);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0750, true);
        }

        $pdf = new Pdf($templatePath, ['command' => self::pdftkPath()]);
        // Cast all to string — pdftk tolerates strings and converts checkbox
        // truthy values to 'Yes'/'On' downstream.
        $stringValues = [];
        foreach ($values as $k => $v) {
            $stringValues[(string) $k] = is_bool($v) ? ($v ? 'Yes' : 'Off') : (string) ($v ?? '');
        }

        $result = $pdf->fillForm($stringValues)->needAppearances()->saveAs($outputPath);
        if ($result === false) {
            throw new \RuntimeException('pdftk fill_form failed: ' . $pdf->getError());
        }
        @chmod($outputPath, 0640);
        return true;
    }

    /** Optional flatten step — locks the form fields into static text. */
    public static function flatten(string $pdfPath, string $outputPath): bool
    {
        $pdf = new Pdf($pdfPath, ['command' => self::pdftkPath()]);
        $result = $pdf->flatten()->saveAs($outputPath);
        if ($result === false) {
            throw new \RuntimeException('pdftk flatten failed: ' . $pdf->getError());
        }
        @chmod($outputPath, 0640);
        return true;
    }

    public static function isPdftkAvailable(): bool
    {
        $bin = self::pdftkPath();
        if (!is_file($bin) || !is_executable($bin)) {
            return false;
        }
        return true;
    }

    // ─────────────────────────────────────────────

    private static function pdftkPath(): string
    {
        $cfg = require dirname(__DIR__, 2) . '/config/contracts.php';
        return (string) ($cfg['pdftk_path'] ?? '/usr/bin/pdftk');
    }

    /**
     * Parse the textual output of `pdftk … dump_data_fields_utf8`.
     * Each field is delimited by '---' and contains FieldName / FieldType /
     * FieldFlags / FieldNameAlt(=label) lines. We surface a small struct
     * that the public form template can render directly.
     *
     * @return list<array{name:string,type:string,label:string,required:bool,default:string}>
     */
    private static function parseDumpDataFields(string $output): array
    {
        $blocks = preg_split('/^---\s*$/m', $output) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $name = self::scrape($block, 'FieldName');
            if ($name === '') continue;

            $rawType = self::scrape($block, 'FieldType');
            $type = match (true) {
                $rawType === 'Text'                                          => 'text',
                $rawType === 'Button' && str_contains($block, 'PushButton')  => 'button',
                $rawType === 'Button' && str_contains($block, 'Radio')       => 'radio',
                $rawType === 'Button'                                        => 'checkbox',
                $rawType === 'Choice'                                        => 'select',
                $rawType === 'Sig'                                           => 'signature',
                default                                                       => 'text',
            };

            // Skip pure pushbuttons (no input value).
            if ($type === 'button') continue;

            $flags = (int) self::scrape($block, 'FieldFlags');
            $required = (bool) ($flags & 2);

            $out[] = [
                'name'     => $name,
                'type'     => $type,
                'label'    => self::scrape($block, 'FieldNameAlt') ?: $name,
                'required' => $required,
                'default'  => self::scrape($block, 'FieldValueDefault'),
            ];
        }
        return $out;
    }

    private static function scrape(string $block, string $key): string
    {
        if (preg_match('/^' . preg_quote($key, '/') . ': (.*)$/m', $block, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
