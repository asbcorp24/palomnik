#!/usr/bin/env bash
set -Eeuo pipefail

# Скрипт копирует себя во временный каталог, чтобы git reset не изменил выполняемый файл.
if [[ "${PALOMNIK_DEPLOY_REEXEC:-0}" != "1" ]]; then
    ORIGINAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    TEMP_SCRIPT="$(mktemp "${TMPDIR:-/tmp}/palomnik-deploy.XXXXXX.sh")"
    cp "${BASH_SOURCE[0]}" "$TEMP_SCRIPT"
    chmod 700 "$TEMP_SCRIPT"
    exec env PALOMNIK_DEPLOY_REEXEC=1 PALOMNIK_PROJECT_ROOT="$ORIGINAL_ROOT" bash "$TEMP_SCRIPT" "$@"
fi

ROOT="${PALOMNIK_PROJECT_ROOT:?Не определён каталог проекта}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
REF="${1:-origin/${DEPLOY_BRANCH}}"
ALLOW_DESTRUCTIVE="${DEPLOY_ALLOW_DESTRUCTIVE_MIGRATIONS:-0}"
HEALTH_URL="${DEPLOY_HEALTH_URL:-}"
DEPLOY_DIR="$ROOT/storage/app/deployments"
LOG_DIR="$ROOT/storage/logs"
LOCK_DIR="$DEPLOY_DIR/.deploy-lock"
DEPLOY_ID="$(date -u +%Y%m%dT%H%M%SZ)-$(printf '%04x' "$((RANDOM % 65536))")"
STARTED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
LOG_FILE="$LOG_DIR/deploy.log"
STAGE_DIR=""
PREVIOUS_COMMIT=""
TARGET_COMMIT=""
BACKUP_NAME=""
PREFLIGHT_JSON='{}'
PENDING_MIGRATIONS='[]'
MIGRATIONS_APPLIED=0
CODE_SWITCHED=0
MAINTENANCE_ENABLED=0
ROLLBACK_ATTEMPTED=0

mkdir -p "$DEPLOY_DIR" "$LOG_DIR"

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    echo "Другой deploy/rollback уже выполняется: $LOCK_DIR" >&2
    exit 1
fi

exec > >(tee -a "$LOG_FILE") 2>&1

echo "[$(date -Is)] DEPLOY $DEPLOY_ID started; ref=$REF"

cleanup() {
    local exit_code=$?
    set +e
    if [[ -n "$STAGE_DIR" && -d "$STAGE_DIR" ]]; then
        git -C "$ROOT" worktree remove --force "$STAGE_DIR" >/dev/null 2>&1 || rm -rf "$STAGE_DIR"
    fi
    rmdir "$LOCK_DIR" >/dev/null 2>&1 || true
    [[ -n "${TEMP_SCRIPT:-}" ]] && rm -f "${TEMP_SCRIPT:-}" >/dev/null 2>&1 || true
    return "$exit_code"
}
trap cleanup EXIT

journal() {
    local status="$1"
    local message="$2"
    local finished_at
    finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    DEPLOY_JOURNAL_PATH="$DEPLOY_DIR" \
    DEPLOY_ID_VALUE="$DEPLOY_ID" \
    DEPLOY_STATUS_VALUE="$status" \
    DEPLOY_MESSAGE_VALUE="$message" \
    DEPLOY_STARTED_VALUE="$STARTED_AT" \
    DEPLOY_FINISHED_VALUE="$finished_at" \
    DEPLOY_REF_VALUE="$REF" \
    DEPLOY_PREVIOUS_VALUE="$PREVIOUS_COMMIT" \
    DEPLOY_TARGET_VALUE="$TARGET_COMMIT" \
    DEPLOY_BACKUP_VALUE="$BACKUP_NAME" \
    DEPLOY_PENDING_VALUE="$PENDING_MIGRATIONS" \
    DEPLOY_MIGRATIONS_APPLIED_VALUE="$MIGRATIONS_APPLIED" \
    DEPLOY_ROLLBACK_ATTEMPTED_VALUE="$ROLLBACK_ATTEMPTED" \
    "$PHP_BIN" -r '
        $dir = getenv("DEPLOY_JOURNAL_PATH");
        if (!is_dir($dir)) { mkdir($dir, 0750, true); }
        $pending = json_decode(getenv("DEPLOY_PENDING_VALUE") ?: "[]", true);
        if (!is_array($pending)) { $pending = []; }
        $record = [
            "id" => getenv("DEPLOY_ID_VALUE"),
            "type" => "deploy",
            "status" => getenv("DEPLOY_STATUS_VALUE"),
            "message" => getenv("DEPLOY_MESSAGE_VALUE"),
            "started_at" => getenv("DEPLOY_STARTED_VALUE"),
            "finished_at" => getenv("DEPLOY_FINISHED_VALUE"),
            "ref" => getenv("DEPLOY_REF_VALUE"),
            "previous_commit" => getenv("DEPLOY_PREVIOUS_VALUE") ?: null,
            "target_commit" => getenv("DEPLOY_TARGET_VALUE") ?: null,
            "backup" => getenv("DEPLOY_BACKUP_VALUE") ?: null,
            "pending_migrations" => $pending,
            "migrations_applied" => getenv("DEPLOY_MIGRATIONS_APPLIED_VALUE") === "1",
            "automatic_code_rollback_attempted" => getenv("DEPLOY_ROLLBACK_ATTEMPTED_VALUE") === "1",
            "operator" => getenv("USER") ?: get_current_user(),
            "hostname" => gethostname(),
        ];
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($dir . DIRECTORY_SEPARATOR . $record["id"] . ".json", $json, LOCK_EX);
        file_put_contents($dir . DIRECTORY_SEPARATOR . "history.ndjson", json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($record["status"] === "success") {
            file_put_contents($dir . DIRECTORY_SEPARATOR . "latest-success.json", $json, LOCK_EX);
        }
    '
}

on_error() {
    local code="$1"
    local line="$2"
    local command="$3"
    trap - ERR
    set +e

    local message="Ошибка на строке $line: $command (код $code)"
    echo "[$(date -Is)] $message" >&2

    if [[ "$CODE_SWITCHED" == "1" && -n "$PREVIOUS_COMMIT" ]]; then
        ROLLBACK_ATTEMPTED=1
        echo "Выполняется автоматический откат кода к $PREVIOUS_COMMIT..."
        git -C "$ROOT" reset --hard "$PREVIOUS_COMMIT"
        (cd "$ROOT" && "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress)
        (cd "$ROOT" && "$PHP_BIN" artisan optimize:clear)
        (cd "$ROOT" && "$PHP_BIN" artisan config:cache)
        (cd "$ROOT" && "$PHP_BIN" artisan route:cache)
        (cd "$ROOT" && "$PHP_BIN" artisan view:cache)
        echo "Код возвращён к предыдущему commit."
    fi

    if [[ "$MAINTENANCE_ENABLED" == "1" ]]; then
        (cd "$ROOT" && "$PHP_BIN" artisan up) || true
    fi

    if [[ "$MIGRATIONS_APPLIED" == "1" && "$PENDING_MIGRATIONS" != "[]" ]]; then
        echo "ВНИМАНИЕ: миграции могли быть применены. База автоматически не восстанавливалась." >&2
        echo "Преддеплойная копия: $BACKUP_NAME" >&2
        echo "Для полного возврата используйте: bash scripts/rollback.sh --restore-db" >&2
    fi

    journal "failed" "$message"
    exit "$code"
}
trap 'on_error $? $LINENO "$BASH_COMMAND"' ERR

cd "$ROOT"

if [[ ! -f .env ]]; then
    echo "Файл .env не найден." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "В рабочем дереве есть изменения отслеживаемых файлов. Deploy остановлен." >&2
    git status --short
    exit 1
fi

PREVIOUS_COMMIT="$(git rev-parse HEAD)"
git fetch --prune origin

if ! git rev-parse --verify "${REF}^{commit}" >/dev/null 2>&1; then
    if git rev-parse --verify "origin/${REF}^{commit}" >/dev/null 2>&1; then
        REF="origin/${REF}"
    else
        echo "Не найден ref: $REF" >&2
        exit 1
    fi
fi

TARGET_COMMIT="$(git rev-parse "${REF}^{commit}")"

if [[ "$TARGET_COMMIT" == "$PREVIOUS_COMMIT" && "${DEPLOY_FORCE:-0}" != "1" ]]; then
    echo "Новых изменений нет: $TARGET_COMMIT"
    journal "success" "Новых изменений нет."
    exit 0
fi

echo "Текущий commit:  $PREVIOUS_COMMIT"
echo "Целевой commit:  $TARGET_COMMIT"

STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/palomnik-preflight.XXXXXX")"
rm -rf "$STAGE_DIR"
git worktree add --detach "$STAGE_DIR" "$TARGET_COMMIT"
cp "$ROOT/.env" "$STAGE_DIR/.env"
chmod 600 "$STAGE_DIR/.env" || true
mkdir -p \
    "$STAGE_DIR/storage/app/public" \
    "$STAGE_DIR/storage/framework/cache/data" \
    "$STAGE_DIR/storage/framework/sessions" \
    "$STAGE_DIR/storage/framework/views" \
    "$STAGE_DIR/storage/logs" \
    "$STAGE_DIR/bootstrap/cache"

(
    cd "$STAGE_DIR"
    "$COMPOSER_BIN" validate --no-interaction
    "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
)

PREFLIGHT_ARGS=(deploy:preflight --strict --json)
if [[ "$ALLOW_DESTRUCTIVE" == "1" ]]; then
    PREFLIGHT_ARGS+=(--allow-destructive)
fi

set +e
PREFLIGHT_OUTPUT="$(cd "$STAGE_DIR" && "$PHP_BIN" artisan "${PREFLIGHT_ARGS[@]}" 2>&1)"
PREFLIGHT_CODE=$?
set -e
echo "$PREFLIGHT_OUTPUT"
PREFLIGHT_JSON="$(printf '%s\n' "$PREFLIGHT_OUTPUT" | tail -n 1)"

if [[ "$PREFLIGHT_CODE" != "0" ]]; then
    echo "Предварительная проверка целевой версии не пройдена." >&2
    exit "$PREFLIGHT_CODE"
fi

PENDING_MIGRATIONS="$(PREFLIGHT_JSON_VALUE="$PREFLIGHT_JSON" "$PHP_BIN" -r '
    $data = json_decode(getenv("PREFLIGHT_JSON_VALUE") ?: "{}", true);
    echo json_encode(is_array($data["pending_migrations"] ?? null) ? $data["pending_migrations"] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
')"

(
    cd "$STAGE_DIR"
    "$PHP_BIN" artisan migrate --pretend --force
)

git worktree remove --force "$STAGE_DIR"
STAGE_DIR=""

BACKUP_NAME="$(cd "$ROOT" && "$PHP_BIN" artisan backup:create --label="predeploy-$(echo "$TARGET_COMMIT" | cut -c1-12)" --no-prune --name-only | tail -n 1)"
if [[ -z "$BACKUP_NAME" ]]; then
    echo "Команда резервного копирования не вернула имя копии." >&2
    exit 1
fi
echo "Преддеплойная резервная копия: $BACKUP_NAME"

"$PHP_BIN" artisan down --retry=60
MAINTENANCE_ENABLED=1

git reset --hard "$TARGET_COMMIT"
CODE_SWITCHED=1

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress

FINAL_PREFLIGHT_ARGS=(deploy:preflight --strict --require-backup)
if [[ "$ALLOW_DESTRUCTIVE" == "1" ]]; then
    FINAL_PREFLIGHT_ARGS+=(--allow-destructive)
fi
"$PHP_BIN" artisan "${FINAL_PREFLIGHT_ARGS[@]}"
"$PHP_BIN" artisan migrate --pretend --force
"$PHP_BIN" artisan migrate --force
MIGRATIONS_APPLIED=1

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan deploy:health

if [[ -n "$HEALTH_URL" ]]; then
    curl --fail --silent --show-error --max-time 20 "$HEALTH_URL" >/dev/null
    echo "HTTP health check пройден: $HEALTH_URL"
fi

"$PHP_BIN" artisan up
MAINTENANCE_ENABLED=0

journal "success" "Развёртывание успешно завершено."
echo "[$(date -Is)] DEPLOY $DEPLOY_ID success"
echo "Для отката к $PREVIOUS_COMMIT: bash scripts/rollback.sh"
