<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';
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

function sendCertificateEmail($employeeName, $certificateType, $showResident, $showDiscipline, $jobDesc, $jobDescKorean, $jobDescEnglish, $jobDescContent, $requestDate, &$debugOutput = null) {
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
        $debugOutput .= "smtp_user: " . ($smtpUser ?: '(empty)') . "\n";
        $debugOutput .= "smtp_encryption: " . ($smtpEnc ?: '(empty)') . "\n";
        $debugOutput .= "smtp_from_email: " . ($fromEmail ?: '(empty)') . "\n";
        $debugOutput .= "smtp_auth: " . ($smtpAuth ?: '0') . "\n";
        $debugOutput .= "---\n";
    }
    
    if (empty($smtpHost) || empty($fromEmail)) {
        if ($debugOutput !== null) $debugOutput .= "SMTP 호스트 또는 발신 이메일이 설정되지 않았습니다.\n";
        return false;
    }
    
    // Find admin email
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
    
    $typeLabel = $certificateType === 'career' ? '경력증명서' : '재직증명서';
    $residentLabel = $showResident ? '노출' : '비노출';
    $disciplineLabel = $showDiscipline ? '포함' : '미포함';
    $jobDescLabel = $jobDesc ? '포함' : '미포함';
    $langLabels = [];
    if ($jobDescKorean) $langLabels[] = '국문';
    if ($jobDescEnglish) $langLabels[] = '영문';
    $jobDescLangLabel = $langLabels ? implode('/', $langLabels) : '-';

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = ($smtpAuth === '1');
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpEnc;
        $mail->Port = intval($smtpPort);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, '증명서 발급 시스템');
        $mail->addAddress($adminEmail);
        $mail->isHTML(false);
        $mail->Subject = "[증명서 발급 요청] {$employeeName} 님이 {$typeLabel}를 요청했습니다.";
        $mail->Body = "증명서 발급 요청 알림\n\n"
            . "신청자: {$employeeName}\n"
            . "증명서: {$typeLabel}\n"
            . "주민등록번호: {$residentLabel}\n"
            . "징계여부: {$disciplineLabel}\n"
            . "업무기재: {$jobDescLabel}\n"
            . "언어: {$jobDescLangLabel}\n"
            . ($jobDescContent ? "업무기재 내용:\n{$jobDescContent}\n\n" : "")
            . "신청일시: {$requestDate}\n\n"
            . "관리자페이지에서 확인 후 발급을 진행해주세요.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errMsg = "PHPMailer Exception: " . $e->getMessage();
        error_log("Mail send failed: " . $errMsg);
        if ($debugOutput !== null) {
            $debugOutput .= $errMsg . "\n";
            if (strpos($e->getMessage(), 'Permission denied') !== false) {
                $debugOutput .= "\n[조치안내] 서버에서 외부 SMTP 연결이 차단되었습니다.\n";
                $debugOutput .= "SELinux: setsebool -P httpd_can_sendmail 1\n";
                $debugOutput .= "또는 방화벽(iptables/firewalld)에서 587번 포트 아웃바운드를 허용해주세요.\n";
            }
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
        $certificateType = $input['certificate_type'] ?? '';
        $showResident = intval($input['show_resident'] ?? 0);
        $showDiscipline = intval($input['show_discipline'] ?? 0);
        $jobDesc = intval($input['job_desc'] ?? 0);
        $jobDescKorean = intval($input['job_desc_korean'] ?? 0);
        $jobDescEnglish = intval($input['job_desc_english'] ?? 0);
        $jobDescContent = trim($input['job_desc_content'] ?? '');
        
        if (!in_array($certificateType, ['career', 'employment'])) {
            http_response_code(400);
            echo json_encode(['error' => '증명서 종류가 올바르지 않습니다.']);
            break;
        }
        
        $db = getDB();
        
        // Get employee info
        $stmt = $db->prepare("SELECT name, created_at FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $emp = $stmt->fetch();
        if (!$emp) {
            http_response_code(404);
            echo json_encode(['error' => '사원 정보를 찾을 수 없습니다.']);
            break;
        }
        
        // Insert request
        $stmt = $db->prepare("INSERT INTO certificate_requests (employee_id, certificate_type, show_resident, show_discipline, job_desc, job_desc_korean, job_desc_english, job_desc_content, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested')");
        $stmt->execute([$employeeId, $certificateType, $showResident, $showDiscipline, $jobDesc, $jobDescKorean, $jobDescEnglish, $jobDescContent]);
        $requestId = $db->lastInsertId();
        
        // Send email
        $now = date('Y-m-d H:i');
        $emailSent = sendCertificateEmail($emp['name'], $certificateType, $showResident, $showDiscipline, $jobDesc, $jobDescKorean, $jobDescEnglish, $jobDescContent, $now);
        
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
            SELECT cr.*, e.name, e.emp_no, e.department_id,
                   d.name as department_name,
                   e.resident_no_encrypted
            FROM certificate_requests cr
            JOIN employees e ON cr.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            ORDER BY cr.created_at DESC
        ");
        $stmt->execute();
        $list = $stmt->fetchAll();
        
        foreach ($list as &$r) {
            $plain = decryptResidentNo($r['resident_no_encrypted']);
            $r['resident_no_masked'] = $plain ? maskResidentNo($plain) : '';
            unset($r['resident_no_encrypted']);
            
            $r['certificate_type_label'] = $r['certificate_type'] === 'career' ? '경력증명서' : '재직증명서';
            $r['status_label'] = $r['status'] === 'requested' ? '요청중' : ($r['status'] === 'completed' ? '완료' : '취소');
            $r['show_resident_label'] = $r['show_resident'] ? '노출' : '비노출';
            $r['show_discipline_label'] = $r['show_discipline'] ? 'O' : 'X';
            $r['job_desc_label'] = $r['job_desc'] ? 'O' : 'X';
            $langs = [];
            if ($r['job_desc_korean']) $langs[] = '국문';
            if ($r['job_desc_english']) $langs[] = '영문';
            $r['job_desc_lang_label'] = $langs ? implode('/', $langs) : '-';
        }
        
        echo json_encode(['success' => true, 'data' => $list]);
        break;
    
    case 'complete':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => '요청 ID가 필요합니다.']);
            break;
        }
        
        $db = getDB();
        $stmt = $db->prepare("UPDATE certificate_requests SET status = 'completed', notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->execute([$notes, $_SESSION['user_id'], $id]);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'test_email':
        requireAdmin();
        $db = getDB();
        $stmt = $db->prepare("SELECT name, email FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();
        $now = date('Y-m-d H:i');
        $debugOutput = '';
        $sent = sendCertificateEmail($admin['name'] ?? '관리자', 'employment', 0, 0, 0, 0, 0, '', $now, $debugOutput);
        if ($sent) {
            echo json_encode(['success' => true, 'debug' => $debugOutput]);
        } else {
            echo json_encode(['success' => false, 'error' => '이메일 발송에 실패했습니다.', 'debug' => $debugOutput]);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
