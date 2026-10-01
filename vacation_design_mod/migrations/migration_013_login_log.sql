-- Migration 013: 로그인 로그 테이블
-- 로그인/로그아웃 기록 및 현재 접속자 확인용

CREATE TABLE IF NOT EXISTS login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL COMMENT '사원 ID',
    emp_no VARCHAR(20) NOT NULL COMMENT '사번',
    name VARCHAR(100) NOT NULL COMMENT '이름',
    role VARCHAR(30) NOT NULL COMMENT '권한',
    department_name VARCHAR(100) DEFAULT NULL COMMENT '부서명',
    login_at DATETIME NOT NULL COMMENT '로그인 시간',
    last_activity DATETIME NOT NULL COMMENT '마지막 활동 시간',
    logout_at DATETIME DEFAULT NULL COMMENT '로그아웃 시간',
    session_id VARCHAR(255) NOT NULL COMMENT '세션 ID',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP 주소',
    INDEX idx_session_id (session_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_logout_at (logout_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
