-- Migration 012: 증명서 업무기재 상세내용 컬럼 추가

ALTER TABLE certificate_requests
    ADD COLUMN job_desc_content TEXT DEFAULT NULL COMMENT '업무기재 상세내용' AFTER job_desc_english;
