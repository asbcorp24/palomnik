#!/usr/bin/env python3
"""Проверка и дополнение JSON паломнических объектов через локальную Ollama.

Скрипт:
- читает database/seeders/data/moscow-region-orthodox-places.json;
- локальными правилами удаляет очевидные лавки и служебные здания;
- пакетами отправляет остальные записи в Ollama;
- оставляет только храмы, монастыри, часовни и святые источники;
- исправляет тип и добавляет безопасные описания;
- не выдумывает адреса, телефоны, сайты, расписания и исторические факты;
- создаёт резервную копию, checkpoint и подробный отчёт.

Требования:
    pip install ollama
    ollama pull OxW/Saiga_YandexGPT_8B:q8_0

Запуск:
    python scripts/review_churches_json_with_ollama.py
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
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    import ollama
except ImportError as exc:
    raise SystemExit(
        "Не установлен пакет ollama. Выполните:\n"
        "C:\\Python311\\python.exe -m pip install ollama"
    ) from exc


DEFAULT_MODEL = "OxW/Saiga_YandexGPT_8B:q8_0"
DEFAULT_INPUT = "database/seeders/data/moscow-region-orthodox-places.json"
DEFAULT_PROGRESS = "storage/app/ollama-church-review-progress.json"
DEFAULT_REPORT = "storage/app/ollama-church-review-report.json"

ALLOWED_TYPES = {"temple", "monastery", "chapel", "holy-spring"}

MAIN_NAME_RE = re.compile(
    r"(храм|церков|собор|монастыр|лавр|пустын|подвор|часовн|"
    r"свят\w*\s+(источник|родник|ключ)|купел|"
    r"church|cathedral|monastery|chapel|holy\s+spring)",
    re.IGNORECASE,
)

LOCAL_REMOVE_RULES: tuple[tuple[str, re.Pattern[str]], ...] = (
    (
        "церковная или иконная лавка",
        re.compile(
            r"(церковн\w*\s+лавк|иконн\w*\s+лавк|"
            r"православн\w*\s+магазин|магазин\s+церковн|"
            r"церковн\w*\s+товар)",
            re.IGNORECASE,
        ),
    ),
    (
        "торговый объект",
        re.compile(r"^\s*(лавка|магазин|киоск|павильон)\b", re.IGNORECASE),
    ),
    (
        "придел, а не самостоятельный храм",
        re.compile(r"^\s*(придел|предел)\b", re.IGNORECASE),
    ),
    (
        "служебное церковное здание",
        re.compile(
            r"^\s*(трапезн|просфорн|крестильн|"
            r"воскресн\w*\s+школ|приходск\w*\s+дом|"
            r"дом\s+причта|канцеляр|администрац)",
            re.IGNORECASE,
        ),
    ),
    (
        "колокольня или звонница",
        re.compile(r"^\s*(колокольн|звонниц)", re.IGNORECASE),
    ),
)

AUXILIARY_RE = re.compile(
    r"(трапезн|просфорн|крестильн|воскресн\w*\s+школ|"
    r"приходск\w*\s+дом|дом\s+причта|канцеляр|администрац|"
    r"склад|котельн|гараж|сторожк)",
    re.IGNORECASE,
)

NON_TARGET_RE = re.compile(
    r"(кладбищ|памятник|поклонн\w*\s+крест|ворота|ограда|стена|"
    r"библиотек|музей|гимназ|семинар|общежит|"
    r"мечет|синагог|кост[её]л|кирх|дацан|буддий)",
    re.IGNORECASE,
)

SYSTEM_PROMPT = """
Ты проверяешь каталог православных паломнических объектов Москвы и Московской области.

ОСТАВЛЯЙ ТОЛЬКО:
- temple: самостоятельный православный храм, церковь или собор;
- monastery: православный монастырь, лавра, пустынь или подворье;
- chapel: самостоятельная православная часовня;
- holy-spring: святой источник, святой родник, святой ключ или купель.

ОБЯЗАТЕЛЬНО УДАЛЯЙ:
- церковные и иконные лавки, магазины и киоски;
- приделы внутри другого храма;
- колокольни, звонницы, ворота, стены и ограды;
- трапезные, просфорные, крестильни;
- воскресные школы, приходские дома, дома причта;
- административные и хозяйственные здания;
- кладбища, памятники и поклонные кресты;
- кафе, гостиницы, парковки и обычные коммерческие объекты;
- неправославные религиозные объекты;
- обычные родники, если из названия не видно, что источник святой.

ПРАВИЛА ДОПОЛНЕНИЯ:
- clean_name: только исправление пробелов, регистра, кавычек и пунктуации;
- short_description: одно нейтральное предложение;
- description: 2–3 нейтральных предложения только по названию, типу и адресу;
- history: если во входе истории нет, всегда null;
- не придумывай даты, архитектуру, святыни, мощи, чудеса, историю,
  расписание, телефон, сайт или адрес;
- confidence: уверенность от 0 до 1;
- reason: короткая причина решения.

Верни строго JSON:
{
  "results": [
    {
      "key": "ключ из входа",
      "keep": true,
      "type": "temple",
      "clean_name": "Название",
      "short_description": "Короткий текст",
      "description": "Описание",
      "history": null,
      "reason": "причина",
      "confidence": 0.95
    }
  ]
}

Верни решение для каждого key ровно один раз.
""".strip()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Проверить JSON храмов локальной моделью Ollama."
    )
    parser.add_argument("--input", default=DEFAULT_INPUT)
    parser.add_argument(
        "--output",
        default=None,
        help="По умолчанию исходный JSON перезаписывается с резервной копией.",
    )
    parser.add_argument("--model", default=DEFAULT_MODEL)
    parser.add_argument("--host", default=os.environ.get("OLLAMA_HOST", ""))
    parser.add_argument("--batch-size", type=int, default=8)
    parser.add_argument("--max-retries", type=int, default=3)
    parser.add_argument("--pause", type=float, default=0.7)
    parser.add_argument("--delete-confidence", type=float, default=0.78)
    parser.add_argument("--type-confidence", type=float, default=0.68)
    parser.add_argument("--progress", default=DEFAULT_PROGRESS)
    parser.add_argument("--report", default=DEFAULT_REPORT)
    parser.add_argument("--reset-progress", action="store_true")
    parser.add_argument(
        "--limit",
        type=int,
        default=0,
        help="Для теста проверить только первые N ещё не проверенных записей.",
    )
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--strip-review-metadata", action="store_true")
    return parser.parse_args()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def clean_text(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def optional_text(value: Any, length: int) -> str | None:
    value = clean_text(value)
    return value[:length].rstrip() if value else None


def confidence(value: Any) -> float:
    try:
        return max(0.0, min(1.0, float(value)))
    except (TypeError, ValueError):
        return 0.0


def record_key(item: dict[str, Any], index: int) -> str:
    return clean_text(item.get("source_id") or item.get("slug")) or f"index:{index}"


def fingerprint(item: dict[str, Any]) -> str:
    fields = {
        key: item.get(key)
        for key in (
            "source_id", "slug", "type", "name", "address", "region",
            "latitude", "longitude", "phone", "email", "website",
            "schedule_text", "short_description", "description", "history",
        )
    }
    raw = json.dumps(fields, ensure_ascii=False, sort_keys=True)
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


def local_rejection(item: dict[str, Any]) -> str | None:
    name = clean_text(item.get("name"))
    if not name:
        return "отсутствует название"

    try:
        latitude = float(item.get("latitude"))
        longitude = float(item.get("longitude"))
    except (TypeError, ValueError):
        return "некорректные координаты"

    if not (-90 <= latitude <= 90 and -180 <= longitude <= 180):
        return "координаты вне допустимого диапазона"
    if latitude == 0.0 and longitude == 0.0:
        return "нулевые координаты"

    for reason, pattern in LOCAL_REMOVE_RULES:
        if pattern.search(name):
            return reason

    if AUXILIARY_RE.search(name) and not MAIN_NAME_RE.search(name):
        return "вспомогательное или служебное здание"
    if NON_TARGET_RE.search(name) and not MAIN_NAME_RE.search(name):
        return "не является самостоятельным паломническим объектом"
    return None


def model_payload(item: dict[str, Any], key: str) -> dict[str, Any]:
    return {
        "key": key,
        "name": item.get("name"),
        "type": item.get("type"),
        "region": item.get("region"),
        "address": item.get("address"),
        "source_url": item.get("source_url"),
        "phone_present": bool(clean_text(item.get("phone"))),
        "email_present": bool(clean_text(item.get("email"))),
        "website_present": bool(clean_text(item.get("website"))),
        "schedule_present": bool(clean_text(item.get("schedule_text"))),
        "short_description": item.get("short_description"),
        "description": item.get("description"),
        "history": item.get("history"),
    }


def response_text(response: Any) -> str:
    if isinstance(response, dict):
        message = response.get("message") or {}
        if isinstance(message, dict):
            return str(message.get("content") or "")
    message = getattr(response, "message", None)
    if message is not None:
        return str(getattr(message, "content", "") or "")
    raise ValueError("В ответе Ollama отсутствует message.content")


def parse_model_json(text: str) -> dict[str, Any]:
    text = text.strip()
    try:
        result = json.loads(text)
    except json.JSONDecodeError:
        start = text.find("{")
        end = text.rfind("}")
        if start < 0 or end <= start:
            raise ValueError("Модель не вернула JSON")
        result = json.loads(text[start:end + 1])
    if not isinstance(result, dict):
        raise ValueError("Ответ модели должен быть JSON-объектом")
    return result


def normalize_decision(row: dict[str, Any]) -> dict[str, Any]:
    object_type = clean_text(row.get("type"))
    if object_type not in ALLOWED_TYPES:
        object_type = None
    return {
        "keep": row.get("keep") is True,
        "type": object_type,
        "clean_name": optional_text(row.get("clean_name"), 255),
        "short_description": optional_text(row.get("short_description"), 1000),
        "description": optional_text(row.get("description"), 5000),
        "history": optional_text(row.get("history"), 10000),
        "reason": optional_text(row.get("reason"), 500) or "причина не указана",
        "confidence": confidence(row.get("confidence")),
        "source": "ollama",
    }


def call_model(
    client: Any,
    model: str,
    batch: list[dict[str, Any]],
    retries: int,
) -> dict[str, dict[str, Any]]:
    expected = {str(item["key"]) for item in batch}
    prompt = (
        "Проверь записи. Не используй внешние сведения и не додумывай факты.\n\n"
        + json.dumps({"objects": batch}, ensure_ascii=False, indent=2)
    )
    last_error: Exception | None = None

    for attempt in range(1, retries + 1):
        try:
            print(f"    Ollama: попытка {attempt}/{retries}", flush=True)
            params = {
                "model": model,
                "messages": [
                    {"role": "system", "content": SYSTEM_PROMPT},
                    {"role": "user", "content": prompt},
                ],
                "options": {"temperature": 0.1, "num_ctx": 8192},
                "stream": False,
            }
            try:
                response = client.chat(format="json", **params)
            except TypeError:
                response = client.chat(**params)

            decoded = parse_model_json(response_text(response))
            rows = decoded.get("results")
            if not isinstance(rows, list):
                raise ValueError("Нет массива results")

            by_key: dict[str, dict[str, Any]] = {}
            for row in rows:
                if not isinstance(row, dict):
                    continue
                key = str(row.get("key") or "")
                if key in expected and key not in by_key:
                    by_key[key] = normalize_decision(row)

            missing = expected - set(by_key)
            if missing:
                raise ValueError("Нет решений для: " + ", ".join(sorted(missing)[:5]))
            return by_key
        except Exception as exc:
            last_error = exc
            print(f"    Ошибка Ollama: {exc}", file=sys.stderr, flush=True)
            if attempt < retries:
                time.sleep(attempt * 2)

    raise RuntimeError(f"Не удалось получить корректный ответ: {last_error}")


def read_json(path: Path) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise SystemExit(f"Не найден файл: {path}") from exc
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Некорректный JSON: {exc}") from exc

    if isinstance(value, list):
        return {"meta": {}}, [item for item in value if isinstance(item, dict)]
    if not isinstance(value, dict) or not isinstance(value.get("objects"), list):
        raise SystemExit("В JSON отсутствует массив objects")
    return value, [item for item in value["objects"] if isinstance(item, dict)]


def atomic_write(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(
        json.dumps(value, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    temporary.replace(path)


def load_progress(path: Path, model: str) -> dict[str, Any]:
    if path.is_file():
        try:
            value = json.loads(path.read_text(encoding="utf-8"))
            if isinstance(value, dict) and isinstance(value.get("decisions"), dict):
                return value
        except (OSError, json.JSONDecodeError):
            pass
    return {"version": 1, "model": model, "updated_at": now_iso(), "decisions": {}}


def save_progress(path: Path, progress: dict[str, Any]) -> None:
    progress["updated_at"] = now_iso()
    atomic_write(path, progress)


def split_batches(values: list[Any], size: int) -> list[list[Any]]:
    return [values[index:index + size] for index in range(0, len(values), size)]


def count_types(items: list[dict[str, Any]]) -> Counter[str]:
    return Counter(clean_text(item.get("type")) or "(пусто)" for item in items)


def count_missing(items: list[dict[str, Any]]) -> Counter[str]:
    result: Counter[str] = Counter()
    for item in items:
        for field in (
            "address", "phone", "email", "website", "schedule_text",
            "short_description", "description", "history",
        ):
            if not clean_text(item.get(field)):
                result[field] += 1
    return result


def print_counter(title: str, values: Counter[str]) -> None:
    print(title)
    for key, value in values.most_common():
        print(f"  {key}: {value}")


def apply_decision(
    item: dict[str, Any],
    decision: dict[str, Any],
    delete_threshold: float,
    type_threshold: float,
    strip_metadata: bool,
) -> tuple[dict[str, Any] | None, dict[str, Any]]:
    score = confidence(decision.get("confidence"))
    source = clean_text(decision.get("source")) or "ollama"
    keep = decision.get("keep") is True
    audit = {
        "source": source,
        "confidence": score,
        "reason": optional_text(decision.get("reason"), 500),
        "reviewed_at": now_iso(),
        "removed": False,
        "type_changed": False,
        "name_changed": False,
        "description_added": False,
        "uncertain_removal_kept": False,
    }

    if not keep:
        if source == "local-rule" or score >= delete_threshold:
            audit["removed"] = True
            return None, audit
        audit["uncertain_removal_kept"] = True
        result = dict(item)
        if not strip_metadata:
            result["ai_review"] = audit
        return result, audit

    result = dict(item)
    suggested_type = clean_text(decision.get("type"))
    if (
        suggested_type in ALLOWED_TYPES
        and suggested_type != clean_text(item.get("type"))
        and score >= type_threshold
    ):
        result["type"] = suggested_type
        audit["type_changed"] = True

    suggested_name = optional_text(decision.get("clean_name"), 255)
    if suggested_name and suggested_name != clean_text(item.get("name")):
        result["name"] = suggested_name
        audit["name_changed"] = True

    for field, length in (
        ("short_description", 1000),
        ("description", 5000),
        ("history", 10000),
    ):
        generated = optional_text(decision.get(field), length)
        if generated and not clean_text(result.get(field)):
            result[field] = generated
            audit["description_added"] = True

    if not strip_metadata:
        result["ai_review"] = audit
    return result, audit


def main() -> int:
    args = parse_args()
    if args.batch_size < 1 or args.max_retries < 1:
        raise SystemExit("batch-size и max-retries должны быть больше нуля")
    if not 0 <= args.delete_confidence <= 1:
        raise SystemExit("delete-confidence должен быть от 0 до 1")
    if not 0 <= args.type_confidence <= 1:
        raise SystemExit("type-confidence должен быть от 0 до 1")

    input_path = Path(args.input)
    output_path = Path(args.output) if args.output else input_path
    progress_path = Path(args.progress)
    report_path = Path(args.report)

    snapshot, objects = read_json(input_path)
    original = [dict(item) for item in objects]

    if args.reset_progress and progress_path.exists():
        progress_path.unlink()

    progress = load_progress(progress_path, args.model)
    decisions: dict[str, Any] = progress["decisions"]

    entries: list[dict[str, Any]] = []
    key_occurrences: Counter[str] = Counter()
    resumed = 0
    local_removed = 0

    for index, item in enumerate(objects):
        base_key = record_key(item, index)
        key_occurrences[base_key] += 1
        occurrence = key_occurrences[base_key]
        key = base_key if occurrence == 1 else f"{base_key}#{occurrence}"
        entry = {
            "key": key,
            "item": item,
            "fingerprint": fingerprint(item),
            "index": index,
        }
        entries.append(entry)

        stored = decisions.get(key)
        if (
            isinstance(stored, dict)
            and stored.get("fingerprint") == entry["fingerprint"]
            and stored.get("model") == args.model
            and isinstance(stored.get("decision"), dict)
        ):
            resumed += 1
            continue

        reason = local_rejection(item)
        if reason:
            decisions[key] = {
                "fingerprint": entry["fingerprint"],
                "model": args.model,
                "decision": {
                    "keep": False,
                    "type": None,
                    "clean_name": None,
                    "short_description": None,
                    "description": None,
                    "history": None,
                    "reason": reason,
                    "confidence": 1.0,
                    "source": "local-rule",
                },
            }
            local_removed += 1

    pending = []
    for entry in entries:
        stored = decisions.get(entry["key"])
        if not (
            isinstance(stored, dict)
            and stored.get("fingerprint") == entry["fingerprint"]
            and stored.get("model") == args.model
            and isinstance(stored.get("decision"), dict)
        ):
            pending.append(entry)

    if args.limit > 0:
        pending = pending[:args.limit]

    print("=" * 72)
    print("ПРОВЕРКА КАТАЛОГА ПАЛОМНИЧЕСКИХ ОБЪЕКТОВ")
    print("=" * 72)
    print(f"Файл: {input_path}")
    print(f"Модель: {args.model}")
    print(f"Всего записей: {len(entries)}")
    print(f"Восстановлено из checkpoint: {resumed}")
    print(f"Удалено локальными правилами: {local_removed}")
    print(f"Ожидают Ollama: {len(pending)}")
    print_counter("Типы до проверки:", count_types(original))
    print_counter("Пустые поля до проверки:", count_missing(original))
    print("=" * 72)

    client = ollama.Client(host=args.host) if args.host else ollama
    batches = split_batches(pending, args.batch_size)
    failed_batches: list[dict[str, Any]] = []
    started = time.monotonic()

    for number, batch in enumerate(batches, start=1):
        print(f"[BATCH {number}/{len(batches)}] объектов: {len(batch)}", flush=True)
        payload = [model_payload(entry["item"], entry["key"]) for entry in batch]
        try:
            result = call_model(client, args.model, payload, args.max_retries)
            kept = 0
            removed = 0
            changed = 0
            enriched = 0
            for entry in batch:
                decision = result[entry["key"]]
                decisions[entry["key"]] = {
                    "fingerprint": entry["fingerprint"],
                    "model": args.model,
                    "decision": decision,
                }
                if decision["keep"]:
                    kept += 1
                    if decision.get("type") != clean_text(entry["item"].get("type")):
                        changed += 1
                    if decision.get("short_description") or decision.get("description"):
                        enriched += 1
                else:
                    removed += 1
            print(
                f"    оставить: {kept}; удалить: {removed}; "
                f"смена типа: {changed}; описания: {enriched}",
                flush=True,
            )
        except Exception as exc:
            print(
                f"    Пакет не обработан: {exc}. Записи сохранены без удаления.",
                file=sys.stderr,
            )
            failed_batches.append({
                "batch": number,
                "keys": [entry["key"] for entry in batch],
                "error": str(exc),
            })
            for entry in batch:
                current_type = clean_text(entry["item"].get("type"))
                decisions[entry["key"]] = {
                    "fingerprint": entry["fingerprint"],
                    "model": args.model,
                    "decision": {
                        "keep": True,
                        "type": current_type if current_type in ALLOWED_TYPES else None,
                        "clean_name": None,
                        "short_description": None,
                        "description": None,
                        "history": None,
                        "reason": f"ошибка Ollama: {exc}",
                        "confidence": 0.0,
                        "source": "ollama-error",
                    },
                }

        progress["model"] = args.model
        progress["decisions"] = decisions
        save_progress(progress_path, progress)
        if args.pause > 0 and number < len(batches):
            time.sleep(args.pause)

    kept_objects: list[dict[str, Any]] = []
    removed_objects: list[dict[str, Any]] = []
    audits: list[dict[str, Any]] = []
    stats: Counter[str] = Counter()
    reasons: Counter[str] = Counter()

    for entry in entries:
        stored = decisions.get(entry["key"])
        if isinstance(stored, dict) and isinstance(stored.get("decision"), dict):
            decision = stored["decision"]
        else:
            current_type = clean_text(entry["item"].get("type"))
            decision = {
                "keep": True,
                "type": current_type if current_type in ALLOWED_TYPES else None,
                "reason": "запись ещё не проверена",
                "confidence": 0.0,
                "source": "not-reviewed",
            }

        result, audit = apply_decision(
            entry["item"],
            decision,
            args.delete_confidence,
            args.type_confidence,
            args.strip_review_metadata,
        )
        audits.append({"key": entry["key"], "name": entry["item"].get("name"), **audit})

        if result is None:
            stats["removed"] += 1
            reason = clean_text(audit.get("reason")) or "причина не указана"
            reasons[reason] += 1
            removed_objects.append({
                "key": entry["key"],
                "slug": entry["item"].get("slug"),
                "name": entry["item"].get("name"),
                "type": entry["item"].get("type"),
                "reason": reason,
                "confidence": audit.get("confidence"),
                "source": audit.get("source"),
            })
            continue

        kept_objects.append(result)
        stats["kept"] += 1
        for flag in (
            "type_changed", "name_changed", "description_added",
            "uncertain_removal_kept",
        ):
            if audit.get(flag):
                stats[flag] += 1

    review_meta = {
        "reviewed_at": now_iso(),
        "model": args.model,
        "input_count": len(original),
        "kept_count": len(kept_objects),
        "removed_count": stats["removed"],
        "type_changed_count": stats["type_changed"],
        "name_changed_count": stats["name_changed"],
        "description_added_count": stats["description_added"],
        "uncertain_removal_kept_count": stats["uncertain_removal_kept"],
        "delete_confidence": args.delete_confidence,
        "type_confidence": args.type_confidence,
    }

    output_snapshot = dict(snapshot)
    meta = output_snapshot.get("meta") if isinstance(output_snapshot.get("meta"), dict) else {}
    meta = dict(meta)
    meta["ollama_review"] = review_meta
    meta["imported_count"] = len(kept_objects)
    meta["type_counts"] = dict(count_types(kept_objects))
    output_snapshot["meta"] = meta
    output_snapshot["objects"] = kept_objects

    report = {
        "meta": review_meta,
        "input": str(input_path),
        "output": str(output_path),
        "types_before": dict(count_types(original)),
        "types_after": dict(count_types(kept_objects)),
        "missing_before": dict(count_missing(original)),
        "missing_after": dict(count_missing(kept_objects)),
        "removal_reasons": dict(reasons),
        "removed_objects": removed_objects,
        "failed_batches": failed_batches,
        "audit": audits,
    }

    backup_path: Path | None = None
    if not args.dry_run:
        if output_path.resolve() == input_path.resolve():
            stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
            backup_path = input_path.with_name(
                f"{input_path.stem}.before-ollama-{stamp}{input_path.suffix}"
            )
            shutil.copy2(input_path, backup_path)
        atomic_write(output_path, output_snapshot)

    atomic_write(report_path, report)
    progress["decisions"] = decisions
    save_progress(progress_path, progress)

    print()
    print("=" * 72)
    print("ИТОГОВАЯ СТАТИСТИКА")
    print("=" * 72)
    print(f"Было: {len(original)}")
    print(f"Оставлено: {len(kept_objects)}")
    print(f"Удалено: {stats['removed']}")
    print(f"Изменён тип: {stats['type_changed']}")
    print(f"Исправлено названий: {stats['name_changed']}")
    print(f"Добавлены описания: {stats['description_added']}")
    print(f"Неуверенных удалений оставлено: {stats['uncertain_removal_kept']}")
    print(f"Ошибочных пакетов: {len(failed_batches)}")
    print_counter("Причины удаления:", reasons)
    print_counter("Типы после проверки:", count_types(kept_objects))
    print_counter("Пустые поля после проверки:", count_missing(kept_objects))
    print(f"Время работы: {time.monotonic() - started:.1f} с")
    if args.dry_run:
        print("Dry-run: основной JSON не изменён.")
    else:
        print(f"Итоговый JSON: {output_path}")
        if backup_path:
            print(f"Резервная копия: {backup_path}")
    print(f"Отчёт: {report_path}")
    print(f"Checkpoint: {progress_path}")
    print("=" * 72)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
