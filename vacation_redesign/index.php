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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" integrity="sha512-NHredShh2K4bAM40hiHIGPEzhWGD9mcUwbAt3Q/thHmiqJIe0eTVdaTb4Uaj5dbrITJrbRcnQitc5fZCvr5Z8Q==" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@5.11.3/main.css" integrity="sha512-rgfj1WV7y50DvxkdcIZPOLcoyREL8P3CAbV6JGxJsyDmPbfzMRPfRSfaPWun1WNL2fkPrKnT7H7qXF6UM/YKDg==" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@5.11.3/main.css" integrity="sha512-Gt5XUNqI9B+QsiFDBaDZoq72tDNK2kRoSO/4yNBx2ConympFwhkyY6BFE0A8nDTrVQtKUnYUQFbNfzzWjlhiRg==" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fullcalendar/list@5.11.3/main.css" integrity="sha512-msmq2tDcCDOACHca2zLo+dYqr5LpR0HuJaZ0k3c2SZXKx1UV2/zisALRYnTgKwTmQ4tWXiWWOELirHhE2dO69w==" crossorigin="anonymous">
    <link href="css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
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
                    <h1>휴가신청 시스템 <span id="currentYear" class="year-label"></span></h1>
                </div>
                <div class="header-user">
                    <div class="user-info">
                        <div class="user-name" id="userName">-</div>
                        <div class="user-role" id="userRole">-</div>
                    </div>
                    <button id="btnShowPassword" class="btn btn-sm btn-secondary">비밀번호 변경</button>
                    <a href="admin.php" id="adminLink" class="btn btn-sm btn-secondary hidden">관리자</a>
                    <button id="annualLeaveBtn" class="btn btn-sm btn-secondary hidden">연차 현황</button>
                    <button id="btnAddFavorites" class="btn btn-sm btn-secondary">즐겨찾기 추가</button>
                    <button id="btnLogout" class="btn btn-sm btn-secondary">로그아웃</button>
                </div>
            </div>
        </header>

        <main class="container">
<div class="main-layout">
    <!-- Top Block: Dashboard Cards -->
    <div class="sidebar">
        <div id="dashboardSection" class="dashboard">
            <div class="card">
                <div class="card-icon annual">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </div>
                <div class="card-info"><div class="card-title">잔여 연차</div><div class="card-value"><span id="annualLeave">0</span><span>일</span></div></div>
            </div>
            <div class="card hidden" id="severanceCard">
                <div class="card-icon severance">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                    </svg>
                </div>
                <div class="card-info"><div class="card-title">보전연차</div><div class="card-value"><span id="severanceLeave">0</span><span>일</span></div></div>
            </div>
            <div class="card">
                <div class="card-icon used">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941" />
                    </svg>
                </div>
                <div class="card-info"><div class="card-title">사용 연차</div><div class="card-value"><span id="usedLeave">0</span><span>일</span></div></div>
            </div>
            <div class="card">
                <div class="card-icon total">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                </div>
                <div class="card-info"><div class="card-title">총 신청</div><div class="card-value"><span id="totalRequests">0</span><span>건</span></div></div>
            </div>
        </div>
    </div>

    <!-- Bottom Layout: Calendar and List -->
    <div class="bottom-layout">
        <!-- Block 2: Calendar -->
        <div class="main-content">
            <!-- Calendar Section -->
            <div id="calendarSection" class="section">
                <div class="section-header">
                    <div class="section-header-top">
                        <h2 class="section-title">📅 휴가 캘린더</h2>
                    </div>
                    <div class="section-header-bottom">
                        <button class="btn btn-secondary hidden" id="btnPrintResign">📄 퇴직원</button>
                        <div class="tab-dropdown">
                            <button id="btnSupportMenu" class="btn btn-secondary">📋 행정지원요청 ▾</button>
                            <div class="tab-dropdown-menu" id="supportMenu">
                                <div class="tab-dropdown-group-label">증명서</div>
                                <button class="tab-dropdown-item" id="btnCertCareer">📜 경력증명서</button>
                                <button class="tab-dropdown-item" id="btnCertEmployment">📜 재직증명서</button>
                                <div class="tab-dropdown-divider"></div>
                                <div class="tab-dropdown-group-label">행정지원</div>
                                <button class="tab-dropdown-item" id="btnSupportIdCard">🪪 사원증 발급</button>
                                <button class="tab-dropdown-item" id="btnSupportBizCard">💳 명함 발급</button>
                                <button class="tab-dropdown-item" id="btnSupportOffice">📎 사무용품 신청</button>
                            </div>
                        </div>
                        <button id="btnShowVacation" class="btn btn-primary">+ 휴가 신청</button>
                    </div>
                </div>
                <div class="section-body">
                    <div id="calendar"></div>
                    <div class="legend-wrap">
                        <div class="legend-box">
                            <div class="legend-swatch swatch-family"></div>
                            <span>초록색: Family Day (매월 3째주 금요일)</span>
                        </div>
                        <div class="legend-box">
                            <div class="legend-swatch swatch-holiday"></div>
                            <span>빨간색: 휴일/공휴일</span>
                        </div>
                    </div>
                </div>
            </div>
    </div>

        <!-- Block 3: Vacation List -->
        <div class="vacation-list">
            <!-- Vacation List Section -->
            <div id="vacationListSection" class="section">
<div class="section-header">
    <div class="section-header-top">
        <div class="flex-bar">
            <h2 class="section-title">📋 휴가 신청 내역</h2>
            <label id="myOnlyLabel" class="label-inline d-none">
                <input type="checkbox" id="showMyOnly">
                내 휴가만 보기
            </label>
        </div>
    </div>
    <div class="section-header-bottom">
        <select id="filterDept" class="filter-select-sm d-none">
            <option value="">부서</option>
        </select>
        <select id="filterEmp" class="filter-select-sm d-none">
            <option value="">사원</option>
        </select>
        <select id="filterYear" class="filter-select-sm">
        </select>
        <select id="filterMonth" class="filter-select-sm">
        </select>
        <label class="label-inline">
            <input type="checkbox" id="showCancelled">
            취소 포함
        </label>
        <select id="filterStatus" class="filter-select-sm d-none">
            <option value="">상태</option>
            <option value="applied">신청</option>
            <option value="approved">승인</option>
            <option value="cancelled">취소</option>
        </select>
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
        </div>
    </div>
</div>

            <!-- Annual Leave Status Section -->
            <div id="annualLeaveSection" class="section hidden">
                <div class="section-header">
                    <h2 class="section-title">📊 연차 현황</h2>
                    <div class="flex-bar">
                        <select id="annualLeaveYear" class="filter-select-sm">
                        </select>
                        <button id="btnBackToVacation" class="btn btn-sm btn-secondary">휴가 관리로 돌아가기</button>
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
        <div class="modal modal-w-420">
            <div class="modal-header">
                <h3 class="modal-title" id="certModalTitle">증명서 발급 요청</h3>
                <button class="modal-close" id="btnCloseCert">&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-12 text-sm-14">증명서 옵션을 선택해주세요.</p>
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
                        <input type="checkbox" id="certJobDesc">
                        업무기재
                    </label>
                    <div id="certJobDescContentGroup" class="hidden cert-indent">
                        <textarea id="certJobDescContent" rows="4" class="mb-8 w-full-input text-sm-14" placeholder="업무 내용을 입력해주세요"></textarea>
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
                <div class="flex-bar-8 mt-16">
                    <button id="btnSubmitCert" class="btn btn-primary btn-flex-1">제출</button>
                    <button id="btnCancelCert" class="btn btn-secondary btn-flex-1">취소</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Request Modal -->
    <div id="supportModal" class="modal-overlay hidden">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 class="modal-title" id="supportModalTitle">행정지원 요청</h3>
                <button class="modal-close" id="btnCloseSupport">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="supportType">
                <p id="supportNotice" class="info-box-notice"></p>
                <div class="form-group">
                    <label>요청사항</label>
                    <textarea id="supportContent" rows="5" class="w-full-input text-sm-14" placeholder="요청 내용을 입력해주세요&#10;예: 사원증 재발급 / 명함 100매 / A4 용지 2박스"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnCancelSupport" class="btn btn-secondary">취소</button>
                <button id="btnSubmitSupport" class="btn btn-primary">제출</button>
            </div>
        </div>
    </div>

    <!-- Vacation Request Modal -->
    <div id="vacationModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">휴가 신청</h3>
                <button class="modal-close" id="btnCloseVacation">&times;</button>
            </div>
            <form id="vacationForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>휴가 유형</label>
                        <select id="vacationType" required>
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group d-none" id="condolenceTypeGroup">
                        <label>경조사사유</label>
                        <select id="condolenceType">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group d-none" id="condolenceInfo">
                        <div id="condolenceInfoDefault" class="info-box info-box-warning">
                            기본 <span id="condolenceTotalDays" class="fw-600">20</span>일 중 남은 <span id="condolenceRemainingDays" class="fw-600">20</span>일, 초과 시 연차 차감
                        </div>
                        <div id="condolenceInfoSpouseBirth" class="info-box info-box-warning d-none">
                            <span id="spouseBirthRound" class="fw-600">1/4차</span> - 남은 <span id="spouseBirthRemaining" class="fw-600">20</span>일, 초과 시 연차 차감
                        </div>
                    </div>
                    <div class="form-group">
                        <label>시작일</label>
                        <div class="flex-row gap-6 items-center">
                            <input type="date" id="startDate" required>
                            <div id="startHalfContainer">
                                <select id="startHalf" class="select-half">
                                    <option value="full">종일</option>
                                    <option value="afternoon">오후</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>종료일</label>
                        <div class="flex-row gap-6 items-center">
                            <input type="date" id="endDate" required>
                            <div id="endHalfContainer">
                                <select id="endHalf" class="select-half">
                                    <option value="full">종일</option>
                                    <option value="morning">오전</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>일수</label>
                        <div id="days" class="info-box info-box-gray fw-600">0</div>
                    </div>
                    <div class="form-group">
                        <label>잔여 일수</label>
                        <div id="remainingDays" class="info-box info-box-blue fw-600">0</div>
                    </div>
                    <div class="form-group d-none" id="severanceRemainingGroup">
                        <label>잔여 보전연차</label>
                        <div id="severanceRemainingDays" class="info-box info-box-blue fw-600">0</div>
                    </div>
                    <div class="form-group">
                        <label>비상연락처</label>
                        <div class="flex-bar">
                            <input type="tel" id="phone1" placeholder="010-0000-0000" class="flex-1">
                            <input type="tel" id="phone2" placeholder="02-0000-0000" class="flex-1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <textarea id="reason" rows="3" placeholder="휴가 사유를 입력해주세요" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelVacation">취소</button>
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
                <button class="modal-close" id="btnCloseEdit">&times;</button>
            </div>
            <form id="vacationEditForm">
                <input type="hidden" id="editRequestId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>휴가 유형</label>
                        <select id="editVacationType" required>
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group d-none" id="editCondolenceTypeGroup">
                        <label>경조사사유</label>
                        <select id="editCondolenceType">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="form-group d-none" id="editCondolenceInfo">
                        <div id="editCondolenceInfoDefault" class="info-box info-box-warning">
                            기본 <span id="editCondolenceTotalDays" class="fw-600">20</span>일 중 남은 <span id="editCondolenceRemainingDays" class="fw-600">20</span>일, 초과 시 연차 차감
                        </div>
                        <div id="editCondolenceInfoSpouseBirth" class="info-box info-box-warning d-none">
                            <span id="editSpouseBirthRound" class="fw-600">1/4차</span> - 남은 <span id="editSpouseBirthRemaining" class="fw-600">20</span>일, 초과 시 연차 차감
                        </div>
                    </div>
                    <div class="form-group">
                        <label>시작일</label>
                        <div class="flex-row gap-6 items-center">
                            <input type="date" id="editStartDate" required>
                            <div id="editStartHalfContainer">
                                <select id="editStartHalf" class="select-half">
                                    <option value="full">종일</option>
                                    <option value="afternoon">오후</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>종료일</label>
                        <div class="flex-row gap-6 items-center">
                            <input type="date" id="editEndDate" required>
                            <div id="editEndHalfContainer">
                                <select id="editEndHalf" class="select-half">
                                    <option value="full">종일</option>
                                    <option value="morning">오전</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>일수</label>
                        <div id="editDays" class="info-box info-box-gray fw-600">0</div>
                    </div>
                    <div class="form-group">
                        <label>사유</label>
                        <textarea id="editReason" rows="3" placeholder="휴가 사유를 입력해주세요" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCancelEdit">취소</button>
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
                <button class="modal-close" id="btnCloseDetail">&times;</button>
            </div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Print Date Modal -->
    <div id="printDateModal" class="modal-overlay hidden">
        <div class="modal" style="max-width:400px">
            <div class="modal-header">
                <h3 class="modal-title">휴가 신청서 출력</h3>
                <button class="modal-close" id="btnClosePrintModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>신청일</label>
                    <div id="printCreatedDate" style="padding:8px;background:#f5f5f5;border-radius:4px"></div>
                </div>
                <div class="form-group">
                    <label>출력 날짜</label>
                    <input type="date" id="printSignDate" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelPrintModal">취소</button>
                <button type="button" class="btn btn-primary" id="btnConfirmPrint">출력</button>
            </div>
        </div>
    </div>
    <input type="hidden" id="printRequestId">

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">비밀번호 변경</h3>
                <button class="modal-close" id="btnClosePassword">&times;</button>
            </div>
            <form id="passwordForm">
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
                    <button type="button" class="btn btn-secondary" id="btnCancelPassword">취소</button>
                    <button type="submit" class="btn btn-primary">변경</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js" integrity="sha512-pQxj7UAETUWMJS1KY6W3g+o/hW3m0P+1vxit2SRrjS2GGTMBToNixA7OcQy/t66E17zMdc0XHMCMLpZXLEqDcQ==" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/ko.js" integrity="sha512-GiLCg9iA+FxGj0IFRlx3UCBeZyf54vTBuJaqHJXnEsLHB1c28udB6DdDH4Tu6ApHoB+dDXyhEvw8Qx2ri76gtw==" crossorigin="anonymous"></script>
    <script src="js/api.js"></script>
    <script src="js/calendar.js"></script>
    <script src="js/main.js"></script>
    <script nonce="<?= $cspNonce ?>">
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
