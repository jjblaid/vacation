<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'system_admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>접근 불가</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>403 Forbidden</h1><p>권한이 없습니다.</p><a href="index.php">로그인 페이지로 이동</a></body></html>';
    exit;
}

require_once 'config/security.php';
require_once 'config/database.php';

$view = $_GET['view'] ?? 'active';
$db = getDB();

if ($view === 'all') {
    $stmt = $db->prepare("SELECT * FROM login_log WHERE login_at > DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY login_at DESC");
    $stmt->execute();
} else {
    $stmt = $db->prepare("SELECT * FROM login_log WHERE logout_at IS NULL AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY login_at DESC");
    $stmt->execute();
}
$sessions = $stmt->fetchAll();

$roleNames = [
    'system_admin' => '시스템관리자',
    'reviewer' => '검토자',
    'dept_manager' => '관리자',
    'ceo' => '대표이사',
    'vice_president' => '부대표',
    'user' => '사용자'
];

$isActiveView = $view !== 'all';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($isActiveView): ?>
    <meta http-equiv="refresh" content="15">
<?php endif; ?>
    <title><?= $isActiveView ? '현재 접속자' : '접속 이력' ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { background: #f1f5f9; padding: 32px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .count { font-size: 14px; color: #64748b; }
        .count em { font-style: normal; font-weight: 700; color: #1d4ed8; }
        .ip { font-family: monospace; font-size: 13px; color: #64748b; }
        .time { font-size: 13px; color: #64748b; white-space: nowrap; }
        .now { color: #059669; font-weight: 600; }
        .header-info { display: flex; align-items: center; gap: 12px; }
        .header-info .user { font-size: 13px; color: #64748b; }
        .tabs { display: flex; gap: 0; margin-bottom: 0; }
        .tabs a { display: inline-block; padding: 10px 20px; border-radius: 8px 8px 0 0; font-size: 14px; font-weight: 600; text-decoration: none; color: #64748b; background: #e2e8f0; transition: all 0.2s; }
        .tabs a.active { color: #1e293b; background: #fff; }
        .tabs a:hover:not(.active) { background: #cbd5e1; }
        .status-logout { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <div class="section-header" style="padding-bottom:0;border-bottom:none;flex-direction:column;align-items:stretch;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0 24px 16px;">
                    <div class="header-info">
                        <h2 class="section-title"><?= $isActiveView ? '🟢 현재 접속자' : '📋 접속 이력' ?></h2>
                        <span class="count">총 <em><?= count($sessions) ?></em>건</span>
                    </div>
                    <div class="header-info">
                        <span class="user"><?= htmlspecialchars($_SESSION['user']['name']) ?>님</span>
                        <a href="index.php" class="btn btn-sm btn-secondary">메인으로</a>
                        <a href="admin.php" class="btn btn-sm btn-secondary">관리자</a>
                        <button class="btn btn-sm btn-secondary" onclick="location.reload()">🔄 새로고침</button>
                    </div>
                </div>
                <div class="tabs" style="padding:0 24px;">
                    <a href="login_status.php" class="<?= $isActiveView ? 'active' : '' ?>">🟢 현재 접속자</a>
                    <a href="login_status.php?view=all" class="<?= !$isActiveView ? 'active' : '' ?>">📋 전체 이력 (90일)</a>
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
<?php if (!$isActiveView): ?>
                                <th>로그아웃 시간</th>
                                <th>상태</th>
<?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
<?php if (count($sessions) === 0): ?>
                            <tr><td colspan="<?= $isActiveView ? 7 : 9 ?>" style="text-align:center;color:#94a3b8;"><?= $isActiveView ? '접속 중인 사용자가 없습니다.' : '최근 90일간 접속 기록이 없습니다.' ?></td></tr>
<?php else: ?>
<?php foreach ($sessions as $s): ?>
<?php $isLoggedOut = $s['logout_at'] !== null; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['emp_no']) ?></td>
                                <td><span class="status-badge <?= $isLoggedOut ? 'status-cancelled' : 'status-active' ?>"><?= htmlspecialchars($roleNames[$s['role']] ?? $s['role']) ?></span></td>
                                <td><?= htmlspecialchars($s['department_name'] ?? '-') ?></td>
                                <td class="ip"><?= htmlspecialchars($s['ip_address'] ?? '-') ?></td>
                                <td class="time"><?= htmlspecialchars($s['login_at']) ?></td>
                                <td class="time"><?= htmlspecialchars($s['last_activity']) ?></td>
<?php if (!$isActiveView): ?>
                                <td class="time"><?= $isLoggedOut ? htmlspecialchars($s['logout_at']) : '-' ?></td>
                                <td><span class="status-badge <?= $isLoggedOut ? 'status-cancelled' : 'status-active' ?>"><?= $isLoggedOut ? '로그아웃' : '접속중' ?></span></td>
<?php endif; ?>
                            </tr>
<?php endforeach; ?>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
<?php if ($isActiveView): ?>
                <p class="count" style="margin-top:16px;">※ 30분 이상 활동이 없거나 로그아웃한 사용자는 자동으로 목록에서 제외됩니다.</p>
<?php else: ?>
                <p class="count" style="margin-top:16px;">※ 최근 90일간의 모든 로그인/로그아웃 기록을 표시합니다.</p>
<?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
