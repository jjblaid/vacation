-- ============================================
-- 경조사 사용 이력 테이블 수정
-- 배우자 출산: 출산 event별 20일, 4차 사용
-- ============================================

USE vacation_db;

-- birth_event 컬럼 추가 (이미 있으면 무시)
ALTER TABLE condolence_usage_history 
ADD COLUMN birth_event INT NOT NULL DEFAULT 1 AFTER condolence_type_id;

-- 유니크 키 수정 (출산 event 포함)
ALTER TABLE condolence_usage_history 
DROP INDEX uk_emp_type_round,
ADD UNIQUE KEY uk_emp_type_round_event (employee_id, condolence_type_id, birth_event, usage_round);

SELECT 'condolence_usage_history table updated successfully!' AS result;