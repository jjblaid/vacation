<?php
/**
 * Authentication API
 * Login / Logout
 */

if (!defined('AUTH_INCLUDED')) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/security.php';
    header('Content-Type: application/json; charset=utf-8');
}

function requireAuth() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => '로그인이 필요합니다.']);
        exit;
    }
}

if (!defined('AUTH_INCLUDED')) {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'login':
            login();
            break;
        case 'logout':
            logout();
            break;
        case 'check':
            checkSession();
            break;
        case 'change_password':
            changePassword();
            break;
        case 'verify_password':
            verifyPassword();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken() {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 토큰이 유효하지 않습니다.']);
        exit;
    }
}

function login() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $emp_no = trim($input['emp_no'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($emp_no) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => '계정와 비밀번호를 입력해주세요.']);
        return;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT e.*, d.name as department_name
                           FROM employees e
                           LEFT JOIN departments d ON e.department_id = d.id
                           WHERE e.emp_no = ? AND e.is_active = 1");
    $stmt->execute([$emp_no]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => '계정 또는 비밀번호가 맞지 않습니다.']);
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = [
        'id' => $user['id'],
        'emp_no' => $user['emp_no'],
        'name' => $user['name'],
        'role' => $user['role'],
        'department_id' => $user['department_id'],
        'managed_department_id' => $user['managed_department_id'],
        'annual_leave' => floatval($user['annual_leave']),
        'phone1' => $user['phone1'] ?? '',
        'phone2' => $user['phone2'] ?? ''
    ];
    
    generateCsrfToken();

    $db->prepare("INSERT INTO login_log (employee_id, emp_no, name, role, department_name, login_at, last_activity, session_id, ip_address)
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)")
       ->execute([$user['id'], $user['emp_no'], $user['name'], $user['role'],
                  $user['department_name'] ?? null, session_id(),
                  $_SERVER['REMOTE_ADDR'] ?? null]);
    
    echo json_encode([
        'success' => true,
        'user' => $_SESSION['user'],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

function logout() {
    $sid = session_id();
    $db = getDB();
    $db->prepare("UPDATE login_log SET logout_at = NOW() WHERE session_id = ? AND logout_at IS NULL")
       ->execute([$sid]);
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    session_destroy();
    header('Location: ../index.php');
    exit;
}

function checkSession() {
    if (isset($_SESSION['user'])) {
        autoCreateNextYearLeave();
        
        $db = getDB();
        $db->prepare("UPDATE login_log SET last_activity = NOW() WHERE session_id = ? AND logout_at IS NULL")
           ->execute([session_id()]);
        $currentYear = date('Y');
        
        $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
        $stmt->execute([$_SESSION['user_id'], $currentYear]);
        $leave = $stmt->fetch();
        $granted = $leave ? floatval($leave['annual_leave']) : 0;
        
        $authYearStart = "{$currentYear}-01-01";
        $authYearEnd = ($currentYear + 1) . "-01-01";
        $stmt = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                              FROM vacation_requests 
                              WHERE employee_id = ? 
                              AND status IN ('applied', 'approved')
                              AND start_date >= ? AND start_date < ?");
        $stmt->execute([$_SESSION['user_id'], $authYearStart, $authYearEnd]);
        $result = $stmt->fetch();
        $used = floatval($result['used'] ?? 0);
        
        $_SESSION['user']['annual_leave'] = $granted - $used;
        
        generateCsrfToken();
        
        echo json_encode(['success' => true, 'user' => $_SESSION['user'], 'csrf_token' => $_SESSION['csrf_token']]);
    } else {
        echo json_encode(['success' => false]);
    }
}

function autoCreateNextYearLeave() {
    $db = getDB();
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    
    if (date('n') != 12) return;
    
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM annual_by_year WHERE year = ?");
    $stmt->execute([$nextYear]);
    $result = $stmt->fetch();
    
    if (intval($result['cnt']) == 0) {
        $stmt = $db->prepare("INSERT INTO annual_by_year (employee_id, year, annual_leave)
                              SELECT id, ?, 15 FROM employees WHERE is_active = 1
                              ON DUPLICATE KEY UPDATE annual_leave = VALUES(annual_leave)");
        $stmt->execute([$nextYear]);
        
        $stmt = $db->prepare("UPDATE employees SET annual_leave = 15 WHERE is_active = 1");
        $stmt->execute();
    }
}

function changePassword() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 토큰이 유효하지 않습니다.']);
        return;
    }
    
    $user = $_SESSION['user'];
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['error' => '모든 필드를 입력해주세요.']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => '새 비밀번호가 일치하지 않습니다.']);
        return;
    }
    
    if (strlen($newPassword) < 4) {
        http_response_code(400);
        echo json_encode(['error' => '비밀번호는 4자 이상이어야 합니다.']);
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT password FROM employees WHERE id = ?");
    $stmt->execute([$user['id']]);
    $emp = $stmt->fetch();
    
    if (!$emp || !password_verify($currentPassword, $emp['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '현재 비밀번호가 올바르지 않습니다.']);
        return;
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
    $stmt->execute([$newHash, $user['id']]);
    
    echo json_encode(['success' => true]);
}

function verifyPassword() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => '비밀번호를 입력해주세요.']);
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT password FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $emp = $stmt->fetch();
    
    if (!$emp || !password_verify($password, $emp['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '비밀번호가 올바르지 않습니다.']);
        return;
    }
    
    echo json_encode(['success' => true]);
}
