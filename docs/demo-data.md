# Демонстрационные данные

Проект содержит идемпотентный сидер `Database\\Seeders\\DemoSeeder`. Его можно запускать повторно: записи с теми же slug, email и кодами обновляются, а не дублируются.

## Быстрый запуск

Из корня Laravel-проекта:

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\DemoSeeder
php artisan storage:link
php artisan optimize:clear
```

В Git Bash на Windows также допустим короткий вариант:

```bash
php artisan db:seed --class=DemoSeeder
```

## Автоматическое создание при `db:seed`

Добавьте в `.env`:

```env
DEMO_DATA_ENABLED=true
```

После этого:

```bash
php artisan config:clear
php artisan db:seed
```

По умолчанию флаг выключен, чтобы демонстрационные записи случайно не попали в рабочую базу.

## Демо-аккаунты

| Роль | Email | Пароль |
|---|---|---|
| Паломник | `demo@palomnik.local` | `demo12345` |
| Проверенный организатор | `organizer@palomnik.local` | `demo12345` |
| Участник группы | `pilgrim2@palomnik.local` | `demo12345` |

Администратор создаётся отдельным `AdminUserSeeder` из значений `ADMIN_EMAIL` и `ADMIN_PASSWORD` файла `.env`.

## Что создаётся

- типы объектов;
- викариатства и благочиния;
- святыни;
- девять храмов, монастырей, часовен и источников;
- локальные SVG-обложки в `storage/app/public/demo`;
- четыре опубликованных маршрута;
- три ближайшие групповые поездки;
- четыре события календаря;
- три путевые заметки;
- галерея сообщества;
- отзывы и подтверждённые посещения;
- два совместных паломничества с участниками и сообщениями;
- избранное демо-пользователя;
- персональный маршрут;
- бронирование с QR-билетом.

## Полное пересоздание локальной базы

Команда удаляет все текущие таблицы и данные:

```bash
php artisan migrate:fresh --seed
```

Перед её запуском установите `DEMO_DATA_ENABLED=true`, либо после миграции отдельно выполните `php artisan db:seed --class=DemoSeeder`.
