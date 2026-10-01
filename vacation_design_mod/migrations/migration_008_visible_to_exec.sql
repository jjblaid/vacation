ALTER TABLE employees
  ADD COLUMN visible_to_exec TINYINT(1) DEFAULT 0 COMMENT '임원(CEO/부대표) 휴가조회 허용';

-- 기존 김은솔 직원 허용 설정
UPDATE employees SET visible_to_exec = 1 WHERE name = '김은솔';
