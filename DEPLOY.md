# 휴가신청 시스템 배포 가이드

## 서버 환경

- RockyLinux 8.10
- Apache 2.4.37
- MariaDB 10.3
- PHP 8.1

---

## 1. 파일 업로드

프로젝트 폴더를 서버의 웹 루트에 업로드합니다.

```bash
# Apache 기본 문서 루트
cp -r vacation /var/www/html/

# 또는 VirtualHost 사용 시
cp -r vacation /var/www/vacation.example.com/
```

---

## 2. 데이터베이스 설정

### 2.1 MariaDB 접속

```bash
mysql -u root -p
```

### 2.2 init_db.sql 실행

```bash
mysql -u root -p < /var/www/html/vacation/init_db.sql
```

또는 phpMyAdmin에서 `init_db.sql` 파일을 import합니다.

> `init_db.sql`은 모든 테이블과 초기 데이터를 포함한 **단일 완전 스크립트**입니다.
> 별도의 마이그레이션 SQL 파일을 실행할 필요가 없습니다.

### 2.3 Database 설정 수정

`config/database.php` 파일의 DB 정보를 수정합니다:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vacation_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

---

## 3. 라이브러리 설치

### 3.1 Composer 설치 (없을 경우)

```bash
# RockyLinux
sudo dnf install composer

# 또는
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3.2 라이브러리 설치

```bash
cd /var/www/html/vacation
composer install
```

---

## 4. Apache 설정

### 4.1 VirtualHost 설정 (권장)

```apache
<VirtualHost *:80>
    ServerName vacation.example.com
    DocumentRoot /var/www/html/vacation

    <Directory /var/www/html/vacation>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/vacation_error.log
    CustomLog /var/log/httpd/vacation_access.log combined
</Directory>
```

### 4.2 SELinux 설정 (필요시)

```bash
# SMTP 메일 발송 허용 (증명서 요청 알림)
sudo setsebool -P httpd_can_sendmail on

# DB 접속, SMTP 서버 접속 허용
sudo setsebool -P httpd_can_network_connect on

# 파일 쓰기 권한
sudo chcon -R -t httpd_sys_rw_content_t /var/www/html/vacation
```

### 4.3 Apache 재시작

```bash
sudo systemctl restart httpd
```

---

## 5. 방화벽 설정

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

---

## 6. 초기 접속

### 6.1 로그인 정보

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

### 6.2 PHP 버전에 따른 비밀번호 해시

PHP 버전별 `password_hash()` 생성값이 다릅니다.

**PHP 8.1+ 기본 해시 (기본비밀번호: password123):**
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

**PHP 7.x 대안 해시:**
```
$2y$10$UufbWVsYePRxsjA94gcIbuiDQa6kQRR/47LT5ezh7/wHQbhXRI2eq
```

**직접 생성 방법 (bash):**

```bash
# 방법 1: PHP 명령어로 직접 생성
php -r "echo password_hash('password123', PASSWORD_DEFAULT) . PHP_EOL;"

# 방법 2: 스크립트 파일로 생성
echo '<?php echo password_hash("password123", PASSWORD_DEFAULT); ?>' > /tmp/hash.php
php /tmp/hash.php
rm /tmp/hash.php
```

**기존 사용자 비밀번호 초기화 (필요시):**

```sql
UPDATE employees SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE emp_no = 'adfadmin';
```

### 6.3 접속 URL

```
http://your-server/vacation/
```

---

## 7. 데이터베이스 테이블 구조

### 전체 테이블 목록

| 테이블 | 설명 |
|--------|------|
| departments | 부서 (코드, 이름, 색상) |
| positions | 직급 (정렬순서 포함) |
| employees | 사원 (계정, 역할, 소속부서, 연차잔여) |
| vacation_types | 휴가 유형 (차감여부, 최대일수) |
| condolence_types | 경조사 사유 유형 (제한일수) |
| vacation_requests | 휴가 신청 내역 |
| annual_by_year | 연도별 부여연차 |
| condolence_usage_history | 경조사 사용 내역 (출산 회차 관리) |
| holidays | 공휴일 |

---

## 8. 권한 체계

### 8.1 역할별 조회 범위

| 역할 | 코드 | 휴가 목록 | 연차 현황 | 휴가 승인 | 사원 관리 | 서브코드 보기 |
|------|------|----------|----------|----------|----------|-------------|
| 시스템관리자 | system_admin | 전직원 | 전직원 | ✓ | ✓ | ✓ |
| 검토자 | reviewer | 전직원 | 전직원 | ✓ | ✗ | ✗ |
| 관리자 | dept_manager | 본인 부서 | 본인 부서 | ✗ | ✗ | ✗ |
| 대표이사 | ceo | 팀장급 이상 + 부대표 + visible_to_exec=1 | ✗ | ✗ | ✗ | ✗ |
| 부대표 | vice_president | 투자본부(INV001, INV002) + visible_to_exec=1 | ✗ | ✗ | ✗ | ✗ |
| 사용자 | user | 본인만 | ✗ | ✗ | ✗ | ✗ |

### 8.2 역할별 휴가 신청 권한

| 역할 | 본인 신청 | 타인 대리 신청 | 수정 | 취소 |
|------|----------|-------------|------|------|
| 시스템관리자 | ✓ | ✓ | ✓ | ✓ |
| 검토자 | ✓ | ✓ | ✗ | ✓ |
| 관리자 | ✓ | 본인 부서원 | 본인 + 부서원 | ✗ |
| 대표이사 | ✓ | ✗ | 본인만 | ✗ |
| 부대표 | ✓ | ✗ | 본인만 | ✗ |
| 사용자 | ✓ | ✗ | 본인만 | ✗ |

---

## 9. 주요 기능

### 9.1 부서별 색상 시스템

각 부서에 고유한 색상이 지정되어 있습니다.

| 부서 | 코드 | 색상 |
|------|------|------|
| 임원( 대표, 부대표 ) | CEO | `#1e293b` |
| 경영관리본부 | MGT001 | `#7D00BE` |
| 운용본부 | OPS001 | `#FFA90A` |
| 투자본부 | INV001 | `#4A9DFF` |
| 투자본부 | INV002 | `#223EFF` |
| 준법감시 | COM001 | `#F70BFF` |

- **캘린더**: 이벤트 배경색이 해당 직원의 부서 색상으로 표시됩니다.
- **휴가 목록**: 부서명 옆에 컬러 닷(`color-dot`)이 표시됩니다.
- **변경 방법**: `departments` 테이블의 `color` 컬럼을 DB에서 직접 수정하면 즉시 반영됩니다.

### 9.2 취소 내역 필터

휴가 신청 내역 목록에서 **"취소 내역 포함"** 체크박스로 취소된 신청을 표시/숨길 수 있습니다.

- 기본값: 취소 내역 숨김
- 체크 시: 취소된 신청도 목록에 표시
- 페이지네이션 시에도 체크 상태 유지

### 9.3 연차 현황 조회

**"연차 현황"** 버튼을 통해 직원들의 연차 사용 현황을 조회할 수 있습니다.

- **접근 가능**: 시스템관리자, 검토자(전직원), 관리자(본인 부서)
- **표시 정보**: 사원명, 아이디, 본부, 직급, 부여연차, 사용연차, 잔여연차
- **잔여연차 색상**: 음수=빨간색, 3일 이하=주황색, 정상=초록색
- **연도 필터**: 별도 연도 선택 드롭다운 제공

---

## 10. 출력 기능

출력 기능을 사용하려면 라이브러리 설치가 필요합니다:

```bash
cd /var/www/html/vacation
composer install
```

### 출력 형식

- **인쇄**: 웹페이지에서 `window.print()` 사용

### PHP 정보 노출 방지

`X-Powered-By` 헤더로 PHP 버전이 노출되지 않도록 `php.ini`에서 설정합니다:

```ini
expose_php = Off
```

### Apache 정보 노출 방지

Apache 버전 정보 노출을 최소화하려면 `httpd.conf`에 설정합니다:

```apache
ServerTokens Prod
ServerSignature Off
```

Apache 레벨에서 직접 설정이 어려운 경우 `.htaccess`에 추가할 수 있습니다 (`mod_headers` 필요):

```apache
Header unset Server
```

---

## 11. 비밀번호 변경

기본 비밀번호 `password123`은 **반드시** 변경해주세요.

관리자 페이지 → 사원 수정 → 비밀번호 입력 → 저장

---

## 12. 트러블슈팅

### Q: DB 연결 오류

```bash
# MariaDB 상태 확인
sudo systemctl status mariadb

# 접속 테스트
mysql -u root -p vacation_db
```

### Q: PHP 확장자 없음

```bash
# 필수 PHP 확장 설치
sudo dnf install php-mysqlnd php-json php-mbstring php-xml php-openssl
sudo systemctl restart httpd

# 설치 확인
php -m | grep -E 'openssl|mbstring|json|mysql|xml'
```

### Q: 권한 오류

```bash
# 파일 권한 설정
sudo chown -R apache:apache /var/www/html/vacation
sudo chmod -R 755 /var/www/html/vacation
sudo chmod -R 775 /var/www/html/vacation/uploads 2>/dev/null
```

### Q: SMTP 메일이 발송되지 않음 (증명서 요청 알림)

```bash
# 1. SELinux 확인
getsebool httpd_can_sendmail

# 2. PHP openssl 확장 확인
php -m | grep openssl

# 3. 관리자 페이지 → 환경설정 ▾ → SMTP 설정 확인
#    - 인증사용: 체크 해제 (해당 서버는 AUTH 미지원, IP 기반 신뢰)
#    - 호스트: spamout.adfamc.com
#    - 포트: 25
#    - 암호화: none
#    - 발신이메일: 관리자 이메일 주소
#    - 테스트 발송 버튼으로 연결 확인 가능
```

### Q: 컬럼/테이블 누락 오류

기존 시스템에서 업데이트하는 경우, `init_db.sql`의 모든 CREATE TABLE/ALTER TABLE이 반영되었는지 확인하세요.

```bash
# 누락된 컬럼이 있는지 확인
mysql -u root -p vacation_db -e "DESCRIBE vacation_requests;"

# 누락된 경우 수동 추가
mysql -u root -p vacation_db -e "
ALTER TABLE vacation_requests ADD COLUMN IF NOT EXISTS condolence_type_id INT NULL AFTER vacation_type_id;
ALTER TABLE vacation_requests ADD COLUMN IF NOT EXISTS annual_deduct_days DECIMAL(4,1) DEFAULT 0 AFTER status;
ALTER TABLE vacation_requests ADD COLUMN IF NOT EXISTS condolence_days DECIMAL(4,1) DEFAULT 0 AFTER annual_deduct_days;
ALTER TABLE vacation_requests ADD COLUMN IF NOT EXISTS start_half ENUM('full','morning','afternoon') DEFAULT 'full' AFTER condolence_days;
ALTER TABLE vacation_requests ADD COLUMN IF NOT EXISTS end_half ENUM('full','morning','afternoon') DEFAULT 'full' AFTER start_half;
"
```

---

## 13. 업데이트

```bash
cd /var/www/html/vacation
git pull  # Git 사용 시

# 또는 파일 덮어쓰기 후
composer update
```

### DB 마이그레이션 (신규 테이블/컬럼 추가시)

프로젝트에 포함된 `.sql` 마이그레이션 파일을 `migrations/` 폴더에서 순서대로 실행합니다:

```bash
# 예시: 순차 실행
mysql -u root -p vacation_db < migrations/migration_006_count_all_days.sql
mysql -u root -p vacation_db < migrations/migration_007_severance_leave.sql
mysql -u root -p vacation_db < migrations/migration_008_visible_to_exec.sql
```

> `init_db.sql`을 새로 실행하는 경우 모든 마이그레이션이 포함되어 있으므로 별도 파일이 필요 없습니다.
> 기존 DB를 유지하면서 업데이트하는 경우에만 개별 마이그레이션 파일을 실행하세요.
>
> **주의**: 마이그레이션 파일은 반드시 번호 순서대로 실행해야 합니다. (`006` → `007` → `008`)
