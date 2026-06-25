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
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Role names (must match PHP $roleNames array)
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
                <button class="btn btn-sm btn-secondary" onclick="logout()">로그아웃</button>
            </div>
        </div>
    </header>

    <main class="container">
<div class="tabs">
    <div class="tabs-scroll">
        <button class="tab active" onclick="showTab('employees')">👥 사원 관리</button>
        <button class="tab" onclick="showTab('resigned')">👤 퇴사자</button>
        <button class="tab" onclick="showTab('resigning')">⚠️ 퇴사예정자</button>
        <button class="tab" onclick="showTab('annualLeave')">📅 연도별 연차</button>
        <button class="tab" onclick="showTab('certificate')">📜 증명서 요청</button>
        <button class="tab" onclick="showTab('support')">📋 지원 요청</button>
        <div class="tab-dropdown">
            <button class="tab" onclick="toggleSettingsDropdown(event)">⚙️ 환경설정 ▾</button>
            <div class="tab-dropdown-menu">
                <button class="tab-dropdown-item" onclick="showTab('vacationTypes')">📋 휴가 유형</button>
                <button class="tab-dropdown-item" onclick="showTab('positions')">👔 직급 관리</button>
                <button class="tab-dropdown-item" onclick="showTab('departments')">🏢 부서 관리</button>
                <button class="tab-dropdown-item" onclick="showTab('holidays')">🏖️ 공휴일</button>
                <button class="tab-dropdown-item" onclick="showTab('settings')">⚙️ SMTP 설정</button>
            </div>
        </div>
        <button class="tab" onclick="showTab('allRequests')">📊 전체 휴가 현황</button>
    </div>
</div>

        <!-- Employees Tab -->
        <div id="tabEmployees" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">사원 목록</h2>
                    <button class="btn btn-primary" onclick="showEmployeeModal()">+ 사원 추가</button>
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
                    <button class="btn btn-primary" onclick="showTypeModal()">+ 유형 추가</button>
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
                    <button class="btn btn-primary" onclick="showPositionModal()">+ 직급 추가</button>
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
                    <button class="btn btn-primary" onclick="showDepartmentModal()">+ 부서 추가</button>
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
                    <button class="modal-close" onclick="closeDepartmentModal()">&times;</button>
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
                        <button type="button" class="btn btn-secondary" onclick="closeDepartmentModal()">취소</button>
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
                    <button class="modal-close" onclick="closePositionModal()">&times;</button>
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
                        <button type="button" class="btn btn-secondary" onclick="closePositionModal()">취소</button>
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
                        <select id="holidayYear" onchange="loadHolidays()"></select>
                        <button class="btn btn-primary" onclick="showHolidayModal()">+ 공휴일 추가</button>
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
                    <button class="modal-close" onclick="closeHolidayModal()">&times;</button>
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
                        <button type="button" class="btn btn-secondary" onclick="closeHolidayModal()">취소</button>
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
                    <button class="modal-close" onclick="closeAnnualLeaveEditModal()">&times;</button>
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
                    <button type="button" class="btn btn-secondary" onclick="closeAnnualLeaveEditModal()">취소</button>
                    <button type="button" class="btn btn-primary" onclick="saveAnnualLeaveEdit()">저장</button>
                </div>
            </div>
        </div>

        <!-- Annual Leave Tab -->
        <div id="tabAnnualLeave" class="tab-content hidden">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📅 연도별 연차 관리</h2>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <select id="annualYearSelect" onchange="loadAnnualLeaveTable()" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;">
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
            <button class="modal-close" onclick="closeSupportCompleteModal()">&times;</button>
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
            <button type="button" class="btn btn-secondary" onclick="closeSupportCompleteModal()">취소</button>
            <button type="button" class="btn btn-primary" onclick="saveSupportComplete()">저장</button>
        </div>
    </div>
</div>

<!-- Complete Certificate Modal -->
<div id="certCompleteModal" class="modal-overlay hidden">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3 class="modal-title">증명서 발급 완료</h3>
            <button class="modal-close" onclick="closeCertCompleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="certCompleteId">
            <div class="form-group">
                <label>비고</label>
                <textarea id="certCompleteNotes" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;" placeholder="발급 방식, 전달일 등 비고사항"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCertCompleteModal()">취소</button>
            <button type="button" class="btn btn-primary" onclick="saveCertComplete()">완료</button>
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
                <button class="btn btn-primary" onclick="saveSmtpSettings()">저장</button>
                <button class="btn btn-secondary" onclick="testSmtpSettings()">테스트 발송</button>
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
                <button class="modal-close" onclick="closePasswordVerifyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:12px; font-size:14px; color:#555;">주민등록번호를 보려면 비밀번호를 입력하세요.</p>
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="password" id="pwVerifyInput" maxlength="100" placeholder="비밀번호 입력" style="flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;" onkeydown="if(event.key==='Enter') confirmPasswordVerify()">
                    <span id="pwVerifyToggle" onclick="togglePwVerifyVisible()" style="cursor:pointer; font-size:20px; user-select:none;">👁️</span>
                </div>
                <div id="pwVerifyError" style="color:#dc2626; font-size:13px; margin-top:8px; display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePasswordVerifyModal()">취소</button>
                <button type="button" class="btn btn-primary" onclick="confirmPasswordVerify()">확인</button>
            </div>
        </div>
    </div>

    <!-- Employee Modal -->
    <div id="employeeModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="employeeModalTitle">사원 추가</h3>
                <button class="modal-close" onclick="closeEmployeeModal()">&times;</button>
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
                        <label class="checkbox-label"><input type="checkbox" id="empIsActive" onchange="document.getElementById('empResignDate').style.display=this.checked?'block':'none'"> 퇴직처리</label>
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
                    <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">취소</button>
                    <button type="button" class="btn btn-primary" onclick="saveEmployee()">저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vacation Type Modal -->
    <div id="typeModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="typeModalTitle">휴가 유형 추가</h3>
                <button class="modal-close" onclick="closeTypeModal()">&times;</button>
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
                    <button type="button" class="btn btn-secondary" onclick="closeTypeModal()">취소</button>
                    <button type="submit" class="btn btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js" integrity="sha384-5/vsv56401Wf+RP3yE5/aIKW4wutk4nLY3HjueTXN0rA+DmweMtrYaN6RSjdv31bk" crossorigin="anonymous"></script>
    <script src="js/api.js"></script>
    <script>
let departments = [];
let employees = [];
let resignedEmployees = [];
let resigningEmployees = [];
let vacationTypes = [];
let allRequests = [];
let positions = [];
let annualLeaveData = [];

        document.addEventListener('DOMContentLoaded', async () => {
            const res = await api.auth.check();
            if (res.csrf_token) api.setCsrfToken(res.csrf_token);
            loadDepartments();
            loadPositions();
            loadEmployeesWithLeave();
            loadVacationTypes();
            loadAllRequests();
        });
        
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('hidden');
            document.querySelectorAll('.tab').forEach(btn => {
                const onclick = btn.getAttribute('onclick');
                if (onclick && onclick.includes(`showTab('${tab}')`)) {
                    btn.classList.add('active');
                }
            });
            // Highlight settings dropdown trigger when a sub-tab is active
            const settingsTabs = ['vacationTypes', 'positions', 'departments', 'holidays', 'settings'];
            if (settingsTabs.includes(tab)) {
                const parentBtn = document.querySelector('.tab-dropdown > .tab');
                if (parentBtn) parentBtn.classList.add('active');
            }
            
            if (tab === 'holidays') {
                initHolidayYearSelect();
                loadHolidays();
            }
if (tab === 'resigned') {
    loadResignedEmployees();
}
if (tab === 'resigning') {
    loadResigningEmployees();
}
            if (tab === 'annualLeave') {
                loadAnnualLeaveTable();
            }
            if (tab === 'departments') {
                loadDepartments();
            }
            if (tab === 'certificate') {
                loadCertificates();
            }
            if (tab === 'support') {
                loadSupportRequests();
            }
            if (tab === 'settings') {
                loadSmtpSettings();
            }

            // Close settings dropdown if open
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }

        function toggleSettingsDropdown(e) {
            e.stopPropagation();
            const menu = document.querySelector('.tab-dropdown-menu');
            if (!menu) return;
            const isOpen = menu.classList.contains('show');
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            if (!isOpen) {
                const btn = e.currentTarget;
                const rect = btn.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = rect.left + 'px';
                menu.classList.add('show');
            }
        }

        // Close dropdown on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        });

        async function loadHolidays() {
            const year = document.getElementById('holidayYear').value;
            try {
                const res = await fetch('api/vacation_requests.php?action=holidays&year=' + year);
                const data = await res.json();
                renderHolidays(data.data || []);
            } catch (err) {
                console.error('Load holidays error:', err);
            }
        }

        function renderHolidays(holidays) {
            const tbody = document.getElementById('holidaysList');
            if (!holidays || holidays.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">공휴일 데이터가 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = holidays.map(h => `
                <tr>
                    <td>${h.date}</td>
                    <td>${h.name}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="showHolidayModal(${h.id}, '${escapeHtml(h.date)}', '${escapeHtml(h.name)}')">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteHoliday(${h.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function showHolidayModal(id = null, date = '', name = '') {
            document.getElementById('holidayId').value = id || '';
            document.getElementById('holidayDate').value = date;
            document.getElementById('holidayName').value = name;
            document.getElementById('holidayModalTitle').textContent = id ? '공휴일 수정' : '공휴일 추가';
            document.getElementById('holidayModal').classList.remove('hidden');
        }

        function closeHolidayModal() {
            document.getElementById('holidayModal').classList.add('hidden');
        }

        async function saveHoliday(e) {
            e.preventDefault();
            const id = document.getElementById('holidayId').value;
            const date = document.getElementById('holidayDate').value;
            const name = document.getElementById('holidayName').value;
            const year = date.split('-')[0];
            
            try {
                const res = await api.holidays.save({ id, date, name, year });
                if (res.success) {
                    alert('저장되었습니다.');
                    closeHolidayModal();
                    loadHolidays();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deleteHoliday(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            
            try {
                const res = await api.holidays.delete(id);
                if (res.success) {
                    alert('삭제되었습니다.');
                    loadHolidays();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function loadDepartments() {
            const res = await api.employees.getDepartments();
            departments = res.data;
            populateDepartmentSelects();
            renderDepartments();
        }

        function populateDepartmentSelects() {
            const options = '<option value="">선택하세요</option>' + 
                departments.map(d => `<option value="${d.id}">${d.name}${d.code ? ' (' + d.code + ')' : ''}</option>`).join('');
            document.getElementById('empDept').innerHTML = options;
            document.getElementById('managedDept').innerHTML = options;
        }

        let employeesLeaveData = {};
let resigningSeveranceUsageCache = {};
        
        async function loadEmployeesWithLeave() {
            const res = await api.employees.list({ active: 1 });
            employees = res.data;
            
            const currentYear = new Date().getFullYear();
            
            try {
                const res2 = await api.vacationRequests.employeeLeaveList(currentYear);
                employeesLeaveData = res2.data || {};
            } catch (err) {
                employeesLeaveData = {};
            }
            
            renderEmployees();
        }

async function loadResignedEmployees() {
    document.getElementById('resignedVacationHistory').style.display = 'none';
    const res = await api.employees.list({ active: 0 });
    resignedEmployees = res.data;
    renderResignedEmployees();
}

 async function loadResigningSeveranceUsage() {
     if (resigningEmployees.length === 0) return;
     
     try {
         const currentYear = new Date().getFullYear();
         // Load severance usage for each resigning employee
         for (const employee of resigningEmployees) {
             const res = await api.employees.severance_usage(employee.id, currentYear);
             if (res.success) {
                resigningSeveranceUsageCache[employee.id] = {
                    granted: res.data.severance_granted || 0,
                    used: res.data.severance_used || 0,
                    remaining: res.data.severance_remaining || 0
                };
             }
         }
     } catch (err) {
         console.error('Failed to load severance usage:', err);
         // Continue with empty cache - UI will show 0 values
     }
 }

async function loadResigningEmployees() {
     document.getElementById('resigningVacationHistory').style.display = 'none';
     // First get all employees
     const res = await api.employees.list();
     // Filter for resigning employees (is_resigning = 1)
     resigningEmployees = res.data.filter(e => e.is_resigning == 1);
     // Load severance usage for resigning employees
     await loadResigningSeveranceUsage();
     renderResigningEmployees();
 }

        function renderResignedEmployees() {
            document.getElementById('resignedList').innerHTML = resignedEmployees.map(e => `
                <tr onclick="showResignedHistory(${e.id}, '${escapeHtml(e.name)}')" style="cursor:pointer;">
                    <td>${e.emp_no}</td>
                    <td>${e.name}</td>
                    <td>${e.department_name || '-'}</td>
                    <td>${e.position_name || '-'}</td>
                    <td>${e.hire_date || '-'}</td>
                    <td>${e.resignation_date || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editEmployee(${e.id})">수정</button>
                    </td>
                </tr>
            `).join('');
         }

         function renderResigningEmployees() {
             document.getElementById('resigningList').innerHTML = resigningEmployees.map(e => {
                 // Get severance usage for this employee
                 const usage = resigningSeveranceUsageCache[e.id] || { used: 0, remaining: 0 };
                 return `
                 <tr onclick="showResigningHistory(${e.id}, '${escapeHtml(e.name)}')" style="cursor:pointer;">
                     <td>${e.emp_no}</td>
                     <td>${e.name}</td>
                     <td>${e.department_name || '-'}</td>
                     <td>${e.position_name || '-'}</td>
                     <td>${e.hire_date || '-'}</td>
                     <td>${usage.remaining.toFixed(1)}</td>
                     <td>${(parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                     <td>${(usage.remaining + (parseFloat(e.annual_leave) || 0)).toFixed(1)}</td>
                     <td>
                         <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editEmployee(${e.id})">수정</button>
                     </td>
                 </tr>
                 `;
             }).join('');
         }

         async function showResignedHistory(empId, empName) {
            document.getElementById('resignedEmployeeName').textContent = `${empName} - 휴가 신청 내역`;
            document.getElementById('resignedVacationHistory').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.list(null, null, empId, null, false);
                const list = res.data || [];
                
                document.getElementById('resignedVacationList').innerHTML = list.map(r => `
                    <tr>
                        <td>${r.vacation_type_name || '-'}</td>
                        <td>${r.start_date} ~ ${r.end_date}</td>
                        <td>${r.days}</td>
                        <td><span class="status-badge status-${r.status}">${r.status === 'applied' ? '신청' : r.status === 'approved' ? '승인' : r.status === 'cancelled' ? '취소' : r.status}</span></td>
                        <td style="text-align:left;">${r.reason || '-'}</td>
                    </tr>
                `).join('');
            } catch (err) {
                document.getElementById('resignedVacationList').innerHTML = '<tr><td colspan="5">내역을 불러올 수 없습니다.</td></tr>';
            }
        }

        async function showResigningHistory(empId, empName) {
            document.getElementById('resigningEmployeeName').textContent = `${empName} - 휴가 신청 내역`;
            document.getElementById('resigningVacationHistory').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.list(null, null, empId, null, false);
                const list = res.data || [];
                
                document.getElementById('resigningVacationList').innerHTML = list.map(r => `
                    <tr>
                        <td>${r.vacation_type_name || '-'}</td>
                        <td>${r.start_date} ~ ${r.end_date}</td>
                        <td>${r.days}</td>
                        <td><span class="status-badge status-${r.status}">${r.status === 'applied' ? '신청' : r.status === 'approved' ? '승인' : r.status === 'cancelled' ? '취소' : r.status}</span></td>
                        <td style="text-align:left;">${r.reason || '-'}</td>
                    </tr>
                `).join('');
            } catch (err) {
                document.getElementById('resigningVacationList').innerHTML = '<tr><td colspan="5">내역을 불러올 수 없습니다.</td></tr>';
            }
        }

        function renderEmployees() {
            // roleNames is defined globally in the head section
            const currentYear = new Date().getFullYear();
            
            document.getElementById('employeesList').innerHTML = employees.map(e => `
                <tr>
                    <td>${e.emp_no}</td>
                    <td>${e.hire_date || '-'}</td>
                    <td>${e.name}</td>
                    <td>${e.department_name || '-'}</td>
                    <td>${e.position_name || '-'}</td>
                    <td><span class="role-badge role-${e.role}">${roleNames[e.role]}</span></td>
                    <td>${(employeesLeaveData[e.id]?.granted || parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                    <td>${(employeesLeaveData[e.id]?.used || 0).toFixed(1)}</td>
                    <td>${(employeesLeaveData[e.id]?.remaining || parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                    <td><span class="status-badge status-${e.is_active == 1 ? 'active' : 'inactive'}">${e.is_active == 1 ? '재직' : '퇴사'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editEmployee(${e.id})">수정</button>
                        ${e.id != <?=$currentUser['id']?> ? `<button class="btn btn-sm btn-danger" onclick="deleteEmployee(${e.id})">삭제</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

function showEmployeeModal(id = null) {
    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeForm').reset();
    document.getElementById('empId').value = '';
    document.getElementById('employeeModalTitle').textContent = '사원 추가';
    document.getElementById('empHireDate').value = '';
    document.getElementById('empEmail').value = '';
    document.getElementById('empBirthDate').value = '';
    document.getElementById('empAddress').value = '';
    document.getElementById('empResidentNo').value = '';
    document.getElementById('empIsActive').checked = false;
    document.getElementById('empResignationDate').value = '';
    document.getElementById('empResignDate').style.display = 'none';
    document.getElementById('empIsResigning').checked = false;
    document.getElementById('empVisibleToExec').checked = false;
    document.getElementById('empSeveranceLeave').value = '';
    document.getElementById('empResignGroup').style.display = 'none';
    document.getElementById('empSeveranceGroup').style.display = 'none';
    
    if (!id) {
        document.getElementById('empNo').disabled = false;
    }
}

        function closeEmployeeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

async         function editEmployee(id) {
    const res = await api.employees.get(id);
    if (!res.success) return;
    const emp = res.data;

    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeModalTitle').textContent = '사원 수정';
    document.getElementById('empId').value = emp.id;
    document.getElementById('empNo').value = emp.emp_no;
    document.getElementById('empNo').disabled = true;
    document.getElementById('empName').value = emp.name;
    document.getElementById('empDept').value = emp.department_id || '';
    document.getElementById('empPosition').value = emp.position_id || '';
    document.getElementById('empRole').value = emp.role;
    document.getElementById('managedDept').value = emp.managed_department_id || '';
    document.getElementById('empPhone1').value = emp.phone1 || '';
    document.getElementById('empPhone2').value = emp.phone2 || '';
    document.getElementById('empEmail').value = emp.email || '';
    document.getElementById('empBirthDate').value = emp.birth_date || '';
    document.getElementById('empAddress').value = emp.address || '';
    const residentNoEl = document.getElementById('empResidentNo');
    residentNoEl.value = '********';
    residentNoEl.dataset.original = emp.resident_no || '';
    residentNoEl.type = 'password';
    residentNoEl.readOnly = true;
    document.getElementById('residentNoToggle').textContent = '🔒';
    document.getElementById('empHireDate').value = emp.hire_date || '';
    document.getElementById('empIsActive').checked = emp.is_active == 0;
    document.getElementById('empIsResigning').checked = emp.is_resigning == 1;
    document.getElementById('empResignationDate').value = emp.resignation_date || '';
    document.getElementById('empVisibleToExec').checked = emp.visible_to_exec == 1;
    
    // Show resigning-related fields if employee is resigning or if we're checking the resigning checkbox
    const isResigning = emp.is_resigning == 1;
    document.getElementById('empResignGroup').style.display = 'block';
    document.getElementById('empSeveranceGroup').style.display = 'block';
    if (isResigning) {
        document.getElementById('empResignDate').style.display = 'block';
        document.getElementById('empSeveranceLeave').value = emp.severance_leave || '';
    } else {
        document.getElementById('empResignDate').style.display = 'none';
        document.getElementById('empSeveranceLeave').value = '';
    }
}

        async function saveEmployee(e) {
            if (e) { e.preventDefault(); }
            
            const getVal = (id) => document.getElementById(id)?.value || '';
            
            const id = getVal('empId');
const data = {
    emp_no: getVal('empNo'),
    name: getVal('empName'),
    department_id: getVal('empDept'),
    position_id: getVal('empPosition'),
    role: getVal('empRole'),
    managed_department_id: getVal('managedDept'),
    phone1: getVal('empPhone1'),
    phone2: getVal('empPhone2'),
    email: getVal('empEmail'),
    birth_date: getVal('empBirthDate'),
    address: getVal('empAddress'),
    resident_no: document.getElementById('empResidentNo').type === 'password' ? '' : getVal('empResidentNo'),
    hire_date: getVal('empHireDate'),
    is_active: document.getElementById('empIsActive').checked ? '0' : '1',
    resignation_date: document.getElementById('empIsActive').checked ? getVal('empResignationDate') : '',
    is_resigning: document.getElementById('empIsResigning').checked ? '1' : '0',
    severance_leave: parseFloat(getVal('empSeveranceLeave')) || 0,
    visible_to_exec: document.getElementById('empVisibleToExec').checked ? '1' : '0'
};

            const pw = getVal('empPassword');
            if (pw) data.password = pw;

            try {
                if (id) {
                    data.id = id;
                    await api.employees.update(data);
                } else {
                    await api.employees.create(data);
                }
                closeEmployeeModal();
                await loadEmployeesWithLeave();
                await loadResignedEmployees();
                await loadResigningEmployees();
                loadAllRequests();
                alert('저장되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function toggleResidentNo() {
            const el = document.getElementById('empResidentNo');
            if (!el) return;
            if (el.type === 'password') {
                document.getElementById('pwVerifyInput').value = '';
                document.getElementById('pwVerifyError').style.display = 'none';
                document.getElementById('passwordVerifyModal').classList.remove('hidden');
                document.getElementById('pwVerifyInput').focus();
            } else {
                el.type = 'password';
                el.value = '********';
                el.readOnly = true;
                const toggle = document.getElementById('residentNoToggle');
                if (toggle) toggle.textContent = '🔒';
            }
        }

        function closePasswordVerifyModal() {
            document.getElementById('passwordVerifyModal').classList.add('hidden');
        }

        function togglePwVerifyVisible() {
            const input = document.getElementById('pwVerifyInput');
            const toggle = document.getElementById('pwVerifyToggle');
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
            }
        }

        async function confirmPasswordVerify() {
            const pw = document.getElementById('pwVerifyInput').value;
            if (!pw) {
                document.getElementById('pwVerifyError').textContent = '비밀번호를 입력하세요.';
                document.getElementById('pwVerifyError').style.display = 'block';
                return;
            }
            try {
                const res = await api.auth.verifyPassword(pw);
                if (res.success) {
                    closePasswordVerifyModal();
                    const el = document.getElementById('empResidentNo');
                    el.type = 'text';
                    el.value = el.dataset.original || '';
                    el.readOnly = false;
                    document.getElementById('residentNoToggle').textContent = '🔓';
                }
            } catch (err) {
                document.getElementById('pwVerifyError').textContent = '비밀번호가 올바르지 않습니다.';
                document.getElementById('pwVerifyError').style.display = 'block';
                document.getElementById('pwVerifyInput').value = '';
                document.getElementById('pwVerifyInput').focus();
            }
        }

        async function deleteEmployee(id) {
            if (!confirm('정말로 삭제하시겠습니까?')) return;
            
            try {
                await api.employees.delete(id);
                const currentTab = document.querySelector('.tab.active');
                if (currentTab && currentTab.textContent.includes('퇴사자')) {
                    loadResignedEmployees();
                } else {
                    loadEmployeesWithLeave();
                }
                alert('삭제되었습니다.');
            } catch (err) {
                alert(err.message);
            }
        }

        async function loadVacationTypes() {
            const res = await api.vacationTypes.list();
            vacationTypes = res.data;
            renderVacationTypes();
        }

        function renderVacationTypes() {
            const deductNames = { 'none': '차감없음', 'annual': '연차' };
            const len = vacationTypes.length;

            document.getElementById('typesList').innerHTML = vacationTypes.map((t, i) => `
                <tr>
                    <td>${t.sort_order}</td>
                    <td><span class="color-dot" style="background:${t.color}"></span>${t.name}</td>
                    <td>${t.deduction}</td>
                    <td>${t.max_days >= 999 ? '무제한' : t.max_days}</td>
                    <td>${deductNames[t.deduct_from]}</td>
                    <td>${t.count_all_days == 1 ? '포함' : '제외'}</td>
                    <td><input type="color" value="${t.color}" disabled></td>
                    <td style="white-space:nowrap">
                        <button class="btn btn-sm btn-secondary" onclick="moveType(${t.id}, -1)" ${i === 0 ? 'disabled' : ''}>▲</button>
                        <button class="btn btn-sm btn-secondary" onclick="moveType(${t.id}, 1)" ${i === len - 1 ? 'disabled' : ''}>▼</button>
                        <button class="btn btn-sm btn-secondary" onclick="editType(${t.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteType(${t.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }
        
        async function moveType(id, direction) {
            const idx = vacationTypes.findIndex(t => t.id === id);
            if (idx === -1) return;
            const target = idx + direction;
            if (target < 0 || target >= vacationTypes.length) return;

            const ids = vacationTypes.map(t => t.id);
            [ids[idx], ids[target]] = [ids[target], ids[idx]];

            try {
                await api.vacationTypes.reorder(ids);
                await loadVacationTypes();
            } catch (err) {
                alert(err.message);
            }
        }

        async function deleteType(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await api.vacationTypes.delete(id);
                alert('삭제되었습니다.');
                loadVacationTypes();
            } catch (err) {
                alert(err.message);
            }
        }

        function showTypeModal() {
            document.getElementById('typeModal').classList.remove('hidden');
            document.getElementById('typeForm').reset();
            document.getElementById('typeId').value = '';
            document.getElementById('typeModalTitle').textContent = '휴가 유형 추가';
        }

        function closeTypeModal() {
            document.getElementById('typeModal').classList.add('hidden');
        }

        function editType(id) {
            const type = vacationTypes.find(t => t.id === id);
            if (!type) return;

            document.getElementById('typeModal').classList.remove('hidden');
            document.getElementById('typeModalTitle').textContent = '휴가 유형 수정';
            document.getElementById('typeId').value = type.id;
            document.getElementById('typeName').value = type.name;
            document.getElementById('typeDeduction').value = type.deduction;
            document.getElementById('typeMax').value = type.max_days;
            document.getElementById('typeDeductFrom').value = type.deduct_from;
            document.getElementById('typeColor').value = type.color;
            document.getElementById('typeCountAllDays').checked = type.count_all_days == 1;
        }

        async function saveType(e) {
            e.preventDefault();
            
            const id = document.getElementById('typeId').value;
            const data = {
                name: document.getElementById('typeName').value,
                deduction: document.getElementById('typeDeduction').value,
                max_days: document.getElementById('typeMax').value,
                deduct_from: document.getElementById('typeDeductFrom').value,
                color: document.getElementById('typeColor').value,
                count_all_days: document.getElementById('typeCountAllDays').checked ? 1 : 0
            };

            try {
                if (id) {
                    data.id = id;
                    await api.vacationTypes.update(data);
                } else {
                    await api.vacationTypes.create(data);
                }
                closeTypeModal();
                loadVacationTypes();
                alert('저장되었습니다.');
            } catch (err) {
                alert(err.message);
            }
        }

        async function loadAllRequests() {
            const res = await api.vacationRequests.list();
            allRequests = res.data;
            renderAllRequests();
        }

        async function loadPositions() {
            try {
                const res = await api.positions.list();
                positions = res.data || [];
                populatePositionSelect();
                renderPositions();
            } catch (err) {
                console.error('Load positions error:', err);
            }
        }

        function populatePositionSelect() {
            const select = document.getElementById('empPosition');
            select.innerHTML = '<option value="">선택하세요</option>' + 
                positions.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        }

        function renderPositions() {
            document.getElementById('positionsList').innerHTML = positions.map(p => `
                <tr>
                    <td>${p.name}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editPosition(${p.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deletePosition(${p.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function showPositionModal(id = null, name = '') {
            document.getElementById('positionId').value = id || '';
            document.getElementById('positionName').value = name;
            document.getElementById('positionModalTitle').textContent = id ? '직급 수정' : '직급 추가';
            document.getElementById('positionModal').classList.remove('hidden');
        }

        function closePositionModal() {
            document.getElementById('positionModal').classList.add('hidden');
        }

        async function savePosition(e) {
            e.preventDefault();
            const id = document.getElementById('positionId').value;
            const name = document.getElementById('positionName').value;
            
            try {
                const res = id
                    ? await api.positions.update({ id, name })
                    : await api.positions.create({ name });
                
                if (res.success) {
                    alert('저장되었습니다.');
                    closePositionModal();
                    loadPositions();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deletePosition(id) {
            if (!confirm('정말 삭제하시겠습니까?\n해당 직급을 사용 중인 사원의 직급은 초기화됩니다.')) return;
            
            try {
                const res = await api.positions.delete(id);
                if (res.success) {
                    alert('삭제되었습니다.');
                    loadPositions();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function editPosition(id) {
            const pos = positions.find(p => p.id == id);
            if (pos) showPositionModal(pos.id, pos.name);
        }

        function showDepartmentModal(id = null, code = '', name = '', color = '') {
            document.getElementById('deptId').value = id || '';
            document.getElementById('deptCode').value = code;
            document.getElementById('deptName').value = name;
            document.getElementById('deptColor').value = color || '#667eea';
            document.getElementById('departmentModalTitle').textContent = id ? '부서 수정' : '부서 추가';
            document.getElementById('departmentModal').classList.remove('hidden');
        }

        function closeDepartmentModal() {
            document.getElementById('departmentModal').classList.add('hidden');
        }

        function renderDepartments() {
            document.getElementById('departmentsList').innerHTML = departments.map(d => `
                <tr>
                    <td>${d.code}</td>
                    <td>${d.name}</td>
                    <td><span class="color-dot" style="background:${d.color}"></span>${d.color}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editDepartment(${d.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteDepartment(${d.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function editDepartment(id) {
            const dept = departments.find(d => d.id == id);
            if (dept) showDepartmentModal(dept.id, dept.code, dept.name, dept.color);
        }

        async function saveDepartment(e) {
            e.preventDefault();
            const id = document.getElementById('deptId').value;
            const data = {
                code: document.getElementById('deptCode').value,
                name: document.getElementById('deptName').value,
                color: document.getElementById('deptColor').value
            };

            try {
                if (id) {
                    data.id = id;
                    await api.employees.updateDepartment(data);
                } else {
                    await api.employees.createDepartment(data);
                }
                closeDepartmentModal();
                await loadDepartments();
                await loadEmployeesWithLeave();
                alert('저장되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deleteDepartment(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await api.employees.deleteDepartment(id);
                await loadDepartments();
                await loadEmployeesWithLeave();
                alert('삭제되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function renderAllRequests() {
            const statusNames = { applied: '신청', approved: '승인', cancelled: '취소' };

            document.getElementById('allRequestsList').innerHTML = allRequests.map(r => `
                <tr>
                    <td>${r.employee_name}</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.start_date} ~ ${r.end_date}</td>
                    <td>${r.vacation_type_name}</td>
                    <td>${r.days}일</td>
                    <td>${(r.reason || '').substring(0, 20)}${r.reason && r.reason.length > 20 ? '...' : ''}</td>
                    <td><span class="status-badge status-${r.status}">${statusNames[r.status]}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="window.open('print.php?id=${r.id}', '_blank')">출력</button>
                    </td>
                </tr>
            `).join('');
        }

        function initHolidayYearSelect() {
            const select = document.getElementById('holidayYear');
            if (!select) return;
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            let html = '';
            for (let y = nextYear; y >= currentYear - 5; y--) {
                html += `<option value="${y}">${y}년</option>`;
            }
            select.innerHTML = html;
            select.value = currentYear;
        }

        function initAnnualYearSelect() {
            const select = document.getElementById('annualYearSelect');
            if (!select) return;
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            let html = '';
            for (let y = nextYear; y >= currentYear - 5; y--) {
                html += `<option value="${y}">${y}년</option>`;
            }
            select.innerHTML = html;
            select.value = currentYear;
        }

        async function loadAnnualLeaveTable() {
            const year = document.getElementById('annualYearSelect')?.value;
            const tbody = document.getElementById('annualLeaveTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="9" class="loading"><div class="spinner"></div></td></tr>';

            try {
                const res = await fetch('api/vacation_requests.php?action=annual_list&year=' + year);
                const data = await res.json();
                annualLeaveData = data.data || [];
                renderAnnualLeaveTable(annualLeaveData);
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:red;">데이터 로드 실패</td></tr>';
            }
        }

        function renderAnnualLeaveTable(data) {
            const tbody = document.getElementById('annualLeaveTableBody');
            if (!tbody) return;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">데이터가 없습니다.</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(emp => {
                const remaining = emp.remaining;
                const remainingClass = remaining < 0 ? 'color:#dc2626;font-weight:700;' :
                                      remaining <= 3 ? 'color:#f59e0b;font-weight:600;' :
                                      'color:#16a34a;';
                return `<tr>
                    <td>${emp.name}</td>
                    <td style="color:#64748b;font-size:13px;">${emp.emp_no}</td>
                    <td>${emp.hire_date || '-'}</td>
                    <td>${emp.department_name || '-'}</td>
                    <td>${emp.position_name || '-'}</td>
                    <td style="text-align:center;">${emp.granted.toFixed(1)}</td>
                    <td style="text-align:center;">${emp.used.toFixed(1)}</td>
                    <td style="text-align:center;${remainingClass}">${remaining.toFixed(1)}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editAnnualLeave(${emp.id})">수정</button>
                    </td>
                </tr>`;
            }).join('');
        }

        function editAnnualLeave(empId) {
            const year = document.getElementById('annualYearSelect').value;
            const emp = annualLeaveData.find(e => e.id === empId);
            if (!emp) return;

            document.getElementById('aleEmployeeId').value = emp.id;
            document.getElementById('aleYear').value = year;
            document.getElementById('aleEmployeeInfo').innerHTML =
                `<strong>${emp.name}</strong> (${emp.emp_no}) - ${emp.department_name || '-'} · ${emp.position_name || '-'}<br><small style="color:#64748b;">${year}년 부여연차를 수정합니다.</small>`;
            document.getElementById('aleGranted').value = emp.granted;
            document.getElementById('annualLeaveEditModal').classList.remove('hidden');
        }

        function closeAnnualLeaveEditModal() {
            document.getElementById('annualLeaveEditModal').classList.add('hidden');
        }

        async function saveAnnualLeaveEdit() {
            const empId = document.getElementById('aleEmployeeId').value;
            const year = document.getElementById('aleYear').value;
            const granted = document.getElementById('aleGranted').value;

            if (!empId || !year || !granted) {
                alert('값을 입력해주세요.');
                return;
            }

            try {
                const res = await api.vacationRequests.annualUpdate({ employee_id: empId, year, annual_leave: granted });
                if (res.success) {
                    alert('저장되었습니다.');
                    closeAnnualLeaveEditModal();
                    loadAnnualLeaveTable();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // Init annual year select on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            initHolidayYearSelect();
            initAnnualYearSelect();
            const toggleBtn = document.getElementById('residentNoToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', toggleResidentNo);
            }
        });

        function logout() {
            if (confirm('로그아웃하시겠습니까?')) {
                location.href = 'api/auth.php?action=logout';
            }
        }

        // ── Certificate Requests ──
        async function loadCertificates() {
            try {
                const res = await api.certificate.list();
                renderCertificates(res.data || []);
            } catch (err) {
                document.getElementById('certificateList').innerHTML = '<tr><td colspan="11" style="text-align:center;color:#dc2626;">불러오기 실패</td></tr>';
            }
        }

        function renderCertificates(list) {
            const tbody = document.getElementById('certificateList');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;">요청 내역이 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(r => `
                <tr>
                    <td>${r.name} (${r.emp_no})</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.certificate_type_label}</td>
                    <td>${r.show_resident_label}</td>
                    <td>${r.show_discipline_label}</td>
                    <td title="${r.job_desc_content ? r.job_desc_content.replace(/"/g, '&quot;') : ''}">${r.job_desc_label}</td>
                    <td>${r.job_desc_lang_label}</td>
                    <td>${r.created_at}</td>
                    <td><span class="status-badge status-${r.status === 'requested' ? 'applied' : 'approved'}">${r.status_label}</span></td>
                    <td>${r.notes || '-'}</td>
                    <td>
                        ${r.status === 'requested'
                            ? `<button class="btn btn-sm btn-primary" onclick="openCertCompleteModal(${r.id})">완료</button>`
                            : ''}
                    </td>
                </tr>
            `).join('');
        }

        function openCertCompleteModal(id) {
            document.getElementById('certCompleteId').value = id;
            document.getElementById('certCompleteNotes').value = '';
            document.getElementById('certCompleteModal').classList.remove('hidden');
        }

        function closeCertCompleteModal() {
            document.getElementById('certCompleteModal').classList.add('hidden');
        }

        async function saveCertComplete() {
            const id = document.getElementById('certCompleteId').value;
            const notes = document.getElementById('certCompleteNotes').value;
            try {
                await api.certificate.complete({ id, notes });
                closeCertCompleteModal();
                loadCertificates();
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // ── Support Requests ──
        async function loadSupportRequests() {
            try {
                const res = await api.support.list();
                renderSupportList(res.data || []);
            } catch (err) {
                document.getElementById('supportList').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;">불러오기 실패</td></tr>';
            }
        }

        function renderSupportList(list) {
            const tbody = document.getElementById('supportList');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">요청 내역이 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(r => `
                <tr>
                    <td>${r.name} (${r.emp_no})</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.request_type_label}</td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(r.content || '')}">${r.content || '-'}</td>
                    <td>${r.created_at}</td>
                    <td><span class="status-badge status-${r.status === 'requested' ? 'applied' : 'approved'}">${r.status_label}</span></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(r.notes || '')}">${r.notes || '-'}</td>
                    <td>
                        ${r.status === 'requested'
                            ? `<button class="btn btn-sm btn-primary" onclick="openSupportCompleteModal(${r.id})">완료</button>`
                            : ''}
                    </td>
                </tr>
            `).join('');
        }

        function openSupportCompleteModal(id) {
            document.getElementById('supportCompleteId').value = id;
            document.getElementById('supportCompleteStatus').value = 'completed';
            document.getElementById('supportCompleteNotes').value = '';
            document.getElementById('supportCompleteModal').classList.remove('hidden');
        }

        function closeSupportCompleteModal() {
            document.getElementById('supportCompleteModal').classList.add('hidden');
        }

        async function saveSupportComplete() {
            const id = document.getElementById('supportCompleteId').value;
            const status = document.getElementById('supportCompleteStatus').value;
            const notes = document.getElementById('supportCompleteNotes').value;
            try {
                await api.support.complete({ id, status, notes });
                closeSupportCompleteModal();
                loadSupportRequests();
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // ── SMTP Settings ──
        async function loadSmtpSettings() {
            try {
                const res = await api.settings.get();
                const data = res.data || {};
                document.getElementById('smtpHost').value = data.smtp_host || '';
                document.getElementById('smtpPort').value = data.smtp_port || '587';
                document.getElementById('smtpUser').value = data.smtp_user || '';
                document.getElementById('smtpPass').value = data.smtp_pass || '';
                document.getElementById('smtpEncryption').value = data.smtp_encryption || 'tls';
                document.getElementById('smtpFromEmail').value = data.smtp_from_email || '';
                document.getElementById('smtpAuth').checked = data.smtp_auth === '1';
            } catch (err) {
                document.getElementById('smtpResult').textContent = '설정 불러오기 실패';
                document.getElementById('smtpResult').style.color = '#dc2626';
            }
        }

        async function saveSmtpSettings() {
            const data = {
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_user: document.getElementById('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                smtp_encryption: document.getElementById('smtpEncryption').value,
                smtp_from_email: document.getElementById('smtpFromEmail').value,
                smtp_auth: document.getElementById('smtpAuth').checked ? '1' : '0'
            };
            try {
                await api.settings.save(data);
                document.getElementById('smtpResult').textContent = '저장되었습니다.';
                document.getElementById('smtpResult').style.color = '#16a34a';
            } catch (err) {
                document.getElementById('smtpResult').textContent = '저장 실패: ' + err.message;
                document.getElementById('smtpResult').style.color = '#dc2626';
            }
        }

        async function testSmtpSettings() {
            const resultEl = document.getElementById('smtpResult');
            resultEl.textContent = '테스트 발송 중...';
            resultEl.style.color = '#666';
            try {
                const res = await api.request('certificate.php?action=test_email');
                if (res.success) {
                    resultEl.innerHTML = '✅ 테스트 이메일이 발송되었습니다.';
                    resultEl.style.color = '#16a34a';
                } else {
                    let msg = res.error || '알 수 없는 오류';
                    if (res.debug) {
                        msg += '<br><br><strong>SMTP 디버그 로그:</strong><br><pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:12px;max-height:300px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin-top:8px;">' + escapeHtml(res.debug) + '</pre>';
                    }
                    resultEl.innerHTML = '❌ ' + msg;
                    resultEl.style.color = '#dc2626';
                }
            } catch (err) {
                resultEl.innerHTML = '❌ 발송 실패: ' + escapeHtml(err.message);
                resultEl.style.color = '#dc2626';
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
