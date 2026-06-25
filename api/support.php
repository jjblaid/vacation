<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

function requireAuth() {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => '로그인이 필요합니다.']);
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'system_admin') {
        http_response_code(403);
        echo json_encode(['error' => '권한이 없습니다.']);
        exit;
    }
}

function sendSupportEmail($employeeName, $requestType, $requestContent, $requestDate, &$debugOutput = null) {
    $mail = new PHPMailer(true);

    $smtpHost = getSetting('smtp_host');
    $smtpPort = getSetting('smtp_port') ?: '587';
    $smtpUser = getSetting('smtp_user');
    $smtpPass = getSetting('smtp_pass');
    $smtpEnc = getSetting('smtp_encryption') ?: 'tls';
    $fromEmail = getSetting('smtp_from_email');
    $smtpAuth = getSetting('smtp_auth');

    if ($debugOutput !== null) {
        $debugOutput .= "--- 불러온 SMTP 설정값 ---\n";
        $debugOutput .= "smtp_host: " . ($smtpHost ?: '(empty)') . "\n";
        $debugOutput .= "smtp_port: " . ($smtpPort ?: '(empty)') . "\n";
        $debugOutput .= "smtp_from_email: " . ($fromEmail ?: '(empty)') . "\n";
        $debugOutput .= "---\n";
    }

    if (empty($smtpHost) || empty($fromEmail)) {
        if ($debugOutput !== null) $debugOutput .= "SMTP 호스트 또는 발신 이메일이 설정되지 않았습니다.\n";
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT email FROM employees WHERE role = 'system_admin' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    $adminEmail = $admin ? $admin['email'] : '';

    if (empty($adminEmail)) {
        if ($debugOutput !== null) $debugOutput .= "수신할 관리자 이메일이 설정되지 않았습니다.\n";
        return false;
    }

    if ($debugOutput !== null) {
        $mail->SMTPDebug = 3;
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= $str;
        };
    }

    $typeLabels = [
        'id_card' => '사원증 발급',
        'business_card' => '명함 발급',
        'office_supply' => '사무용품 신청'
    ];
    $typeLabel = $typeLabels[$requestType] ?? $requestType;

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = ($smtpAuth === '1');
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpEnc;
        $mail->Port = intval($smtpPort);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, '행정지원 요청 시스템');
        $mail->addAddress($adminEmail);
        $mail->isHTML(false);

        $mail->Subject = "[행정지원 요청] {$employeeName} 님이 {$typeLabel}를 요청했습니다.";
        $body = "행정지원 요청 알림\n\n"
            . "신청자: {$employeeName}\n"
            . "요청종류: {$typeLabel}\n"
            . "신청일시: {$requestDate}\n";
        if (!empty($requestContent)) {
            $body .= "요청사항:\n{$requestContent}\n";
        }
        $body .= "\n관리자페이지에서 확인 후 처리해주세요.";
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errMsg = "PHPMailer Exception: " . $e->getMessage();
        error_log("Mail send failed: " . $errMsg);
        if ($debugOutput !== null) {
            $debugOutput .= $errMsg . "\n";
        }
        return false;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'request':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $employeeId = intval($input['employee_id'] ?? $_SESSION['user_id']);
        $requestType = $input['request_type'] ?? '';
        $content = trim($input['content'] ?? '');

        if (!in_array($requestType, ['id_card', 'business_card', 'office_supply'])) {
            http_response_code(400);
            echo json_encode(['error' => '요청 종류가 올바르지 않습니다.']);
            break;
        }

        $db = getDB();

        $stmt = $db->prepare("SELECT name FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $emp = $stmt->fetch();
        if (!$emp) {
            http_response_code(404);
            echo json_encode(['error' => '사원 정보를 찾을 수 없습니다.']);
            break;
        }

        $stmt = $db->prepare("INSERT INTO support_requests (employee_id, request_type, content, status) VALUES (?, ?, ?, 'requested')");
        $stmt->execute([$employeeId, $requestType, $content]);
        $requestId = $db->lastInsertId();

        $now = date('Y-m-d H:i');
        $emailSent = sendSupportEmail($emp['name'], $requestType, $content, $now);

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $requestId,
                'email_sent' => $emailSent
            ]
        ]);
        break;

    case 'list':
        requireAdmin();
        $db = getDB();
        $stmt = $db->prepare("
            SELECT sr.*, e.name, e.emp_no, e.department_id,
                   d.name as department_name
            FROM support_requests sr
            JOIN employees e ON sr.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();

        $typeLabels = [
            'id_card' => '사원증',
            'business_card' => '명함',
            'office_supply' => '사무용품'
        ];
        $statusLabels = [
            'requested' => '요청중',
            'completed' => '완료'
        ];

        foreach ($list as &$r) {
            $r['request_type_label'] = $typeLabels[$r['request_type']] ?? $r['request_type'];
            $r['status_label'] = $statusLabels[$r['status']] ?? $r['status'];
        }

        echo json_encode(['success' => true, 'data' => $list]);
        break;

    case 'complete':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? 'completed';
        $notes = trim($input['notes'] ?? '');

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => '요청 ID가 필요합니다.']);
            break;
        }

        if ($status !== 'completed') {
            http_response_code(400);
            echo json_encode(['error' => '상태값이 올바르지 않습니다.']);
            break;
        }

        $db = getDB();
        $stmt = $db->prepare("UPDATE support_requests SET status = ?, notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->execute([$status, $notes, $_SESSION['user_id'], $id]);

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
