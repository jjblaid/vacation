CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL COMMENT '부서 코드',
    name VARCHAR(50) NOT NULL COMMENT '부서명',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    color VARCHAR(7) DEFAULT '#667eea' COMMENT '부서별 색상',
    UNIQUE KEY code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT '직급명',
    is_active TINYINT DEFAULT 1,
    sort_order INT DEFAULT 0,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_no VARCHAR(20) NOT NULL,
    name VARCHAR(50) NOT NULL,
    department_id INT DEFAULT NULL,
    position VARCHAR(30) DEFAULT NULL,
    position_id INT DEFAULT NULL,
    role ENUM('system_admin','reviewer','dept_manager','user','ceo','vice_president') DEFAULT 'user',
    managed_department_id INT DEFAULT NULL,
    annual_leave DECIMAL(4,1) DEFAULT 0.0 COMMENT '연차 잔여',
    hire_date DATE DEFAULT NULL COMMENT '입사일',
    resignation_date DATE DEFAULT NULL COMMENT '퇴직일',
    password VARCHAR(255) NOT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    phone1 VARCHAR(20) DEFAULT '',
    phone2 VARCHAR(20) DEFAULT '',
    email VARCHAR(100) DEFAULT '' COMMENT '이메일',
    birth_date DATE DEFAULT NULL COMMENT '생년월일',
    address VARCHAR(200) DEFAULT '' COMMENT '주소',
    resident_no_encrypted VARCHAR(255) DEFAULT '' COMMENT '주민등록번호(AES-256-CBC 암호화)',
    is_resigning TINYINT(1) DEFAULT 0 COMMENT '퇴사예정자 여부',
    severance_leave DECIMAL(10,1) DEFAULT 0.0 COMMENT '보전연차 잔여',
    visible_to_exec TINYINT(1) DEFAULT 0 COMMENT '임원(CEO/부대표) 휴가조회 허용',
    UNIQUE KEY emp_no (emp_no),
    KEY managed_department_id (managed_department_id),
    KEY idx_emp_dept (department_id),
    KEY position_id (position_id),
    CONSTRAINT employees_ibfk_1 FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT employees_ibfk_2 FOREIGN KEY (managed_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT employees_ibfk_3 FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL,
    CONSTRAINT employees_ibfk_4 FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS vacation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    deduction DECIMAL(3,1) DEFAULT 0.0,
    max_days DECIMAL(4,1) DEFAULT 999.0,
    deduct_from ENUM('annual','none') DEFAULT 'none' COMMENT 'annual=연차차감, none=차감없음',
    color VARCHAR(7) DEFAULT '#667eea',
    is_active TINYINT DEFAULT 1,
    sort_order INT DEFAULT 0,
    limit_days DECIMAL(4,1) DEFAULT 0.0 COMMENT '경조사 기본 허용 일수, 0=무제한',
    count_all_days TINYINT DEFAULT 0 COMMENT '1=모든일수(주말/공휴일 포함) 카운팅, 0=주말/공휴일 제외'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS condolence_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    limit_days DECIMAL(4,1) DEFAULT 0.0,
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS vacation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    vacation_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(3,1) NOT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('applied','approved','cancelled') DEFAULT 'applied',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    condolence_type_id INT DEFAULT NULL,
    annual_deduct_days DECIMAL(3,1) DEFAULT 0.0 COMMENT '연차 차감 일수',
    condolence_days DECIMAL(5,1) DEFAULT NULL,
    start_half VARCHAR(10) DEFAULT 'full' COMMENT 'full/am/pm',
    end_half VARCHAR(10) DEFAULT 'full' COMMENT 'full/am/pm',
    severance_deduct_days DECIMAL(10,1) DEFAULT 0.0 COMMENT '보전연차 차감일',
    KEY vacation_type_id (vacation_type_id),
    KEY idx_vac_emp_status_dates (employee_id, status, start_date, end_date),
    KEY idx_vac_dates (start_date, end_date),
    KEY idx_vac_created (created_at),
    CONSTRAINT vacation_requests_ibfk_1 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT vacation_requests_ibfk_2 FOREIGN KEY (vacation_type_id) REFERENCES vacation_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS annual_by_year (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    year YEAR NOT NULL,
    annual_leave DECIMAL(4,1) DEFAULT 15.0,
    used_all TINYINT DEFAULT 0 COMMENT '1=모두 사용, 0=미사용',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_emp_year (employee_id, year),
    CONSTRAINT annual_by_year_ibfk_1 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condolence_usage_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    condolence_type_id INT NOT NULL,
    birth_event INT NOT NULL DEFAULT 1,
    usage_round INT NOT NULL DEFAULT 1,
    days_used DECIMAL(4,1) DEFAULT 0.0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_emp_type_round_event (employee_id, condolence_type_id, birth_event, usage_round),
    KEY condolence_type_id (condolence_type_id),
    CONSTRAINT condolence_usage_history_ibfk_1 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT condolence_usage_history_ibfk_2 FOREIGN KEY (condolence_type_id) REFERENCES condolence_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT '공휴일명',
    year YEAR NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY date (date),
    UNIQUE KEY uk_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) NOT NULL,
    `value` TEXT DEFAULT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS certificate_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    certificate_type ENUM('career','employment') NOT NULL COMMENT '경력증명서/재직증명서',
    show_resident TINYINT(1) DEFAULT 0 COMMENT '주민등록번호 노출 여부',
    show_discipline TINYINT(1) DEFAULT 0 COMMENT '징계여부',
    job_desc TINYINT(1) DEFAULT 0 COMMENT '업무기재',
    job_desc_korean TINYINT(1) DEFAULT 0 COMMENT '국문',
    job_desc_english TINYINT(1) DEFAULT 0 COMMENT '영문',
    job_desc_content TEXT DEFAULT NULL COMMENT '업무기재 상세내용',
    status ENUM('requested','completed','cancelled') DEFAULT 'requested',
    notes TEXT DEFAULT NULL COMMENT '관리자 비고',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    KEY employee_id (employee_id),
    KEY processed_by (processed_by),
    CONSTRAINT certificate_requests_ibfk_1 FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT certificate_requests_ibfk_2 FOREIGN KEY (processed_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    request_type ENUM('id_card','business_card','office_supply') NOT NULL COMMENT 'id_card=사원증, business_card=명함, office_supply=사무용품',
    content TEXT DEFAULT NULL COMMENT '요청사항',
    status ENUM('requested','completed') DEFAULT 'requested',
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    KEY employee_id (employee_id),
    KEY processed_by (processed_by),
    CONSTRAINT support_requests_ibfk_1 FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT support_requests_ibfk_2 FOREIGN KEY (processed_by) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
