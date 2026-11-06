<?php
declare(strict_types=1);

ob_start();
// Ensure all errors return JSON so the frontend can display them
set_exception_handler(function (\Throwable $e) {
    if (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => ['file' => basename($e->getFile()), 'line' => $e->getLine()]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal: ' . $err['message'],
            'debug' => ['file' => basename($err['file']), 'line' => $err['line']]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/core/ApiResponse.php';
require_once dirname(__DIR__) . '/core/SessionHelper.php';
require_once dirname(__DIR__) . '/services/CultivationService.php';
require_once dirname(__DIR__) . '/services/RealmService.php';

use Game\Helper\ApiResponse;
use Game\Helper\SessionHelper;
use Game\Core\Validator;
use Game\Service\CultivationService;
use Game\Service\RealmService;

$userId = SessionHelper::requireUserIdForApi();
Validator::requirePost();

// Transport fields do not affect gameplay; no gameplay parameters are currently accepted.
$allowed = ['action', 'operation_key', '_token'];
if (array_diff(array_keys($_POST), $allowed) !== [] || (isset($_POST['action']) && $_POST['action'] !== 'cultivate')) {
    ApiResponse::error('Invalid cultivation input.', 422, ['operation_status' => 'invalid_request']);
}
$key = $_POST['operation_key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');
if (isset($_POST['operation_key'], $_SERVER['HTTP_IDEMPOTENCY_KEY']) && $_POST['operation_key'] !== $_SERVER['HTTP_IDEMPOTENCY_KEY']) {
    ApiResponse::error('Conflicting request keys.', 422, ['operation_status' => 'invalid_request']);
}
if (!is_string($key) || !\App\Support\GameplayFingerprint::validKey($key)) {
    ApiResponse::error('A valid cultivation request key is required.', 422, ['operation_status' => 'invalid_request']);
}
$result = (new CultivationService())->cultivate($userId, $key);
$status = $result['operation_status'] ?? 'unavailable';
if (!$result['success']) {
    $http = $status === 'conflict' ? 409 : (in_array($status, ['uncertain', 'unavailable', 'rolled_back', 'busy'], true) ? 503 : 400);
    ApiResponse::error($result['error'] ?? 'Cultivation failed.', $http, $result);
}
// Return the original committed display snapshot, including on replay. No post-commit DB work.
$payload = array_merge($result, $result['display']);
unset($payload['display']);
ApiResponse::success($payload);
