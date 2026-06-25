# 휴가신청 시스템 개발정의서

## 1. 프로젝트 개요

- **프로젝트명**: 휴가신청 시스템
- **목적**: 기업 내 직원 연차/휴가 신청 및 관리
- **기술 스택**: PHP 8.1, MariaDB 10.3, JavaScript (ES6+), FullCalendar 5.11.3
- **서버**: RockyLinux 8.10, Apache 2.4
- **최초 작성일**: 2026-04-24
- **최종 수정일**: 2026-06-15

---

## 2. 사용자 역할 및 권한

### 2.1 역할별 조회 범위

| 역할 | 코드 | 휴가 목록 | 연차 현황 | 휴가 승인 | 사원 관리 | 서브코드 보기 |
|------|------|----------|----------|----------|----------|-------------|
| 시스템관리자 | system_admin | 전직원 | 전직원 | ✓ | ✓ | ✓ |
| 검토자 | reviewer | 전직원 | 전직원 | ✓ | ✗ | ✗ |
| 관리자 | dept_manager | 본인 부서 | 본인 부서 | ✗ | ✗ | ✗ |
| 대표이사 | ceo | 팀장급 이상 + 부대표 + visible_to_exec=1 | ✗ | ✗ | ✗ | ✗ |
| 부대표 | vice_president | 투자본부(INV001, INV002) + visible_to_exec=1 | ✗ | ✗ | ✗ | ✗ |
| 사용자 | user | 본인만 | ✗ | ✗ | ✗ | ✗ |

### 2.2 역할별 휴가 신청 권한

| 역할 | 본인 신청 | 타인 대리 신청 | 수정 | 취소 |
|------|----------|-------------|------|------|
| 시스템관리자 | ✓ | ✓ | ✓ | ✓ |
| 검토자 | ✓ | ✓ | ✗ | ✓ |
| 관리자 | ✓ | 본인 부서원 | 본인 + 부서원 | ✗ |
| 대표이사 | ✓ | ✗ | 본인만 | ✗ |
| 부대표 | ✓ | ✗ | 본인만 | ✗ |
| 사용자 | ✓ | ✗ | 본인만 | ✗ |

### 2.3 기본 계정 정보

| 역할 | 계정(emp_no) | 이름 | 비밀번호 |
|------|-------------|------|----------|
| 시스템관리자 | adfadmin | 관리자 | password123 |
| 검토자 | chkim | 김창현 | password123 |
| 검토자 | mkang | 강우영 | password123 |
| 검토자 | ickim | 김인철 | password123 |
| 관리자(운용본부) | ytkim | 김영탁 | password123 |
| 관리자(준법감시) | dhlee | 이동훈 | password123 |
| 관리자(투자본부1) | wscho | 조욱상 | password123 |
| 관리자(투자본부2) | khuh | 허근 | password123 |

---

## 3. 부서 목록

| 코드 | 부서명 | 색상 | 비고 |
|------|--------|------|------|
| CEO | 임원(대표, 부대표) | `#1e293b` | |
| MGT001 | 경영관리본부 | `#7D00BE` | |
| OPS001 | 운용본부 | `#FFA90A` | |
| INV001 | 투자본부 | `#4A9DFF` | |
| INV002 | 투자본부 | `#223EFF` | 서브코드 (시스템관리자만 조회) |
| COM001 | 준법감시 | `#F70BFF` | |

> 색상은 `departments.color` 컬럼에 저장되며, DB 직접 수정으로 변경 가능합니다.
> 캘린더 이벤트 배경색과 목록의 부서명 컬러 닷에 사용됩니다.

---

## 4. 주요 기능

### 4.1 인증

- 로그인/로그아웃
- 세션 기반 인증 (`$_SESSION['user']`)
- 비밀번호 변경 (bcrypt 해시)
- 자동 연차 부여 (12월: 다음 해 연차 15일)

### 4.2 대시보드

- 잔여 연차 (현재 연도 기준, `getMyAnnualInfo` API)
- 사용 연차
- 총 신청 건수 (취소 제외)
- 연도 필터 변경 시 카드 연동

### 4.3 휴가 신청

| 항목 | 설명 |
|------|------|
| 휴가 유형 | 연차, 반차(오전), 반차(오후), 경조사, 공가, 특별휴가 |
| 경조사 사유 | 본인 결혼, 배우자 출산(회차관리), 자녀 결혼, 부모상 등 |
| 시작일/종료일 | datepicker |
| 반차 옵션 | 종일(full), 오전(morning), 오후(afternoon) |
| 일수 계산 | 공휴일/주말 제외, 자동 계산 (`calculateWorkingDays`) |
| 연차 차감 | `annual_deduct_days` 컬럼에 차감 일수 저장 |
| 경조사 초과분 | 제한일수 초과 시 연차 자동 차감 |
| 배우자 출산 | 4회차 Round 관리, Round 내 잔여일 초과 시 연차 차감 |
| 잔여 연차 | 실시간 표시 |
| 비상연락처 | phone1, phone2 |
| 사유 | 텍스트 입력 |

### 4.4 휴가 유형

| 유형 | 연차 차감 | 기본 일수 | 최대 일수 |
|------|----------|-----------|----------|
| 연차 | 1.0 | 1.0 | 999 |
| 반차(오전) | 0.5 | 0.5 | 0.5 |
| 반차(오후) | 0.5 | 0.5 | 0.5 |
| 경조사 | 0 (초과시 차감) | - | 999 |
| 공가 | 0 | - | 999 |
| 특별휴가 | 0 | - | 999 |

### 4.5 휴가 목록

- 연도 필터 (2025 ~ 다음 년도)
- 월 필터 (전체 / 1~12월)
- 본부 필터 (검토자/시스템관리자만 표시)
- 사원 필터 (본부 선택 시 해당 부서만)
- 연도 변경 시 캘린더 연동
- 페이지네이션 (10개씩)
- **취소 내역 포함** 체크박스 (기본: 숨김, 체크 시 취소된 신청 표시)

### 4.6 부서 색상

- 캘린더: 이벤트 배경색이 담당 직원의 **부서 색상**으로 표시
- 목록: 부서명 옆 **컬러 닷**(`color-dot`) 표시
- 색상 출처: `departments.color` 컬럼
- 변경: DB 직접 UPDATE (`departments SET color = ? WHERE code = ?`)

### 4.7 캘린더

- 월별/주간/목록 뷰 (FullCalendar)
- 토요일: 파란색 (#3b82f6)
- 일요일: 빨간색 (#ef4444)
- 공휴일: 빨간색 배경 + 휴일명 표시
- Family Day: 초록색 배경 (매월 3째주 금요일)
- 연도 필터 연동 (날짜 이동 시 필터 연도 자동 변경)
- 이벤트 클릭: 상세 정보 모달
- 날짜 클릭: 해당일 휴가 목록

### 4.8 연차 관리

- 부여 출처: `annual_by_year` 테이블 (employee_id, year, annual_leave)
- 사용 계산: `SUM(vacation_requests.annual_deduct_days)` WHERE status IN ('applied', 'approved')
- 잔여 = 부여 - 사용 (실시간 계산)
- 12월 접속 시 다음 해 연차 자동 부여 (`autoCreateNextYearLeave`)
- 부여량: 15일
- 이미 부여된 연도는 다시 부여 안 함 (UNIQUE KEY)

### 4.9 출력

| 형식 | 파일 | 설명 |
|------|------|------|
| 웹 인쇄 | print.php | 팝업 창, 인쇄/닫기 |

### 4.10 연차 현황 조회

- 접근 권한: 시스템관리자, 검토자(전직원), 관리자(본인 부서)
- 진입: 헤더 **"연차 현황"** 버튼 (토글 방식)
- 표시 정보: 사원명, 아이디, 본부, 직급, 부여연차, 사용연차, 잔여연차
- 잔여연차 색상: 음수=빨강, 3일이하=주황, 정상=초록
- 연도 선택: 별도 드롭다운 (`#annualLeaveYear`)
- 복귀: "휴가 관리로 돌아가기" 버튼

### 4.11 관리자 페이지 (admin.php)

- 사원 관리 (CRUD, 연차 수정, 권한 변경, 입사일/퇴사일)
  - `is_active` 체크박스로 퇴직처리/복직 토글
  - `is_resigning` 체크박스로 퇴사예정자 지정
  - `visible_to_exec` 체크박스로 CEO/부대표 휴가조회 허용
  - 퇴사예정자 전용: 보전연차(severance_leave) 입력 필드
- 퇴사자 탭: 퇴사자 목록 + 행 클릭 시 휴가 신청 내역 조회
- 퇴사예정자 탭: 퇴사예정자 목록 + 보전연차 부여/사용/잔여 표시 + 수정
- 부서 관리
- 직급 관리
- 휴가 유형 관리 (reorder, count_all_days)
- 공휴일 관리 (년별)
- 연도별 연차 관리 (annual_by_year 기준)
- 전체 현황 (all requests)

---

## 5. 데이터베이스 구조

### 5.1 departments (부서)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| code | VARCHAR(20) | 부서 코드 (UNIQUE) |
| name | VARCHAR(50) | 부서명 |
| color | VARCHAR(7) | 부서 색상 (#hex) |
| created_at | TIMESTAMP | 생성일 |

### 5.2 positions (직급)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| name | VARCHAR(50) | 직급명 (UNIQUE) |
| is_active | TINYINT | 활성화 |
| sort_order | INT | 정렬 순서 |

### 5.3 employees (사원)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| emp_no | VARCHAR(20) | 계정 (UNIQUE) |
| name | VARCHAR(50) | 이름 |
| department_id | INT | 부서 (FK → departments.id) |
| position | VARCHAR(30) | 직위 (text, legacy) |
| position_id | INT | 직급 ID (FK → positions.id) |
| role | ENUM | 역할 (system_admin/reviewer/dept_manager/user/ceo/vice_president) |
| managed_department_id | INT | 관리 부서 (FK → departments.id) |
| annual_leave | DECIMAL(4,1) | 연차 잔여 (denormalized) |
| phone1 | VARCHAR(20) | 비상연락처1 |
| phone2 | VARCHAR(20) | 비상연락처2 |
| hire_date | DATE | 입사일 |
| resignation_date | DATE | 퇴직일 |
| password | VARCHAR(255) | bcrypt 해시 |
| is_active | TINYINT(1) | 활성화 (1=재직, 0=퇴사) |
| is_resigning | TINYINT(1) | 퇴사예정자 여부 |
| severance_leave | DECIMAL(10,1) | 보전연차 잔여 (REMAINING 기준) |
| visible_to_exec | TINYINT(1) | 임원(CEO/부대표) 휴가조회 허용 |
| created_at | TIMESTAMP | 생성일 |
| updated_at | TIMESTAMP | 수정일 (ON UPDATE CURRENT_TIMESTAMP) |

### 5.4 vacation_types (휴가 유형)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| name | VARCHAR(50) | 유형명 |
| deduction | DECIMAL(3,1) | 차감 일수 |
| max_days | DECIMAL(4,1) | 최대 일수 |
| deduct_from | ENUM | 차감 대상 (annual/none) |
| color | VARCHAR(7) | 색상 (미사용) |
| is_active | TINYINT | 활성화 |
| sort_order | INT | 정렬 순서 |

### 5.5 condolence_types (경조사 유형)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| name | VARCHAR(50) | 경조사 사유명 |
| limit_days | DECIMAL(4,1) | 제한 일수 |
| is_active | TINYINT | 활성화 |
| sort_order | INT | 정렬 순서 |

### 5.6 vacation_requests (휴가 신청)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| employee_id | INT | 사원 (FK → employees.id) |
| vacation_type_id | INT | 휴가 유형 (FK → vacation_types.id) |
| condolence_type_id | INT | 경조사 사유 (FK → condolence_types.id) |
| start_date | DATE | 시작일 |
| end_date | DATE | 종료일 |
| days | DECIMAL(3,1) | 총 일수 |
| reason | TEXT | 사유 |
| status | ENUM | 상태 (applied/approved/cancelled) |
| annual_deduct_days | DECIMAL(4,1) | 차감된 연차 일수 |
| condolence_days | DECIMAL(4,1) | 경조사 일수 (비차감) |
| start_half | ENUM | 시작일 반차 (full/morning/afternoon) |
| end_half | ENUM | 종료일 반차 (full/morning/afternoon) |
| created_at | TIMESTAMP | 생성일 |
| updated_at | TIMESTAMP | 수정일 |

### 5.7 annual_by_year (연도별 연차)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| employee_id | INT | 사원 (FK → employees.id) |
| year | YEAR | 연도 |
| annual_leave | DECIMAL(4,1) | 부여 연차 |
| used_all | TINYINT | 모두 사용 여부 |
| created_at | TIMESTAMP | 생성일 |

> UNIQUE KEY: (employee_id, year)

### 5.8 condolence_usage_history (경조사 사용 내역)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| employee_id | INT | 사원 (FK → employees.id) |
| condolence_type_id | INT | 경조사 유형 (FK → condolence_types.id) |
| birth_event | INT | 출산 이벤트 회차 |
| usage_round | INT | 사용 차次 (1~4) |
| days_used | DECIMAL(4,1) | 해당 차次 사용 일수 |
| created_at | TIMESTAMP | 생성일 |
| updated_at | TIMESTAMP | 수정일 |

> UNIQUE KEY: (employee_id, condolence_type_id, birth_event, usage_round)

### 5.9 holidays (공휴일)

| 필드 | 타입 | 설명 |
|------|------|------|
| id | INT | PK |
| date | DATE | 날짜 (UNIQUE) |
| name | VARCHAR(50) | 명칭 |
| year | YEAR | 연도 |

---

## 6. API 엔드포인트

### 6.1 auth.php

| Action | Method | Description |
|--------|--------|------------|
| login | POST | 로그인 (emp_no + password) |
| logout | POST | 로그아웃 (세션 파기) |
| check | GET | 세션 확인 + 연차 자동 부여 |
| change_password | POST | 비밀번호 변경 |

### 6.2 employees.php

| Action | Method | Description | 권한 |
|--------|--------|------------|------|
| list | GET | 사원 목록 (역할별 필터링) | 전체 |
| get | GET | 사원 상세 | 전체 |
| create | POST | 사원 생성 | system_admin |
| update | POST | 사원 수정 | system_admin |
| delete | POST | 사원 삭제 | system_admin |
| departments | GET | 부서 목록 | 전체 |
| severance_usage | GET | 보전연차 사용/잔여 조회 | 전체 (본인/관리자) |

### 6.3 vacation_requests.php

| Action | Method | Description | 권한 |
|--------|--------|------------|------|
| list | GET | 휴가 목록 (필터+페이지) | 전체 |
| calendar | GET | 캘린더 이벤트 | 전체 |
| detail | GET | 휴가 상세 | 전체 |
| create | POST | 휴가 생성 | 전체 |
| update | POST | 휴가 수정 | 본인/관리자 |
| cancel | POST | 휴가 취소 | 본인/검토자 |
| approve | POST | 휴가 승인 | system_admin/reviewer |
| my_remaining | GET | 내 연차 잔여 (단순) | 전체 |
| my_remaining_year | GET | 내 연차 상세 (년별) | 전체 |
| my_annual_info | GET | 내 연차 정보 (total/remaining/used) | 전체 |
| my_condolence_info | GET | 내 경조사 정보 | 전체 |
| employee_leave | GET | 특정 사원 연차 정보 | admin/reviewer/ceo/vp |
| **employee_annual_list** | **GET** | **전체/부서별 연차 현황 목록** | **system_admin/reviewer/dept_manager** |
| holidays | GET | 공휴일 목록 | 전체 |
| holiday_save | POST | 공휴일 저장 | system_admin |
| holiday_delete | POST | 공휴일 삭제 | system_admin |

### 6.4 vacation_types.php

| Action | Method | Description | 권한 |
|--------|--------|------------|------|
| list | GET | 휴가 유형 목록 | 전체 |
| create | POST | 유형 생성 | system_admin |
| update | POST | 유형 수정 | system_admin |
| delete | POST | 유형 삭제 | system_admin |

### 6.5 condolence_types.php

| Action | Method | Description | 권한 |
|--------|--------|------------|------|
| list | GET | 경조사 유형 목록 | 전체 |
| get | GET | 경조사 유형 상세 | 전체 |
| get_used | GET | 경조사 사용 일수 | 전체 |

### 6.6 positions.php

| Action | Method | Description | 권한 |
|--------|--------|------------|------|
| list | GET | 직급 목록 | 전체 |
| create | POST | 직급 생성 | system_admin |
| update | POST | 직급 수정 | system_admin |
| delete | POST | 직급 삭제 | system_admin |

---

## 7. 화면 구성

### 7.1 메인 페이지 (index.php)

| 영역 | 설명 |
|------|------|
| 로그인 페이지 | emp_no + password 로그인 |
| 헤더 | 사용자 정보, 비밀번호 변경, 관리자 링크, **연차 현황**, 로그아웃 |
| 대시보드 | 잔여연차/사용연차/총신청 카드 (3개) |
| 휴가 캘린더 | FullCalendar (월/주/목록), 공휴일/Family Day 표시 |
| 휴가 신청 내역 | 필터(본부/사원/년/월/취소포함), 테이블, 페이지네이션 |
| **연차 현황** (**토글**) | **사원별 부여/사용/잔여연차 테이블, 연도 선택** |

### 7.2 모달

| 모달 | 설명 |
|------|------|
| 휴가 신청 | 유형/기간/반차/일수/연락처/사유 |
| 휴가 수정 | 유형/기간/일수/사유 (연락처 제외) |
| 휴가 상세 | 신청 정보 표시 |
| 비밀번호 변경 | 현재/새 비밀번호 |

### 7.3 관리자 페이지 (admin.php)

- 접근 권한: `system_admin` 전용
- 탭: 사원 관리 / 휴가 유형 / 직급 / 공휴일 / 전체 현황

---

## 8. 데이터 흐름

### 8.1 휴가 신청

```
사용자 입력 → JS 유효성 검사 → API createRequest()
  → DB INSERT (vacation_requests)
  → 연차 차감 (employees.annual_leave 업데이트)
  → 배우자출산 시: updateSpouseBirthUsage() → condolence_usage_history 업데이트
  → 세션 업데이트 (annual_leave)
```

### 8.2 휴가 취소

```
사용자 클릭 → confirm → API cancelRequest()
  → DB UPDATE status='cancelled'
  → 연차 환불 (employees.annual_leave + refund)
  → 배우자출산 시: refundSpouseBirthUsage()
  → 세션 업데이트
```

### 8.3 연차 계산

```
부여: annual_by_year.annual_leave (employee_id + year)
사용: SUM(vacation_requests.annual_deduct_days) WHERE status IN ('applied','approved')
잔여: 부여 - 사용 (실시간 계산)
```

### 8.4 보전연차 계산

- `employees.severance_leave`: **REMAINING** 값 (부여량이 아님)
- 부여(granted) = 잔여(REMAINING) + SUM(사용)
- 사용: `SUM(severance_deduct_days)` WHERE status IN ('applied','approved')
- 차감 순서: 일반연차 먼저 차감 → 일반연차 소진 시 보전연차 차감
- `employees.annual_leave`: `annual_by_year` 기준 연산 결과로 UPDATE (createRequest/updateRequest에서 동기화)

### 8.5 색상 표시

```
DB departments.color
  → API: d.color as department_color / COALESCE(d.color, '#667eea') as backgroundColor
  → 캘린더: event.backgroundColor
  → 목록: <span class="color-dot" style="background:${deptColor}">
```

---

## 9. 변경 이력

| 날짜 | 변경 내용 |
|------|----------|
| 2026-04-24 | 초기 작성 |
| | 본부필터 추가 (검토자/시스템관리자) |
| | 연도 필터 연도별 연동 |
| | 캘린더 주말/공휴일 색상 |
| | 연차 자동 부여 로직 수정 (12월) |
| | 출력 팝업 창 변경 |
| | 카드 연도 연동 |
| 2026-05-08 | 부서별 색상 시스템 추가 (`d.color`取代 `vt.color`) |
| | 취소 내역 포함 체크박스 추가 |
| | 연차 현황 페이지 추가 (reviewer/dept_manager/system_admin) |
| | 사원/부서 필터 파라미터 전달 버그 수정 |
| | `employee_annual_list` API 신규 |
| | vacation_requests에 누락 컬럼 6개 추가 |
| | condolence_types/condolence_usage_history/holidays 테이블 추가 |
| | ceo/vice_president 역할 추가 |
| | init_db.sql / DEPLOY.md / SPEC.md 최신화 |
| 2026-05-12 | `start_half`/`end_half` HTML option 값 불일치 수정 (`"am"`→`"morning"`, `"pm"`→`"afternoon"`) |
| | `createRequest()` INSERT에 `start_half`, `end_half` 컬럼 추가 |
| | `updateRequest()` UPDATE에 `start_half`, `end_half` 컬럼 추가 |
| | `print.php` 체크박스 로직: DB 값(`start_half`/`end_half`) + 휴가구분명 모두 참조 |
| | `print.php` 휴가기간: `halfLabel()` fallback 함수 추가 (DB 값 없으면 휴가구분명으로 유추) |
| 2026-05-12 | `employees.php` `getList()`에 `active` 파라미터 추가 (기본 `is_active = 1`) |
| | `admin.php` 사원관리 탭: 상태 컬럼(재직중/퇴사) + 퇴사 처리/복직 버튼 추가 |
| | `admin.php` 퇴사자 탭 신규: 퇴사자 목록 + 행 클릭 시 휴가 내역 표시 |
| 2026-05-12 | `employees` 테이블: `hire_date`, `resignation_date` 컬럼 추가 |
| | `employees.php` `createEmployee`/`updateEmployee`에 hire_date, resignation_date 지원 |
| | `admin.php` 사원관리 테이블 컬럼 단축 (부여연차→부여 등) |
| | `admin.php` 수정 모달에 입사일/퇴사(체크박스)/퇴직일 통합 |
| | `admin.php` 퇴사 처리/복직 버튼 제거 → 수정 모달에서 is_active 토글로 대체 |
| | `admin.php` 퇴사자 탭: 퇴직일 컬럼 추가, 복직 버튼 → 수정 버튼 |
| 2026-06-08 | 보전연차(severance leave) 시스템 추가 |
| | `employees.severance_leave` 컬럼 (REMAINING 기준) — 퇴사예정자 보전연차 관리 |
| | `migrations/migration_007_severance_leave.sql` — severance_leave 컬럼 추가 |
| | 차감 순서: 일반연차 → 보전연차 (createRequest/updateRequest) |
| | `admin.php` 퇴사예정자 탭 신규 — 보전연차 부여/사용/잔여 표시 |
| | `index.php` 대시보드 — 퇴사예정자 보전연차 카드 (0.0일도 항상 표시) |
| 2026-06-08 | 김은솔 하드코딩 제거 → `visible_to_exec` 컬럼으로 대체 |
| | `migrations/migration_008_visible_to_exec.sql` — visible_to_exec 컬럼 + 김은솔 UPDATE |
| | `vacation_requests.php` CEO/VP 조건 6군데: `e.name = '김은솔'` → `e.visible_to_exec = 1` |
| | `admin.php` 사원 수정 모달: 임원 휴가조회 허용 체크박스 추가 |
| 2026-06-08 | 버그 수정: `updateRequest()` 차감 분할 기준 GRANTED→REMAINING |
| | 버그 수정: `updateRequest()` total remaining + severance net increase validation |
| | 버그 수정: `employees.php` `$fields`에 `resignation_date`, `hire_date` 누락 |
| | 버그 수정: `is_active=0` 시 `is_resigning` 자동 `0` SET (제거→강제설정) |
| | 버그 수정: DATE 필드 빈 문자열 → NULL 변환 |
| | 버그 수정: `admin.php` 저장 후 모든 탭 데이터 갱신 |
| | 버그 수정: 사원관리 탭 `active: 1` 필터 적용 |



## 10. 별도 요청사항
| | 은정어쏘 요청사항
| | 1. 대표님 권한 -> 팀장 이상만 조회 + 김은솔 매니저 추가
| | 2. 부대표님 권한 -> 투자본부만 조회
| | 3. 캘린더 - 휴가기간에 이름 + 휴가유형 표시
| | 4. 휴가유형별 색상 -> 본부별 색상 변경
| | 5. 바탕화면 바로가기 아이콘 생성 -> 추후 사이트 접속 후 생성
| | 6. 휴가신청내역에 년도 필터 외에 월필터 추가요청
| | 7. 취소한 내역 삭제 -> 삭제대신 토글버튼으로 표시되거나 표시되지 않도록 변경
| | 8. 전직원 잔여연차 표시 페이지 추가(검토자만)
| | 9. 긴급연락처 좌측정렬 1번만 입력 후 2번은 '-' 표시 적용완료
| | 10. 반차(오전), 반차(오후), 연차, 경조사 (연차, 경조사 섞어서 사용시) 체크표시 안되는 부분 → 수정완료 (2026-05-12) |
| | 11. 퇴사자 관리: 사원관리 탭 상태 컬럼 + 퇴사 처리 버튼, 퇴사자 탭에서 휴가 내역 조회 → 수정완료 (2026-05-12) |
| | 12. 2027, 2028, ....2032 ... 다음년도 연차부여 DB쿼리작업으로 한번에 처리 예정(쿼리 내용 만들어야 함)
| | 13. 퇴사자에 대한 퇴사 구분 및 퇴사일,입사일 작성 처리 → 수정완료 (2026-05-12)
| | 14. 김은솔 하드코딩 제거 → `visible_to_exec` DB 컬럼 + 관리자 체크박스 → 수정완료 (2026-06-08)
| | 15. 퇴사예정자 보전연차(severance leave) 시스템 — 관리자 수동 입력, 일반연차→보전연차 순서 차감, 대시보드 카드 → 수정완료 (2026-06-08)

---

## 11. 파일 관계도

### 11.1 파일 계층 구조

```
vacation/
├── index.php              ← 메인 페이지 (로그인 + 대시보드 + 캘린더 + 휴가 신청/목록)
├── admin.php              ← 관리자 페이지 (사원/휴가유형/직급/공휴일/전체현황 CRUD)
│
├── print.php              ← 휴가신청서 출력 (HTML 인쇄) — ?id=X
├── print_backup.php       ← print.php 구버전 백업
│
├── apply_db_changes.php   ← DB 마이그레이션 원클릭 실행 (1회용, 보안삭제 권장)
├── update_admin.php       ← admin 계정을 adfadmin으로 변경
│
├── config/
│   └── database.php       ← DB 연결 설정 (PDO Singleton, host/user/pass)
│
├── api/                   ← RESTful API 엔드포인트 (PHP 백엔드)
│   ├── auth.php           ─── 로그인/로그아웃/세션확인/비번변경
│   ├── employees.php      ─── 사원 CRUD + 부서목록
│   ├── vacation_requests.php ─── 휴가 CRUD + 캘린더 + 연차정보 + 공휴일
│   ├── vacation_types.php ─── 휴가유형 CRUD
│   ├── condolence_types.php ─── 경조사유형 조회
│   └── positions.php      ─── 직급 CRUD
│
├── js/                    ← 프론트엔드 JS
│   ├── api.js             ─── API 클라이언트 (fetch wrapper)
│   ├── main.js            ─── 메인 로직 (로그인/대시보드/휴가신청/필터/모달)
│   └── calendar.js        ─── FullCalendar 통합 (휴일/Family Day 하이라이트)
│
├── css/
│   └── styles.css         ← 모든 스타일 (로그인/헤더/카드/테이블/모달/인쇄/캘린더)
│
├── init_db.sql            ← 완전한 DB 초기화 (9개 테이블 + 시드데이터)
├── vacation_db_back.sql   ← mysqldump 백업
│
├── add_department_colors.sql        ← departments.color 컬럼 추가 (부서별 색상)
├── add_condolence_usage_history.sql ← condolence_usage_history 테이블 생성
├── add_hire_resignation_dates.sql   ← employees.hire_date, resignation_date 추가
├── add_performance_indexes.sql      ← 성능 인덱스 추가
├── update_condolence_usage_history.sql ← birth_event 컬럼 + 유니크키 수정
│
├── composer.json          ← 의존성: phpmailer/phpmailer
├── SPEC.md                ← 개발정의서
└── DEPLOY.md              ← 배포가이드
```

### 11.2 데이터 흐름 (Data Flow)

```
사용자 (index.php)
  └→ js/api.js (fetch)
       └→ api/vacation_requests.php (PHP)
            └→ config/database.php (PDO)
                 └→ MariaDB (vacation_db)

인쇄: print.php?id=X ─→ DB ─→ HTML + CSS (window.print)
```

### 11.3 JS ↔ API 의존 관계

| JS 함수 (main.js) | 호출 API | 설명 |
|---|---|---|
| `checkAuth()` | `api.auth.check()` | 세션 확인 |
| `login()` | `api.auth.login()` | 로그인 |
| `renderDashboard()` | `api.vacationRequests.getMyAnnualInfo()` | 잔여/사용 연차 |
| `loadVacationList()` | `api.vacationRequests.list()` | 휴가 목록 (년/월/부서/사원 필터) |
| `submitVacation()` | `api.vacationRequests.create()` | 휴가 신청 |
| `submitVacationEdit()` | `api.vacationRequests.update()` | 휴가 수정 |
| `cancelRequest()` | `api.vacationRequests.cancel()` | 휴가 취소 (연차 환불) |
| `approveRequest()` | `api.vacationRequests.approve()` | 휴가 승인 |
| `loadEmployeeAnnualList()` | `api.vacationRequests.employeeAnnualList()` | 전직원 연차 현황 |
| `loadCalendarEvents()` | `api.vacationRequests.calendar()` | 캘린더 이벤트 |
| `loadHolidays()` | `api.holidays.list()` | 공휴일 목록 |

### 11.4 DB 테이블 관계도

```
departments (부서)
  ← employees.department_id (FK)
  ← employees.managed_department_id (FK)

positions (직급)
  ← employees.position_id (FK)

employees (사원) ── 중심 테이블
  → vacation_requests.employee_id (FK, CASCADE)
  → annual_by_year.employee_id (FK, CASCADE)
  → condolence_usage_history.employee_id (FK, CASCADE)

vacation_types (휴가유형)
  → vacation_requests.vacation_type_id (FK, RESTRICT)

condolence_types (경조사유형)
  → vacation_requests.condolence_type_id (FK, RESTRICT)
  → condolence_usage_history.condolence_type_id (FK, RESTRICT)

vacation_requests (휴가신청) ── 핵심 트랜잭션 테이블
annual_by_year (연도별연차)
condolence_usage_history (경조사사용내역)
holidays (공휴일)
```

## 12. 증명서 발급 시스템

### 12.1 개요
- 경력증명서 / 재직증명서 발급 요청 기능
- 모든 사용자(비로그인 제외) 사용 가능
- 발급 요청 시 관리자 이메일로 PHPMailer 알림 발송

### 12.2 요청 흐름
```
1. 사용자 → index.php 📋 행정지원요청 ▾ 드롭다운 → 증명서 항목 선택
2. 체크박스형 모달: 주민번호노출 / 징계여부 / 업무기재 / 국문 / 영문 중 선택 후 제출
3. 업무기재 체크 시 textarea 입력 폼 표시 → 상세 업무 내용 입력 가능
4. 선택 시 api/certificate.php?action=request 호출
4. PHP: certificate_requests 테이블 INSERT + sendCertificateEmail() PHPMailer 발송
5. 관리자 페이지 → 📜 증명서 요청 탭 → 전체 목록 조회 (옵션별 컬럼 표시)
6. 관리자: 완료처리 모달 → 상태(completed/rejected) + 비고(notes) 입력
```

### 12.3 SMTP 설정
- **접근 방법**: 관리자 페이지 → ⚙️ 환경설정 ▾ → SMTP 설정
- **설정 항목** (settings 테이블 key-value 저장):

| 키 | 설명 | 예시 |
|----|------|------|
| smtp_host | SMTP 서버 호스트 | spamout.adfamc.com |
| smtp_port | SMTP 포트 | 25 |
| smtp_user | SMTP 계정 (선택) | |
| smtp_pass | SMTP 암호 (선택) | |
| smtp_encryption | 암호화 방식 (none/ssl/tls) | none |
| smtp_from_email | 발신 이메일 주소 | admin@example.com |
| smtp_auth | 인증 사용 여부 (0/1) | 0 |

- **smtp_auth=0 (체크 해제)**: SMTP 서버가 AUTH를 지원하지 않는 경우 사용 (IP 기반 신뢰)
- **테스트 발송**: SMTP 설정 저장 후 테스트 발송 버튼으로 연결 확인 가능
- **디버그 출력**: 테스트 실패 시 SMTP 통신 내용이 `<pre>` 박스에 표시됨

### 12.4 주민번호 처리
- **DB 저장**: AES-256-CBC 암호화 (`config/encryption.php`)
  - 암호화 키는 `settings.encryption_key`에 저장 (최초 호출 시 자동 생성)
  - 키 생성: `hash('sha256', random_bytes(32))`
- **조회 시**: 관리자 목록에서는 `maskResidentNo()`로 마스킹 처리 (********)
- **사원 수정 시**:
  1. 모달 열기: `********` + 🔒 (잠김 상태)
  2. 🔒 클릭 → 비밀번호 확인 모달 → `api/auth.php?action=verify_password`
  3. 인증 성공 → 🔓 (원본 표시)
  4. 저장 시 필드가 잠겨있으면(🔒) 주민번호 미전송 → 기존 암호화값 유지

### 12.5 증명서 요청 API

| Action | Method | 설명 | 권한 |
|--------|--------|------|------|
| request | POST | 증명서 발급 요청 (DB 저장 + 메일 발송, job_desc_content 포함) | 로그인 사용자 |
| list | GET | 전체 요청 목록 조회 (주민번호 마스킹) | system_admin, reviewer |
| complete | POST | 요청 완료처리 (상태 + 비고) | system_admin, reviewer |

### 12.6 certificate_requests 테이블

```sql
CREATE TABLE certificate_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    certificate_type ENUM('career','employment') NOT NULL,
    show_resident TINYINT(1) DEFAULT 0 COMMENT '주민등록번호 노출',
    show_discipline TINYINT(1) DEFAULT 0 COMMENT '징계여부',
    job_desc TINYINT(1) DEFAULT 0 COMMENT '업무기재',
    job_desc_korean TINYINT(1) DEFAULT 0 COMMENT '국문',
    job_desc_english TINYINT(1) DEFAULT 0 COMMENT '영문',
    job_desc_content TEXT DEFAULT NULL COMMENT '업무기재 상세내용',
    status ENUM('pending','completed','rejected') DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    processed_by INT,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (processed_by) REFERENCES employees(id)
);
```

### 12.7 필요한 서버 설정

```bash
# SELinux: SMTP 메일 발송 허용
sudo setsebool -P httpd_can_sendmail on

# PHP openssl 확장 (AES-256-CBC 암호화에 필요)
sudo dnf install php-openssl
sudo systemctl restart httpd
```

## 13. 행정지원 요청 시스템

### 13.1 개요
- 사원증 발급 / 명함 발급 / 사무용품 신청 요청 기능
- 모든 사용자(비로그인 제외) 사용 가능
- 발급 요청 시 관리자 이메일로 PHPMailer 알림 발송

### 13.2 요청 흐름
```
1. 사용자 → index.php 📋 행정지원요청 ▾ 드롭다운 → 항목 선택
2. 3가지 항목: 사원증 발급 / 명함 발급 / 사무용품 신청
3. 선택 시 요청사항 입력 모달 오픈 (textarea)
4. 제출 → api/support.php?action=request 호출
5. PHP: support_requests 테이블 INSERT + sendSupportEmail() PHPMailer 발송
6. 관리자 페이지 → 📋 지원 요청 탭 → 전체 목록 조회
7. 관리자: 처리 모달 → 상태(완료/반려) + 비고 입력
```

### 13.3 지원 요청 API

| Action | Method | 설명 | 권한 |
|--------|--------|------|------|
| request | POST | 지원 요청 (DB 저장 + 메일 발송) | 로그인 사용자 |
| list | GET | 전체 요청 목록 조회 | system_admin, reviewer |
| complete | POST | 요청 처리 (완료/반려 + 비고) | system_admin, reviewer |

### 13.4 support_requests 테이블

```sql
CREATE TABLE support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    request_type ENUM('id_card','business_card','office_supply') NOT NULL COMMENT 'id_card=사원증, business_card=명함, office_supply=사무용품',
    content TEXT COMMENT '요청사항',
    status ENUM('requested','completed','rejected') DEFAULT 'requested',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    processed_by INT,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (processed_by) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 11.5 요청 처리 흐름 예시 (휴가 신청)

```
1. index.php 로드 → session_start() → js/api.js 로드
2. main.js: `checkAuth()` → api/auth.php?action=check → 세션확인
3. 사용자가 휴가 신청 → main.js: `submitVacation()`
4. → api.js: `api.vacationRequests.create(data)`
5. → fetch('api/vacation_requests.php?action=create', POST, JSON body)
6. → PHP: DB INSERT + 연차차감 + 경조사이력 업데이트
7. → JSON 응답 → JS에서 alert + 캘린더/목록 새로고침
```
