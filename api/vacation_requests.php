<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$_PARSED_BODY = json_decode(file_get_contents('php://input'), true) ?? [];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function requireAuth() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => '로그인이 필요합니다.']);
        exit;
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

function getList() {
    requireAuth();
    $db = getDB();
    $user = $_SESSION['user'];
    
    $year = intval($_GET['year'] ?? date('Y'));
    $month = $_GET['month'] ?? null;
    $empId = $_GET['emp_id'] ?? null;
    $deptId = $_GET['dept_id'] ?? null;
    
    $sql = "SELECT vr.*, e.name as employee_name, e.emp_no, p.name as position_name, e.department_id,
            d.name as department_name, d.code as department_code, d.color as department_color,
            vt.name as vacation_type_name,
            ct.name as condolence_type_name
            FROM vacation_requests vr
            JOIN employees e ON vr.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN departments d ON e.department_id = d.id
            JOIN vacation_types vt ON vr.vacation_type_id = vt.id
            LEFT JOIN condolence_types ct ON vr.condolence_type_id = ct.id";
    
    $params = [];
    $conditions = [];
    
    // Year/Month filter (range-based for index usage)
    if ($month !== null && $month !== '' && $year > 0) {
        $monthVal = intval($month);
        if ($monthVal >= 1 && $monthVal <= 12) {
            $monthStart = "{$year}-{$monthVal}-01";
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $conditions[] = "(vr.start_date <= ? AND vr.end_date >= ?)";
            $params[] = $monthEnd;
            $params[] = $monthStart;
        }
    } elseif ($year > 0) {
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";
        $conditions[] = "(vr.start_date <= ? AND vr.end_date >= ?)";
        $params[] = $yearEnd;
        $params[] = $yearStart;
    }
    
    // Employee filter (from dropdown)
    if ($empId !== null && $empId !== '') {
        $empId = intval($empId);
        if ($empId > 0) {
            $conditions[] = "vr.employee_id = ?";
            $params[] = $empId;
        }
    }
    
    // Department filter (from dropdown)
    if ($deptId !== null && $deptId !== '') {
        $deptId = intval($deptId);
        if ($deptId > 0) {
            $conditions[] = "e.department_id = ?";
            $params[] = $deptId;
        }
    }
    
    // Show only active employees (skip when specific empId is requested for resigned history)
    if ($empId === null || $empId === '') {
        $conditions[] = "e.is_active = 1";
    }
    
    // Role-based filtering (skip when specific empId is explicitly requested)
    if ($empId === null || $empId === '') {
        switch ($user['role']) {
            case 'system_admin':
            case 'reviewer':
                break;
            case 'ceo':
                // 팀장 이상 또는 김은솔 보기
                $conditions[] = "((p.sort_order <= 9 AND p.sort_order >= 3) OR p.name = '부대표' OR e.visible_to_exec = 1)";
                break;
            case 'vice_president':
                // 투자본부 모두 보기 + 본인 + 김은솔
                $conditions[] = "(d.code IN ('INV001', 'INV002') OR vr.employee_id = ? OR e.visible_to_exec = 1)";
                $params[] = $user['id'];
                break;
            case 'dept_manager':
                $conditions[] = "e.department_id = ?";
                $params[] = $user['managed_department_id'];
                break;
            case 'user':
                $conditions[] = "vr.employee_id = ?";
                $params[] = $user['id'];
                break;
        }
    }
    
    // Exclude cancelled (기본값: 취소 내역 제외)
    $excludeCancelled = $_GET['exclude_cancelled'] ?? '1';
    if ($excludeCancelled === '1') {
        $conditions[] = "vr.status != 'cancelled'";
    }
    
    // Build WHERE clause
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY vr.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    foreach ($requests as &$req) {
        if ($user['role'] !== 'system_admin') {
            unset($req['department_code']);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $requests]);
}

function getCalendarEvents() {
    requireAuth();
    $db = getDB();
    $user = $_SESSION['user'];
    
    $start = $_GET['start'] ?? date('Y-01-01');
    $end = $_GET['end'] ?? date('Y-12-31');
    
    $sql = "SELECT vr.id, vr.start_date, vr.end_date, vr.days, vr.status, vr.reason,
            e.name as title, e.id as emp_id,
            d.name as department_name, d.code as department_code,
            vt.name as vacation_type_name, COALESCE(d.color, '#667eea') as backgroundColor
            FROM vacation_requests vr
            JOIN employees e ON vr.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN departments d ON e.department_id = d.id
            JOIN vacation_types vt ON vr.vacation_type_id = vt.id
            WHERE vr.status IN ('applied', 'approved')
            AND e.is_active = 1
            AND vr.start_date <= ?
            AND vr.end_date >= ?";
    
    $params = [$end, $start];
    
    switch ($user['role']) {
        case 'system_admin':
        case 'reviewer':
            break;
        case 'ceo':
            // 팀장 이상 또는 김은솔 보기
            $sql .= " AND ((p.sort_order <= 9 AND p.sort_order >= 3) OR p.name = '부대표' OR e.visible_to_exec = 1)";
            break;
        case 'vice_president':
            // 투자본부 모두 보기 + 본인 + 김은솔
            $sql .= " AND (d.code IN ('INV001', 'INV002') OR vr.employee_id = ? OR e.visible_to_exec = 1)";
            $params[] = $user['id'];
            break;
        case 'dept_manager':
            $sql .= " AND e.department_id = ?";
            $params[] = $user['managed_department_id'];
            break;
        case 'user':
            $sql .= " AND vr.employee_id = ?";
            $params[] = $user['id'];
            break;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    $result = [];
    foreach ($events as $event) {
        $title = $event['title'] . ' ' . $event['vacation_type_name'];
        if (in_array($user['role'], ['system_admin', 'reviewer', 'ceo', 'vice_president'])) {
            $title .= ' (' . $event['department_name'] . ')';
        }
        
        $result[] = [
            'id' => $event['id'],
            'title' => $title,
            'start' => $event['start_date'],
            'end' => date('Y-m-d', strtotime($event['end_date'] . ' +1 day')),
            'backgroundColor' => $event['backgroundColor'],
            'borderColor' => $event['backgroundColor'],
            'extendedProps' => [
                'status' => $event['status'],
                'days' => $event['days'],
                'type' => $event['vacation_type_name'],
                'emp_id' => $event['emp_id'],
                'reason' => $event['reason']
            ]
        ];
    }
    
    echo json_encode($result);
}

function getDetail() {
    requireAuth();
    $id = $_GET['id'] ?? 0;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT vr.*, e.name as employee_name, e.emp_no, p.name as position_name, e.department_id,
            d.name as department_name, d.code as department_code, d.color as department_color,
            vt.name as vacation_type_name, vt.deduction, vt.deduct_from,
            ct.name as condolence_type_name
            FROM vacation_requests vr
            JOIN employees e ON vr.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN departments d ON e.department_id = d.id
            JOIN vacation_types vt ON vr.vacation_type_id = vt.id
            LEFT JOIN condolence_types ct ON vr.condolence_type_id = ct.id
            WHERE vr.id = ?");
    $stmt->execute([$id]);
    $detail = $stmt->fetch();
    
    if (!$detail) {
        http_response_code(404);
        echo json_encode(['error' => '신청건을 찾을 수 없습니다.']);
        return;
    }
    
    $user = $_SESSION['user'];
    
    $isOwnRequest = (intval($detail['employee_id']) == intval($user['id']));
    $isDeptMember = ($user['role'] === 'dept_manager' && intval($detail['department_id']) == intval($user['managed_department_id']));
    $isSystemAdmin = ($user['role'] === 'system_admin');
    $isReviewer = ($user['role'] === 'reviewer');
    
    if (!$isOwnRequest && !$isDeptMember && !$isSystemAdmin && !$isReviewer) {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        return;
    }
    
    if ($user['role'] !== 'system_admin') {
        unset($detail['department_code']);
    }
    
    echo json_encode(['success' => true, 'data' => $detail]);
}

function createRequest() {
    requireAuth();
    global $_PARSED_BODY;
    $user = $_SESSION['user'];
    $data = $_PARSED_BODY;
    
    $vacation_type_id = intval($data['vacation_type_id'] ?? 0);
    $condolence_type_id = isset($data['condolence_type_id']) && $data['condolence_type_id'] ? intval($data['condolence_type_id']) : null;
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $days = floatval($data['days'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    $phone1 = trim($data['phone1'] ?? '');
    $phone2 = trim($data['phone2'] ?? '');
    $startHalf = $data['start_half'] ?? 'full';
    $endHalf = $data['end_half'] ?? 'full';
    
    if (!$vacation_type_id || empty($start_date) || empty($end_date) || $days <= 0) {
        http_response_code(400);
        echo json_encode(['error' => '모든 필드를 입력해주세요.']);
        return;
    }
    
    $db = getDB();

    try {
        if (($phone1 !== '') || ($phone2 !== '')) {
            $stmtPhone = $db->prepare("UPDATE employees SET phone1 = ?, phone2 = ? WHERE id = ?");
            $stmtPhone->execute([$phone1, $phone2, $user['id']]);
        }
    } catch (Exception $e) {
    }
    
    $stmt = $db->prepare("SELECT * FROM vacation_types WHERE id = ? AND is_active = 1");
    $stmt->execute([$vacation_type_id]);
    $vacationType = $stmt->fetch();
    
    if (!$vacationType) {
        http_response_code(400);
        echo json_encode(['error' => '유효하지 않은 휴가 유형입니다.']);
        return;
    }
    
    if (strpos($vacationType['name'], '반차') === false) {
        $stmtOverlap = $db->prepare("SELECT COUNT(*) as cnt FROM vacation_requests 
                                      WHERE employee_id = ? 
                                      AND status IN ('applied', 'approved')
                                      AND (
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date >= ? AND end_date <= ?)
                                      )");
        $stmtOverlap->execute([
            $user['id'], $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        ]);
        $overlapResult = $stmtOverlap->fetch();
        if (intval($overlapResult['cnt']) > 0) {
            http_response_code(400);
            echo json_encode(['error' => '해당 기간에 이미 휴가 신청이 있습니다.']);
            return;
        }
    } else {
        $stmtOverlap = $db->prepare("SELECT COUNT(*) as cnt FROM vacation_requests 
                                      WHERE employee_id = ? 
                                      AND status IN ('applied', 'approved')
                                      AND vacation_type_id = ?
                                      AND (
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date >= ? AND end_date <= ?)
                                      )");
        $stmtOverlap->execute([
            $user['id'], $vacation_type_id,
            $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        ]);
        $overlapResult = $stmtOverlap->fetch();
        if (intval($overlapResult['cnt']) > 0) {
            http_response_code(400);
            echo json_encode(['error' => '해당 기간에 이미 같은 반차 신청이 있습니다.']);
            return;
        }
        
        $stmtOverlap = $db->prepare("SELECT COUNT(*) as cnt FROM vacation_requests 
                                      WHERE employee_id = ? 
                                      AND status IN ('applied', 'approved')
                                      AND (
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date <= ? AND end_date >= ?) OR
                                          (start_date >= ? AND end_date <= ?)
                                      )
                                      AND vacation_type_id NOT IN (
                                          SELECT id FROM vacation_types WHERE name LIKE '%반차%'
                                      )");
        $stmtOverlap->execute([
            $user['id'], $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        ]);
        $overlapResult = $stmtOverlap->fetch();
        if (intval($overlapResult['cnt']) > 0) {
            http_response_code(400);
            echo json_encode(['error' => '해당 기간에 이미 휴가 신청이 있습니다.']);
            return;
        }
    }
    
    $requestYear = intval(date('Y', strtotime($start_date)));
    $stmtYearLeave = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
    $stmtYearLeave->execute([$user['id'], $requestYear]);
    $yearLeave = $stmtYearLeave->fetch();
$grantedLeave = $yearLeave ? floatval($yearLeave['annual_leave']) : 0;

// 퇴사예정자 정보 조회 (퇴사예정자일 경우 보전연차도 고려)
$isResigning = false;
$sevRemaining = 0;
$empCheck = $db->prepare("SELECT is_resigning, severance_leave FROM employees WHERE id = ?");
$empCheck->execute([$user['id']]);
$empRow = $empCheck->fetch();
if ($empRow && intval($empRow['is_resigning'])) {
    $isResigning = true;
    $sevRemaining = floatval($empRow['severance_leave']);
}

// grantedLeave <= 0 체크 완화: 퇴사예정자일 경우 보전연차도 고려
if ($grantedLeave <= 0 && !($isResigning && $sevRemaining > 0)) {
    http_response_code(400);
    echo json_encode(['error' => $requestYear . '년도의 연차가 존재하지 않습니다.']);
    return;
}
    
    $usedYearStart = "{$requestYear}-01-01";
    $usedYearEnd = ($requestYear + 1) . "-01-01";
    $stmtUsed = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used FROM vacation_requests WHERE employee_id = ? AND status IN ('applied', 'approved') AND start_date >= ? AND start_date < ?");
    $stmtUsed->execute([$user['id'], $usedYearStart, $usedYearEnd]);
    $usedResult = $stmtUsed->fetch();
    $usedDays = floatval($usedResult['used'] ?? 0);
    $currentLeave = $grantedLeave - $usedDays;
    
    $annualDeduct = 0;
$severanceDeduct = 0;
    $isSpouseBirth = false;
    
    if ($vacationType['name'] === '경조사' && $condolence_type_id) {
        $stmtCond = $db->prepare("SELECT * FROM condolence_types WHERE id = ? AND is_active = 1");
        $stmtCond->execute([$condolence_type_id]);
        $condolenceType = $stmtCond->fetch();
        
        if (!$condolenceType) {
            http_response_code(400);
            echo json_encode(['error' => '유효하지 않은 경조사 사유입니다.']);
            return;
        }
        
        $isSpouseBirth = (stripos($condolenceType['name'], '배우자') !== false && stripos($condolenceType['name'], '출산') !== false);
        
        if ($isSpouseBirth) {
            $spouseBirthInfo = getSpouseBirthInfo($db, $user['id'], $condolence_type_id);
            
            $currentRoundRemaining = $spouseBirthInfo['round_remaining'];
            
            if ($days > $currentRoundRemaining) {
                $deductDays = $days - $currentRoundRemaining;
            }
        } else {
            $limitDays = floatval($condolenceType['limit_days']);
            
            $nowYear = date('Y');
            $nowYearStart = "{$nowYear}-01-01";
            $nowYearEnd = ($nowYear + 1) . "-01-01";
            $stmtUsed = $db->prepare("SELECT COALESCE(SUM(vr.days), 0) as used_days 
                                      FROM vacation_requests vr
                                      WHERE vr.employee_id = ? 
                                      AND vr.condolence_type_id = ?
                                      AND vr.status IN ('applied', 'approved')
                                      AND vr.start_date >= ? AND vr.start_date < ?");
            $stmtUsed->execute([$user['id'], $condolence_type_id, $nowYearStart, $nowYearEnd]);
            $usedResult = $stmtUsed->fetch();
            $alreadyUsed = floatval($usedResult['used_days']);
            
            if (($alreadyUsed + $days) > $limitDays) {
                $deductDays = ($alreadyUsed + $days) - $limitDays;
            }
        }
        
        if (($usedDays + $deductDays) > $grantedLeave) {
            http_response_code(400);
            $available = $grantedLeave - $usedDays;
            echo json_encode(['error' => "연차가 부족합니다. (잔여: {$available}일, 신청: {$deductDays}일)"]);
            return;
        }
    } else {
        if ($vacationType['deduct_from'] !== 'none') {
            // Calculate remaining annual and severance leave
            $annualRemaining = $currentLeave;
            $severanceRemaining = $isResigning ? $sevRemaining : 0;
            $totalRemaining = $annualRemaining + $severanceRemaining;
            
            if ($totalRemaining < $days) {
                http_response_code(400);
                $availableAnnual = $grantedLeave - $usedDays;
                $availableSeverance = $isResigning ? $sevRemaining : 0;
                echo json_encode(['error' => "연차가 부족합니다. (잔여 연차: {$availableAnnual}일, 잔여 보전연차: {$availableSeverance}일, 신청: {$days}일)"]);
                return;
            }
            
            if ($vacationType['deduct_from'] === 'annual') {
                // Use annual leave first, then severance leave if needed
                if ($annualRemaining >= $days) {
                    $annualDeduct = $days;
                    $severanceDeduct = 0;
                } else {
                    $annualDeduct = $annualRemaining;
                    $severanceDeduct = $days - $annualRemaining;
                }
                
                // Validate annual leave deduction
                if (($usedDays + $annualDeduct) > $grantedLeave) {
                    http_response_code(400);
                    $available = $grantedLeave - $usedDays;
                    echo json_encode(['error' => "연차가 부족합니다. (잔여: {$available}일, 신청: {$annualDeduct}일)"]);
                    return;
                }
                
                // Validate severance leave deduction (if applicable)
                if ($severanceDeduct > 0 && $severanceDeduct > $sevRemaining) {
                    http_response_code(400);
                    echo json_encode(['error' => "보전연차가 부족합니다. (잔여: {$sevRemaining}일, 신청: {$severanceDeduct}일)"]);
                    return;
                }
            }
        }
    }
    
    if ($vacationType['max_days'] < 999 && $days > $vacationType['max_days']) {
        http_response_code(400);
        echo json_encode(['error' => "최대 {$vacationType['max_days']}일까지 신청 가능합니다."]);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO vacation_requests (employee_id, vacation_type_id, condolence_type_id, start_date, end_date, start_half, end_half, days, reason, annual_deduct_days, severance_deduct_days, condolence_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $condolenceDays = $days - $annualDeduct - $severanceDeduct;
        $stmt->execute([$user['id'], $vacation_type_id, $condolence_type_id, $start_date, $end_date, $startHalf, $endHalf, $days, $reason, $annualDeduct, $severanceDeduct, $isSpouseBirth ? $condolenceDays : null]);
        $requestId = $db->lastInsertId();
        
        $newAnnualRemaining = $grantedLeave - ($usedDays + $annualDeduct);
        if ($annualDeduct > 0) {
            $stmt = $db->prepare("UPDATE employees SET annual_leave = ? WHERE id = ?");
            $stmt->execute([$newAnnualRemaining, $user['id']]);
        }
        
        $newSeveranceRemaining = $sevRemaining - $severanceDeduct;
        if ($severanceDeduct > 0) {
            $stmt = $db->prepare("UPDATE employees SET severance_leave = ? WHERE id = ?");
            $stmt->execute([$newSeveranceRemaining, $user['id']]);
        }
        
        if ($vacationType['name'] === '경조사' && $condolence_type_id && $isSpouseBirth) {
            updateSpouseBirthUsage($db, $user['id'], $condolence_type_id, $condolenceDays, $start_date, $end_date);
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '휴가 신청 처리 중 오류가 발생했습니다.']);
        return;
    }
    
    $annualRemaining = $grantedLeave - ($usedDays + $annualDeduct);
    $_SESSION['user']['annual_leave'] = $annualRemaining;
    
    echo json_encode(['success' => true, 'id' => $requestId]);
}

function updateRequest() {
    requireAuth();
    global $_PARSED_BODY;
    $user = $_SESSION['user'];
    $data = $_PARSED_BODY;
    
    $id = intval($data['id'] ?? 0);
    $vacation_type_id = intval($data['vacation_type_id'] ?? 0);
    $condolence_type_id = isset($data['condolence_type_id']) && $data['condolence_type_id'] ? intval($data['condolence_type_id']) : null;
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $days = floatval($data['days'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    
    if (!$id || !$vacation_type_id || empty($start_date) || empty($end_date) || $days <= 0) {
        http_response_code(400);
        echo json_encode(['error' => '모든 필드를 입력해주세요.']);
        return;
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT vr.*, e.department_id, e.annual_leave 
                          FROM vacation_requests vr 
                          JOIN employees e ON vr.employee_id = e.id
                          WHERE vr.id = ?");
    $stmt->execute([$id]);
    $original = $stmt->fetch();
    
    if (!$original) {
        http_response_code(404);
        echo json_encode(['error' => '신청건을 찾을 수 없습니다.']);
        return;
    }
    
    $isOwner = (intval($original['employee_id']) == intval($user['id']));
    $isDeptManager = false;
    
    if (!$isOwner) {
        $stmtDept = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$original['employee_id']]);
        $empDept = $stmtDept->fetch();
        
        if ($empDept && $user['role'] === 'dept_manager' && intval($empDept['department_id']) == intval($user['managed_department_id'])) {
            $isDeptManager = true;
        }
    }
    
    if (!$isOwner && !$isDeptManager) {
        http_response_code(403);
        echo json_encode(['error' => '수정권한이 없습니다.']);
        return;
    }
    
    if ($original['status'] !== 'applied') {
        http_response_code(400);
        echo json_encode(['error' => '신청 상태에서만 수정할 수 있습니다.']);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM vacation_types WHERE id = ? AND is_active = 1");
    $stmt->execute([$vacation_type_id]);
    $vacationType = $stmt->fetch();
    
    if (!$vacationType) {
        http_response_code(400);
        echo json_encode(['error' => '유효하지 않은 휴가 유형입니다.']);
        return;
    }
    
$requestYear = intval(date('Y', strtotime($start_date)));
$stmtYearLeave = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
$stmtYearLeave->execute([$original['employee_id'], $requestYear]);
$yearLeave = $stmtYearLeave->fetch();
$currentLeave = $yearLeave ? floatval($yearLeave['annual_leave']) : 0;

// 퇴사예정자 정보 조회 (퇴사예정자일 경우 보전연차도 고려)
$isResigning = false;
$sevRemaining = 0;
$empCheck = $db->prepare("SELECT is_resigning, severance_leave FROM employees WHERE id = ?");
$empCheck->execute([$original['employee_id']]);
$empRow = $empCheck->fetch();
if ($empRow && intval($empRow['is_resigning'])) {
    $isResigning = true;
    $sevRemaining = floatval($empRow['severance_leave']);
}

// annual leave <= 0 체크 완화: 퇴사예정자일 경우 보전연차도 고려
if ($currentLeave <= 0 && !($isResigning && $sevRemaining > 0)) {
    http_response_code(400);
    echo json_encode(['error' => $requestYear . '년도의 연차가 존재하지 않습니다.']);
    return;
}

// Calculate remaining annual leave excluding this request
$usedYearStart = "{$requestYear}-01-01";
$usedYearEnd = ($requestYear + 1) . "-01-01";
$usedQ = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                       FROM vacation_requests 
                       WHERE employee_id = ? 
                       AND status IN ('applied', 'approved')
                       AND start_date >= ? AND start_date < ?
                       AND id != ?");
$usedQ->execute([$original['employee_id'], $usedYearStart, $usedYearEnd, $id]);
$otherUsedDays = floatval($usedQ->fetch()['used'] ?? 0);
$remainingAnnual = $currentLeave - $otherUsedDays;
if ($remainingAnnual < 0) $remainingAnnual = 0;

// Total available: remaining annual + remaining severance + old severance (freed by edit)
$oldSevDeduct = floatval($original['severance_deduct_days'] ?? 0);
$sevAvailable = $isResigning ? $sevRemaining + $oldSevDeduct : 0;
$totalAvailable = $remainingAnnual + $sevAvailable;
if ($totalAvailable < $days) {
    http_response_code(400);
    $availableAnnual = max(0, $remainingAnnual);
    echo json_encode(['error' => "연차가 부족합니다. (잔여 연차: {$availableAnnual}일, 잔여 보전연차: {$sevAvailable}일, 신청: {$days}일)"]);
    return;
}
    
    $startHalf = $data['start_half'] ?? 'full';
    $endHalf = $data['end_half'] ?? 'full';
    
    $overlapStmt = $db->prepare("SELECT vr.id, vr.start_date, vr.end_date, vr.days, vt.name as type_name,
                              vr.start_half, vr.end_half
                              FROM vacation_requests vr
                              JOIN vacation_types vt ON vr.vacation_type_id = vt.id
                              WHERE vr.employee_id = ? 
                              AND vr.status IN ('applied', 'approved')
                              AND vr.id != ?
                              AND vr.start_date <= ?
                              AND vr.end_date >= ?");
    $overlapStmt->execute([$original['employee_id'], $id, $end_date, $start_date]);
    $overlapping = $overlapStmt->fetchAll();
    
    if ($overlapping) {
        foreach ($overlapping as $existing) {
            $existingStartHalf = $existing['start_half'] ?? 'full';
            $existingEndHalf = $existing['end_half'] ?? 'full';
            
            if ($start_date === $end_date && $existing['start_date'] === $existing['end_date']) {
                $totalDays = 0;
                if ($startHalf === 'full') $totalDays += 1;
                else $totalDays += 0.5;
                if ($endHalf !== 'full' && $endHalf !== $startHalf) $totalDays += 0.5;
                
                if ($existingStartHalf === 'full') $totalDays += 1;
                else $totalDays += 0.5;
                if ($existingEndHalf !== 'full' && $existingEndHalf !== $existingStartHalf) $totalDays += 0.5;
                
                if ($totalDays > 1) {
                    http_response_code(400);
                    echo json_encode(['error' => '동일한 날짜에 이미 신청된 휴가(' . $existing['type_name'] . ')가 있습니다.']);
                    return;
                }
            }
        }
    }
    
$annualDeduct = 0;
$severanceDeduct = 0;
    
    if ($vacationType['name'] === '경조사' && $condolence_type_id) {
        $stmtCond = $db->prepare("SELECT * FROM condolence_types WHERE id = ? AND is_active = 1");
        $stmtCond->execute([$condolence_type_id]);
        $condolenceType = $stmtCond->fetch();
        
        if ($condolenceType) {
            $limitDays = floatval($condolenceType['limit_days']);
            
            $usedYearStart = "{$requestYear}-01-01";
            $usedYearEnd = ($requestYear + 1) . "-01-01";
            $stmtUsed = $db->prepare("SELECT COALESCE(SUM(vr.days), 0) as used_days 
                                       FROM vacation_requests vr
                                       WHERE vr.employee_id = ? 
                                       AND vr.condolence_type_id = ?
                                       AND vr.status IN ('applied', 'approved')
                                       AND vr.id != ?
                                       AND vr.start_date >= ? AND vr.start_date < ?");
            $stmtUsed->execute([$original['employee_id'], $condolence_type_id, $id, $usedYearStart, $usedYearEnd]);
            $usedResult = $stmtUsed->fetch();
            $alreadyUsed = floatval($usedResult['used_days']);
            
            if (($alreadyUsed + $days) > $limitDays) {
                // For bereavement leave, deduct from annual first, then severance
                $totalDeduct = ($alreadyUsed + $days) - $limitDays;
                if ($remainingAnnual >= $totalDeduct) {
                    $annualDeduct = $totalDeduct;
                    $severanceDeduct = 0;
                } else {
                    $annualDeduct = $remainingAnnual;
                    $severanceDeduct = $totalDeduct - $remainingAnnual;
                }
                
                // Validate severance deduction if applicable
                $oldSevDeduct = floatval($original['severance_deduct_days'] ?? 0);
                $sevIncrease = $severanceDeduct - $oldSevDeduct;
                if ($severanceDeduct > 0 && $sevIncrease > $sevRemaining) {
                    http_response_code(400);
                    echo json_encode(['error' => "보전연차가 부족합니다. (잔여: " . ($sevRemaining + $oldSevDeduct) . "일, 신청: {$severanceDeduct}일)"]);
                    return;
                }
            }
        }
    } elseif ($vacationType['deduct_from'] === 'annual') {
        // Use annual leave first, then severance leave if needed
        if ($remainingAnnual >= $days) {
            $annualDeduct = $days;
            $severanceDeduct = 0;
        } else {
            $annualDeduct = $remainingAnnual;
            $severanceDeduct = $days - $remainingAnnual;
        }
        
        // Validate severance deduction if applicable
        $oldSevDeduct = floatval($original['severance_deduct_days'] ?? 0);
        $sevIncrease = $severanceDeduct - $oldSevDeduct;
        if ($severanceDeduct > 0 && $sevIncrease > $sevRemaining) {
            http_response_code(400);
            echo json_encode(['error' => "보전연차가 부족합니다. (잔여: " . ($sevRemaining + $oldSevDeduct) . "일, 신청: {$severanceDeduct}일)"]);
            return;
        }
    }
    
    $oldAnnualDeduct = floatval($original['annual_deduct_days'] ?? 0);
    $oldSeveranceDeduct = floatval($original['severance_deduct_days'] ?? 0);
    $diffAnnualDeduct = $annualDeduct - $oldAnnualDeduct;
    $diffSeveranceDeduct = $severanceDeduct - $oldSeveranceDeduct;
    
    try {
        $db->beginTransaction();
        
        // Calculate new annual remaining balance
        $newAnnualRemaining = $currentLeave - ($otherUsedDays + $annualDeduct);
        if ($annualDeduct != $oldAnnualDeduct) {
            $stmt = $db->prepare("UPDATE employees SET annual_leave = ? WHERE id = ?");
            $stmt->execute([$newAnnualRemaining, $original['employee_id']]);
        }
        
        if ($severanceDeduct != $oldSeveranceDeduct) {
            $newSevRemaining = $sevRemaining - ($severanceDeduct - $oldSeveranceDeduct);
            $stmt = $db->prepare("UPDATE employees SET severance_leave = ? WHERE id = ?");
            $stmt->execute([$newSevRemaining, $original['employee_id']]);
        }
        
        $condolenceDays = $vacationType['name'] === '경조사' ? ($days - $annualDeduct - $severanceDeduct) : null;
        
        $stmt = $db->prepare("UPDATE vacation_requests 
                              SET vacation_type_id = ?, condolence_type_id = ?, start_date = ?, end_date = ?, start_half = ?, end_half = ?, days = ?, reason = ?, annual_deduct_days = ?, severance_deduct_days = ?, condolence_days = ?
                              WHERE id = ?");
        $stmt->execute([$vacation_type_id, $condolence_type_id, $start_date, $end_date, $startHalf, $endHalf, $days, $reason, $annualDeduct, $severanceDeduct, $condolenceDays, $id]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '휴가 수정 처리 중 오류가 발생했습니다.']);
        return;
    }
    
    // Update session annual leave
    $annualRemaining = $currentLeave - ($otherUsedDays + $annualDeduct);
    $_SESSION['user']['annual_leave'] = $annualRemaining;
    
    echo json_encode(['success' => true]);
}

function cancelRequest() {
    requireAuth();
    global $_PARSED_BODY;
    $user = $_SESSION['user'];
    $id = intval($_PARSED_BODY['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT vr.*, vt.name as vacation_type_name 
                          FROM vacation_requests vr 
                          JOIN vacation_types vt ON vr.vacation_type_id = vt.id 
                          WHERE vr.id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => '신청건을 찾을 수 없습니다.']);
        return;
    }
    
    if ($request['employee_id'] != $user['id'] && !in_array($user['role'], ['system_admin', 'reviewer'])) {
        http_response_code(403);
        echo json_encode(['error' => '취소 권한이 없습니다.']);
        return;
    }
    
    if ($request['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode(['error' => '이미 취소된 신청건입니다.']);
        return;
    }
    
    $refundAnnualDays = floatval($request['annual_deduct_days'] ?? 0);
$refundSeveranceDays = floatval($request['severance_deduct_days'] ?? 0);
    
    try {
        $db->beginTransaction();
        
        if ($refundAnnualDays > 0) {
            $stmt = $db->prepare("UPDATE employees SET annual_leave = annual_leave + ? WHERE id = ?");
            $stmt->execute([$refundAnnualDays, $request['employee_id']]);
        }
        
        if ($refundSeveranceDays > 0) {
            $stmt = $db->prepare("UPDATE employees SET severance_leave = severance_leave + ? WHERE id = ?");
            $stmt->execute([$refundSeveranceDays, $request['employee_id']]);
        }
        
        if ($request['vacation_type_name'] === '경조사' && $request['condolence_type_id']) {
            $stmtCond = $db->prepare("SELECT name FROM condolence_types WHERE id = ?");
            $stmtCond->execute([$request['condolence_type_id']]);
            $condolenceType = $stmtCond->fetch();
            
            if ($condolenceType && stripos($condolenceType['name'], '배우자') !== false && stripos($condolenceType['name'], '출산') !== false) {
                refundSpouseBirthUsage($db, $request['employee_id'], $request['condolence_type_id'], $id);
            }
        }
        
        $stmt = $db->prepare("UPDATE vacation_requests SET status = 'cancelled', annual_deduct_days = 0, severance_deduct_days = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '휴가 취소 처리 중 오류가 발생했습니다.']);
        return;
    }
    
    echo json_encode(['success' => true]);
}

function approveRequest() {
    requireAuth();
    global $_PARSED_BODY;
    $user = $_SESSION['user'];
    $id = intval($_PARSED_BODY['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!in_array($user['role'], ['system_admin', 'reviewer'])) {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        return;
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM vacation_requests WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => '신청건을 찾을 수 없습니다.']);
        return;
    }
    
    if ($request['status'] !== 'applied') {
        http_response_code(400);
        echo json_encode(['error' => '이미 처리된 신청건입니다.']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE vacation_requests SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}

function getMyAnnualLeave() {
    requireAuth();
    $user = $_SESSION['user'];
    
    $db = getDB();
    $currentYear = date('Y');
    $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
    $stmt->execute([$user['id'], $currentYear]);
    $leave = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'annual_leave' => $leave ? floatval($leave['annual_leave']) : 0
        ]
    ]);
}

function getMyRemainingByYear() {
    requireAuth();
    $user = $_SESSION['user'];
    $year = intval($_GET['year'] ?? date('Y'));
    
    $db = getDB();
    $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
    $stmt->execute([$user['id'], $year]);
    $leave = $stmt->fetch();
    $granted = $leave ? floatval($leave['annual_leave']) : 0;
    
    $remainingYearStart = "{$year}-01-01";
    $remainingYearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                          FROM vacation_requests 
                          WHERE employee_id = ? 
                          AND status IN ('applied', 'approved')
                          AND start_date >= ? AND start_date < ?");
    $stmt->execute([$user['id'], $remainingYearStart, $remainingYearEnd]);
    $result = $stmt->fetch();
    $used = floatval($result['used'] ?? 0);
    
    $remaining = $granted - $used;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'annual_leave' => $remaining,
            'granted' => $granted,
            'used' => $used,
            'year' => $year
        ]
    ]);
}

function getMyAnnualInfo() {
    requireAuth();
    $user = $_SESSION['user'];
    $year = intval($_GET['year'] ?? date('Y'));
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
    $stmt->execute([$user['id'], $year]);
    $leave = $stmt->fetch();
    $granted = $leave ? floatval($leave['annual_leave']) : 0;
    
    $infoYearStart = "{$year}-01-01";
    $infoYearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare("SELECT COALESCE(SUM(vr.annual_deduct_days), 0) as used 
                           FROM vacation_requests vr
                           WHERE vr.employee_id = ? 
                           AND vr.status IN ('applied', 'approved')
                           AND vr.start_date >= ? AND vr.start_date < ?");
    $stmt->execute([$user['id'], $infoYearStart, $infoYearEnd]);
    $result = $stmt->fetch();
    $used = floatval($result['used'] ?? 0);
    
    $remaining = $granted - $used;
    
    // Get severance leave info for resigning employees
    $isResigning = 0;
    $severanceGranted = 0;
    $severanceUsed = 0;
    $severanceRemaining = 0;
    
    $empStmt = $db->prepare("SELECT is_resigning, severance_leave FROM employees WHERE id = ?");
    $empStmt->execute([$user['id']]);
    $empRow = $empStmt->fetch();
    if ($empRow) {
        $isResigning = intval($empRow['is_resigning']);
        $severanceRemaining = floatval($empRow['severance_leave']);
        
        $sevUsedStmt = $db->prepare("SELECT COALESCE(SUM(severance_deduct_days), 0) as used 
                                     FROM vacation_requests
                                     WHERE employee_id = ? 
                                     AND status IN ('applied', 'approved')
                                     AND start_date >= ? AND start_date < ?");
        $sevUsedStmt->execute([$user['id'], $infoYearStart, $infoYearEnd]);
        $sevUsedResult = $sevUsedStmt->fetch();
        $severanceUsed = floatval($sevUsedResult['used'] ?? 0);
        $severanceGranted = $severanceRemaining + $severanceUsed;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $granted,
            'remaining' => $remaining,
            'used' => $used,
            'year' => $year,
            'is_resigning' => $isResigning,
            'severance_leave' => $severanceGranted,
            'severance_used' => $severanceUsed,
            'severance_remaining' => $severanceRemaining
        ]
    ]);
}

function getMyCondolenceInfo() {
    requireAuth();
    $user = $_SESSION['user'];
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id FROM vacation_types WHERE name = '경조사' AND is_active = 1");
    $stmt->execute();
    $condolenceType = $stmt->fetch();
    
    if (!$condolenceType) {
        echo json_encode(['success' => true, 'data' => ['total' => 20, 'used' => 0, 'remaining' => 20]]);
        return;
    }
    
    $total = 20;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(vr.days), 0) as used 
                          FROM vacation_requests vr
                          WHERE vr.employee_id = ? 
                          AND vr.vacation_type_id = ?
                          AND vr.status != 'cancelled'");
    $stmt->execute([$user['id'], $condolenceType['id']]);
    $result = $stmt->fetch();
    $used = floatval($result['used'] ?? 0);
    
    $remaining = $total - $used;
    
    $responseData = [
        'total' => $total,
        'used' => $used,
        'remaining' => $remaining
    ];
    
    $stmtCondType = $db->prepare("SELECT id, name FROM condolence_types WHERE name LIKE '%배우자%출산%' AND is_active = 1");
    $stmtCondType->execute();
    $spouseBirthType = $stmtCondType->fetch();
    
    if ($spouseBirthType) {
        $spouseBirthInfo = getSpouseBirthInfo($db, $user['id'], $spouseBirthType['id']);
        $responseData['is_spouse_birth'] = true;
        $responseData['spouse_birth'] = $spouseBirthInfo;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $responseData
    ]);
}

function getSpouseBirthInfo($db, $userId, $condolenceTypeId) {
    $stmtMaxEvent = $db->prepare("
        SELECT COALESCE(MAX(birth_event), 0) as max_event 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ?
    ");
    $stmtMaxEvent->execute([$userId, $condolenceTypeId]);
    $maxEventResult = $stmtMaxEvent->fetch();
    $maxBirthEvent = intval($maxEventResult['max_event']);
    
    $currentBirthEvent = $maxBirthEvent > 0 ? $maxBirthEvent : 1;
    
    $stmtUsage = $db->prepare("
        SELECT days_used, usage_round 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ? AND birth_event = ?
        ORDER BY usage_round ASC
    ");
    $stmtUsage->execute([$userId, $condolenceTypeId, $currentBirthEvent]);
    $usageRecords = $stmtUsage->fetchAll();
    
    $totalUsed = 0;
    $usageCount = count($usageRecords);
    $isExhausted = false;
    
    if ($usageCount > 0) {
        $lastRecord = end($usageRecords);
        $totalUsed = floatval($lastRecord['days_used']);
        
        if ($usageCount >= 4 || $totalUsed >= 20) {
            $currentBirthEvent = $maxBirthEvent + 1;
            $usageCount = 0;
            $totalUsed = 0;
            $isExhausted = true;
        }
    }
    
    $currentRound = $usageCount + 1;
    $roundRemaining = max(0, 20 - $totalUsed);
    
    return [
        'current_round' => $currentRound,
        'total_used' => $totalUsed,
        'round_remaining' => $roundRemaining,
        'total_rounds' => 4,
        'birth_event' => $currentBirthEvent,
        'exhausted' => $isExhausted,
        'exhausted_previous' => $isExhausted
    ];
}

function calculateWorkingDays($startDate, $endDate, $db) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    
    $stmtHolidays = $db->prepare("SELECT date FROM holidays WHERE date BETWEEN ? AND ?");
    $stmtHolidays->execute([$startDate, $endDate]);
    $holidays = array_column($stmtHolidays->fetchAll(), 'date');
    
    $workingDays = 0;
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $day) {
        $dayOfWeek = (int)$day->format('w');
        $dateStr = $day->format('Y-m-d');
        
        if ($dayOfWeek == 0 || $dayOfWeek == 6) continue;
        if (in_array($dateStr, $holidays)) continue;
        
        $workingDays++;
    }
    
    return $workingDays;
}

function updateSpouseBirthUsage($db, $userId, $condolenceTypeId, $days, $startDate, $endDate) {
    $stmtMaxEvent = $db->prepare("
        SELECT COALESCE(MAX(birth_event), 0) as max_event 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ?
    ");
    $stmtMaxEvent->execute([$userId, $condolenceTypeId]);
    $maxEventResult = $stmtMaxEvent->fetch();
    $maxBirthEvent = intval($maxEventResult['max_event']);
    
    $currentBirthEvent = $maxBirthEvent > 0 ? $maxBirthEvent : 1;
    
    $stmtUsage = $db->prepare("
        SELECT days_used, usage_round 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ? AND birth_event = ?
        ORDER BY usage_round ASC
    ");
    $stmtUsage->execute([$userId, $condolenceTypeId, $currentBirthEvent]);
    $usageRecords = $stmtUsage->fetchAll();
    
    $totalUsed = 0;
    $usageCount = count($usageRecords);
    
    if ($usageCount > 0) {
        $lastRecord = end($usageRecords);
        $totalUsed = floatval($lastRecord['days_used']);
    }
    
    $currentRound = $usageCount + 1;
    $actualWorkingDays = calculateWorkingDays($startDate, $endDate, $db);
    
    if ($usageCount >= 4 || $totalUsed >= 20) {
        $currentBirthEvent = $maxBirthEvent + 1;
        $usageCount = 0;
        $totalUsed = 0;
        $currentRound = 1;
    }
    
    $newUsed = $totalUsed + $actualWorkingDays;
    
    $stmt = $db->prepare("
        INSERT INTO condolence_usage_history (employee_id, condolence_type_id, birth_event, usage_round, days_used) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $condolenceTypeId, $currentBirthEvent, $currentRound, $newUsed]);
    
    return true;
}

function refundSpouseBirthUsage($db, $userId, $condolenceTypeId, $requestId) {
    $stmt = $db->prepare("
        SELECT days, annual_deduct_days 
        FROM vacation_requests 
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch();
    
    if (!$request) return true;
    
    $refundDays = floatval($request['days'] ?? 0);
    
    $stmtMaxEvent = $db->prepare("
        SELECT COALESCE(MAX(birth_event), 0) as max_event 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ?
    ");
    $stmtMaxEvent->execute([$userId, $condolenceTypeId]);
    $maxEventResult = $stmtMaxEvent->fetch();
    $currentBirthEvent = intval($maxEventResult['max_event']) > 0 ? intval($maxEventResult['max_event']) : 1;
    
    $stmtLast = $db->prepare("
        SELECT id, days_used 
        FROM condolence_usage_history 
        WHERE employee_id = ? AND condolence_type_id = ? AND birth_event = ?
        ORDER BY usage_round DESC LIMIT 1
    ");
    $stmtLast->execute([$userId, $condolenceTypeId, $currentBirthEvent]);
    $lastUsage = $stmtLast->fetch();
    
    if (!$lastUsage) return true;
    
    $originalUsed = floatval($lastUsage['days_used']);
    $newUsed = max(0, $originalUsed - $refundDays);
    
    if ($originalUsed >= 20 && $currentBirthEvent > 1) {
        $stmtOrphan = $db->prepare("DELETE FROM condolence_usage_history WHERE employee_id = ? AND condolence_type_id = ? AND birth_event = ?");
        $stmtOrphan->execute([$userId, $condolenceTypeId, $currentBirthEvent]);
    } else if ($newUsed < $originalUsed) {
        $stmtDelete = $db->prepare("DELETE FROM condolence_usage_history WHERE id = ?");
        $stmtDelete->execute([$lastUsage['id']]);
    } else {
        $stmtUpdate = $db->prepare("UPDATE condolence_usage_history SET days_used = ? WHERE id = ?");
        $stmtUpdate->execute([$newUsed, $lastUsage['id']]);
    }
    
    return true;
}

switch ($action) {
    case 'list':
        getList();
        break;
    case 'calendar':
        getCalendarEvents();
        break;
    case 'detail':
        getDetail();
        break;
    case 'create':
        requireCsrfToken();
        createRequest();
        break;
    case 'update':
        requireCsrfToken();
        updateRequest();
        break;
    case 'cancel':
        requireCsrfToken();
        cancelRequest();
        break;
    case 'approve':
        requireCsrfToken();
        approveRequest();
        break;
    case 'my_remaining':
        getMyAnnualLeave();
        break;
    case 'my_remaining_year':
        getMyRemainingByYear();
        break;
    case 'my_annual_info':
        getMyAnnualInfo();
        break;
    case 'my_condolence_info':
        getMyCondolenceInfo();
        break;
    case 'holidays':
        getHolidays();
        break;
    case 'holiday_save':
        requireCsrfToken();
        saveHoliday();
        break;
    case 'holiday_delete':
        requireCsrfToken();
        deleteHoliday();
        break;
    case 'employee_leave':
        getEmployeeLeave();
        break;
    case 'employee_leave_list':
        getEmployeeLeaveList();
        break;
    case 'employee_annual_list':
        getEmployeeAnnualList();
        break;
    case 'annual_list':
        getAnnualList();
        break;
    case 'annual_update':
        requireCsrfToken();
        updateAnnualLeave();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getEmployeeAnnualList() {
    requireAuth();
    $user = $_SESSION['user'];
    $year = intval($_GET['year'] ?? date('Y'));

    $allowedRoles = ['system_admin', 'reviewer', 'dept_manager'];
    if (!in_array($user['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        return;
    }

    $db = getDB();

    $sql = "SELECT e.id, e.name, e.emp_no,
            d.name as department_name, d.code as department_code,
            p.name as position_name,
            ay.annual_leave as granted,
            COALESCE(u.used, 0) as used
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN annual_by_year ay ON e.id = ay.employee_id AND ay.year = ?
            LEFT JOIN (
                SELECT employee_id, SUM(annual_deduct_days) as used
                FROM vacation_requests
                WHERE status IN ('applied', 'approved') AND start_date >= ? AND start_date < ?
                GROUP BY employee_id
            ) u ON e.id = u.employee_id";

    $listYearStart = "{$year}-01-01";
    $listYearEnd = ($year + 1) . "-01-01";
    $params = [$year, $listYearStart, $listYearEnd];
    $conditions = ["e.is_active = 1"];

    if ($user['role'] === 'dept_manager') {
        $conditions[] = "e.department_id = ?";
        $params[] = $user['managed_department_id'];
    }

    $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY d.code, p.sort_order, e.name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $result = [];
    foreach ($employees as $emp) {
        $granted = floatval($emp['granted'] ?? 0);
        $used = floatval($emp['used'] ?? 0);

        $result[] = [
            'id' => $emp['id'],
            'name' => $emp['name'],
            'emp_no' => $emp['emp_no'],
            'department_name' => $emp['department_name'],
            'department_code' => $emp['department_code'],
            'position_name' => $emp['position_name'],
            'granted' => $granted,
            'used' => $used,
            'remaining' => $granted - $used
        ];
    }

    echo json_encode(['success' => true, 'data' => $result]);
}

function getHolidays() {
    $db = getDB();
    $year = intval($_GET['year'] ?? date('Y'));
    
    // 3월 4일 회사 기념일 자동 추가
    $companyHolidayDate = "{$year}-03-04";
    $companyHolidayName = '금융투자업 인가기념일';
    
    $stmtCheck = $db->prepare("SELECT id FROM holidays WHERE date = ?");
    $stmtCheck->execute([$companyHolidayDate]);
    if (!$stmtCheck->fetch()) {
        $stmtInsert = $db->prepare("INSERT IGNORE INTO holidays (date, name, year) VALUES (?, ?, ?)");
        $stmtInsert->execute([$companyHolidayDate, $companyHolidayName, $year]);
    }
    
    $stmt = $db->prepare("SELECT id, date, name FROM holidays WHERE year = ? ORDER BY date");
    $stmt->execute([$year]);
    $holidays = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $holidays, 'year' => $year]);
}

function saveHoliday() {
    global $_PARSED_BODY;
    requireAuth();
    $data = $_PARSED_BODY;
    
    $date = $data['date'] ?? '';
    $name = trim($data['name'] ?? '');
    $year = intval($data['year'] ?? date('Y'));
    
    if (empty($date) || empty($name)) {
        echo json_encode(['error' => '날짜와 명칭을 입력해주세요.']);
        return;
    }
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("INSERT INTO holidays (date, name, year) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE name = ?");
        $stmt->execute([$date, $name, $year, $name]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteHoliday() {
    global $_PARSED_BODY;
    requireAuth();
    $data = $_PARSED_BODY;
    
    $id = intval($data['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['error' => '유효하지 않은 ID입니다.']);
        return;
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}

function getEmployeeLeave() {
    requireAuth();
    $user = $_SESSION['user'];
    $empId = intval($_GET['emp_id'] ?? 0);
    $year = intval($_GET['year'] ?? date('Y'));
    
    $db = getDB();
    
    // 권한 체크: CEO와 부대표는 특정 사원만 조회 가능
    if ($user['role'] === 'ceo' || $user['role'] === 'vice_president') {
        $stmt = $db->prepare("SELECT e.id, e.name, p.sort_order, d.code 
                               FROM employees e 
                               LEFT JOIN positions p ON e.position_id = p.id 
                               LEFT JOIN departments d ON e.department_id = d.id 
                               WHERE e.id = ?");
        $stmt->execute([$empId]);
        $emp = $stmt->fetch();
        
        if ($emp) {
            $hasPermission = false;
            if ($user['role'] === 'ceo') {
                $hasPermission = ($emp['sort_order'] <= 9 && $emp['sort_order'] >= 3) || 
                                $emp['sort_order'] == 3 || 
                                $emp['visible_to_exec'] == 1;
            } else if ($user['role'] === 'vice_president') {
                // 투자본부(INV001, INV002) 소속 또는 김은솔 확인
                $hasPermission = in_array($emp['code'], ['INV001', 'INV002']) || $emp['visible_to_exec'] == 1;
            }
            
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode(['error' => '권한이 없습니다.']);
                return;
            }
        }
    }
    
    $stmt = $db->prepare("SELECT annual_leave FROM annual_by_year WHERE employee_id = ? AND year = ?");
    $stmt->execute([$empId, $year]);
    $leave = $stmt->fetch();
    $granted = $leave ? floatval($leave['annual_leave']) : 0;
    
    $empYearStart = "{$year}-01-01";
    $empYearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                          FROM vacation_requests 
                          WHERE employee_id = ? 
                          AND status IN ('applied', 'approved')
                          AND start_date >= ? AND start_date < ?");
    $stmt->execute([$empId, $empYearStart, $empYearEnd]);
    $result = $stmt->fetch();
    $used = floatval($result['used'] ?? 0);
    
    echo json_encode([
        'granted' => $granted,
        'used' => $used,
        'remaining' => $granted - $used
    ]);
}

function getEmployeeLeaveList() {
    requireAuth();
    $user = $_SESSION['user'];
    $year = intval($_GET['year'] ?? date('Y'));
    
    $db = getDB();
    
    $sql = "SELECT e.id,
            COALESCE(ay.annual_leave, e.annual_leave, 0) as granted,
            COALESCE(u.used, 0) as used
            FROM employees e
            LEFT JOIN annual_by_year ay ON e.id = ay.employee_id AND ay.year = ?
            LEFT JOIN (
                SELECT employee_id, SUM(annual_deduct_days) as used
                FROM vacation_requests
                WHERE status IN ('applied', 'approved') AND start_date >= ? AND start_date < ?
                GROUP BY employee_id
            ) u ON e.id = u.employee_id
            WHERE e.is_active = 1";
    
    $yearStart = "{$year}-01-01";
    $yearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year, $yearStart, $yearEnd]);
    $rows = $stmt->fetchAll();
    
    $result = [];
    foreach ($rows as $row) {
        $granted = floatval($row['granted']);
        $used = floatval($row['used']);
        $result[$row['id']] = [
            'granted' => $granted,
            'used' => $used,
            'remaining' => $granted - $used
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function getAnnualList() {
    requireAuth();
    $user = $_SESSION['user'];
    if ($user['role'] !== 'system_admin') {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        return;
    }
    
    $year = intval($_GET['year'] ?? date('Y'));
    $db = getDB();
    
    $sql = "SELECT e.id, e.name, e.emp_no, e.hire_date,
            d.name as department_name, d.code as department_code,
            p.name as position_name,
            COALESCE(ay.annual_leave, 0) as granted,
            COALESCE(u.used, 0) as used
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN annual_by_year ay ON e.id = ay.employee_id AND ay.year = ?
            LEFT JOIN (
                SELECT employee_id, SUM(annual_deduct_days) as used
                FROM vacation_requests
                WHERE status IN ('applied', 'approved') AND start_date >= ? AND start_date < ?
                GROUP BY employee_id
            ) u ON e.id = u.employee_id
            WHERE e.is_active = 1
            ORDER BY e.hire_date, d.code, p.sort_order, e.name";
    
    $yearStart = "{$year}-01-01";
    $yearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year, $yearStart, $yearEnd]);
    $employees = $stmt->fetchAll();
    
    $result = [];
    foreach ($employees as $emp) {
        $granted = floatval($emp['granted']);
        $used = floatval($emp['used']);
        $result[] = [
            'id' => $emp['id'],
            'name' => $emp['name'],
            'emp_no' => $emp['emp_no'],
            'hire_date' => $emp['hire_date'] ?? null,
            'department_name' => $emp['department_name'],
            'department_code' => $emp['department_code'],
            'position_name' => $emp['position_name'],
            'granted' => $granted,
            'used' => $used,
            'remaining' => $granted - $used
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function updateAnnualLeave() {
    requireAuth();
    $user = $_SESSION['user'];
    if ($user['role'] !== 'system_admin') {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        return;
    }
    
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    $annualLeave = floatval($_POST['annual_leave'] ?? 0);
    
    if ($employeeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => '유효하지 않은 사원입니다.']);
        return;
    }
    
    $db = getDB();
    
    $check = $db->prepare("SELECT id FROM employees WHERE id = ?");
    $check->execute([$employeeId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '사원을 찾을 수 없습니다.']);
        return;
    }
    
    $yearStart = "{$year}-01-01";
    $yearEnd = ($year + 1) . "-01-01";
    $stmt = $db->prepare("SELECT COALESCE(SUM(annual_deduct_days), 0) as used 
                          FROM vacation_requests 
                          WHERE employee_id = ? 
                          AND status IN ('applied', 'approved')
                          AND start_date >= ? AND start_date < ?");
    $stmt->execute([$employeeId, $yearStart, $yearEnd]);
    $result = $stmt->fetch();
    $used = floatval($result['used'] ?? 0);
    
    $newRemaining = $annualLeave - $used;
    
    $stmt = $db->prepare("INSERT INTO annual_by_year (employee_id, year, annual_leave, used_all) 
                          VALUES (?, ?, ?, 0)
                          ON DUPLICATE KEY UPDATE annual_leave = ?, used_all = 0");
    $stmt->execute([$employeeId, $year, $annualLeave, $annualLeave]);
    
    $currentYear = intval(date('Y'));
    if ($year == $currentYear) {
        $stmt = $db->prepare("UPDATE employees SET annual_leave = ? WHERE id = ?");
        $stmt->execute([$newRemaining, $employeeId]);
    }
    
    echo json_encode(['success' => true]);
}
