<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\Formatters\CrbrNoteFormatter;
use App\Services\Formatters\KrsNoteFormatter;
use PHPUnit\Framework\TestCase;

/**
 * PESEL must NEVER appear in plaintext in the rendered HTML stored
 * in client_external_notes.formatted_html — only the last 4 digits.
 * The raw value still lives in raw_json (the row is read only by office,
 * never by the client whose data it concerns), but the rendered surface
 * leaks less even if a future tab unwittingly exposes it.
 */
final class NoteFormatterPiiMaskTest extends TestCase
{
    public function testKrsBoardMembersHaveMaskedPesel(): void
    {
        $resp = [
            'odpis' => ['dane' => ['dzial2' => [
                'organReprezentacji' => [
                    'skladOsobowy' => ['sklad' => [
                        ['imiePierwsze' => 'Jan', 'nazwisko' => 'Kowalski',
                         'funkcjaWOrganie' => 'Prezes', 'pesel' => '85010112345'],
                    ]],
                ],
            ]]],
        ];
        $html = KrsNoteFormatter::format($resp);

        // Last 4 digits visible, the rest masked. Full value MUST NOT
        // appear anywhere in the output.
        self::assertStringContainsString('*******2345', $html);
        self::assertStringNotContainsString('85010112345', $html);
        self::assertStringNotContainsString('850101', $html, 'No leading PESEL fragment');
    }

    public function testCrbrBeneficiariesHaveMaskedPesel(): void
    {
        $resp = [
            'beneficiaries' => [
                ['firstName' => 'Anna', 'lastName' => 'Nowak',
                 'citizenship' => 'PL', 'pesel' => '90120567890',
                 'natureOfControl' => 'udziałowiec', 'ownershipPercent' => 30.5],
            ],
        ];
        $html = CrbrNoteFormatter::format($resp);
        self::assertStringContainsString('*******7890', $html);
        self::assertStringNotContainsString('90120567890', $html);
    }

    public function testCrbrEmptyResponseDoesNotCrash(): void
    {
        $html = CrbrNoteFormatter::format(['beneficiaries' => []]);
        self::assertStringContainsString('Brak zgłoszonych beneficjentów', $html);
    }

    public function testKrsHandlesMissingSectionsGracefully(): void
    {
        // A response with only dzial1 (no dzial2/3) must not crash.
        $resp = [
            'odpis' => ['dane' => ['dzial1' => [
                'danePodmiotu' => ['nazwa' => 'Tylko podstawowe', 'identyfikatory' => ['krs' => '0000123456']],
            ]]],
        ];
        $html = KrsNoteFormatter::format($resp);
        self::assertStringContainsString('Tylko podstawowe', $html);
        self::assertStringContainsString('0000123456', $html);
    }

    public function testFormatterEscapesHostileNames(): void
    {
        $resp = [
            'odpis' => ['dane' => ['dzial1' => [
                'danePodmiotu' => [
                    'nazwa' => '<script>alert(1)</script>',
                    'identyfikatory' => ['krs' => '0000123456'],
                ],
            ]]],
        ];
        $html = KrsNoteFormatter::format($resp);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('&lt;script', $html);
    }
}
