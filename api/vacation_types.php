<?php
error_reporting(0);
ini_set('display_errors', 0);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$_PARSED_BODY = json_decode(file_get_contents('php://input'), true) ?? [];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

if ($action !== 'list') {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => '로그인이 필요합니다.']);
        exit;
    }
    
    if ($action === 'create' || $action === 'update' || $action === 'delete' || $action === 'reorder') {
        if ($_SESSION['user']['role'] !== 'system_admin') {
            http_response_code(403);
            echo json_encode(['error' => '권한이 없습니다.']);
            exit;
        }
    }
}

function requireCsrfToken() {
    global $_PARSED_BODY;
    $token = $_PARSED_BODY['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 토큰이 유효하지 않습니다.']);
        exit;
    }
}

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT * FROM vacation_types WHERE is_active = 1 ORDER BY sort_order");
            $types = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $types]);
            break;
            
        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM vacation_types WHERE id = ?");
            $stmt->execute([$id]);
            $type = $stmt->fetch();
            if (!$type) {
                http_response_code(404);
                echo json_encode(['error' => '휴가 유형을 찾을 수 없습니다.']);
            } else {
                echo json_encode(['success' => true, 'data' => $type]);
            }
            break;
            
        case 'create':
            requireCsrfToken();
            $name = trim($_POST['name'] ?? '');
            $deduction = floatval($_POST['deduction'] ?? 0);
            $max_days = floatval($_POST['max_days'] ?? 999);
            $deduct_from = $_POST['deduct_from'] ?? 'none';
            $color = $_POST['color'] ?? '#667eea';
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $count_all_days = intval($_POST['count_all_days'] ?? 0);

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => '유형명은 필수입니다.']);
                break;
            }

            $stmt = $db->prepare("INSERT INTO vacation_types (name, deduction, max_days, deduct_from, color, sort_order, count_all_days) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $deduction, $max_days, $deduct_from, $color, $sort_order, $count_all_days]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
            
        case 'update':
            requireCsrfToken();
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $deduction = floatval($_POST['deduction'] ?? 0);
            $max_days = floatval($_POST['max_days'] ?? 999);
            $deduct_from = $_POST['deduct_from'] ?? 'none';
            $color = $_POST['color'] ?? '#667eea';
            $is_active = intval($_POST['is_active'] ?? 1);
            $count_all_days = intval($_POST['count_all_days'] ?? 0);

            $stmt = $db->prepare("UPDATE vacation_types SET name=?, deduction=?, max_days=?, deduct_from=?, color=?, is_active=?, count_all_days=? WHERE id=?");
            $stmt->execute([$name, $deduction, $max_days, $deduct_from, $color, $is_active, $count_all_days, $id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'reorder':
            requireCsrfToken();
            $ids = $_PARSED_BODY['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['error' => '유효하지 않은 요청입니다.']);
                break;
            }
            $stmt = $db->prepare("UPDATE vacation_types SET sort_order = ? WHERE id = ?");
            foreach ($ids as $index => $id) {
                $stmt->execute([$index + 1, intval($id)]);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            requireCsrfToken();
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vacation_requests WHERE vacation_type_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['cnt'] > 0) {
                $stmt = $db->prepare("UPDATE vacation_types SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => '사용 중인 유형은 비활성화했습니다.']);
            } else {
                $stmt = $db->prepare("DELETE FROM vacation_types WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '서버 오류: ' . $e->getMessage()]);
}
