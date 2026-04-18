<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrPfronDeclaration;
use App\Services\HrPfronService;

class HrPfronController extends HrController
{
    public function pfron(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $selectedYear = (int) ($_GET['year'] ?? date('Y'));
        $declarations = HrPfronDeclaration::findByClient($clientId, $selectedYear);
        $years = HrPfronDeclaration::getYearsForClient($clientId);
        if (empty($years)) $years = [(int) date('Y')];

        $this->render('office/hr/pfron', compact('client', 'clientId', 'declarations', 'years', 'selectedYear'));
    }

    public function pfronCalculate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/pfron");
            return;
        }

        $month = (int) ($_POST['month'] ?? date('n'));
        $year  = (int) ($_POST['year'] ?? date('Y'));

        try {
            $result = HrPfronService::calculate($clientId, $month, $year, $this->actorType(), $this->actorId());
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_pfron_calculate',
                json_encode(['client_id' => $clientId, 'month' => $month, 'year' => $year, 'levy' => $result['levy_amount']]),
                'hr_pfron_declaration', $result['id']);

            if ($result['pfron_liable']) {
                Session::flash('success', "PFRON obliczony: wp\u0142ata {$result['levy_amount']} PLN (ratio: " . round($result['disability_ratio'] * 100, 2) . "%).");
            } else {
                Session::flash('success', "PFRON: brak zobowi\u0105zania (pracownik\u00f3w: {$result['total_employees']}, niepe\u0142nosprawnych: {$result['disabled_employees']}).");
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d obliczania PFRON: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/pfron?year={$year}");
    }
}
