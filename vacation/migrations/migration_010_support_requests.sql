-- Migration 010: 행정지원 요청 시스템
-- 1. support_requests 테이블 생성

CREATE TABLE IF NOT EXISTS support_requests (
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
