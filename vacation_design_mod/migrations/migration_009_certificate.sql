-- ============================================================
-- Migration 009: 증명서 발급 시스템 + 직원 추가 정보
-- 
-- 변경 내용:
-- 1. employees 테이블: email, birth_date, address, resident_no_encrypted 컬럼 추가
-- 2. settings 테이블 생성 (SMTP 설정 + 암호화 키)
-- 3. certificate_requests 테이블 생성 (증명서 요청 내역)
-- ============================================================

-- 1. employees 테이블에 컬럼 추가
ALTER TABLE employees
    ADD COLUMN email VARCHAR(100) DEFAULT '' COMMENT '이메일' AFTER phone2,
    ADD COLUMN birth_date DATE NULL COMMENT '생년월일' AFTER email,
    ADD COLUMN address VARCHAR(200) DEFAULT '' COMMENT '주소' AFTER birth_date,
    ADD COLUMN resident_no_encrypted VARCHAR(255) DEFAULT '' COMMENT '주민등록번호(AES-256-CBC 암호화)' AFTER address;

-- 2. settings 테이블 생성
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. certificate_requests 테이블 생성
CREATE TABLE IF NOT EXISTS certificate_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    certificate_type ENUM('career', 'employment') NOT NULL COMMENT '경력증명서/재직증명서',
    show_resident TINYINT(1) DEFAULT 0 COMMENT '주민등록번호 노출 여부',
    status ENUM('requested', 'completed', 'cancelled') DEFAULT 'requested',
    notes TEXT COMMENT '관리자 비고',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. 초기 데이터
UPDATE employees SET email = 'jjpark@adfamc.com', birth_date = NULL, address = '', resident_no_encrypted = '' WHERE emp_no = 'adfadmin';

INSERT INTO settings (`key`, `value`) VALUES
('smtp_host', 'mail.adfamc.com'),
('smtp_port', '587'),
('smtp_user', 'jjpark@adfamc.com'),
('smtp_pass', 'wjdwnsgptjd1!@'),
('smtp_encryption', 'tls'),
('smtp_from_email', 'jjpark@adfamc.com'),
('smtp_auth', '0'),
('encryption_key', SHA2(RAND(), 256))
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
