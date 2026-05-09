<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/ApiResponse.php';
require_once dirname(__DIR__) . '/core/SessionHelper.php';
require_once dirname(__DIR__) . '/services/ExplorationService.php';
require_once dirname(__DIR__) . '/services/ActivityService.php';
require_once dirname(__DIR__) . '/services/TitleService.php';

use Game\Helper\ApiResponse;
use Game\Helper\SessionHelper;
use Game\Service\ExplorationService;
use Game\Service\ActivityService;
use Game\Service\TitleService;

$userId = SessionHelper::requireUserIdForApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed.', 405);
}

$regionId = (int)($_POST['region_id'] ?? $_GET['region_id'] ?? 0);
if ($regionId < 1) {
    ApiResponse::error('Invalid region.');
}

$service = new ExplorationService();
$result = $service->exploreRegion($userId, $regionId);

if (!$result['success']) {
    $extra = [];
    foreach (['cooldown_remaining', 'explore_burst_used', 'explore_burst_max', 'explore_in_long_rest'] as $k) {
        if (array_key_exists($k, $result)) {
            $extra[$k] = $result[$k];
        }
    }
    ApiResponse::error($result['message'] ?? 'Exploration failed.', 400, $extra !== [] ? $extra : null);
}

try {
    (new ActivityService())->recordExploration($userId);
} catch (\Throwable $e) {
    error_log('Activity exploration: ' . $e->getMessage());
}
try {
    (new TitleService())->onExplore($userId);
} catch (\Throwable $e) {
    error_log('Title exploration: ' . $e->getMessage());
}

$payload = [
    'event_type' => $result['event_type'] ?? 'nothing',
    'cooldown_remaining' => (int)($result['cooldown_remaining'] ?? 0),
    'region_name' => $result['region_name'] ?? '',
    'explore_burst_used' => (int)($result['explore_burst_used'] ?? 0),
    'explore_burst_max' => (int)($result['explore_burst_max'] ?? 10),
    'explore_in_long_rest' => (bool)($result['explore_in_long_rest'] ?? false),
];
if (isset($result['data'])) {
    $payload['data'] = $result['data'];
}

ApiResponse::success($payload, $result['message'] ?? 'Exploration complete.');


