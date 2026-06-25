<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

function requireAdmin() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => '로그인이 필요합니다.']);
        exit;
    }
    if ($_SESSION['user']['role'] !== 'system_admin') {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        exit;
    }
}

requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        $keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from_email', 'smtp_auth'];
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = getSetting($k);
        }
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'save':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => '잘못된 요청입니다.']);
            break;
        }
        $allowed = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from_email', 'smtp_auth'];
        foreach ($allowed as $k) {
            if (isset($input[$k])) {
                setSetting($k, trim($input[$k]));
            }
        }
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
