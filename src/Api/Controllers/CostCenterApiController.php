<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\ClientCostCenter;

class CostCenterApiController
{
    // GET /api/v1/profile/cost-centers
    public function index(array $params, ?int $clientId): void
    {
        $centers = ClientCostCenter::findByClient($clientId, true);

        ApiResponse::success(array_map(fn($c) => [
            'id'   => (int) $c['id'],
            'name' => $c['name'],
        ], $centers));
    }
}
