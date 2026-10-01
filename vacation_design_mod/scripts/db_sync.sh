#!/usr/bin/env bash
#
# DB 동기화 스크립트 (운영 → 개발)
#
# 사용법:
#   ./db_sync.sh dump            운영 DB 덤프 생성 (db_sync_prod_<timestamp>.sql)
#   ./db_sync.sh import [FILE]   개발 DB를 덤프로 교체 (파일 생략 시 가장 최근 덤프 사용)
#   ./db_sync.sh sync [FILE]     dump + import 한번에
#
# 접속 정보는 아래 설정 블록을 직접 수정하세요.
#
set -euo pipefail

# ===== 접속 정보 설정 =====
# 운영(소스) DB
PROD_DB_HOST='10.146.10.116'
PROD_DB_PORT='3306'
PROD_DB_NAME='vacation_db'
PROD_DB_USER='root'
PROD_DB_PASS='jjblaid!@#'

# 개발(타깃) DB
DEV_DB_HOST='localhost'
DEV_DB_PORT='3306'
DEV_DB_NAME='vacation_db'
DEV_DB_USER='root'
DEV_DB_PASS='jjblaid!@#'

DUMP_DIR="/var/lib/mysql/Prod_dump"

TS="$(date +%Y%m%d_%H%M%S)"
PROD_DUMP="$DUMP_DIR/db_sync_prod_$TS.sql"
DEV_BACKUP="$DUMP_DIR/db_sync_dev_backup_$TS.sql"

color() { local c=$1; shift; if [[ -t 1 ]]; then printf '\033[%sm%s\033[0m\n' "$c" "$*"; else printf '%s\n' "$*"; fi; }
green() { color '1;32' "$@"; }
red()   { color '1;31' "$@"; }
cyan()  { color '1;36' "$@"; }

usage() {
    sed -n '3,9p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
}

check_client() {
    command -v mysqldump >/dev/null 2>&1 || { red "[오류] mysqldump을 찾을 수 없습니다."; exit 1; }
    command -v mysql >/dev/null 2>&1 || { red "[오류] mysql 클라이언트를 찾을 수 없습니다."; exit 1; }
}

# 작업 대상(운영/개발)에 따라 접속 전역 변수 설정
set_target() {
    if [[ "$1" == "prod" ]]; then
        DB_HOST=$PROD_DB_HOST; DB_PORT=$PROD_DB_PORT; DB_NAME=$PROD_DB_NAME
        DB_USER=$PROD_DB_USER; DB_PASS=$PROD_DB_PASS
    else
        DB_HOST=$DEV_DB_HOST; DB_PORT=$DEV_DB_PORT; DB_NAME=$DEV_DB_NAME
        DB_USER=$DEV_DB_USER; DB_PASS=$DEV_DB_PASS
    fi
}

run_mysql() {
    MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" -u "$DB_USER" "$@"
}

run_mysqldump() {
    MYSQL_PWD="$DB_PASS" mysqldump --host="$DB_HOST" --port="$DB_PORT" -u "$DB_USER" \
        --single-transaction --routines=false --triggers \
        --default-character-set=utf8mb4 "$DB_NAME"
}

cmd_dump() {
    set_target prod
    color '1;36' "[운영] $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME → 덤프 생성 중..."
    run_mysqldump > "$PROD_DUMP"
    green "[완료] 운영 덤프 생성: $PROD_DUMP ($(du -h "$PROD_DUMP" | cut -f1))"
}

cmd_import() {
    local file="${1:-}"
    [[ -z "$file" ]] && file="$(ls -t "$DUMP_DIR"/db_sync_prod_*.sql 2>/dev/null | head -1 || true)"
    if [[ -z "$file" || ! -f "$file" ]]; then
        red "[오류] 가져올 덤프 파일이 없습니다. 먼저 dump 또는 sync를 실행하세요."
        exit 1
    fi

    set_target dev
    cyan "[개발] 대상: $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
    cyan "[개발] 덤프:  $file"
    read -rp "이대로 진행할까요? (개발 DB는 덮어써지며 백업이 자동 생성됩니다) [y/N] " ans
    [[ "$ans" == "y" || "$ans" == "Y" ]] || { red "취소되었습니다."; exit 1; }

    echo "[개발] 기존 DB 백업 생성 중..."
    run_mysqldump > "$DEV_BACKUP" \
        || { red "[오류] 개발 DB 백업에 실패하여 중단합니다. (교체 미실행)"; exit 1; }
    green "[완료] 개발 백업: $DEV_BACKUP ($(du -h "$DEV_BACKUP" | cut -f1))"

    run_mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; \
        CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || { red "[오류] DB 재생성 실패."; exit 1; }

    echo "[개발] 덤프 import 중..."
    run_mysql "$DB_NAME" < "$file" \
        || { red "[오류] import 실패. 백업($DEV_BACKUP)으로 복구하세요."; exit 1; }

    green "[완료] 개발 DB가 운영 데이터로 교체되었습니다."
}

case "${1:-help}" in
    dump)   check_client; cmd_dump ;;
    import) check_client; cmd_import "${2:-}" ;;
    sync)   check_client; cmd_dump; cmd_import "${2:-}" ;;
    help|-h|--help) usage ;;
    *)      red "알 수 없는 명령: $1"; usage ;;
esac