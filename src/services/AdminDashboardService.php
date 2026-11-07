<?php
require_once __DIR__ . '/../models/PlanModel.php';
require_once __DIR__ . '/../models/PageVisitModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class AdminDashboardService
{
    public static function build(PDO $pdo): array
    {
        PlanModel::ensureSchema($pdo);
        PageVisitModel::ensureSchema($pdo);
        UsuarioModel::ensureSchema($pdo);

        $userSummary = UsuarioModel::getResumen($pdo);
        $visitSummary = PageVisitModel::getSummary($pdo);
        $plans = PlanModel::getSelectablePlans($pdo);
        $planCounts = UsuarioModel::getUserCountsByPlan($pdo);
        $planActivity = PageVisitModel::getPlanActivity($pdo);

        foreach ($plans as &$plan) {
            $planId = (int) ($plan['id_plan'] ?? 0);
            $plan['usuarios_total'] = (int) ($planCounts[$planId]['usuarios_total'] ?? 0);
            $plan['usuarios_activos_7d'] = (int) ($planActivity[$planId]['usuarios_activos_7d'] ?? 0);
            $plan['visitas_30d'] = (int) ($planActivity[$planId]['visitas_30d'] ?? 0);
            $plan['precio_label'] = PlanModel::formatPriceLabel($plan);
        }
        unset($plan);

        return [
            'summary' => [
                'customer_users' => (int) ($userSummary['users'] ?? 0),
                'admin_users' => (int) ($userSummary['admins'] ?? 0),
                'page_visits_30d' => (int) ($visitSummary['page_visits_30d'] ?? 0),
                'active_users_7d' => (int) ($visitSummary['active_users_7d'] ?? 0),
                'frequent_users_30d' => (int) ($visitSummary['frequent_users_30d'] ?? 0),
                'active_countries_30d' => (int) ($visitSummary['active_countries_30d'] ?? 0),
            ],
            'plans' => $plans,
            'countries' => PageVisitModel::getCountryBreakdown($pdo, 10),
            'routes' => PageVisitModel::getTopRoutes($pdo, 8),
        ];
    }
}
