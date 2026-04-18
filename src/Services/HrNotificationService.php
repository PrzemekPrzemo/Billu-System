<?php

namespace App\Services;

use App\Models\Notification;

class HrNotificationService
{
    public static function notifyPayrollApproved(int $clientId, int $month, int $year, string $totalCost): void
    {
        $monthNames = ['','Stycze\u0144','Luty','Marzec','Kwiecie\u0144','Maj','Czerwiec',
                       'Lipiec','Sierpie\u0144','Wrzesie\u0144','Pa\u017adziernik','Listopad','Grudzie\u0144'];
        Notification::create(
            'client',
            $clientId,
            "Lista p\u0142ac zatwierdzona: {$monthNames[$month]} {$year}",
            "Zatwierdzona lista p\u0142ac za {$monthNames[$month]} {$year}. Koszt pracodawcy: {$totalCost} PLN.",
            'success',
            '/client/hr/payslips'
        );
    }

    public static function notifyPayrollLocked(int $clientId, int $month, int $year): void
    {
        $monthNames = ['','Stycze\u0144','Luty','Marzec','Kwiecie\u0144','Maj','Czerwiec',
                       'Lipiec','Sierpie\u0144','Wrzesie\u0144','Pa\u017adziernik','Listopad','Grudzie\u0144'];
        Notification::create(
            'client',
            $clientId,
            "Lista p\u0142ac zablokowana: {$monthNames[$month]} {$year}",
            "Lista p\u0142ac za {$monthNames[$month]} {$year} zosta\u0142a zablokowana i jest ostateczna.",
            'info',
            '/client/hr/payslips'
        );
    }

    public static function notifyLeaveCreatedByOffice(int $clientId, string $employeeName, string $dateFrom, string $dateTo): void
    {
        Notification::create(
            'client',
            $clientId,
            "Nowy wniosek urlopowy: {$employeeName}",
            "Biuro z\u0142o\u017cy\u0142o wniosek urlopowy dla {$employeeName} ({$dateFrom} \u2014 {$dateTo}). Wymaga akceptacji.",
            'warning',
            '/client/hr/leaves?status=pending'
        );
    }

    public static function notifyLeaveApprovedByOffice(int $clientId, string $employeeName, string $dateFrom, string $dateTo): void
    {
        Notification::create(
            'client',
            $clientId,
            "Urlop zatwierdzony: {$employeeName}",
            "Wniosek urlopowy {$employeeName} ({$dateFrom} \u2014 {$dateTo}) zosta\u0142 zatwierdzony przez biuro.",
            'success',
            '/client/hr/leaves?status=approved'
        );
    }

    public static function notifyLeaveRejectedByOffice(int $clientId, string $employeeName): void
    {
        Notification::create(
            'client',
            $clientId,
            "Urlop odrzucony: {$employeeName}",
            "Wniosek urlopowy {$employeeName} zosta\u0142 odrzucony przez biuro.",
            'danger',
            '/client/hr/leaves?status=rejected'
        );
    }

    public static function notifyZusGenerated(int $clientId, int $month, int $year): void
    {
        $monthNames = ['','Stycze\u0144','Luty','Marzec','Kwiecie\u0144','Maj','Czerwiec',
                       'Lipiec','Sierpie\u0144','Wrzesie\u0144','Pa\u017adziernik','Listopad','Grudzie\u0144'];
        Notification::create(
            'client',
            $clientId,
            "Deklaracja ZUS wygenerowana: {$monthNames[$month]} {$year}",
            "Deklaracja ZUS DRA za {$monthNames[$month]} {$year} jest gotowa.",
            'info',
            '/client/hr/costs'
        );
    }

    public static function notifyPitGenerated(int $clientId, string $type, int $year): void
    {
        Notification::create(
            'client',
            $clientId,
            "Deklaracja {$type} wygenerowana: {$year}",
            "Deklaracja {$type} za rok {$year} jest gotowa do pobrania.",
            'info',
            '/client/hr/costs'
        );
    }

    public static function notifyEmployeeArchived(int $clientId, string $employeeName, string $reason): void
    {
        $reasonLabels = [
            'end_of_contract' => 'zako\u0144czenie umowy',
            'resignation'     => 'rezygnacja',
            'dismissal'       => 'zwolnienie',
            'other'           => 'inne',
        ];
        $reasonText = $reasonLabels[$reason] ?? $reason;
        Notification::create(
            'client',
            $clientId,
            "Pracownik zarchiwizowany: {$employeeName}",
            "Pracownik {$employeeName} zosta\u0142 zarchiwizowany. Pow\u00f3d: {$reasonText}.",
            'warning',
            '/client/hr/employees'
        );
    }
}
