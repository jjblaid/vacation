# 변경 이력

## v1.14 (2026-06-26) — 부대표 권한 확대
- 부대표(vice_president): 휴가 목록/캘린더/연차현황 전직원 조회 가능으로 변경
- `api/vacation_requests.php`: `getList()`, `getCalendarEvents()`, `getEmployeeLeave()`, `getEmployeeAnnualList()` 수정
- `js/main.js`: 연차현황 버튼/부서필터 VP 표시
- `DEPLOY.md`, `SPEC.md`: 권한 테이블 업데이트

## v1.13 (2026-06-24) — ZAP 보안 취약점 수정
- `config/security.php`: 신규 — 중앙 보안 헤더 (CSP, X-Frame-Options, X-Content-Type-Options, expose_php, header_remove X-Powered-By)
- 모든 PHP 진입점: `config/security.php` include 추가 (13개 파일)
- `index.php` / `admin.php`: CDN 링크/스크립트에 SRI `integrity` 속성 추가 (8개 태그)
- `DEPLOY.md`: PHP/Apache 정보 노출 방지 설정 섹션 추가 (expose_php, ServerTokens)
- `download_pdf.php` / `download_docx.php`: 삭제 (미사용)
- `SPEC.md`: 출력 표/디렉토리 구조/데이터 흐름도 정리
- `DEPLOY.md`: 출력 형식 섹션 간소화

## 보안 (CSRF)
- `api/vacation_requests.php`: 7개 write action CSRF 보호 + `$_PARSED_BODY` 캐싱
- `api/employees.php`: 6개 write action CSRF 보호 + `$_PARSED_BODY` 캐싱
- `api/positions.php`: 3개 write action CSRF 보호 + `$_PARSED_BODY` 캐싱
- `api/vacation_types.php`: 4개 write action CSRF 보호 + `$_PARSED_BODY` 캐싱
- `api/auth.php`: `changePassword()` CSRF 검증 (이중-read 회피)
- `js/api.js`: CSRF 토큰 자동주입 (URLSearchParams/JSON 모두 지원)
- `admin.php`: `api.auth.check()`로 CSRF 토큰 초기화
- 모든 PHP 파일: `session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax'])`

## 버그 수정
- `api/vacation_requests.php`: 취소/승인 `$_PARSED_BODY['id']` 읽기, `getEmployeeLeave()` SELECT에 `e.name` 추가, `display_errors=0` 전환
- `admin.php`: `showTab()` event.target → querySelectorAll, raw fetch 5건 → `api.*` 메서드, 삭제 후 loadEmployeesWithLeave()
- `js/main.js`: 같은 날 반차 계산 수정, 연도 필터 하한 currentYear-5
- `js/calendar.js`: 다중일정 이벤트 필터, 달력 연도 이동 시 공휴일 갱신(datesSet), 종료일 month-end 보정
- `js/calendar.js`: 패밀리데이가 공휴일과 겹칠 경우 전날로 이동 (3째주 금요일 → 공휴일이면 전날 목요일)
- `index.php`: 로그인/비밀번호 변경 `<form>` 래핑, autocomplete 속성 추가

## 기능 추가
- VP (vice_president): `getList()`/`getCalendarEvents()` 본인 휴가 조회 가능 (`OR vr.employee_id = ?`)
- 경조사 edit modal: `editCondolenceTotalDays` 변수, editCondolenceType change handler로 잔여일 계산
- 휴가유형 reorder (▲▼ 버튼 + `reorder` API + `api.js`)
- 육아휴직 `count_all_days`: DB 마이그레이션, admin modal 체크박스, `main.js` 주말/공휴일 필터 스킵
- 부서관리 탭 (CRUD API + admin UI + api.js)
- 연도별 연차 관리 탭 (annual_by_year 기준)
- 공휴일 수정 버튼 추가
- `getEmployeeLeaveList()`: N+1 제거 단일 쿼리

## 데이터 무결성
- `vacation_requests.php`: 트랜잭션(beginTransaction/commit/rollBack) create/update/cancel
- `updateRequest()`: `condolence_days` 필드 저장
- `employees.php`: 신규 사원 생성 시 `annual_by_year` 자동 생성
- `annual_by_year`를 부여 연차 source of truth로 사용

## 코드 정리
- `$_PARSED_BODY` 전역 캐싱 패턴 도입 (vacation_requests.php, employees.php, vacation_types.php, positions.php)
- `getMyRemaining()` → `getMyAnnualLeave()` 이름 변경
- `index.php` 중복 `loadVacationTypes()` 제거
- `auth.php` logout(): 세션 쿠키 명시적 삭제
- `api.js`: `holidays.save/delete`, `positions.*`, `vacationRequests.annualUpdate` 메서드 추가
- `error_reporting(0)` 통일 (vacation_requests.php, vacation_types.php, positions.php, condolence_types.php, employees.php)
- `init_db.sql`: 최신 스키마 + 시드 데이터로 업데이트

## 2026-06-08 — 보전연차 + 권한 체계 개선 + 버그 수정

### 기능 추가
- 보전연차(severance leave): `employees.severance_leave` 컬럼 (REMAINING 기준) — 퇴사예정자에게 관리자가 수동 부여
- 차감 순서: 일반연차(annual_deduct_days) → 보전연차(severance_deduct_days) 순서로 차감
- `admin.php`: 퇴사예정자 탭 신규 — 보전연차 부여/사용/잔여 표시, 수정 버튼
- `index.php` 대시보드: 퇴사예정자용 보전연차 카드 (0.0일도 항상 표시)
- `migrations/migration_008_visible_to_exec.sql`: `employees.visible_to_exec` 컬럼 추가

### 권한 체계 변경
- 하드코딩된 `e.name = '김은솔'` 제거 (3군데) → `e.visible_to_exec = 1`로 대체
  - `vacation_requests.php`: CEO `getList()`, `getCalendarEvents()`, `getEmployeeLeave()` 조건 변경
  - `vacation_requests.php`: VP `getList()`, `getCalendarEvents()`, `getEmployeeLeave()` 조건 변경
- `admin.php` 사원 수정 모달: 임원(CEO/부대표) 휴가조회 허용 체크박스 추가

### 데이터 일관성
- `employees.severance_leave` REMAINING 기준 일관화
  - `createRequest()`, `updateRequest()`, `getMyAnnualInfo()` — DB 값을 REMAINING으로 직접 해석
  - `getSeveranceUsage()` — 기존대로 REMAINING 기준 (변경 없음)
  - `admin.php` `loadResigningSeveranceUsage()` — 캐시에 `granted` 필드 포함

### 버그 수정
- `vacation_requests.php` `updateRequest()` — 차감 분할 기준을 GRANTED(`annual_by_year`) → REMAINING(`granted - otherUsedDays`)으로 변경
  - 수정 시 연차가 -7일까지 차감되던 버그 해결
- `vacation_requests.php` `updateRequest()` — total remaining validation 추가 (차감 전 잔여 확인)
- `vacation_requests.php` `updateRequest()` — severance validation: 순증분(`new - old`)만 검증 (감소 편집 허용)
- `employees.php` `getList()` — `active` 파라미터 필터 추가 (`WHERE e.is_active = ?`)
- `employees.php` `getList()` — `LEFT JOIN positions p` + `p.name as position_name` SELECT (직급 미표시 버그)
- `employees.php` `updateEmployee()` — `$fields`에 `resignation_date`, `hire_date` 누락 수정
- `employees.php` `updateEmployee()` — `$fields`에 `position_id` 누락 수정 + `createEmployee()` INSERT에 position_id 추가
- `employees.php` `updateEmployee()` — `is_active = 0`(퇴직처리) 시 `is_resigning` 자동으로 `0` SET (제거가 아닌 강제 설정)
- `employees.php` `updateEmployee()` — DATE 필드(`resignation_date`, `hire_date`) 빈 문자열 → NULL 변환
- `js/main.js` `loadEmpFilter()`/`filterEmpByDept()` — `api.employees.list()` 호출 시 `{ active: 1 }` 파라미터 추가 (메인페이지 퇴사자 표시 방지)
- `vacation_requests.php` `getList()` — `$empId` 미지정 시 `e.is_active = 1` 조건 추가 (휴가신청내역 퇴사자 표시 방지)
- `vacation_requests.php` `getCalendarEvents()` — 조건에 `e.is_active = 1` 추가 (캘린더 퇴사자 표시 방지)
- `admin.php` `saveEmployee()` — 저장 후 모든 탭(사원관리/퇴사자/퇴사예정자) 데이터 갱신

### 기능 개선
- `employees.php` `getList()` — 정렬 기준 변경: `active=1` → `ORDER BY e.hire_date`, `active=0` → `ORDER BY e.resignation_date`
- `vacation_requests.php` `getAnnualList()` — `e.hire_date` SELECT + `ORDER BY e.hire_date` (연도별연차 입사일순 정렬)
- `admin.php` 연도별연차 탭 — 입사일 컬럼 추가 (`<th>입사일</th>` + `<td>${emp.hire_date}</td>` + colspan 8→9)
- `admin.php` 사원관리 탭 — 연차(`annual_leave`) 입력 필드 완전 제거 (연차 관리는 연도별연차 탭으로)
- `js/calendar.js` `showVacationDetail()` — 휴가상세에 종료일 표시 (시작일 ~ 종료일 (일수)), UTC 시차 보정을 위해 `toISOString()` 대신 로컬 Date getter 사용

### DB 마이그레이션
- `migrations/migration_007_severance_leave.sql`: `employees.severance_leave` 컬럼 추가
- `migrations/migration_008_visible_to_exec.sql`: `employees.visible_to_exec` 컬럼 추가 + 김은솔 UPDATE

## 2026-06-10 — 관리자 UI 개선 + 퇴직원 출력 + 휴가신청 잔여보전연차 표시

### 기능 추가
- `print_resignation.php` 전면 재작성: `resignation_requests` 테이블 의존성 제거 → `employees` 직접 조회, 없는 필드(생년월일/주민번호/주소) 제거, 사번/연락처 컬럼 추가, 사유 입력 textarea + `printWithReason()` 인쇄 로직
- `index.php` 섹션헤더: `#btnPrintResign` 버튼 추가 — 퇴사예정자만 표시 (display:none → `data.is_resigning` true 시 inline-block)
- `main.js` `printResignation()`: `window.open('print_resignation.php', ...)` 새 창 오픈

### 기능 개선
- `admin.php` 퇴사자 탭: 입사일 컬럼 추가 (직급 오른쪽, 퇴직일 앞)
- `admin.php` 퇴사예정자 탭: 보전연차/사용 보전연차/잔여 보전연차 → 보전연차(잔여)/잔여연차/합계 컬럼 변경 (모두 DB 저장값 사용, 합계만 프론트 계산)
- `index.php` 휴가신청 모달: `#severanceRemainingGroup` 추가 (display:none) — 퇴사예정자용 잔여 보전연차 표시 영역
- `index.php` 버튼 정렬: 휴가 신청·퇴직원 버튼 `<div style="display:flex; gap:8px">`로 그룹화 → 우측 정렬 (순서: 퇴직원 → 휴가 신청)
- `main.js` `loadMyInfo()`: `currentUser.is_resigning`/`severance_remaining` 저장
- `main.js` `showVacationModal()`: 퇴사예정자면 잔여연차 아래에 잔여 보전연차 표시
- `print_resignation.php` 결재란(approval-table) 주석 처리, `.main-table`에 `margin-top: 15mm` 추가, 퇴사사유 `<th>` vertical-align: top → middle

## 2026-06-11 — 증명서 발급 시스템 + SMTP 설정 + 사원정보 확장

### DB 스키마
- `employees`: `email`/`birth_date`/`address`/`resident_no_encrypted` 컬럼 추가
- `settings` 테이블 신규 (key-value, SMTP+encryption_key 저장)
- `certificate_requests` 테이블 신규 (employee_id, certificate_type, show_resident, status, notes, processed_at, processed_by)

### 기능 추가
- `config/encryption.php`: AES-256-CBC `encryptResidentNo()`/`decryptResidentNo()`/`maskResidentNo()` 함수
- `config/database.php`: `getSetting()`/`setSetting()` 정적 캐시 적용 헬퍼
- `api/settings.php`: SMTP 설정 get/save API (6개 key: smtp_host/port/user/pass/encryption/from_email)
- `api/certificate.php`: `request`(DB저장+PHPMailer 알림메일), `list`(관리자 전체조회+주민번호 마스킹), `complete`(상태+비고 저장), `test_email`(SMTP 테스트)
- `api/employees.php`: `getEmployee()` resident_no 복호화, `updateEmployee()`/`createEmployee()` email/birth_date/address/resident_no_encrypted 저장
- `js/api.js`: `certificate.*`(request/list/complete), `settings.*`(get/save) 메서드
- `admin.php` 증명서 요청 탭: 전체 목록 조회 + 완료처리 모달
- `admin.php` SMTP 설정 탭: 호스트/포트/계정/암호/암호화방식/발신이메일 입력 + 저장/테스트 발송 버튼
- `admin.php` 사원수정 모달: email/birth_date/address/resident_no 입력 필드 추가
- `index.php`: 경력증명서·재직증명서 버튼 추가 (휴가신청·퇴직원 버튼과 같은 행)
- `index.php`: 증명서 발급 확인 모달 (주민번호 노출/비노출/취소 3-option)
- `js/main.js`: `requestCertificate()`/`closeCertificateModal()`/`submitCertificate()` — 3-option dialog + `api.certificate.request()` 호출
- `composer.json`: `phpmailer/phpmailer "^6.9"` 추가, 미사용 phpword/dompdf 제거

### init_db.sql 업데이트
- `employees` 테이블: `email`/`birth_date`/`address`/`resident_no_encrypted` 컬럼 반영
- `settings` 테이블, `certificate_requests` 테이블 추가

## 2026-06-11 — SMTP 디버깅 + 주민번호 보호 + 탭 UI 개선

### 버그 수정
- `api/employees.php`: `updateEmployee()` `$fields` 배열에 `resident_no_encrypted` 누락 수정 (주민번호가 DB에 저장되지 않던 버그)
- `api/employees.php`: 221번 줄 여분의 `}` 제거 (모든 employees API 파싱 에러로 탭 빈화면)
- `admin.php` `editEmployee()`: 로컬 캐시 배열 대신 `api.employees.get(id)` 호출 → 복호화된 주민번호 정상 표시

### 기능 개선
- SMTP 디버깅: `sendCertificateEmail()`에 `&$debugOutput` 파라미터, SMTP 설정값 로그 출력, 테스트 발송 시 SMTP 통신 내용 `<pre>` 박스로 표시
- `smtp_auth` 설정: SMTP 인증 사용 체크박스 추가 (인증 불필요 서버 대응)
- 주민번호 보호: 사원수정 모달 `********` 가림 + 🔒/🔓 토글; 비밀번호 확인 모달(👁️/🙈) 인증 후 표시; 저장 시 가려진 상태면 주민번호 미전송
- `api/auth.php`: `verify_password` 액션 추가
- 탭 UI: 11개 탭 → 7개 (휴가유형/직급관리/부서관리/공휴일/SMTP설정 → ⚙️ 환경설정 ▾ 드롭다운 통합); `position: fixed` 드롭다운으로 스크롤 문제 해결
- `css/styles.css`: `.tabs-scroll` 래퍼 도입 (`overflow-x: auto; overflow-y: hidden`)

### DB 마이그레이션
- `migrations/migration_009_certificate.sql`: `smtp_auth` 기본값 `'0'` INSERT 추가

### 기능 개선
- `print_resignation.php`: 사번 → 생년월일 컬럼 변경
- `SPEC.md`: 증명서 발급 시스템 / SMTP 설정 / 주민번호 처리 / 서버 설정 섹션 추가
- `DEPLOY.md`: SELinux `httpd_can_sendmail on` 설정 추가, SMTP 트러블슈팅 추가, `php-openssl` 확장 안내 추가
- 증명서 발급 모달: 3-버튼(노출/비노출/취소) → 5개 체크박스(주민번호노출/징계여부/업무기재/국문/영문) + 제출/취소 버튼으로 변경
  - `index.php`: 체크박스형 모달 HTML로 교체
  - `js/main.js`: `submitCertificate()` 체크박스 값 수집, `requestCertificate()` 체크박스 초기화
  - `api/certificate.php`: `sendCertificateEmail()` 새 옵션 파라미터 + 메일 본문에 옵션 표시
  - `api/certificate.php` `request` action: INSERT에 4개 새 컬럼 추가
  - `api/certificate.php` `list` action: show_resident_label/show_discipline_label/job_desc_label/job_desc_lang_label 필드 추가
  - `admin.php`: 증명서 목록 테이블에 징계/업무기재/언어 컬럼 추가 (colspan 8→11)
  - `css/styles.css`: `.checkbox-row` 스타일 추가
  - `init_db.sql`: `certificate_requests` 테이블 컬럼 4개 추가
  - `migrations/migration_011_certificate_options.sql`: 신규

### 기능 추가
- 행정지원 요청 시스템: 사원증 발급 / 명함 발급 / 사무용품 신청
  - `migrations/migration_010_support_requests.sql`: `support_requests` 테이블 생성
  - `api/support.php`: request(DB저장+PHPMailer)+list+complete API
  - `js/api.js`: `support.*`(request/list/complete) 메서드
  - `index.php`: 증명서+행정지원 통합 드롭다운, 요청사항 입력 모달
  - `js/main.js`: `openSupportModal()`/`closeSupportModal()`/`submitSupportRequest()`
  - `admin.php`: 📋 지원 요청 탭 (목록 + 완료/반려 처리 모달)
  - `css/styles.css`: `.tab-dropdown-group-label`, `.tab-dropdown-divider` 스타일
  - `init_db.sql`: `support_requests` 테이블 추가
  - `index.php` 행정지원요청 드롭다운: 증명서(경력/재직) → 행정지원(사원증/명함/사무용품)

## v1.11 (2026-06-15) — 증명서 업무기재 상세내용
- `migrations/migration_012_certificate_job_desc_content.sql`: `certificate_requests` 테이블에 `job_desc_content TEXT` 컬럼 추가
- `api/certificate.php`: `sendCertificateEmail()` 함수에 `$jobDescContent` 파라미터 추가, 이메일 본문에 업무기재 내용 포함, INSERT 쿼리에 `job_desc_content` 포함
- `index.php`: 업무기재 체크박스에 `onchange="toggleJobDescContent()"`, 체크 시 표시되는 textarea 추가 (`#certJobDescContent`)
- `js/main.js`: `toggleJobDescContent()` 함수 추가 (업무기재 체크 시 textarea 표시/숨김), `requestCertificate()` textarea 초기화, `submitCertificate()`에 `job_desc_content` 전송
- `admin.php`: 증명서 목록 업무기재 컬럼 title 툴팁에 `job_desc_content` 출력
- `init_db.sql`: `certificate_requests` 테이블에 `job_desc_content` 컬럼 추가 반영

### 버그 수정
- `api/employees.php` `updateEmployee()`: `birth_date` 빈 문자열 MySQL strict mode DATE 오류 수정 ($field === 'birth_date' NULL 변환 조건 추가)
- `api/employees.php` `updateEmployee()`: PDOException try-catch 추가 (500 대신 JSON 오류 메시지 반환)
