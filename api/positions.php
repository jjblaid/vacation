<?php
error_reporting(0);
ini_set('display_errors', 0);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$_PARSED_BODY = json_decode(file_get_contents('php://input'), true) ?? [];

function requireCsrfToken() {
    global $_PARSED_BODY;
    $token = $_PARSED_BODY['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 토큰이 유효하지 않습니다.']);
        exit;
    }
}

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
    
    if ($action === 'create' || $action === 'update' || $action === 'delete') {
        if ($_SESSION['user']['role'] !== 'system_admin') {
            http_response_code(403);
            echo json_encode(['error' => '권한이 없습니다.']);
            exit;
        }
    }
}

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT * FROM positions WHERE is_active = 1 ORDER BY sort_order, name");
            $positions = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $positions]);
            break;
            
        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM positions WHERE id = ?");
            $stmt->execute([$id]);
            $position = $stmt->fetch();
            if (!$position) {
                http_response_code(404);
                echo json_encode(['error' => '직급을 찾을 수 없습니다.']);
            } else {
                echo json_encode(['success' => true, 'data' => $position]);
            }
            break;
            
        case 'create':
            requireCsrfToken();
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => '직급명은 필수입니다.']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO positions (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
            
        case 'update':
            requireCsrfToken();
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => '직급명은 필수입니다.']);
                break;
            }
            
            $stmt = $db->prepare("UPDATE positions SET name=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $is_active, $id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            requireCsrfToken();
            $id = intval($_POST['id'] ?? 0);
            
            // 해당 직급을 사용 중인 사원들의 position_id를 NULL로 설정 (2b 적용)
            $stmt = $db->prepare("UPDATE employees SET position_id = NULL WHERE position_id = ?");
            $stmt->execute([$id]);
            
            // 직급 삭제
            $stmt = $db->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => '삭제되었습니다. (해당 직급을 사용 중인 사원의 직급은 초기화되었습니다.)']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '서버 오류: ' . $e->getMessage()]);
}
