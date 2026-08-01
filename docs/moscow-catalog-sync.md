# Синхронизация храмов Москвы и Московской области

Единая синхронизация принимает два файла:

- `moscow-region-orthodox-places.json` — храмы, монастыри и часовни;
- `moscow-region-nearby-points.json` — парковки, кафе и гостиницы рядом с объектами.

По умолчанию файлы должны находиться в `database/seeders/data`.

## Что делает синхронизация

1. Проверяет формат обоих JSON.
2. Исключает святые источники, приделы и ошибочно названные «пределы».
3. Объединяет повторяющиеся OSM-записи по названию и координатам.
4. Создаёт новые объекты.
5. Обновляет существующие объекты с `slug`, начинающимся на `osm-`.
6. Не затирает заполненные редакторские описания, историю и краткое описание.
7. Переносит связанные данные при объединении дублей.
8. Архивирует старые OSM-объекты, отсутствующие в новом полном снимке.
9. Создаёт и обновляет ближайшие точки.
10. Архивирует устаревшие автоматически импортированные точки OpenStreetMap.
11. Записывает результат в журнал действий администраторов.

Ручные записи, у которых `slug` не начинается с `osm-`, автоматически не удаляются.

## Рекомендуемый запуск

Сначала сделать резервную копию:

```bash
php artisan backup:create --label=before-catalog-sync
```

Проверочный импорт без удаления старых записей:

```bash
php artisan catalog:sync-moscow \
  database/seeders/data/moscow-region-orthodox-places.json \
  database/seeders/data/moscow-region-nearby-points.json \
  --no-clean
```

После проверки выполнить полную синхронизацию:

```bash
php artisan catalog:sync-moscow \
  database/seeders/data/moscow-region-orthodox-places.json \
  database/seeders/data/moscow-region-nearby-points.json \
  --yes
```

При использовании стандартных путей достаточно:

```bash
php artisan catalog:sync-moscow --yes
```

## Запуск как сидера

```bash
php artisan db:seed --class=MoscowRegionCatalogSyncSeeder --force
```

Для нестандартных путей можно использовать переменные окружения:

```bash
MOSCOW_REGION_OBJECTS_JSON=/full/path/moscow-region-orthodox-places.json \
MOSCOW_REGION_NEARBY_JSON=/full/path/moscow-region-nearby-points.json \
php artisan db:seed --class=MoscowRegionCatalogSyncSeeder --force
```

Импорт без очистки через сидер:

```bash
MOSCOW_REGION_SYNC_CLEAN=0 \
php artisan db:seed --class=MoscowRegionCatalogSyncSeeder --force
```

## Защита от неполного файла

Полная очистка автоматически отменяется, если после проверки в JSON осталось менее 100 объектов. Для частичного файла следует использовать `--no-clean`.

## После синхронизации

```bash
php artisan objects:scan-duplicates
php artisan optimize:clear
```

Оставшиеся неоднозначные совпадения проверяются в административном разделе `/admin/duplicates`.
