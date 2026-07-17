# Московский паломник

Единая цифровая платформа для организации, планирования и сопровождения паломнических поездок по Москве и Московской области.

## Состав проекта

- публичный адаптивный сайт в визуальном стиле мобильного приложения;
- REST API для приложения Android/iOS;
- личный кабинет паломника;
- административная панель;
- кабинет паломнической службы и представителей храмов;
- карта объектов, маршруты, бронирование, платежи, билеты, достижения и статистика.

## Основной стек

- Laravel 9;
- PHP 8.1+;
- MySQL 8;
- Laravel Sanctum;
- Blade + Laravel Mix;
- Яндекс.Карты API;
- Redis и очереди — на этапе развертывания.

## Уже реализовано

- расширенный профиль пользователя и базовые роли;
- справочники типов объектов, викариатств и благочиний;
- каталог храмов, монастырей, часовен и святых источников;
- святыни и медиаматериалы объектов;
- публичный API каталога с поиском, фильтрами и пагинацией;
- базовые API-тесты.

## Локальный запуск

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Для Linux/macOS вместо `copy`:

```bash
cp .env.example .env
```

Перед миграцией создайте базу MySQL `palomnik` и укажите доступы в `.env`.

## API v1

```text
GET /api/v1/health
GET /api/v1/directories/object-types
GET /api/v1/directories/vicariates
GET /api/v1/directories/deaneries
GET /api/v1/directories/sanctities
GET /api/v1/objects
GET /api/v1/objects/{slug}
GET /api/v1/user                       auth:sanctum
```

Фильтры каталога:

```text
/api/v1/objects?q=Сергий&type=temple&vicariate=...&deanery=...&sanctity=...&sort=name&per_page=15
```

## Тесты

```bash
php artisan test
```

Для тестов используется отдельная SQLite-база в памяти.

Разработка ведётся в основной ветке `main`.
