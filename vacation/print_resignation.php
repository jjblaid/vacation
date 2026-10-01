<?php
require_once __DIR__ . '/config/database.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once 'config/security.php';
if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$employeeId = intval($_GET['employee_id'] ?? $_SESSION['user_id']);
$db = getDB();

$stmt = $db->prepare("
    SELECT e.*,
           p.name AS position_name,
           d.name AS department_name
    FROM employees e
    LEFT JOIN positions  p ON e.position_id = p.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch();
if (!$emp) { die('사원 정보를 찾을 수 없습니다.'); }

$resignDate = $emp['resignation_date']
    ? date('Y년  m월  d일', strtotime($emp['resignation_date']))
    : '';
$today = date('Y년  m월  d일');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사직서</title>
    <style nonce="<?= $cspNonce ?>">
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f0f0f0;
            font-family: 'Noto Sans KR', '맑은 고딕', '굴림', sans-serif;
            color: #000;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 18mm 16mm 20mm 16mm;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }

        .doc-title {
            text-align: center;
            font-size: 26pt;
            font-weight: 700;
            letter-spacing: 14px;
            margin-bottom: 4mm;
            margin-top: 4mm;
        }
        .doc-divider {
            text-align: center;
            font-size: 11pt;
            letter-spacing: -1px;
            margin-bottom: 10mm;
            color: #000;
        }

        table { border-collapse: collapse; }

        .approval-table {
            width: 100%;
            margin-bottom: 8mm;
            font-size: 11pt;
        }
        .approval-table td {
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            padding: 4px 8px;
        }
        .approval-table .label-v {
            font-weight: 600;
            font-size: 9pt;
            width: 22px;
            line-height: 1.8;
        }
        .approval-table .hdr  { font-weight: 600; height: 26px; min-width: 90px; }
        .approval-table .stamp { height: 75px; min-width: 90px; }
        .approval-table .gap  { border: none !important; width: 14px; }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
            margin-top: 20mm;
            font-size: 11pt;
        }
        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 8px 12px;
            vertical-align: middle;
        }
        .main-table th {
            background: #BDBDBD !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            font-size: 11pt;
        }
        .main-table td { text-align: left; }
        .main-table td.center { text-align: center; }

        .reason-cell { min-height: 120px; padding: 10px 12px; }
        .main-table tr { height: 65px; }
        .main-table tr.h-150 { height: 150px; }
        .main-table tr.h-90 { height: 90px; }
        .main-table th { width: 22%; }
        .main-table td { width: 28%; }
        .signature-memo { color:#EAEAEA; font-size:7pt; }
        .signature-label { color:#aaa; }
        .recipient-line { text-align:right; font-size:12pt; margin-top:10mm; }

        .footer-text {
            text-align: center;
            font-size: 11pt;
            margin-bottom: 22mm;
            line-height: 2;
        }

        .sign-area { text-align: center; margin-bottom: 14mm; }
        .sign-area .date-line       { font-size: 12pt; margin-bottom: 10mm; letter-spacing: 2px; }
        .sign-area .applicant-line  { font-size: 13pt; }

        .toolbar { text-align:center; padding:12px; background:#fff; border-bottom:1px solid #ddd; }
        .toolbar-inner { max-width:600px; margin:0 auto; }
        .toolbar-textarea { width:100%; padding:10px; font-size:14px; font-family:'Noto Sans KR',sans-serif; border:1px solid #ccc; border-radius:4px; resize:vertical; margin-bottom:10px; }
        .btn-print { padding:8px 28px; font-size:14px; background:#222; color:#fff; border:none; cursor:pointer; border-radius:3px; font-family:'Noto Sans KR',sans-serif; }
        .btn-close { margin-left:10px; padding:8px 20px; font-size:14px; background:#fff; color:#444; border:1px solid #aaa; cursor:pointer; border-radius:3px; font-family:'Noto Sans KR',sans-serif; }

        @media print {
            body  { background: #fff; }
            .page { margin: 0; box-shadow: none; padding: 15mm 16mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print toolbar">
    <div class="toolbar-inner">
        <textarea id="reasonInput" rows="4"
            placeholder="퇴사 사유를 입력해주세요"
            class="toolbar-textarea"></textarea>
        <div>
            <button id="btnPrintResign" class="btn-print">🖨 인쇄</button>
            <button id="btnCloseResign" class="btn-close">✕ 닫기</button>
        </div>
    </div>
</div>

<div class="page">

    <div><img src="Logo.jpg" align="left" alt=""></div>

    <div class="doc-title">사 &nbsp; 직 &nbsp; 서</div>
    <p>

    <!--<table class="approval-table">
        <tr>
            <td rowspan="2" class="label-v">결<br><br><br>재</td>
            <td class="hdr">부&nbsp;&nbsp;서&nbsp;&nbsp;장</td>
            <td class="hdr">본&nbsp;&nbsp;부&nbsp;&nbsp;장</td>
            <td class="hdr">부&nbsp;&nbsp;대&nbsp;&nbsp;표</td>
            <td rowspan="2" class="gap"></td>
            <td rowspan="2" class="gap"></td>
            <td rowspan="2" class="gap"></td>
            <td rowspan="2" class="gap"></td>
            <td rowspan="2" class="gap"></td>
            <td rowspan="2" class="label-v">경<br>영<br>관<br>리</td>
            <td class="hdr">담&nbsp;&nbsp;&nbsp;&nbsp;당</td>
            <td class="hdr">본&nbsp;&nbsp;부&nbsp;&nbsp;장</td>
            <td class="hdr">대&nbsp;표&nbsp;이&nbsp;사</td>
        </tr>
        <tr>
            <td class="stamp"></td>
            <td class="stamp"></td>
            <td class="stamp"></td>

            <td class="stamp"></td>
            <td class="stamp"></td>
            <td class="stamp"></td>
        </tr>
    </table>-->
	<p>
    <table class="main-table">
        <tr>
            <th>소&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;속</th>
            <td><?= htmlspecialchars($emp['department_name'] ?? '') ?></td>
            <th>직&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;위</th>
            <td><?= htmlspecialchars($emp['position_name'] ?? '') ?></td>
        </tr>
        <tr>
            <th>성&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;명</th>
            <td><?= htmlspecialchars($emp['name'] ?? '') ?></td>
            <th>생&nbsp;년&nbsp;월&nbsp;일</th>
            <td><?= htmlspecialchars($emp['birth_date'] ?? '') ?></td>
        </tr>
        <tr>
            <th>입&nbsp;사&nbsp;년&nbsp;월&nbsp;일</th>
            <td><?= htmlspecialchars($emp['hire_date'] ?? '') ?></td>
            <th>연&nbsp;락&nbsp;처</th>
            <td><?= htmlspecialchars($emp['phone1'] ?? ($emp['phone2'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>퇴사&nbsp;예정일</th>
            <td colspan="3"><?= htmlspecialchars($resignDate) ?></td>
        </tr>
        <tr class="h-150">
            <th>퇴&nbsp;사&nbsp;사&nbsp;유</th>
            <td colspan="3" class="reason-cell">
                <span id="printReason"></span>
            </td>
        </tr>
        <tr class="h-90">
            <th>메&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;모<br><span class="signature-memo">(수기 작성 공간)</span></th>
            <td colspan="3"></td>
        </tr>
    </table>

    <div class="footer-text">
        <p>위와 같은 사유로 사직하고자 하오니 수리하여 주시기 바랍니다.</p>
    </div>

    <div class="sign-area">
        <div class="date-line"><?= htmlspecialchars($today) ?></div>
        <div class="applicant-line">
            제출자&nbsp;&nbsp;&nbsp;
            <?= htmlspecialchars($emp['name'] ?? '') ?>
            &nbsp;&nbsp;&nbsp;<span class="signature-label">(서명)</span>
        </div>
    </div>

    <div class="recipient-line">
        대표이사 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 귀하
    </div>

</div>

<script nonce="<?= $cspNonce ?>">
document.getElementById('btnPrintResign').addEventListener('click', function() {
    var reason = document.getElementById('reasonInput').value;
    document.getElementById('printReason').textContent = reason;
    window.print();
});
document.getElementById('btnCloseResign').addEventListener('click', function() {
    window.close();
});
</script>
</body>
</html>
