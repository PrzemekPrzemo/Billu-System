<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Language;
use App\Core\Session;
use App\Services\HrAccessService;
use App\Services\HrBatchService;

class HrBatchController extends Controller
{
    private int $officeId;

    public function __construct()
    {
        Auth::requireOffice();
        $lang = Session::get('office_language', 'pl');
        Language::setLocale($lang);
        $this->officeId = (int) Session::get('office_id');

        if (!HrAccessService::isEnabledForOffice($this->officeId)) {
            Session::flash('error', 'Modu\u0142 Kadry i P\u0142ace nie jest aktywny.');
            $this->redirect('/office');
        }
    }

    public function dashboard(): void
    {
        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_GET['year'] ?? date('Y')));

        $clients = HrBatchService::getClientsOverview($this->officeId, $month, $year);

        $totalEmployees = array_sum(array_column($clients, 'employee_count'));
        $totalPending   = array_sum(array_column($clients, 'pending_leaves'));
        $clientsWithHr  = count($clients);
        $missingPayroll = count(array_filter($clients, fn($c) => $c['employee_count'] > 0 && !$c['payroll_status']));

        $this->render('office/hr/multi_client_dashboard', compact(
            'clients', 'month', 'year',
            'totalEmployees', 'totalPending', 'clientsWithHr', 'missingPayroll'
        ));
    }

    public function compliance(): void
    {
        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_GET['year'] ?? date('Y')));

        $matrix = HrBatchService::getComplianceMatrix($this->officeId, $month, $year);

        $zusDeadline = date('d.m.Y', mktime(0,0,0, $month + 1, 15, $year));
        $pitDeadline = date('d.m.Y', mktime(0,0,0, $month + 1, 20, $year));

        $this->render('office/hr/compliance_matrix', compact('matrix', 'month', 'year', 'zusDeadline', 'pitDeadline'));
    }
}
