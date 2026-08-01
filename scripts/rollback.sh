#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${PALOMNIK_ROLLBACK_REEXEC:-0}" != "1" ]]; then
    ORIGINAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    TEMP_SCRIPT="$(mktemp "${TMPDIR:-/tmp}/palomnik-rollback.XXXXXX.sh")"
    cp "${BASH_SOURCE[0]}" "$TEMP_SCRIPT"
    chmod 700 "$TEMP_SCRIPT"
    exec env \
        PALOMNIK_ROLLBACK_REEXEC=1 \
        PALOMNIK_PROJECT_ROOT="$ORIGINAL_ROOT" \
        PALOMNIK_TEMP_SCRIPT="$TEMP_SCRIPT" \
        bash "$TEMP_SCRIPT" "$@"
fi

ROOT="${PALOMNIK_PROJECT_ROOT:?Не определён каталог проекта}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
DEPLOY_DIR="$ROOT/storage/app/deployments"
LOG_DIR="$ROOT/storage/logs"
LOCK_DIR="$DEPLOY_DIR/.deploy-lock"
ROLLBACK_ID="rollback-$(date -u +%Y%m%dT%H%M%SZ)-$(printf '%04x' "$((RANDOM % 65536))")"
STARTED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
LOG_FILE="$LOG_DIR/deploy.log"
RESTORE_DB=0
RESTORE_FILES=0
CODE_ONLY=0
DEPLOYMENT_ID=""
TARGET_OVERRIDE=""
BACKUP_OVERRIDE=""
SOURCE_RECORD=""
TARGET_COMMIT=""
CURRENT_COMMIT=""
BACKUP_NAME=""
PENDING_MIGRATIONS='[]'
MAINTENANCE_ENABLED=0
CODE_SWITCHED=0
SAFETY_BACKUP=""

usage() {
    cat <<'EOF'
Использование:
  bash scripts/rollback.sh [параметры]

Параметры:
  --restore-db          Восстановить базу из преддеплойной копии.
  --restore-files       Восстановить storage/app/public.
  --code-only           Явно разрешить откат только кода при наличии миграций.
  --deployment=ID       Использовать конкретную запись storage/app/deployments/ID.json.
  --to=COMMIT           Вернуть код к указанному commit вместо previous_commit.
  --backup=NAME         Использовать указанную резервную копию.
  --help                Показать справку.

Без параметров используется latest-success.json. Если при деплое ожидались миграции,
скрипт потребует --restore-db либо явный --code-only.
EOF
}

for argument in "$@"; do
    case "$argument" in
        --restore-db) RESTORE_DB=1 ;;
        --restore-files) RESTORE_FILES=1 ;;
        --code-only) CODE_ONLY=1 ;;
        --deployment=*) DEPLOYMENT_ID="${argument#*=}" ;;
        --to=*) TARGET_OVERRIDE="${argument#*=}" ;;
        --backup=*) BACKUP_OVERRIDE="${argument#*=}" ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Неизвестный параметр: $argument" >&2; usage; exit 1 ;;
    esac
done

mkdir -p "$DEPLOY_DIR" "$LOG_DIR"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    echo "Другой deploy/rollback уже выполняется: $LOCK_DIR" >&2
    exit 1
fi

exec > >(tee -a "$LOG_FILE") 2>&1

echo "[$(date -Is)] ROLLBACK $ROLLBACK_ID started"

cleanup() {
    local exit_code=$?
    set +e
    rmdir "$LOCK_DIR" >/dev/null 2>&1 || true
    rm -f "${PALOMNIK_TEMP_SCRIPT:-}" >/dev/null 2>&1 || true
    return "$exit_code"
}
trap cleanup EXIT

json_field() {
    local file="$1"
    local field="$2"
    "$PHP_BIN" -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) { exit(2); }
        $value = $data[$argv[2]] ?? null;
        if (is_array($value)) {
            echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($value !== null) {
            echo $value;
        }
    ' "$file" "$field"
}

journal() {
    local status="$1"
    local message="$2"
    local finished_at
    finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    DEPLOY_JOURNAL_PATH="$DEPLOY_DIR" \
    ROLLBACK_ID_VALUE="$ROLLBACK_ID" \
    ROLLBACK_STATUS_VALUE="$status" \
    ROLLBACK_MESSAGE_VALUE="$message" \
    ROLLBACK_STARTED_VALUE="$STARTED_AT" \
    ROLLBACK_FINISHED_VALUE="$finished_at" \
    ROLLBACK_CURRENT_VALUE="$CURRENT_COMMIT" \
    ROLLBACK_TARGET_VALUE="$TARGET_COMMIT" \
    ROLLBACK_SOURCE_RECORD_VALUE="$SOURCE_RECORD" \
    ROLLBACK_BACKUP_VALUE="$BACKUP_NAME" \
    ROLLBACK_SAFETY_BACKUP_VALUE="$SAFETY_BACKUP" \
    ROLLBACK_RESTORE_DB_VALUE="$RESTORE_DB" \
    ROLLBACK_RESTORE_FILES_VALUE="$RESTORE_FILES" \
    ROLLBACK_PENDING_VALUE="$PENDING_MIGRATIONS" \
    "$PHP_BIN" -r '
        $dir = getenv("DEPLOY_JOURNAL_PATH");
        if (!is_dir($dir)) { mkdir($dir, 0750, true); }
        $pending = json_decode(getenv("ROLLBACK_PENDING_VALUE") ?: "[]", true);
        if (!is_array($pending)) { $pending = []; }
        $record = [
            "id" => getenv("ROLLBACK_ID_VALUE"),
            "type" => "rollback",
            "status" => getenv("ROLLBACK_STATUS_VALUE"),
            "message" => getenv("ROLLBACK_MESSAGE_VALUE"),
            "started_at" => getenv("ROLLBACK_STARTED_VALUE"),
            "finished_at" => getenv("ROLLBACK_FINISHED_VALUE"),
            "from_commit" => getenv("ROLLBACK_CURRENT_VALUE") ?: null,
            "target_commit" => getenv("ROLLBACK_TARGET_VALUE") ?: null,
            "source_deployment" => getenv("ROLLBACK_SOURCE_RECORD_VALUE") ?: null,
            "restored_backup" => getenv("ROLLBACK_BACKUP_VALUE") ?: null,
            "safety_backup" => getenv("ROLLBACK_SAFETY_BACKUP_VALUE") ?: null,
            "database_restored" => getenv("ROLLBACK_RESTORE_DB_VALUE") === "1",
            "public_files_restored" => getenv("ROLLBACK_RESTORE_FILES_VALUE") === "1",
            "source_pending_migrations" => $pending,
            "operator" => getenv("USER") ?: get_current_user(),
            "hostname" => gethostname(),
        ];
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($dir . DIRECTORY_SEPARATOR . $record["id"] . ".json", $json, LOCK_EX);
        file_put_contents($dir . DIRECTORY_SEPARATOR . "history.ndjson", json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($record["status"] === "success") {
            file_put_contents($dir . DIRECTORY_SEPARATOR . "latest-rollback.json", $json, LOCK_EX);
        }
    '
}

on_error() {
    local code="$1"
    local line="$2"
    local command="$3"
    trap - ERR
    set +e
    local message="Ошибка rollback на строке $line: $command (код $code)"
    echo "[$(date -Is)] $message" >&2
    echo "Сайт оставлен в режиме обслуживания для безопасной диагностики." >&2
    journal "failed" "$message"
    exit "$code"
}
trap 'on_error $? $LINENO "$BASH_COMMAND"' ERR

cd "$ROOT"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "В рабочем дереве есть изменения отслеживаемых файлов. Rollback остановлен." >&2
    git status --short
    exit 1
fi

CURRENT_COMMIT="$(git rev-parse HEAD)"

if [[ -n "$DEPLOYMENT_ID" ]]; then
    SOURCE_RECORD="$DEPLOY_DIR/$DEPLOYMENT_ID.json"
else
    SOURCE_RECORD="$DEPLOY_DIR/latest-success.json"
fi

if [[ -f "$SOURCE_RECORD" ]]; then
    [[ -z "$TARGET_OVERRIDE" ]] && TARGET_COMMIT="$(json_field "$SOURCE_RECORD" previous_commit)"
    [[ -z "$BACKUP_OVERRIDE" ]] && BACKUP_NAME="$(json_field "$SOURCE_RECORD" backup)"
    PENDING_MIGRATIONS="$(json_field "$SOURCE_RECORD" pending_migrations)"
    [[ -z "$PENDING_MIGRATIONS" ]] && PENDING_MIGRATIONS='[]'
else
    if [[ -z "$TARGET_OVERRIDE" ]]; then
        echo "Не найдена запись успешного деплоя: $SOURCE_RECORD" >&2
        exit 1
    fi
fi

[[ -n "$TARGET_OVERRIDE" ]] && TARGET_COMMIT="$TARGET_OVERRIDE"
[[ -n "$BACKUP_OVERRIDE" ]] && BACKUP_NAME="$BACKUP_OVERRIDE"

if [[ -z "$TARGET_COMMIT" ]]; then
    echo "В журнале не указан previous_commit. Используйте --to=COMMIT." >&2
    exit 1
fi

if [[ "$RESTORE_DB" == "1" || "$RESTORE_FILES" == "1" ]]; then
    if [[ -z "$BACKUP_NAME" ]]; then
        echo "Для восстановления не указана резервная копия. Используйте --backup=NAME." >&2
        exit 1
    fi
fi

PENDING_COUNT="$(PENDING_VALUE="$PENDING_MIGRATIONS" "$PHP_BIN" -r '
    $value = json_decode(getenv("PENDING_VALUE") ?: "[]", true);
    echo is_array($value) ? count($value) : 0;
')"

if [[ "$PENDING_COUNT" -gt 0 && "$RESTORE_DB" != "1" && "$CODE_ONLY" != "1" ]]; then
    echo "В исходном деплое были ожидающие миграции: $PENDING_COUNT." >&2
    echo "Безопасный rollback требует --restore-db либо явного --code-only." >&2
    exit 1
fi

git fetch --prune origin
if ! git cat-file -e "${TARGET_COMMIT}^{commit}" 2>/dev/null; then
    echo "Commit для rollback не найден: $TARGET_COMMIT" >&2
    exit 1
fi
TARGET_COMMIT="$(git rev-parse "${TARGET_COMMIT}^{commit}")"

if [[ "$TARGET_COMMIT" == "$CURRENT_COMMIT" ]]; then
    echo "Код уже находится на commit $TARGET_COMMIT."
    journal "success" "Откат не требовался: целевой commit уже активен."
    exit 0
fi

echo "Текущий commit: $CURRENT_COMMIT"
echo "Возврат к:      $TARGET_COMMIT"
echo "Резервная копия деплоя: ${BACKUP_NAME:-не выбрана}"

SAFETY_BACKUP="$("$PHP_BIN" artisan backup:create --label="pre-rollback-$(echo "$CURRENT_COMMIT" | cut -c1-12)" --no-prune --name-only | tail -n 1)"
echo "Страховочная копия текущего состояния: $SAFETY_BACKUP"

"$PHP_BIN" artisan down --retry=60
MAINTENANCE_ENABLED=1

if [[ "$RESTORE_DB" == "1" || "$RESTORE_FILES" == "1" ]]; then
    RESTORE_ARGS=(backup:restore "$BACKUP_NAME" --force --skip-safety-backup)
    [[ "$RESTORE_DB" == "1" ]] && RESTORE_ARGS+=(--database)
    [[ "$RESTORE_FILES" == "1" ]] && RESTORE_ARGS+=(--files)
    "$PHP_BIN" artisan "${RESTORE_ARGS[@]}"
fi

git reset --hard "$TARGET_COMMIT"
CODE_SWITCHED=1

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

if "$PHP_BIN" artisan list --raw | grep -q '^deploy:health'; then
    "$PHP_BIN" artisan deploy:health
else
    "$PHP_BIN" artisan migrate:status --no-interaction >/dev/null
fi

"$PHP_BIN" artisan up
MAINTENANCE_ENABLED=0

journal "success" "Откат успешно завершён."
echo "[$(date -Is)] ROLLBACK $ROLLBACK_ID success"
echo "Активный commit: $TARGET_COMMIT"
