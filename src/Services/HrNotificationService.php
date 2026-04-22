<?php

namespace App\Services;

use App\Models\Notification;

class HrNotificationService
{
    public static function notifyPayrollApproved(int $clientId, int $month, int $year, string $totalCost): void
    {
        $monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                       'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
        Notification::create(
            'client', $clientId,
            "Lista płac zatwierdzona: {$monthNames[$month]} {$year}",
            "Zatwierdzona lista płac za {$monthNames[$month]} {$year}. Koszt pracodawcy: {$totalCost} PLN.",
            'success', '/client/hr/payslips'
        );
    }

    public static function notifyPayrollLocked(int $clientId, int $month, int $year): void
    {
        $monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                       'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
        Notification::create(
            'client', $clientId,
            "Lista płac zablokowana: {$monthNames[$month]} {$year}",
            "Lista płac za {$monthNames[$month]} {$year} została zablokowana i jest ostateczna.",
            'info', '/client/hr/payslips'
        );
    }

    public static function notifyLeaveCreatedByOffice(int $clientId, string $employeeName, string $dateFrom, string $dateTo): void
    {
        Notification::create(
            'client', $clientId,
            "Nowy wniosek urlopowy: {$employeeName}",
            "Biuro złożyło wniosek urlopowy dla {$employeeName} ({$dateFrom} — {$dateTo}). Wymaga akceptacji.",
            'warning', '/client/hr/leaves?status=pending'
        );
    }

    public static function notifyLeaveApprovedByOffice(int $clientId, string $employeeName, string $dateFrom, string $dateTo): void
    {
        Notification::create(
            'client', $clientId,
            "Urlop zatwierdzony: {$employeeName}",
            "Wniosek urlopowy {$employeeName} ({$dateFrom} — {$dateTo}) został zatwierdzony przez biuro.",
            'success', '/client/hr/leaves?status=approved'
        );
    }

    public static function notifyLeaveRejectedByOffice(int $clientId, string $employeeName): void
    {
        Notification::create(
            'client', $clientId,
            "Urlop odrzucony: {$employeeName}",
            "Wniosek urlopowy {$employeeName} został odrzucony przez biuro.",
            'danger', '/client/hr/leaves?status=rejected'
        );
    }

    public static function notifyZusGenerated(int $clientId, int $month, int $year): void
    {
        $monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                       'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
        Notification::create(
            'client', $clientId,
            "Deklaracja ZUS wygenerowana: {$monthNames[$month]} {$year}",
            "Deklaracja ZUS DRA za {$monthNames[$month]} {$year} jest gotowa.",
            'info', '/client/hr/costs'
        );
    }

    public static function notifyPitGenerated(int $clientId, string $type, int $year): void
    {
        Notification::create(
            'client', $clientId,
            "Deklaracja {$type} wygenerowana: {$year}",
            "Deklaracja {$type} za rok {$year} jest gotowa do pobrania.",
            'info', '/client/hr/costs'
        );
    }

    public static function notifyEmployeeArchived(int $clientId, string $employeeName, string $reason): void
    {
        $reasonLabels = [
            'end_of_contract' => 'zakończenie umowy',
            'resignation'     => 'rezygnacja',
            'dismissal'       => 'zwolnienie',
            'other'           => 'inne',
        ];
        $reasonText = $reasonLabels[$reason] ?? $reason;
        Notification::create(
            'client', $clientId,
            "Pracownik zarchiwizowany: {$employeeName}",
            "Pracownik {$employeeName} został zarchiwizowany. Powód: {$reasonText}.",
            'warning', '/client/hr/employees'
        );
    }
}
