<?php
// DB 연결 및 데이터 가져오기 (수정 불필요)
require_once __DIR__ . '/config/database.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once 'config/security.php';
if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$id = intval($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("
    SELECT vr.*, e.name, e.emp_no, p.name as position_name, d.name as department_name,
           vt.name as vacation_type_name, e.phone1, e.phone2
    FROM vacation_requests vr
    JOIN employees e ON vr.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON e.department_id = d.id
    JOIN vacation_types vt ON vr.vacation_type_id = vt.id
    WHERE vr.id = ?
");
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) { die('요청을 찾을 수 없습니다.'); }
$startDate = date('Y년 m월 d일', strtotime($request['start_date']));
$endDate   = date('Y년 m월 d일', strtotime($request['end_date']));
$createdDate = date('Y년  m월  d일', strtotime($request['created_at']));

// 요일 변환
function getDayOfWeek($dateStr) {
    $days = ['일', '월', '화', '수', '목', '금', '토'];
    return $days[date('w', strtotime($dateStr))];
}
$startDow = getDayOfWeek($request['start_date']);
$endDow   = getDayOfWeek($request['end_date']);

// 휴가 구분 체크박스
$vacationType = $request['vacation_type_name'] ?? '';
$isHanchaAM = in_array($vacationType, ['반차', '반차(오전)']) || ($request['end_half'] ?? 'full') === 'morning';
$isHanchaPM = ($vacationType === '반차(오후)') || ($request['start_half'] ?? 'full') === 'afternoon';
$isHancha   = $isHanchaAM || $isHanchaPM;
$isYeoncha  = ($vacationType === '연차');
$isGyeongjo = in_array($vacationType, ['경조사', '경조 휴가']);
$isEtc      = !$isHancha && !$isYeoncha && !$isGyeongjo;
$etcText    = $isEtc ? $vacationType : '';

// 체크박스 문자 (HTM 스타일: ☑/☐)
function chk($bool) { return $bool ? '&#9746;' : '&#9744;'; }
function halfLabel($halfDb, $vacationType) {
    if ($halfDb === 'morning') return ' 오전';
    if ($halfDb === 'afternoon') return ' 오후';
    if (in_array($vacationType, ['반차', '반차(오전)'])) return ' 오전';
    if ($vacationType === '반차(오후)') return ' 오후';
    return '';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>휴가 신청서</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f0f0f0;
            font-family: 'Noto Sans KR', '맑은 고딕', sans-serif;
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
            font-size: 22pt;
            font-weight: 700;
            letter-spacing: 10px;
            margin-bottom: 12mm;
            margin-top: 4mm;
        }

        table { border-collapse: collapse; }

        /* 결재란 */
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
            text-orientation: mixed;
            font-weight: 600;
            font-size: 9pt;
            width: 4px;
        }
        .approval-table .hdr { font-weight: 600; height: 26px; width: 100px; }
        .approval-table .stamp { height: 75px; min-width: 100px; }
        .approval-table .gap { border: none !important; width: 12px; }

        /* 본문 테이블 */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
            font-size: 11pt;
        }
        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: middle;
        }
        .main-table th {
            background: #BDBDBD !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            font-size: 12pt;
        }
        .main-table td { text-align: center; }

        .contact-inner { width: 100%; }
        .contact-inner td { border: none; padding: 2px 0; text-align: left; font-size: 11pt; }

        .vtype-line { text-align: left; font-size: 14pt; line-height: 2; }
        .vtype-line .lbl { font-size: 11pt; }

        .footer-text { text-align: center; font-size: 11pt; margin-bottom: 22mm; }

        .sign-area { text-align: center; margin-bottom: 14mm; }
        .sign-area .date-line { font-size: 12pt; margin-bottom: 10mm; letter-spacing: 2px; }
        .sign-area .applicant-line { font-size: 14pt; }


        @media print {
            body { background: #fff; }
            .page { margin: 0; box-shadow: none; padding: 15mm 16mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; padding:12px; background:#fff; border-bottom:1px solid #ddd;">
    <button onclick="window.print()" style="padding:8px 28px; font-size:14px; background:#222; color:#fff; border:none; cursor:pointer; border-radius:3px; font-family:'Noto Sans KR',sans-serif;">
        🖨 인쇄
    </button>
    <button onclick="window.close()" style="margin-left:10px; padding:8px 20px; font-size:14px; background:#fff; color:#444; border:1px solid #aaa; cursor:pointer; border-radius:3px; font-family:'Noto Sans KR',sans-serif;">
        ✕ 닫기
    </button>
</div>

<div class="page">
<div> <img src="Logo.jpg" align="Left"> </img></div>

    <div class="doc-title">휴 가 신 청 서</div>

            <!-- 결재란 -->
    <table class="approval-table">
        <tr>
            <td rowspan="2" class="label-v">결<br><br><br>재</td>
            <td class="hdr">본부장</td>
            <td class="hdr">부대표</td>
            <td rowspan="2" class="gap"></td>
			<td rowspan="2" class="gap"></td>
			<td rowspan="2" class="gap"></td>
			<td rowspan="2" class="gap"></td>
			<td rowspan="2" class="gap"></td>
			<td rowspan="2" class="gap"></td>
            <td rowspan="2" class="label-v">경<br>영<br>관<br>리</td>
            <td class="hdr">담 당</td>
            <td class="hdr">본부장</td>
            <td class="hdr">대표이사</td>
        </tr>
        <tr>
            <td class="stamp"></td>
            <td class="stamp"></td>

            <td class="stamp"></td>
            <td class="stamp"></td>
            <td class="stamp"></td>
        </tr>
    </table>

    <!-- 본문 테이블 -->
    <table class="main-table">
        <tr style="height:65px">
            <th style="width:25%" >소&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;속</th>
            <td style="width:25%"><?= htmlspecialchars($request['department_name'] ?? '') ?></td>
            <th style="width:25%">직&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;급</th>
            <td style="width:25%"><?= htmlspecialchars($request['position_name'] ?? '') ?></td>
        </tr>
        <tr style="height:70px">
            <th>이&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;름</th>
            <td><?= htmlspecialchars($request['name'] ?? '') ?></td>
            <th>긴급연락처</th>
            <td>
                <div style="display:flex;align-items:center;">
                    <span style="width:30px;flex-shrink:0;">1</span>
                    <span style="flex:1;text-align:center;">
                        <?= !empty($request['phone1']) ? htmlspecialchars($request['phone1']) : '-' ?>
                    </span>
                </div>
                <div style="display:flex;align-items:center;">
                    <span style="width:30px;flex-shrink:0;">2</span>
                    <span style="flex:1;text-align:center;">
                        <?= !empty($request['phone2']) ? htmlspecialchars($request['phone2']) : '-' ?>
                    </span>
                </div>
            </td>
        </tr>
        <tr>
            <th>휴가 구분</th>
            <td colspan="3" style="text-align:left; padding:10px 14px;">
                <div class="vtype-line">
                    <?= chk($isHanchaAM) ?> <span class="lbl">1. 반차(오전)</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <?= chk($isHanchaPM) ?> <span class="lbl">2. 반차(오후)</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <?= chk($isYeoncha) ?> <span class="lbl">3. 연차</span>
                </div>
                <div class="vtype-line">
					<?= chk($isGyeongjo) ?> <span class="lbl">4. 경조휴가</span>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <?= chk($isEtc) ?> <span class="lbl">5. 기타(<?= htmlspecialchars($etcText) ?>)</span>
                </div>
            </td>
        </tr>
        <tr style="height:65px">
            <th>휴가 기간</th>
            <td colspan="3">
                <?php
                $startLabel = halfLabel($request['start_half'] ?? 'full', $vacationType);
                $endLabel   = halfLabel($request['end_half'] ?? 'full', $vacationType);
                echo htmlspecialchars($startDate) . ' (' . $startDow . ')' . $startLabel . ' 부터 ' . htmlspecialchars($endDate) . ' (' . $endDow . ')' . $endLabel . ' 까지';
                ?>
            </td>
        </tr>
        <tr style="height:65px">
            <th>휴가 일수</th>
            <td colspan="3"><?= htmlspecialchars($request['days'] ?? '') ?> 일</td>
        </tr>
        <tr style="height:65px">
            <th>휴가 사유</th>
            <td colspan="3"><?= htmlspecialchars($request['reason'] ?? '') ?></td>
        </tr>
        <tr style="height:65px">
            <th>메모<br><span style="color:#EAEAEA; font-size:7pt;">(수기 작성 공간)</span></th>
            <td colspan="3" style="height:40px;"></td>
        </tr>
    </table>
    <div class="footer-text"><p><p>위와 같이 휴가를 신청하오니 허가하여 주시기 바랍니다.</div>

    <div class="sign-area">
        <div class="date-line"><?= htmlspecialchars($createdDate) ?></div>
        <div class="applicant-line">
            신청인&nbsp;&nbsp;&nbsp;
            <?= htmlspecialchars($request['name'] ?? '') ?>
            &nbsp;&nbsp;&nbsp;<span style="fontcolor:litegray">(서명)</span>
        </div>
    </div>

</div>

</body>
</html>