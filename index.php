<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config/security.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADF 휴가신청 시스템</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@5.11.3/main.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@5.11.3/main.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@fullcalendar/list@5.11.3/main.css" rel="stylesheet">
	<link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Login Page -->
    <div id="loginPage" class="login-wrapper hidden">
        <div class="login-card">
            <div class="login-logo">
                <img src="Logo.jpg">
				<h1>휴가신청 시스템</h1>
                <p>계정과 비밀번호로 로그인해주세요</p>
            </div>
            <div id="loginAlert" class="alert alert-error hidden"></div>
            <form id="loginForm">
                <div class="form-group">
                    <label>계정</label>
                    <input type="text" id="loginEmpNo" placeholder="계정 입력" required>
                </div>
                <div class="form-group">
                    <label>비밀번호</label>
                    <input type="password" id="loginPassword" placeholder="비밀번호 입력" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">로그인</button>
            </form>
        </div>
    </div>

    <!-- App Page -->
    <div id="appPage" class="hidden">
        <header class="header">
            <div class="header-content">
                <div class="header-logo">
                    <h1>휴가신청 시스템 <span id="currentYear" style="font-size:14px; font-weight:400;"></span></h1>
                </div>
                <div class="header-user">
                    <div class="user-info">
                        <div class="user-name" id="userName">-</div>
                        <div class="user-role" id="userRole">-</div>
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="showPasswordModal()">비밀번호 변경</button>
                    <a href="admin.php" id="adminLink" class="btn btn-sm btn-secondary hidden">관리자</a>
                    <button id="annualLeaveBtn" class="btn btn-sm btn-secondary hidden" onclick="toggleAnnualLeaveView()">연차 현황</button>
                    <button class="btn btn-sm btn-secondary" onclick="addToFavorites()">즐겨찾기 추가</button>
                    <button class="btn btn-sm btn-secondary" onclick="logout()">로그아웃</button>
                </div>
            </div>
        </header>

        <main class="container">
<!-- Dashboard Cards -->
<div id="dashboardSection" class="dashboard">
    <div class="card">
        <div class="card-icon annual">📅</div>
        <div class="card-title">잔여 연차</div>
        <div class="card-value"><span id="annualLeave">0</span><span>일</span></div>
    </div>
    <div class="card hidden" id="severanceCard">
        <div class="card-icon severance">🔖</div>
        <div class="card-title">보전연차</div>
        <div class="card-value"><span id="severanceLeave">0</span><span>일</span></div>
    </div>
    <div class="card">
        <div class="card-icon used">✅</div>
        <div class="card-title">사용 연차</div>
        <div class="card-value"><span id="usedLeave">0</span><span>일</span></div>
    </div>
    <div class="card">
        <div class="card-icon total">📋</div>
        <div class="card-title">총 신청</div>
        <div class="card-value"><span id="totalRequests">0</span><span>건</span></div>
    </div>
</div>

            <!-- Calendar Section -->
            <div id="calendarSection" class="section">
                <div class="section-header">
                    <h2 class="section-title">📅 휴가 캘린더</h2>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-secondary" id="btnPrintResign" style="display:none;" onclick="printResignation()">📄 퇴직원</button>
                        <div class="tab-dropdown" style="flex-shrink:0;">
                            <button class="btn btn-secondary" onclick="toggleSupportMenu(event)">📋 행정지원요청 ▾</button>
                            <div class="tab-dropdown-menu" id="supportMenu">
                                <div class="tab-dropdown-group-label">증명서</div>
                                <button class="tab-dropdown-item" onclick="requestCertificate('career')">📜 경력증명서</button>
                                <button class="tab-dropdown-item" onclick="requestCertificate('employment')">📜 재직증명서</button>
                                <div class="tab-dropdown-divider"></div>
                                <div class="tab-dropdown-group-label">행정지원</div>
                                <button class="tab-dropdown-item" onclick="openSupportModal('id_card','사원증')">🪪 사원증 발급</button>
                                <button class="tab-dropdown-item" onclick="openSupportModal('business_card','명함')">💳 명함 발급</button>
                                <button class="tab-dropdown-item" onclick="openSupportModal('office_supply','사무용품')">📎 사무용품 신청</button>
                            </div>
                        </div>
                        <button class="btn btn-primary" onclick="showVacationModal()">+ 휴가 신청</button>
                    </div>
                </div>
                <div class="section-body">
                    <div id="calendar"></div>
                    <div style="margin-top: 12px; font-size: 13px; color: #64748b; display: flex; gap: 20px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div style="width: 12px; height: 12px; background-color: #dcfce7; border: 1px solid #166534; border-radius: 2px;"></div>
                            <span>초록색: Family Day (매월 3째주 금요일)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div style="width: 12px; height: 12px; background-color: #fee2e2; border: 1px solid #dc2626; border-radius: 2px;"></div>
                            <span>빨간색: 휴일/공휴일</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vacation List Section -->
            <div id="vacationListSection" class="section">
<div class="section-header">
    <div style="display:flex;align-items:center;gap:12px;">
        <h2 class="section-title">📋 휴가 신청 내역</h2>
        <label id="myOnlyLabel" style="display:none;align-items:center;gap:6px;cursor:pointer;font-size:13px;white-space:nowrap;user-select:none;">
            <input type="checkbox" id="showMyOnly" onchange="toggleMyVacationOnly()">
            내 휴가만 보기
        </label>
    </div>
    <div style="display:flex; gap:10px;">
        <select id="filterDept" onchange="filterByDept()" style="padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; display:none;">
            <option value="">전체 본부</option>
        </select>
        <select id="filterEmp" onchange="filterByEmp()" style="padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0; display:none;">
            <option value="">전체 사원</option>
        </select>
        <select id="filterYear" onchange="filterByYear()" style="padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0;">
        </select>
        <select id="filterMonth" onchange="filterByYear()" style="padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0;">
        </select>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;white-space:nowrap;user-select:none;">
            <input type="checkbox" id="showCancelled" onchange="filterByYear()">
            취소 내역 포함
        </label>
    </div>
</div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr id="tableHeader">
                                    <th>기간</th>
                                    <th>휴가 유형</th>
                                    <th>일수</th>
                                    <th>상태</th>
                                    <th>작업</th>
                                </tr>
                            </thead>
                            <tbody id="vacationList">
                                <tr><td colspan="5" class="loading"><div class="spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Annual Leave Status Section -->
            <div id="annualLeaveSection" class="section hidden">
                <div class="section-header">
                    <h2 class="section-title">📊 연차 현황</h2>
                    <div style="display:flex; gap:10px;">
                        <select id="annualLeaveYear" onchange="loadEmployeeAnnualList()" style="padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                        </select>
                        <button class="btn btn-sm btn-secondary" onclick="toggleAnnualLeaveView()">휴가 관리로 돌아가기</button>
                    </div>
                </div>
                <div class="section-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>사원명</th>
                                    <th>아이디</th>
                                    <th>본부</th>
                                    <th>직급</th>
                                    <th>부여연차</th>
                                    <th>사용연차</th>
                                    <th>잔여연차</th>
                                </tr>
                            </thead>
                            <tbody id="annualLeaveList">
                                <tr><td colspan="7" class="loading"><div class="spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Certificate Request Modal -->
    <div id="certificateModal" class="modal-overlay hidden">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <h3 class="modal-title" id="certModalTitle">증명서 발급 요청</h3>
                <button class="modal-close" onclick="closeCertificateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:12px; font-size:14px; color:#333;">증명서 옵션을 선택해주세요.</p>
                <input type="hidden" id="certType">
                <label class="checkbox-row">
                    <input type="checkbox" id="certShowResident">
                    주민등록번호 노출
                </label>
                <div id="certDisciplineRow">
                    <label class="checkbox-row">
                        <input type="checkbox" id="certShowDiscipline">
                        징계여부
                    </label>
                </div>
                <div id="certJobDescRow">
                    <label class="checkbox-row">
                        <input type="checkbox" id="certJobDesc" onchange="toggleJobDescContent()">
                        업무기재
                    </label>
                    <div id="certJobDescContentGroup" class="hidden" style="padding-left:26px; margin-bottom:8px;">
                        <textarea id="certJobDescContent" rows="4" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;" placeholder="업무 내용을 입력해주세요"></textarea>
                    </div>
                </div>
                <label class="checkbox-row">
                    <input type="checkbox" id="certJobDescKorean">
                    국문
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" id="certJobDescEnglish">
                    영문
                </label>
                <div style="display:flex; gap:8px; margin-top:16px;">
                    <button class="btn btn-primary" onclick="submitCertificate()" style="flex:1;">제출</button>
                    <button class="btn btn-secondary" onclick="closeCertificateModal()" style="flex:1;">취소</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Request Modal -->
    <div id="supportModal" class="modal-overlay hidden">
        <div class="modal" style="max-width:450px;">
            <div class="modal-header">
                <h3 class="modal-title" id="supportModalTitle">행정지원 요청</h3>
                <button class="modal-close" onclick="closeSupportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="supportType">
                <p id="supportNotice" style="margin-bottom:12px; padding:10px; background:#fff3cd; border:1px solid #ffeeba; border-radius:6px; font-size:13px; color:#856404;"></p>
                <div class="form-group">
                    <label>요청사항</label>
                    <textarea id="supportContent" rows="5" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;" placeholder="요청 내용을 입력해주세요&#10;예: 사원증 재발급 / 명함 100매 / A4 용지 2박스"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSupportModal()">취소</button>
                <button class="btn btn-primary" onclick="submitSupportRequest()">제출</button>
            </div>
        </div>
    </div>

    <!-- Vacation Request Modal -->
    <div id="vacationModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">휴가 신청</h3>
                <button class="modal-close" onclick="closeVacationModal()">&times;</button>
            </div>
            <form id="vacationForm" onsubmit="submitVacation(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>휴가 유형</label>
                        <select id="vacationType" required onchange="onVacationTypeChange()">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group" id="condolenceTypeGroup" style="display:none;">
                        <label>경조사사유</label>
                        <select id="condolenceType" onchange="onCondolenceTypeChange()">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group" id="condolenceInfo" style="display:none;">
                        <div id="condolenceInfoDefault" style="padding: 12px 16px; background: #fef3c7; border-radius: 12px; font-size: 14px;">
                            기본 <span id="condolenceTotalDays" style="font-weight:600;">20</span>일 중 남은 <span id="condolenceRemainingDays" style="font-weight:600;">20</span>일, 초과 시 연차 차감
                        </div>
                        <div id="condolenceInfoSpouseBirth" style="padding: 12px 16px; background: #fef3c7; border-radius: 12px; font-size: 14px; display:none;">
                            <span id="spouseBirthRound" style="font-weight:600;">1/4차</span> - 남은 <span id="spouseBirthRemaining" style="font-weight:600;">20</span>일, 초과 시 연차 차감
                        </div>
                    </div>
                    <div class="form-group">
                        <label>시작일</label>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input type="date" id="startDate" required>
                            <div id="startHalfContainer">
                                <select id="startHalf" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="full">종일</option>
                                    <option value="afternoon">오후</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>종료일</label>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input type="date" id="endDate" required>
                            <div id="endHalfContainer">
                                <select id="endHalf" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="full">종일</option>
                                    <option value="morning">오전</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>일수</label>
                        <div id="days" style="padding: 12px 16px; background: #f1f5f9; border-radius: 12px; font-weight: 600;">0</div>
                    </div>
                    <div class="form-group">
                        <label>잔여 일수</label>
                        <div id="remainingDays" style="padding: 12px 16px; background: #e0e7ff; border-radius: 12px; font-weight: 600;">0</div>
                    </div>
                    <div class="form-group" id="severanceRemainingGroup" style="display:none;">
                        <label>잔여 보전연차</label>
                        <div id="severanceRemainingDays" style="padding: 12px 16px; background: #e0e7ff; border-radius: 12px; font-weight: 600;">0</div>
                    </div>
                    <div class="form-group">
                        <label>비상연락처</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="tel" id="phone1" placeholder="010-0000-0000" style="flex: 1;">
                            <input type="tel" id="phone2" placeholder="02-0000-0000" style="flex: 1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <textarea id="reason" rows="3" placeholder="휴가 사유를 입력해주세요" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeVacationModal()">취소</button>
                    <button type="submit" class="btn btn-primary">신청하기</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vacation Edit Modal -->
    <div id="vacationEditModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">휴가 수정</h3>
                <button class="modal-close" onclick="closeVacationEditModal()">&times;</button>
            </div>
            <form id="vacationEditForm" onsubmit="submitVacationEdit(event)">
                <input type="hidden" id="editRequestId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>휴가 유형</label>
                        <select id="editVacationType" required>
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group" id="editCondolenceTypeGroup" style="display:none;">
                        <label>경조사사유</label>
                        <select id="editCondolenceType">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group" id="editCondolenceInfo" style="display:none;">
                        <div id="editCondolenceInfoDefault" style="padding: 12px 16px; background: #fef3c7; border-radius: 12px; font-size: 14px;">
                            기본 <span id="editCondolenceTotalDays" style="font-weight:600;">20</span>일 중 남은 <span id="editCondolenceRemainingDays" style="font-weight:600;">20</span>일, 초과 시 연차 차감
                        </div>
                        <div id="editCondolenceInfoSpouseBirth" style="padding: 12px 16px; background: #fef3c7; border-radius: 12px; font-size: 14px; display:none;">
                            <span id="editSpouseBirthRound" style="font-weight:600;">1/4차</span> - 남은 <span id="editSpouseBirthRemaining" style="font-weight:600;">20</span>일, 초과 시 연차 차감
                        </div>
                    </div>
                    <div class="form-group">
                        <label>시작일</label>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input type="date" id="editStartDate" required>
                            <div id="editStartHalfContainer">
                                <select id="editStartHalf" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="full">종일</option>
                                    <option value="afternoon">오후</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>종료일</label>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <input type="date" id="editEndDate" required>
                            <div id="editEndHalfContainer">
                                <select id="editEndHalf" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                                    <option value="full">종일</option>
                                    <option value="morning">오전</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>일수</label>
                        <div id="editDays" style="padding: 12px 16px; background: #f1f5f9; border-radius: 12px; font-weight: 600;">0</div>
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <textarea id="editReason" rows="3" placeholder="휴가 사유를 입력해주세요" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeVacationEditModal()">취소</button>
                    <button type="submit" class="btn btn-primary">저장하기</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vacation Detail Modal -->
    <div id="vacationDetailModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">휴가 상세</h3>
                <button class="modal-close" onclick="document.getElementById('vacationDetailModal').classList.add('hidden')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">비밀번호 변경</h3>
                <button class="modal-close" onclick="closePasswordModal()">&times;</button>
            </div>
            <form id="passwordForm" onsubmit="return changePassword(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>현재 비밀번호</label>
                        <input type="password" id="currentPassword" placeholder="현재 비밀번호 입력">
                    </div>
                    <div class="form-group">
                        <label>새 비밀번호</label>
                        <input type="password" id="newPassword" placeholder="새 비밀번호 입력">
                    </div>
                    <div class="form-group">
                        <label>새 비밀번호 확인</label>
                        <input type="password" id="confirmPassword" placeholder="새 비밀번호 확인">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">취소</button>
                    <button type="submit" class="btn btn-primary">변경</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/ko.js"></script>
    <script src="js/api.js"></script>
    <script src="js/calendar.js"></script>
    <script src="js/main.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const empNo = document.getElementById('loginEmpNo').value;
            const password = document.getElementById('loginPassword').value;
            const alert = document.getElementById('loginAlert');
            
            try {
                const res = await api.auth.login(empNo, password);
                if (res.success) {
                    currentUser = res.user;
                    if (res.csrf_token) api.setCsrfToken(res.csrf_token);
                    showApp();
                }
            } catch (err) {
                alert.textContent = err.message;
                alert.classList.remove('hidden');
            }
        });

        // loadVacationTypes() and populateVacationTypes() are defined in js/main.js
    </script>
</body>
</html>
