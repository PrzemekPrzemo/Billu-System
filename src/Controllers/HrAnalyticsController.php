<?php

namespace App\Controllers;

use App\Services\HrAnalyticsService;

class HrAnalyticsController extends HrController
{
    public function analytics(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $year = (int) ($_GET['year'] ?? date('Y'));

        $kpi          = HrAnalyticsService::getKpiSummary($clientId);
        $costTrend    = HrAnalyticsService::getMonthlyCostTrend($clientId, 12);
        $distribution = HrAnalyticsService::getContractTypeDistribution($clientId);
        $topEmployees = HrAnalyticsService::getTopEmployeesByCost($clientId, 5);
        $rotationRate = HrAnalyticsService::getRotationRate($clientId, $year);

        $this->render('office/hr/analytics', compact(
            'client', 'clientId', 'year',
            'kpi', 'costTrend', 'distribution', 'topEmployees', 'rotationRate'
        ));
    }
}
