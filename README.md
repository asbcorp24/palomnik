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
- защищённая административная панель;
- вход и выход администратора;
- обзорная страница со статистикой;
- полный CRUD типов объектов;
- полный CRUD викариатств;
- полный CRUD благочиний;
- полный CRUD святынь;
- полный CRUD храмов, монастырей, часовен и святых источников;
- загрузка, редактирование, выбор обложки и удаление медиаматериалов;
- публикация и черновики объектов;
- публичный API каталога с поиском, фильтрами и пагинацией;
- автоматические тесты и GitHub Actions.

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

## Учётная запись администратора

В `.env` должны быть указаны:

```env
ADMIN_NAME="Главный администратор"
ADMIN_EMAIL=admin@palomnik.local
ADMIN_PASSWORD=ChangeMe123!
```

После изменения этих параметров выполните:

```bash
php artisan config:clear
php artisan db:seed --class=AdminUserSeeder
```

Административная панель:

```text
http://127.0.0.1:8000/admin/login
```

Пароль из `.env.example` предназначен только для локальной разработки. Перед размещением на сервере обязательно замените его.

## Обновление уже установленного проекта

```bash
git pull origin main
composer install
php artisan optimize:clear
php artisan migrate --seed
php artisan storage:link
php artisan test
```

Если символическая ссылка `public/storage` уже существует, сообщение `The [public/storage] link already exists` является нормальным.

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
