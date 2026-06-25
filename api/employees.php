<?php
/**
 * Employees API
 * CRUD operations
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

function requireAuth() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        $json = json_encode(['error' => '로그인이 필요합니다.']);
        error_log("requireAuth failed, session: " . print_r($_SESSION, true));
        echo $json;
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'system_admin') {
        http_response_code(403);
        $json = json_encode(['error' => '권한이 없습니다.']);
        error_log("requireAdmin failed, role: " . $_SESSION['user']['role']);
        echo $json;
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        getList();
        break;
    case 'get':
        getEmployee();
        break;
    case 'create':
        createEmployee();
        break;
    case 'update':
        updateEmployee();
        break;
    case 'delete':
        deleteEmployee();
        break;
    case 'departments':
        getDepartments();
        break;
    case 'severance_usage':
        getSeveranceUsage();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getList() {
    requireAuth();
    $db = getDB();
    
    $sql = "SELECT e.*, d.name as department_name, d.code as department_code,
            md.name as managed_department_name,
            p.name as position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN departments md ON e.managed_department_id = md.id
            LEFT JOIN positions p ON e.position_id = p.id";
    
    $params = [];
    if (isset($_GET['active'])) {
        $sql .= " WHERE e.is_active = ?";
        $params[] = intval($_GET['active']);
    }
    
    if (isset($_GET['active'])) {
        $active = intval($_GET['active']);
        if ($active === 1) {
            $sql .= " ORDER BY e.hire_date";
        } elseif ($active === 0) {
            $sql .= " ORDER BY e.resignation_date";
        } else {
            $sql .= " ORDER BY e.created_at DESC";
        }
    } else {
        $sql .= " ORDER BY e.created_at DESC";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    foreach ($employees as &$emp) {
        if ($_SESSION['user']['role'] !== 'system_admin') {
            unset($emp['department_code']);
        }
        unset($emp['password']);
    }
    
    echo json_encode(['success' => true, 'data' => $employees]);
}

function getEmployee() {
    requireAuth();
    $id = $_GET['id'] ?? $_SESSION['user_id'];
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['error' => '사원을 찾을 수 없습니다.']);
        return;
    }
    
    unset($emp['password']);
    if (!empty($emp['resident_no_encrypted'])) {
        $emp['resident_no'] = decryptResidentNo($emp['resident_no_encrypted']);
    } else {
        $emp['resident_no'] = '';
    }
    echo json_encode(['success' => true, 'data' => $emp]);
}

function createEmployee() {
    requireAdmin();
    
    $data = $_POST;
    $emp_no = trim($data['emp_no'] ?? '');
    $name = trim($data['name'] ?? '');
    $department_id = $data['department_id'] ?: null;
    $position = trim($data['position'] ?? '');
    $position_id = $data['position_id'] ?: null;
    $role = $data['role'] ?? 'user';
    $managed_department_id = $data['managed_department_id'] ?: null;
    $annual_leave = floatval($data['annual_leave'] ?? 15);
    $phone1 = trim($data['phone1'] ?? '');
    $phone2 = trim($data['phone2'] ?? '');
    $email = trim($data['email'] ?? '');
    $birth_date = $data['birth_date'] ?: null;
    $hire_date = $data['hire_date'] ?: null;
    $address = trim($data['address'] ?? '');
    $resident_no_encrypted = !empty($data['resident_no']) ? encryptResidentNo($data['resident_no']) : '';
    $is_active = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
    $is_resigning = isset($data['is_resigning']) ? ($data['is_resigning'] ? 1 : 0) : 0;
    $severance_leave = floatval($data['severance_leave'] ?? 0);
    $visible_to_exec = isset($data['visible_to_exec']) ? ($data['visible_to_exec'] ? 1 : 0) : 0;
    $resignation_date = $data['resignation_date'] ?: null;
    $password = $data['password'] ?? 'password123';

    if (empty($emp_no) || empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => '계정와 이름은 필수입니다.']);
        return;
    }

    $db = getDB();
    
    $check = $db->prepare("SELECT id FROM employees WHERE emp_no = ?");
    $check->execute([$emp_no]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => '이미 존재하는 계정입니다.']);
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

try {
        $stmt = $db->prepare("INSERT INTO employees (emp_no, name, department_id, position, position_id, role, managed_department_id, annual_leave, phone1, phone2, email, birth_date, hire_date, address, resident_no_encrypted, password, is_active, is_resigning, severance_leave, visible_to_exec, resignation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$emp_no, $name, $department_id, $position, $position_id, $role, $managed_department_id, $annual_leave, $phone1, $phone2, $email, $birth_date, $hire_date, $address, $resident_no_encrypted, $hash, $is_active, $is_resigning, $severance_leave, $visible_to_exec, $resignation_date]);
        $newId = $db->lastInsertId();
        
        $currentYear = date('Y');
        $stmt = $db->prepare("INSERT INTO annual_by_year (employee_id, year, annual_leave, used_all) VALUES (?, ?, ?, 0)");
        $stmt->execute([$newId, $currentYear, $annual_leave]);
        
        echo json_encode(['success' => true, 'id' => $newId]);
    } catch (Exception $ee) {
        error_log("Insert error: " . $ee->getMessage());
        echo json_encode(['error' => $ee->getMessage()]);
    }
}

function updateEmployee() {
    requireAdmin();
    
    $id = $_POST['id'] ?? 0;
    $data = $_POST;
    
    $db = getDB();
    
    $check = $db->prepare("SELECT * FROM employees WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '사원을 찾을 수 없습니다.']);
        return;
    }

    $updates = [];
    $params = [];

    $fields = ['emp_no', 'name', 'department_id', 'position', 'position_id', 'role', 'managed_department_id', 'annual_leave', 'phone1', 'phone2', 'email', 'birth_date', 'address', 'is_active', 'is_resigning', 'severance_leave', 'visible_to_exec', 'resignation_date', 'hire_date'];
    
    // Handle resident_no encryption (only when non-empty to preserve existing value)
    if (!empty($data['resident_no'])) {
        $fields[] = 'resident_no_encrypted';
        $data['resident_no_encrypted'] = encryptResidentNo($data['resident_no']);
    }
    
    foreach ($fields as $field) {
            $updates[] = "$field = ?";
            $val = $data[$field];
            if ($field === 'resignation_date' || $field === 'hire_date' || $field === 'birth_date') {
                $val = $val ?: null;
            } elseif ($field === 'department_id' || $field === 'managed_department_id') {
                $val = $val ?: null;
            }
            $params[] = $val;
        }

      // Force is_resigning to 0 when marking as inactive (resigned)
       if (isset($data['is_active']) && !$data['is_active']) {
           $found = false;
           foreach ($updates as $i => $update) {
               if (strpos($update, 'is_resigning = ') === 0) {
                   $params[$i] = '0';
                   $found = true;
                   break;
               }
           }
           if (!$found) {
               $updates[] = 'is_resigning = ?';
               $params[] = '0';
           }
       }

     if (!empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => '수정할 데이터가 없습니다.']);
        return;
    }

    $params[] = $id;
    $sql = "UPDATE employees SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB 업데이트 실패: ' . $e->getMessage()]);
        return;
    }
    
    if (isset($data['annual_leave'])) {
        $currentYear = date('Y');
        
        $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
        $stmt->execute([$id, $currentYear]);
        $oldLeave = $stmt->fetch();
        $newRemaining = floatval($data['annual_leave']);
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                              FROM vacation_requests 
                              WHERE employee_id = ? 
                              AND status IN ('applied', 'approved')
                              AND YEAR(start_date) = ?");
        $stmt->execute([$id, $currentYear]);
        $result = $stmt->fetch();
        $used = floatval($result['used'] ?? 0);
        
        $newGranted = $newRemaining + $used;
        
        $stmt = $db->prepare("UPDATE employees SET annual_leave = ? WHERE id = ?");
        $stmt->execute([$newRemaining, $id]);
        
        $stmt = $db->prepare("INSERT INTO annual_by_year (employee_id, year, annual_leave, used_all) 
                              VALUES (?, ?, ?, 0)
                              ON DUPLICATE KEY UPDATE annual_leave = ?, used_all = 0");
        $stmt->execute([$id, $currentYear, $newGranted, $newGranted]);
    }
    
    echo json_encode(['success' => true]);
}

function getSeveranceUsage() {
    requireAdmin();
    
    $employee_id = intval($_GET['employee_id'] ?? 0);
    $year = intval($_GET['year'] ?? date('Y'));
    
    if ($employee_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => '사원 ID가 필요합니다.']);
        return;
    }
    
    $db = getDB();
    
    // DB의 severance_leave는 REMAINING 값
    $stmt = $db->prepare("SELECT severance_leave FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    $severanceRemaining = $emp ? floatval($emp['severance_leave']) : 0;
    
    // Calculate used severance leave for the year
    $yearStart = "{$year}-01-01";
    $yearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare("SELECT COALESCE(SUM(severance_deduct_days), 0) as used 
                          FROM vacation_requests
                          WHERE employee_id = ? 
                          AND status IN ('applied', 'approved')
                          AND start_date >= ? AND start_date < ?");
    $stmt->execute([$employee_id, $yearStart, $yearEnd]);
    $result = $stmt->fetch();
    $severanceUsed = floatval($result['used'] ?? 0);
    $severanceGranted = $severanceRemaining + $severanceUsed;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'employee_id' => $employee_id,
            'year' => $year,
            'severance_granted' => $severanceGranted,
            'severance_used' => $severanceUsed,
            'severance_remaining' => $severanceRemaining
        ]
    ]);
}

function deleteEmployee() {
    requireAdmin();
    
    $id = $_POST['id'] ?? 0;
    
    $db = getDB();
    
    if ($id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => '자신의 계정은 삭제할 수 없습니다.']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}

function getDepartments() {
    requireAuth();
    $db = getDB();
    
    $stmt = $db->query("SELECT * FROM departments ORDER BY code");
    $departments = $stmt->fetchAll();
    
    foreach ($departments as &$dept) {
        if ($_SESSION['user']['role'] !== 'system_admin') {
            unset($dept['code']);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $departments]);
}

