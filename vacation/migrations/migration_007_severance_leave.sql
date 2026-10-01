ALTER TABLE employees
  ADD COLUMN is_resigning TINYINT(1) DEFAULT 0 COMMENT '퇴사예정자 여부',
  ADD COLUMN severance_leave DECIMAL(10,1) DEFAULT 0 COMMENT '보전연차 잔여';

ALTER TABLE vacation_requests
  ADD COLUMN severance_deduct_days DECIMAL(10,1) DEFAULT 0 COMMENT '보전연차 차감일';