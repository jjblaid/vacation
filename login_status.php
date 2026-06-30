<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'system_admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>접근 불가</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>403 Forbidden</h1><p>권한이 없습니다.</p><a href="index.php">로그인 페이지로 이동</a></body></html>';
    exit;
}

require_once 'config/security.php';
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT * FROM login_log WHERE logout_at IS NULL AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY login_at DESC");
$stmt->execute();
$sessions = $stmt->fetchAll();

$roleNames = [
    'system_admin' => '시스템관리자',
    'reviewer' => '검토자',
    'dept_manager' => '관리자',
    'ceo' => '대표이사',
    'vice_president' => '부대표',
    'user' => '사용자'
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>현재 접속자</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { background: #f1f5f9; padding: 32px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .count { font-size: 14px; color: #64748b; }
        .count em { font-style: normal; font-weight: 700; color: #1d4ed8; }
        .ip { font-family: monospace; font-size: 13px; color: #64748b; }
        .time { font-size: 13px; color: #64748b; white-space: nowrap; }
        .now { color: #059669; font-weight: 600; }
        .header-info { display: flex; align-items: center; gap: 12px; }
        .header-info .user { font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <div class="section-header">
                <div class="header-info">
                    <h2 class="section-title">🟢 현재 접속자</h2>
                    <span class="count">총 <em><?= count($sessions) ?></em>명</span>
                </div>
                <div class="header-info">
                    <span class="user"><?= htmlspecialchars($_SESSION['user']['name']) ?>님</span>
                    <a href="index.php" class="btn btn-sm btn-secondary">메인으로</a>
                    <a href="admin.php" class="btn btn-sm btn-secondary">관리자</a>
                    <button class="btn btn-sm btn-secondary" onclick="location.reload()">🔄 새로고침</button>
                </div>
            </div>
            <div class="section-body">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>이름</th>
                                <th>아이디</th>
                                <th>권한</th>
                                <th>부서</th>
                                <th>IP 주소</th>
                                <th>로그인 시간</th>
                                <th>마지막 활동</th>
                            </tr>
                        </thead>
                        <tbody>
<?php if (count($sessions) === 0): ?>
                            <tr><td colspan="7" style="text-align:center;color:#94a3b8;">접속 중인 사용자가 없습니다.</td></tr>
<?php else: ?>
<?php foreach ($sessions as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['emp_no']) ?></td>
                                <td><span class="status-badge status-active"><?= htmlspecialchars($roleNames[$s['role']] ?? $s['role']) ?></span></td>
                                <td><?= htmlspecialchars($s['department_name'] ?? '-') ?></td>
                                <td class="ip"><?= htmlspecialchars($s['ip_address'] ?? '-') ?></td>
                                <td class="time"><?= htmlspecialchars($s['login_at']) ?></td>
                                <td class="time"><?= htmlspecialchars($s['last_activity']) ?></td>
                            </tr>
<?php endforeach; ?>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="count" style="margin-top:16px;">※ 30분 이상 활동이 없거나 로그아웃한 사용자는 자동으로 목록에서 제외됩니다.</p>
            </div>
        </div>
    </div>
</body>
</html>
