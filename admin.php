<?php
define('AUTH_INCLUDED', true);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once 'config/security.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'system_admin') {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$db = getDB();
$currentYear = date('Y');
$nextYear = $currentYear + 1;

if ((int)date('n') === 12) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM annual_by_year WHERE year = ?");
    $stmt->execute([$nextYear]);
    $result = $stmt->fetch();

    if (intval($result['cnt']) == 0) {
        $stmt = $db->prepare("INSERT INTO annual_by_year (employee_id, year, annual_leave)
                              SELECT id, ?, 15 FROM employees WHERE is_active = 1");
        $stmt->execute([$nextYear]);
        
        $stmt = $db->prepare("UPDATE employees SET annual_leave = 15 WHERE is_active = 1");
        $stmt->execute();
    }
}

$currentUser = $_SESSION['user'];

// 권한 한글명 매핑
$roleNames = [
    'system_admin' => '시스템관리자',
    'reviewer' => '검토자',
    'dept_manager' => '관리자',
    'ceo' => '대표이사',
    'vice_president' => '부대표',
    'user' => '사용자'
];
$currentUserRoleName = $roleNames[$currentUser['role']] ?? $currentUser['role'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 페이지 - 휴가신청 시스템</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" integrity="sha384-39yVKLsD9lMelmY+ij49KZgE+Mfk6hjdUPNE8yKHqdMPceLXzhlCJAK81xlD5jDjday" crossorigin="anonymous">
    <link href="css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700&display=swap" integrity="sha512-8o9GL4vAqkQv5ASESaPGM9ynmyrOu6tsrukDDRwRnsbC0d2xlN35dCr6S9W37/4HbKhIEVCvfCtBZSrl4hA6bQ==" crossorigin="anonymous">
    <script nonce="<?= $cspNonce ?>">
        const roleNames = {"system_admin":"시스템관리자","reviewer":"검토자","dept_manager":"관리자","ceo":"대표이사","vice_president":"부대표","user":"사용자"};
    </script>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-logo">
                <h1>⚙️ 관리자 페이지</h1>
            </div>
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?=htmlspecialchars($currentUser['name'])?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentUserRoleName); ?></div>
                </div>
                <a href="index.php" class="btn btn-sm btn-secondary">← 휴가신청</a>
                <button id="adminLogout" class="btn btn-sm btn-secondary">로그아웃</button>
            </div>
        </div>
    </header>

    <main class="container">
<div class="tabs">
    <div class="tabs-scroll">
        <button class="tab active" data-tab="employees" id="tabEmployeesBtn">👥 사원 관리</button>
        <button class="tab" data-tab="resigned" id="tabResignedBtn">👤 퇴사자</button>
        <button class="tab" data-tab="resigning" id="tabResigningBtn">⚠️ 퇴사예정자</button>
        <button class="tab" data-tab="annualLeave" id="tabAnnualLeaveBtn">📅 연도별 연차</button>
        <button class="tab" data-tab="certificate" id="tabCertificateBtn">📜 증명서 요청</button>
        <button class="tab" data-tab="support" id="tabSupportBtn">📋 지원 요청</button>
        <div class="tab-dropdown">
            <button class="tab" id="tabSettingsDropdown">⚙️ 환경설정 ▾</button>
            <div class="tab-dropdown-menu">
                <button class="tab-dropdown-item" data-tab="vacationTypes" id="tabVacationTypesItem">📋 휴가 유형</button>
                <button class="tab-dropdown-item" data-tab="positions" id="tabPositionsItem">👔 직급 관리</button>
                <button class="tab-dropdown-item" data-tab="departments" id="tabDepartmentsItem">🏢 부서 관리</button>
                <button class="tab-dropdown-item" data-tab="holidays" id="tabHolidaysItem">🏖️ 공휴일</button>
                <button class="tab-dropdown-item" data-tab="settings" id="tabSettingsItem">⚙️ SMTP 설정</button>
            </div>
        </div>
        <button class="tab" data-tab="allRequests" id="tabAllRequestsBtn">📊 전체 휴가 현황</button>
    </div>
</div>

        <!-- Employees Tab -->
        <div id="tabEmployees" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">사원 목록</h2>
                    <button id="btnAddEmployee" class="btn btn-primary">+ 사원 추가</button>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>계정</th>
                                    <th>입사일</th>
                                    <th>이름</th>
                                    <th>부서</th>
                                    <th>직급</th>
                                    <th>권한</th>
                                    <th>부여</th>
                                    <th>사용</th>
                                    <th>잔여</th>
                                    <th>상태</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="employeesList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<!-- Resigned Tab -->
<div id="tabResigned" class="tab-content hidden">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">퇴사자 목록</h2>
        </div>
        <div class="section-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>계정</th>
                            <th>이름</th>
                            <th>부서</th>
                            <th>직급</th>
                            <th>입사일</th>
                            <th>퇴직일</th>
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody id="resignedList"></tbody>
                </table>
            </div>
            <div id="resignedVacationHistory" style="margin-top: 20px; display: none;">
                <h3 id="resignedEmployeeName" style="margin-bottom: 10px;"></h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>휴가 유형</th>
                                <th>기간</th>
                                <th>일수</th>
                                <th>상태</th>
                                <th>사유</th>
                            </tr>
                        </thead>
                        <tbody id="resignedVacationList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resigning Employees Tab -->
<div id="tabResigning" class="tab-content hidden">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">퇴사예정자 관리</h2>
        </div>
        <div class="section-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>계정</th>
                            <th>이름</th>
                            <th>부서</th>
                            <th>직급</th>
                            <th>입사일</th>
                            <th>보전연차(잔여)</th>
                            <th>잔여연차</th>
                            <th>합계</th>
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody id="resigningList"></tbody>
                </table>
            </div>
            <div id="resigningVacationHistory" style="margin-top:20px; display:none;">
                <h3 id="resigningEmployeeName" style="margin-bottom:10px;"></h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>휴가 유형</th>
                                <th>기간</th>
                                <th>일수</th>
                                <th>상태</th>
                                <th>사유</th>
                            </tr>
                        </thead>
                        <tbody id="resigningVacationList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Vacation Types Tab -->
        <div id="tabVacationTypes" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">휴가 유형 관리</h2>
                    <button id="btnAddType" class="btn btn-primary">+ 유형 추가</button>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>순서</th>
                                    <th>유형명</th>
                                    <th>차감일수</th>
                                    <th>최대일수</th>
                                    <th>차감 대상</th>
                                    <th>주말/공휴일</th>
                                    <th>색상</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="typesList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Positions Tab -->
        <div id="tabPositions" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">직급 관리</h2>
                    <button id="btnAddPosition" class="btn btn-primary">+ 직급 추가</button>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>직급명</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="positionsList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments Tab -->
        <div id="tabDepartments" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">부서 관리</h2>
                    <button id="btnAddDepartment" class="btn btn-primary">+ 부서 추가</button>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>부서코드</th>
                                    <th>부서명</th>
                                    <th>색상</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="departmentsList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Modal -->
        <div id="departmentModal" class="modal-overlay hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="departmentModalTitle">부서 추가</h3>
                    <button class="modal-close" id="btnCloseDept">&times;</button>
                </div>
                <form id="departmentForm" onsubmit="saveDepartment(event)">
                    <input type="hidden" id="deptId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>부서코드</label>
                            <input type="text" id="deptCode" required maxlength="20">
                        </div>
                        <div class="form-group">
                            <label>부서명</label>
                            <input type="text" id="deptName" required maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>색상</label>
                            <input type="color" id="deptColor" value="#667eea">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="btnCancelDept">취소</button>
                        <button type="submit" class="btn btn-primary">저장</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Position Modal -->
        <div id="positionModal" class="modal-overlay hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="positionModalTitle">직급 추가</h3>
                    <button class="modal-close" id="btnClosePosition">&times;</button>
                </div>
                <form id="positionForm" onsubmit="savePosition(event)">
                    <input type="hidden" id="positionId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>직급명</label>
                            <input type="text" id="positionName" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="btnCancelPosition">취소</button>
                        <button type="submit" class="btn btn-primary">저장하기</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Holidays Tab -->
        <div id="tabHolidays" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">공휴일 관리</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="holidayYear"></select>
                        <button class="btn btn-primary" id="btnAddHoliday">+ 공휴일 추가</button>
                    </div>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>날짜</th>
                                    <th>명칭</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="holidaysList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Holiday Modal -->
        <div id="holidayModal" class="modal-overlay hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="holidayModalTitle">공휴일 추가</h3>
                    <button class="modal-close" id="btnCloseHoliday">&times;</button>
                </div>
                <form id="holidayForm" onsubmit="saveHoliday(event)">
                    <input type="hidden" id="holidayId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>날짜</label>
                            <input type="date" id="holidayDate" required>
                        </div>
                        <div class="form-group">
                            <label>명칭</label>
                            <input type="text" id="holidayName" placeholder="공휴일명" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="btnCancelHoliday">취소</button>
                        <button type="submit" class="btn btn-primary">저장하기</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Annual Leave Edit Modal -->
        <div id="annualLeaveEditModal" class="modal-overlay hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">연차 부여 수정</h3>
                    <button class="modal-close" id="btnCloseAnnualLeaveEdit">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="aleEmployeeId">
                    <input type="hidden" id="aleYear">
                    <p id="aleEmployeeInfo" style="margin-bottom:16px;"></p>
                    <div class="form-group">
                        <label>부여 연차</label>
                        <input type="number" id="aleGranted" step="0.5" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelAnnualLeaveEdit">취소</button>
                    <button type="button" class="btn btn-primary" id="btnSaveAnnualLeaveEdit">저장</button>
                </div>
            </div>
        </div>

        <!-- Annual Leave Tab -->
        <div id="tabAnnualLeave" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📅 연도별 연차 관리</h2>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <select id="annualYearSelect" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        </select>
                    </div>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>사원명</th>
                                    <th>계정</th>
                                    <th>입사일</th>
                                    <th>부서</th>
                                    <th>직급</th>
                                    <th>부여연차</th>
                                    <th>사용연차</th>
                                    <th>잔여연차</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="annualLeaveTableBody">
                                <tr><td colspan="8" class="loading"><div class="spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Requests Tab -->
        <div id="tabAllRequests" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">전체 휴가 현황</h2>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>사원</th>
                                    <th>부서</th>
                                    <th>기간</th>
                                    <th>유형</th>
                                    <th>일수</th>
                                    <th>사유</th>
                                    <th>상태</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="allRequestsList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<!-- Certificate Requests Tab -->
<div id="tabCertificate" class="tab-content hidden">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">증명서 발급 요청</h2>
        </div>
        <div class="section-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>신청자</th>
                            <th>소속</th>
                            <th>증명서</th>
                            <th>주민번호</th>
                            <th>징계</th>
                            <th>업무기재</th>
                            <th>언어</th>
                            <th>신청일시</th>
                            <th>상태</th>
                            <th>비고</th>
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody id="certificateList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Support Requests Tab -->
<div id="tabSupport" class="tab-content hidden">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">행정지원 요청</h2>
        </div>
        <div class="section-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>신청자</th>
                            <th>소속</th>
                            <th>요청종류</th>
                            <th>요청사항</th>
                            <th>신청일시</th>
                            <th>상태</th>
                            <th>비고</th>
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody id="supportList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Complete Support Modal -->
<div id="supportCompleteModal" class="modal-overlay hidden">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3 class="modal-title" id="supportCompleteTitle">행정지원 요청 처리</h3>
            <button class="modal-close" id="btnCloseSupportComplete">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="supportCompleteId">
            <input type="hidden" id="supportCompleteStatus" value="completed">
            <div class="form-group">
                <label>비고</label>
                <textarea id="supportCompleteNotes" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;" placeholder="처리 결과, 전달일 등 비고사항"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelSupportComplete">취소</button>
            <button type="button" class="btn btn-primary" id="btnSaveSupportComplete">저장</button>
        </div>
    </div>
</div>

<!-- Complete Certificate Modal -->
<div id="certCompleteModal" class="modal-overlay hidden">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3 class="modal-title">증명서 발급 완료</h3>
            <button class="modal-close" id="btnCloseCertComplete">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="certCompleteId">
            <div class="form-group">
                <label>비고</label>
                <textarea id="certCompleteNotes" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;" placeholder="발급 방식, 전달일 등 비고사항"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelCertComplete">취소</button>
            <button type="button" class="btn btn-primary" id="btnSaveCertComplete">완료</button>
        </div>
    </div>
</div>

<!-- SMTP Settings Tab -->
<div id="tabSettings" class="tab-content hidden">
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">SMTP 설정</h2>
        </div>
        <div class="section-body">
            <div class="form-group">
                <label>SMTP 호스트</label>
                <input type="text" id="smtpHost" maxlength="200" placeholder="smtp.gmail.com" style="width:100%;">
            </div>
            <div class="form-group">
                <label>SMTP 포트</label>
                <input type="text" id="smtpPort" maxlength="10" placeholder="587" style="width:200px;">
            </div>
            <div class="form-group">
                <label>계정</label>
                <input type="text" id="smtpUser" maxlength="200" placeholder="user@gmail.com" style="width:100%;">
            </div>
            <div class="form-group">
                <label>암호</label>
                <input type="password" id="smtpPass" maxlength="200" style="width:100%;">
            </div>
            <div class="form-group">
                <label>암호화 방식</label>
                <select id="smtpEncryption" style="width:200px;">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="">없음</option>
                </select>
            </div>
            <div class="form-group">
                <label>발신 이메일</label>
                <input type="email" id="smtpFromEmail" maxlength="200" placeholder="noreply@example.com" style="width:100%;">
            </div>
            <div class="form-group">
                <label class="checkbox-label"> SMTP 인증 사용 <input type="checkbox" id="smtpAuth" value="1"> </label>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button class="btn btn-primary" id="btnSaveSmtp">저장</button>
                <button class="btn btn-secondary" id="btnTestSmtp">테스트 발송</button>
            </div>
            <div id="smtpResult" style="margin-top:10px; font-weight:600;"></div>
        </div>
    </div>
</div>
    </main>

    <!-- Password Verify Modal -->
    <div id="passwordVerifyModal" class="modal-overlay hidden" style="z-index:1100;">
        <div class="modal" style="max-width:380px;">
            <div class="modal-header">
                <h3 class="modal-title">비밀번호 확인</h3>
                <button class="modal-close" id="btnClosePwVerify">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:12px; font-size:14px; color:#555;">주민등록번호를 보려면 비밀번호를 입력하세요.</p>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="password" id="pwVerifyInput" maxlength="100" placeholder="비밀번호 입력" style="flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;" onkeydown="if(event.key==='Enter') confirmPasswordVerify()">
                    <span id="pwVerifyToggle" style="cursor:pointer; font-size:20px; user-select:none;">👁️</span>
                </div>
                <div id="pwVerifyError" style="color:#dc2626; font-size:13px; margin-top:8px; display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelPwVerify">취소</button>
                <button type="button" class="btn btn-primary" id="btnConfirmPwVerify">확인</button>
            </div>
        </div>
    </div>

    <!-- Employee Modal -->
    <div id="employeeModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="employeeModalTitle">사원 추가</h3>
                <button class="modal-close" id="btnCloseEmployee">&times;</button>
            </div>
            <form id="employeeForm">
                <div class="modal-body">
                    <input type="hidden" id="empId">
                    <div class="form-group">
                        <label>계정</label>
                        <input type="text" id="empNo" required>
                    </div>
                    <div class="form-group">
                        <label>이름</label>
                        <input type="text" id="empName" required>
                    </div>
                    <div class="form-group">
                        <label>부서</label>
                        <select id="empDept"></select>
                    </div>
                    <div class="form-group">
                        <label>직급</label>
                        <select id="empPosition">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>권한</label>
                        <select id="empRole">
                            <option value="user">사용자</option>
                            <option value="dept_manager">관리자(부서장)</option>
                            <option value="reviewer">검토자</option>
                            <option value="ceo">대표이사</option>
                            <option value="vice_president">부대표</option>
                            <option value="system_admin">시스템관리자</option>
                        </select>
                    </div>
                    <div class="form-group" id="managedDeptGroup">
                        <label>관리 부서</label>
                        <select id="managedDept"></select>
                    </div>
                    <div class="form-group">
                        <label>비상연락처1</label>
                        <input type="text" id="empPhone1" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>비상연락처2</label>
                        <input type="text" id="empPhone2" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>이메일</label>
                        <input type="email" id="empEmail" maxlength="100" placeholder="admin@example.com">
                    </div>
                    <div class="form-group">
                        <label>생년월일</label>
                        <input type="date" id="empBirthDate">
                    </div>
                    <div class="form-group">
                        <label>주소</label>
                        <input type="text" id="empAddress" maxlength="200" placeholder="기본 주소">
                    </div>
                    <div class="form-group">
                        <label>주민등록번호</label>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <input type="password" id="empResidentNo" maxlength="14" placeholder="********" readonly style="flex:1;" data-original="">
                            <button type="button" id="residentNoToggle" class="btn-toggle" style="cursor:pointer; font-size:20px; padding:0 6px; border:none; background:none;">🔒</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>입사일</label>
                        <input type="date" id="empHireDate">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label"><input type="checkbox" id="empIsActive"> 퇴직처리</label>
                    </div>
<div class="form-group" id="empResignDate" style="display:none;">
    <label>퇴직일</label>
    <input type="date" id="empResignationDate">
</div>
<div class="form-group" id="empResignGroup" style="display:none;">
    <label class="checkbox-label"><input type="checkbox" id="empIsResigning"> 퇴사예정자</label>
</div>
<div class="form-group" id="empSeveranceGroup" style="display:none;">
    <label>보전연차 (일)</label>
    <input type="number" id="empSeveranceLeave" step="0.5" min="0" value="0">
</div>
<div class="form-group">
    <label class="checkbox-label"><input type="checkbox" id="empVisibleToExec"> 임원(CEO/부대표) 휴가조회 허용</label>
</div>
<div class="form-group">
    <label>비밀번호 <small>(수정時만 입력)</small></label>
    <input type="password" id="empPassword">
</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelEmployee">취소</button>
                    <button type="button" class="btn btn-primary" id="btnSaveEmployee">저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vacation Type Modal -->
    <div id="typeModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="typeModalTitle">휴가 유형 추가</h3>
                <button class="modal-close" id="btnCloseType">&times;</button>
            </div>
            <form id="typeForm" onsubmit="saveType(event)">
                <div class="modal-body">
                    <input type="hidden" id="typeId">
                    <div class="form-group">
                        <label>유형명</label>
                        <input type="text" id="typeName" required>
                    </div>
                    <div class="form-group">
                        <label>차감일수</label>
                        <input type="number" id="typeDeduction" step="0.5" value="1">
                    </div>
                    <div class="form-group">
                        <label>최대 일수</label>
                        <input type="number" id="typeMax" step="0.5" value="999">
                    </div>
                    <div class="form-group">
                        <label>차감 대상</label>
                        <select id="typeDeductFrom">
                            <option value="none">차감 없음</option>
                            <option value="annual">연차 차감</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>색상</label>
                        <input type="color" id="typeColor" value="#667eea">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="typeCountAllDays" value="1">
                            모든일수 카운팅 (주말/공휴일 포함)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelType">취소</button>
                    <button type="submit" class="btn btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js" integrity="sha384-5/vsv56401Wf+RP3yE5/aIKW4wutk4nLY3HjueTXN0rA+DmweMtrYaN6RSjdv31bk" crossorigin="anonymous"></script>
    <script src="js/api.js"></script>
    <script nonce="<?= $cspNonce ?>">const currentUserId = <?= $currentUser['id'] ?>;</script>
    <script src="js/admin.js"></script>
</body>
</html>
