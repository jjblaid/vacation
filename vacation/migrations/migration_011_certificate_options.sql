-- Migration 011: 증명서 발급 옵션 확장
-- certificate_requests 테이블에 체크박스 옵션 컬럼 추가

ALTER TABLE certificate_requests
    ADD COLUMN show_discipline TINYINT(1) DEFAULT 0 COMMENT '징계여부' AFTER show_resident,
    ADD COLUMN job_desc TINYINT(1) DEFAULT 0 COMMENT '업무기재' AFTER show_discipline,
    ADD COLUMN job_desc_korean TINYINT(1) DEFAULT 0 COMMENT '국문' AFTER job_desc,
    ADD COLUMN job_desc_english TINYINT(1) DEFAULT 0 COMMENT '영문' AFTER job_desc_korean;
