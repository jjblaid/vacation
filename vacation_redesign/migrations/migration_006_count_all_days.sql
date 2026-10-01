ALTER TABLE vacation_types ADD COLUMN count_all_days TINYINT DEFAULT 0 COMMENT '1=모든일수(주말/공휴일 포함) 카운팅, 0=주말/공휴일 제외';

UPDATE vacation_types SET count_all_days = 1 WHERE name = '육아휴직';
