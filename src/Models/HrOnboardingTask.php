<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrOnboardingTask
{
    private static array $defaultTasks = [
        'onboarding' => [
            'documents' => [
                'Zebranie dokumentów tożsamości',
                'Podpisanie umowy o pracę',
                'Złożenie oświadczenia PIT-2',
                'Podpisanie klauzuli informacyjnej RODO',
            ],
            'medical' => [
                'Wystawienie skierowania na badania wstępne',
                'Dostarczenie zaświadczenia lekarskiego',
            ],
            'training' => [
                'Szkolenie BHP (wstępne)',
                'Szkolenie stanowiskowe',
            ],
            'payroll' => [
                'Weryfikacja numeru konta bankowego',
                'Rejestracja w ZUS (formularz ZUA lub ZZA)',
            ],
        ],
        'offboarding' => [
            'documents' => [
                'Podpisanie rozwiązania umowy o pracę (forma pisemna)',
                'Wystawienie świadectwa pracy (art. 97 KP)',
            ],
            'payroll' => [
                'Rozliczenie urlopu zaległego / ekwiwalent',
                'Przygotowanie finalnej listy płac',
            ],
            'other' => [
                'Zwrót sprzętu firmowego / dostępów',
                'Wyrejestrowanie z ZUS (formularz ZWUA)',
            ],
        ],
    ];

    public static function findByEmployee(int $empId, string $phase): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_onboarding_tasks
             WHERE employee_id = ? AND phase = ?
             ORDER BY category, id",
            [$empId, $phase]
        );
    }

    public static function getProgress(int $empId, string $phase): array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT COUNT(*) AS total, SUM(is_done) AS done
             FROM hr_onboarding_tasks
             WHERE employee_id = ? AND phase = ?",
            [$empId, $phase]
        );

        $total = (int)($row['total'] ?? 0);
        $done  = (int)($row['done']  ?? 0);

        return [
            'done'  => $done,
            'total' => $total,
            'pct'   => $total > 0 ? (int)round($done / $total * 100) : 0,
        ];
    }

    public static function createDefaultsForEmployee(int $empId, int $clientId, string $phase): void
    {
        $db = HrDatabase::getInstance();

        $existing = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_onboarding_tasks WHERE employee_id = ? AND phase = ?",
            [$empId, $phase]
        );
        if ((int)($existing['cnt'] ?? 0) > 0) {
            return;
        }

        $tasks = self::$defaultTasks[$phase] ?? [];

        foreach ($tasks as $category => $titles) {
            foreach ($titles as $title) {
                $db->insert('hr_onboarding_tasks', [
                    'employee_id' => $empId,
                    'client_id'   => $clientId,
                    'phase'       => $phase,
                    'category'    => $category,
                    'title'       => $title,
                ]);
            }
        }
    }

    public static function markDone(int $id, string $actorType, int $actorId): void
    {
        HrDatabase::getInstance()->update('hr_onboarding_tasks', [
            'is_done'      => 1,
            'done_at'      => date('Y-m-d H:i:s'),
            'done_by_type' => $actorType,
            'done_by_id'   => $actorId,
        ], 'id = ?', [$id]);
    }

    public static function markUndone(int $id): void
    {
        HrDatabase::getInstance()->update('hr_onboarding_tasks', [
            'is_done'      => 0,
            'done_at'      => null,
            'done_by_type' => null,
            'done_by_id'   => null,
        ], 'id = ?', [$id]);
    }

    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_onboarding_tasks WHERE id = ?",
            [$id]
        );
    }
}
