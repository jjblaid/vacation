<?php
error_reporting(0);
ini_set('display_errors', 0);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit;
}

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT * FROM condolence_types WHERE is_active = 1 ORDER BY sort_order");
            $types = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $types]);
            break;
            
        case 'get':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM condolence_types WHERE id = ?");
            $stmt->execute([$id]);
            $type = $stmt->fetch();
            if (!$type) {
                http_response_code(404);
                echo json_encode(['error' => '경조사 유형을 찾을 수 없습니다.']);
            } else {
                echo json_encode(['success' => true, 'data' => $type]);
            }
            break;
            
        case 'get_used':
            $typeId = intval($_GET['type_id'] ?? 0);
            $userId = intval($_SESSION['user']['id'] ?? 0);
            
            $nowYear = date('Y');
            $nowYearStart = "{$nowYear}-01-01";
            $nowYearEnd = ($nowYear + 1) . "-01-01";
            $stmt = $db->prepare("SELECT COALESCE(SUM(vr.days), 0) as used_days 
                                  FROM vacation_requests vr
                                  JOIN vacation_types vt ON vr.vacation_type_id = vt.id
                                  WHERE vr.employee_id = ? 
                                  AND vr.condolence_type_id = ?
                                  AND vr.status IN ('applied', 'approved')
                                  AND vt.name = '경조사'
                                  AND vr.start_date >= ? AND vr.start_date < ?");
            $stmt->execute([$userId, $typeId, $nowYearStart, $nowYearEnd]);
            $result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'used_days' => floatval($result['used_days'])
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '서버 오류: ' . $e->getMessage()]);
}
