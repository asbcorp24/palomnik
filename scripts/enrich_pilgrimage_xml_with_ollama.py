#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Консервативное заполнение пустых полей phpMyAdmin XML через локальную Ollama.

Скрипт работает с XML-дампом таблицы pilgrimage_objects вида:

    <database name="...">
      <table name="pilgrimage_objects">
        <column name="id">...</column>
        <column name="name">...</column>
        ...
      </table>
    </database>

Главные правила:
- никогда не перезаписывает непустые значения;
- при сомнении оставляет поле пустым;
- контактные, исторические и режимные данные принимает только с источником;
- не меняет id, slug, тип, адрес, координаты, публикацию и поля верификации;
- создаёт отчёт, checkpoint и резервную копию при перезаписи исходника.

Требования:
    python -m pip install lxml

Ollama:
    ollama serve
    ollama pull OxW/Saiga_YandexGPT_8B:q8_0

Пример:
    python scripts/enrich_pilgrimage_xml_with_ollama.py pilgrimage_objects.xml ^
        --output pilgrimage_objects.enriched.xml ^
        --model OxW/Saiga_YandexGPT_8B:q8_0 ^
        --verify-urls

Сначала проверить XML без обращения к модели:
    python scripts/enrich_pilgrimage_xml_with_ollama.py pilgrimage_objects.xml --validate-only
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    from lxml import etree
except ImportError as exc:
    raise SystemExit(
        "Не установлен пакет lxml.\n"
        "Установите его командой:\n"
        "    python -m pip install lxml"
    ) from exc


DEFAULT_MODEL = "OxW/Saiga_YandexGPT_8B:q8_0"
DEFAULT_OLLAMA_URL = os.environ.get("OLLAMA_HOST", "http://127.0.0.1:11434")

DEFAULT_FIELDS = (
    "short_description",
    "description",
    "history",
    "phone",
    "email",
    "website",
    "schedule_text",
    "parking_info",
    "accessibility_info",
    "information_source_url",
)

TEXT_DERIVABLE_FIELDS = {
    "short_description",
    "description",
}

STRICT_FACT_FIELDS = {
    "history",
    "phone",
    "email",
    "website",
    "schedule_text",
    "parking_info",
    "accessibility_info",
    "information_source_url",
}

NEVER_EDIT_FIELDS = {
    "id",
    "object_type_id",
    "parent_object_id",
    "vicariate_id",
    "deanery_id",
    "name",
    "slug",
    "address",
    "latitude",
    "longitude",
    "information_verified_at",
    "verified_by",
    "next_verification_at",
    "verification_status",
    "is_published",
    "published_at",
    "created_at",
    "updated_at",
    "deleted_at",
}

FIELD_LIMITS = {
    "short_description": 4000,
    "description": 30000,
    "history": 30000,
    "phone": 64,
    "email": 255,
    "website": 255,
    "schedule_text": 10000,
    "parking_info": 10000,
    "accessibility_info": 10000,
    "information_source_url": 1000,
}

BANNED_DOMAINS = {
    "example.org",
    "example.com",
    "example.net",
    "localhost",
}

UNCERTAINTY_RE = re.compile(
    r"\b("
    r"возможно|вероятно|предположительно|по всей видимости|"
    r"скорее всего|обычно|как правило|может быть|"
    r"требует уточнения|информация отсутствует|неизвестно|"
    r"демонстрацион\w*|пример\w*|условн\w*"
    r")\b",
    re.IGNORECASE,
)

DEMO_VALUE_RE = re.compile(
    r"(example\.(?:org|com|net)|"
    r"@palomnik\.local|"
    r"\+?7[\s()\-]*495[\s\-]*0{3}[\s\-]*0{2}[\s\-]*0{2}|"
    r"демонстрацион\w*|"
    r"подготовлен\w*\s+как\s+пример)",
    re.IGNORECASE,
)

EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
PHONE_RE = re.compile(r"^\+?[0-9][0-9\s().\-]{8,63}$")

SYSTEM_PROMPT = r"""
Ты выполняешь крайне консервативное дополнение каталога православных храмов,
монастырей и часовен.

КРИТИЧЕСКОЕ ПРАВИЛО:
Если ты не знаешь точное значение или хотя бы немного сомневаешься, верни null.
Нельзя правдоподобно сочинять, предполагать, обобщать или заполнять шаблонным текстом.

Разрешено:
1. short_description и description:
   - только нейтрально пересказать уже имеющиеся во входе сведения;
   - не добавлять даты, архитектурный стиль, святыни, события и статус,
     которых нет во входе и в которых нет полной уверенности.
2. history:
   - только конкретные известные исторические факты;
   - при отсутствии точного знания вернуть null.
3. phone, email, website, schedule_text, parking_info, accessibility_info:
   - только точные сведения, в которых ты уверен;
   - обязательно укажи source_url конкретной официальной страницы;
   - если точной официальной страницы не знаешь, value должен быть null.
4. information_source_url:
   - только точный URL официального сайта объекта, епархии или монастыря;
   - не использовать поисковики, карты, каталоги, соцсети и придуманные URL.

Запрещено:
- выдумывать телефон, email, URL, расписание, историю и условия доступности;
- писать "обычно открыт", "как правило", "вероятно", "нужно уточнить";
- использовать example.org, localhost, тестовые телефоны или .local;
- заменять существующие непустые значения;
- заполнять поля, которых нет в requested_fields.

Для каждого поля верни:
- value: строка или null;
- confidence: число 0..1;
- basis:
  - "known" — точное известное сведение;
  - "derived_from_input" — только переформулировка входных данных;
  - "unknown" — сведений недостаточно;
- source_url: точный источник или null;
- reason: короткое объяснение.

Для фактических полей history, phone, email, website, schedule_text,
parking_info, accessibility_info и information_source_url без source_url
возвращай null.

Ответ строго в JSON без Markdown:
{
  "records": [
    {
      "key": "ключ входной записи",
      "fields": {
        "short_description": {
          "value": null,
          "confidence": 0.0,
          "basis": "unknown",
          "source_url": null,
          "reason": "нет точных сведений"
        }
      }
    }
  ]
}
Для каждого входного key верни ровно один объект.
""".strip()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Консервативно заполнить пустые поля phpMyAdmin XML через Ollama."
    )
    parser.add_argument("input", help="Исходный phpMyAdmin XML.")
    parser.add_argument(
        "--output",
        help="Итоговый XML. По умолчанию: <input>.enriched.xml",
    )
    parser.add_argument("--model", default=DEFAULT_MODEL)
    parser.add_argument("--ollama-url", default=DEFAULT_OLLAMA_URL)
    parser.add_argument(
        "--fields",
        default=",".join(DEFAULT_FIELDS),
        help="Список разрешённых полей через запятую.",
    )
    parser.add_argument("--batch-size", type=int, default=2)
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--max-retries", type=int, default=3)
    parser.add_argument("--timeout", type=int, default=300)
    parser.add_argument("--pause", type=float, default=0.3)
    parser.add_argument(
        "--min-confidence",
        type=float,
        default=0.92,
        help="Минимальная уверенность для описательных полей.",
    )
    parser.add_argument(
        "--strict-confidence",
        type=float,
        default=0.97,
        help="Минимальная уверенность для истории, контактов, расписания и URL.",
    )
    parser.add_argument(
        "--verify-urls",
        action="store_true",
        help="Проверять доступность source_url и URL-полей через HTTP.",
    )
    parser.add_argument(
        "--replace-demo-placeholders",
        action="store_true",
        help="Считать явные demo/example значения пустыми.",
    )
    parser.add_argument(
        "--overwrite-input",
        action="store_true",
        help="Перезаписать исходный XML с резервной копией.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Обратиться к Ollama и сформировать отчёт, но не записывать XML.",
    )
    parser.add_argument(
        "--validate-only",
        action="store_true",
        help="Только проверить структуру XML, без Ollama.",
    )
    parser.add_argument(
        "--reset-checkpoint",
        action="store_true",
        help="Удалить старый checkpoint и обработать записи заново.",
    )
    return parser.parse_args()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def normalize_ollama_url(value: str) -> str:
    value = value.strip().rstrip("/")
    if not value:
        return "http://127.0.0.1:11434"
    if not re.match(r"^https?://", value, re.IGNORECASE):
        value = "http://" + value
    return value


def is_blank(value: str | None) -> bool:
    return value is None or value.strip() == "" or value.strip().upper() == "NULL"


def is_demo_placeholder(value: str | None) -> bool:
    return bool(value and DEMO_VALUE_RE.search(value))


def clean_text(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def clip_text(value: str, limit: int) -> str:
    value = clean_text(value)
    if len(value) <= limit:
        return value
    return value[:limit].rsplit(" ", 1)[0].rstrip(" ,.;:-")


def safe_float(value: Any) -> float:
    try:
        return max(0.0, min(1.0, float(value)))
    except (TypeError, ValueError):
        return 0.0


def normalize_url(value: Any) -> str | None:
    text = clean_text(value)
    if not text:
        return None
    if not re.match(r"^https?://", text, re.IGNORECASE):
        text = "https://" + text
    try:
        parsed = urllib.parse.urlparse(text)
    except ValueError:
        return None
    host = (parsed.hostname or "").lower()
    if not host or "." not in host or host in BANNED_DOMAINS or host.endswith(".local"):
        return None
    if parsed.scheme not in {"http", "https"}:
        return None
    return text


def url_is_reachable(url: str, timeout: int = 12) -> bool:
    headers = {
        "User-Agent": "PalomnikDataVerifier/1.0",
        "Accept": "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.1",
    }
    for method in ("HEAD", "GET"):
        request = urllib.request.Request(url, headers=headers, method=method)
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                return 200 <= int(response.status) < 400
        except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError):
            continue
    return False


def parse_xml(path: Path) -> etree._ElementTree:
    parser = etree.XMLParser(
        remove_blank_text=False,
        recover=False,
        resolve_entities=False,
        no_network=True,
        huge_tree=True,
    )
    return etree.parse(str(path), parser)


def table_rows(tree: etree._ElementTree) -> list[etree._Element]:
    return tree.xpath(".//database/table[@name='pilgrimage_objects']")


def column_map(row: etree._Element) -> dict[str, etree._Element]:
    return {
        str(column.get("name")): column
        for column in row.findall("column")
        if column.get("name")
    }


def row_values(row: etree._Element) -> dict[str, str | None]:
    return {
        name: column.text
        for name, column in column_map(row).items()
    }


def record_key(values: dict[str, str | None], index: int) -> str:
    return clean_text(values.get("id") or values.get("slug")) or f"row:{index}"


def fingerprint(values: dict[str, str | None], fields: set[str]) -> str:
    stable = {
        key: values.get(key)
        for key in sorted(
            {
                "id",
                "object_type_id",
                "parent_object_id",
                "name",
                "slug",
                "address",
                "latitude",
                "longitude",
                *fields,
            }
        )
    }
    raw = json.dumps(stable, ensure_ascii=False, sort_keys=True)
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


def selected_fields(raw: str) -> set[str]:
    fields = {item.strip() for item in raw.split(",") if item.strip()}
    unknown = fields - set(DEFAULT_FIELDS)
    if unknown:
        raise SystemExit(
            "Неподдерживаемые поля: " + ", ".join(sorted(unknown))
        )
    forbidden = fields & NEVER_EDIT_FIELDS
    if forbidden:
        raise SystemExit(
            "Эти поля запрещено изменять: " + ", ".join(sorted(forbidden))
        )
    return fields


def missing_fields(
    values: dict[str, str | None],
    allowed_fields: set[str],
    replace_demo: bool,
) -> list[str]:
    result: list[str] = []
    for field in sorted(allowed_fields):
        if field not in values:
            continue
        value = values.get(field)
        if is_blank(value) or (replace_demo and is_demo_placeholder(value)):
            result.append(field)
    return result


def model_record(
    values: dict[str, str | None],
    key: str,
    fields: list[str],
) -> dict[str, Any]:
    context_fields = (
        "id",
        "object_type_id",
        "parent_object_id",
        "name",
        "slug",
        "address",
        "latitude",
        "longitude",
        "phone",
        "email",
        "website",
        "schedule_text",
        "short_description",
        "description",
        "history",
        "parking_info",
        "accessibility_info",
        "information_source_url",
    )
    return {
        "key": key,
        "requested_fields": fields,
        "record": {field: values.get(field) for field in context_fields},
    }


def ollama_chat(
    ollama_url: str,
    model: str,
    records: list[dict[str, Any]],
    timeout: int,
    max_retries: int,
) -> dict[str, Any]:
    endpoint = normalize_ollama_url(ollama_url) + "/api/chat"
    payload = {
        "model": model,
        "stream": False,
        "format": "json",
        "messages": [
            {"role": "system", "content": SYSTEM_PROMPT},
            {
                "role": "user",
                "content": json.dumps(
                    {"records": records},
                    ensure_ascii=False,
                    indent=2,
                ),
            },
        ],
        "options": {
            "temperature": 0,
            "seed": 42,
            "num_ctx": 8192,
        },
    }
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    request = urllib.request.Request(
        endpoint,
        data=body,
        headers={
            "Content-Type": "application/json; charset=utf-8",
            "Accept": "application/json",
        },
        method="POST",
    )

    last_error: Exception | None = None
    for attempt in range(1, max_retries + 1):
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                envelope = json.loads(response.read().decode("utf-8"))
            content = envelope.get("message", {}).get("content")
            if not isinstance(content, str) or not content.strip():
                raise ValueError("Ollama не вернула message.content")
            parsed = json.loads(content)
            if not isinstance(parsed, dict):
                raise ValueError("Ответ модели не является JSON-объектом")
            return parsed
        except (
            urllib.error.URLError,
            urllib.error.HTTPError,
            TimeoutError,
            json.JSONDecodeError,
            ValueError,
        ) as exc:
            last_error = exc
            if attempt >= max_retries:
                break
            time.sleep(min(10.0, 1.5 * attempt))

    raise RuntimeError(f"Ошибка Ollama после {max_retries} попыток: {last_error}")


def decision_by_key(response: dict[str, Any]) -> dict[str, dict[str, Any]]:
    records = response.get("records")
    if not isinstance(records, list):
        raise ValueError("В ответе Ollama отсутствует массив records")
    result: dict[str, dict[str, Any]] = {}
    for item in records:
        if not isinstance(item, dict):
            continue
        key = clean_text(item.get("key"))
        fields = item.get("fields")
        if key and isinstance(fields, dict) and key not in result:
            result[key] = fields
    return result


def validate_candidate(
    field: str,
    candidate: Any,
    existing_values: dict[str, str | None],
    min_confidence: float,
    strict_confidence: float,
    verify_urls: bool,
) -> tuple[str | None, str]:
    if not isinstance(candidate, dict):
        return None, "ответ поля не является объектом"

    value = candidate.get("value")
    if value is None:
        return None, clean_text(candidate.get("reason")) or "модель вернула null"

    value_text = clean_text(value)
    if not value_text:
        return None, "пустое значение"
    if UNCERTAINTY_RE.search(value_text):
        return None, "значение содержит предположение или шаблонную фразу"
    if is_demo_placeholder(value_text):
        return None, "значение похоже на демонстрационные данные"

    confidence = safe_float(candidate.get("confidence"))
    basis = clean_text(candidate.get("basis")).lower()
    source_url = normalize_url(candidate.get("source_url"))

    if field in TEXT_DERIVABLE_FIELDS:
        if confidence < min_confidence:
            return None, f"уверенность {confidence:.2f} ниже {min_confidence:.2f}"
        if basis not in {"known", "derived_from_input"}:
            return None, f"недопустимое основание: {basis or 'не указано'}"
    else:
        if confidence < strict_confidence:
            return None, (
                f"уверенность {confidence:.2f} ниже строгого порога "
                f"{strict_confidence:.2f}"
            )
        if basis != "known":
            return None, "фактическое поле допускается только с basis=known"

        record_source = normalize_url(existing_values.get("information_source_url"))
        effective_source = source_url or record_source

        if field == "information_source_url":
            effective_source = normalize_url(value_text)
            if source_url and effective_source != source_url:
                return None, "source_url не совпадает с предлагаемым URL"

        if effective_source is None:
            return None, "для фактического поля не указан официальный источник"
        if verify_urls and not url_is_reachable(effective_source):
            return None, "официальный источник недоступен по HTTP"

    limit = FIELD_LIMITS[field]
    value_text = clip_text(value_text, limit)

    if field == "phone":
        if not PHONE_RE.match(value_text):
            return None, "телефон имеет некорректный формат"
        digits = re.sub(r"\D+", "", value_text)
        if len(digits) < 10 or len(set(digits[-7:])) <= 2:
            return None, "телефон выглядит тестовым или неполным"

    elif field == "email":
        if not EMAIL_RE.match(value_text):
            return None, "email имеет некорректный формат"
        if value_text.lower().endswith(".local"):
            return None, "локальный email запрещён"

    elif field in {"website", "information_source_url"}:
        normalized = normalize_url(value_text)
        if normalized is None:
            return None, "URL некорректен или относится к тестовому домену"
        if verify_urls and not url_is_reachable(normalized):
            return None, "URL недоступен по HTTP"
        value_text = normalized

    return value_text, "принято"


def load_checkpoint(path: Path, reset: bool) -> dict[str, Any]:
    if reset and path.exists():
        path.unlink()
    if not path.exists():
        return {"version": 1, "records": {}}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {"version": 1, "records": {}}
    if not isinstance(data, dict) or not isinstance(data.get("records"), dict):
        return {"version": 1, "records": {}}
    return data


def save_json_atomic(path: Path, data: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(
        json.dumps(data, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    temporary.replace(path)


def write_xml_atomic(
    tree: etree._ElementTree,
    output: Path,
    source: Path,
    overwrite_input: bool,
) -> Path | None:
    output.parent.mkdir(parents=True, exist_ok=True)
    backup: Path | None = None

    same_file = output.resolve() == source.resolve()
    if same_file and not overwrite_input:
        raise RuntimeError(
            "Выходной файл совпадает с исходным. "
            "Добавьте --overwrite-input или укажите другой --output."
        )

    if same_file:
        stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
        backup = source.with_suffix(source.suffix + f".{stamp}.bak")
        shutil.copy2(source, backup)

    temporary = output.with_suffix(output.suffix + ".tmp")
    tree.write(
        str(temporary),
        encoding="utf-8",
        xml_declaration=True,
        pretty_print=False,
    )
    temporary.replace(output)
    return backup


def apply_values_to_row(
    row: etree._Element,
    accepted: dict[str, str],
    replace_demo: bool,
) -> list[str]:
    columns = column_map(row)
    changed: list[str] = []
    for field, value in accepted.items():
        column = columns.get(field)
        if column is None:
            continue
        current = column.text
        if not is_blank(current) and not (
            replace_demo and is_demo_placeholder(current)
        ):
            continue
        column.text = value
        changed.append(field)
    return changed


def main() -> int:
    args = parse_args()
    source = Path(args.input).expanduser().resolve()
    if not source.is_file():
        print(f"XML не найден: {source}", file=sys.stderr)
        return 2

    if args.overwrite_input:
        output = source
    elif args.output:
        output = Path(args.output).expanduser().resolve()
    else:
        output = source.with_name(source.stem + ".enriched.xml")

    report_path = output.with_suffix(output.suffix + ".report.json")
    checkpoint_path = output.with_suffix(output.suffix + ".checkpoint.json")

    fields = selected_fields(args.fields)
    tree = parse_xml(source)
    rows = table_rows(tree)

    if not rows:
        print(
            "В XML не найдены строки "
            "<database><table name=\"pilgrimage_objects\">.",
            file=sys.stderr,
        )
        return 3

    rows_with_missing = 0
    missing_counter: dict[str, int] = {field: 0 for field in sorted(fields)}
    for row in rows:
        values = row_values(row)
        missing = missing_fields(
            values,
            fields,
            args.replace_demo_placeholders,
        )
        if missing:
            rows_with_missing += 1
            for field in missing:
                missing_counter[field] += 1

    print(f"XML: {source}")
    print(f"Строк pilgrimage_objects: {len(rows)}")
    print(f"Строк с разрешёнными пустыми полями: {rows_with_missing}")
    print(
        "Пустые поля: "
        + ", ".join(
            f"{field}={count}"
            for field, count in missing_counter.items()
            if count
        )
    )

    if args.validate_only:
        print("Структура XML корректна. Ollama не запускалась.")
        return 0

    checkpoint = load_checkpoint(checkpoint_path, args.reset_checkpoint)
    checkpoint_records: dict[str, Any] = checkpoint["records"]
    report_records: list[dict[str, Any]] = []

    pending: list[tuple[int, etree._Element, dict[str, str | None], str, list[str]]] = []
    reused = 0
    applied_total = 0

    for index, row in enumerate(rows):
        values = row_values(row)
        missing = missing_fields(
            values,
            fields,
            args.replace_demo_placeholders,
        )
        if not missing:
            continue

        key = record_key(values, index)
        row_fingerprint = fingerprint(values, fields)
        cached = checkpoint_records.get(key)

        if (
            isinstance(cached, dict)
            and cached.get("fingerprint") == row_fingerprint
            and isinstance(cached.get("accepted"), dict)
        ):
            changed = apply_values_to_row(
                row,
                {
                    str(field): str(value)
                    for field, value in cached["accepted"].items()
                    if value is not None
                },
                args.replace_demo_placeholders,
            )
            reused += 1
            applied_total += len(changed)
            report_records.append(cached)
            continue

        pending.append((index, row, values, key, missing))

    if args.limit > 0:
        pending = pending[: args.limit]

    print(f"Нужно запросить у Ollama: {len(pending)}")
    print(f"Повторно применено из checkpoint: {reused}")

    batch_size = max(1, min(10, int(args.batch_size)))

    for batch_start in range(0, len(pending), batch_size):
        batch = pending[batch_start : batch_start + batch_size]
        payload = [
            model_record(values, key, missing)
            for _, _, values, key, missing in batch
        ]

        print(
            f"Ollama: {batch_start + 1}-"
            f"{batch_start + len(batch)} из {len(pending)}",
            flush=True,
        )

        try:
            response = ollama_chat(
                args.ollama_url,
                args.model,
                payload,
                args.timeout,
                args.max_retries,
            )
            decisions = decision_by_key(response)
        except Exception as exc:
            print(f"  Ошибка пакета: {exc}", file=sys.stderr)
            decisions = {}

        for index, row, values, key, missing in batch:
            field_decisions = decisions.get(key, {})
            accepted: dict[str, str] = {}
            rejected: dict[str, str] = {}

            for field in missing:
                candidate = (
                    field_decisions.get(field)
                    if isinstance(field_decisions, dict)
                    else None
                )
                accepted_value, reason = validate_candidate(
                    field,
                    candidate,
                    values,
                    args.min_confidence,
                    args.strict_confidence,
                    args.verify_urls,
                )
                if accepted_value is None:
                    rejected[field] = reason
                else:
                    accepted[field] = accepted_value

            changed = apply_values_to_row(
                row,
                accepted,
                args.replace_demo_placeholders,
            )
            applied_total += len(changed)

            entry = {
                "key": key,
                "row_index": index,
                "name": values.get("name"),
                "slug": values.get("slug"),
                "fingerprint": fingerprint(values, fields),
                "requested_fields": missing,
                "accepted": accepted,
                "changed_fields": changed,
                "rejected": rejected,
                "processed_at": now_iso(),
                "model": args.model,
            }
            checkpoint_records[key] = entry
            report_records.append(entry)

            print(
                f"  {key}: принято {len(changed)}, "
                f"отклонено {len(rejected)}"
            )

        checkpoint["updated_at"] = now_iso()
        checkpoint["model"] = args.model
        save_json_atomic(checkpoint_path, checkpoint)

        if args.pause > 0:
            time.sleep(args.pause)

    report = {
        "generated_at": now_iso(),
        "input": str(source),
        "output": str(output),
        "model": args.model,
        "dry_run": bool(args.dry_run),
        "verify_urls": bool(args.verify_urls),
        "replace_demo_placeholders": bool(args.replace_demo_placeholders),
        "rows_total": len(rows),
        "rows_with_missing": rows_with_missing,
        "records_requested": len(pending),
        "records_reused_from_checkpoint": reused,
        "fields_applied": applied_total,
        "records": report_records,
    }
    save_json_atomic(report_path, report)

    if args.dry_run:
        print("Dry-run: XML не записан.")
        print(f"Отчёт: {report_path}")
        print(f"Checkpoint: {checkpoint_path}")
        return 0

    backup = write_xml_atomic(
        tree,
        output,
        source,
        args.overwrite_input,
    )

    print(f"Готово. Заполнено полей: {applied_total}")
    print(f"XML: {output}")
    print(f"Отчёт: {report_path}")
    print(f"Checkpoint: {checkpoint_path}")
    if backup:
        print(f"Резервная копия: {backup}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
