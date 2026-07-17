<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Deanery;
use App\Models\FavoriteList;
use App\Models\JointPilgrimage;
use App\Models\JointPilgrimageMember;
use App\Models\JointPilgrimageMessage;
use App\Models\ObjectMedia;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Review;
use App\Models\Sanctity;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserMedia;
use App\Models\UserRoutePlan;
use App\Models\Vicariate;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo12345';

    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        DB::transaction(function () {
            $users = $this->seedUsers();
            [$vicariates, $deaneries, $sanctities] = $this->seedDirectories();
            $images = $this->seedImages();
            $objects = $this->seedObjects($vicariates, $deaneries, $sanctities, $images);
            [$routes, $trips] = $this->seedRoutesAndTrips($objects, $images);
            $this->seedCalendar($objects, $routes, $trips, $users);
            $this->seedCommunity($objects, $routes, $users, $images);
            $this->seedUserCabinet($objects, $trips, $users);
        });

        $this->command?->newLine();
        $this->command?->info('Демонстрационные данные созданы или обновлены.');
        $this->command?->line('Паломник: demo@palomnik.local / '.self::PASSWORD);
        $this->command?->line('Организатор: organizer@palomnik.local / '.self::PASSWORD);
        $this->command?->line('Участник: pilgrim2@palomnik.local / '.self::PASSWORD);
        $this->command?->warn('Для отображения локальных изображений выполните: php artisan storage:link');
    }

    private function seedUsers(): array
    {
        $commonPreferences = [
            'notifications' => true,
            'privacy' => 'public',
            'theme' => 'system',
            'font_size' => 'normal',
            'interests' => ['temples', 'history', 'family'],
        ];

        $demo = User::query()->updateOrCreate(
            ['email' => 'demo@palomnik.local'],
            [
                'name' => 'Дмитрий Паломников',
                'phone' => '+7 900 100-10-01',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_PILGRIM,
                'birth_date' => '1988-05-14',
                'preferences' => $commonPreferences,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $organizer = User::query()->updateOrCreate(
            ['email' => 'organizer@palomnik.local'],
            [
                'name' => 'Анна Петрова',
                'phone' => '+7 900 100-10-02',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_PILGRIM,
                'birth_date' => '1985-09-22',
                'preferences' => [...$commonPreferences, 'interests' => ['routes', 'community', 'youth']],
                'is_active' => true,
                'is_verified_organizer' => true,
                'verified_organizer_at' => now()->subMonths(3),
                'email_verified_at' => now(),
            ]
        );

        $participant = User::query()->updateOrCreate(
            ['email' => 'pilgrim2@palomnik.local'],
            [
                'name' => 'Михаил Соколов',
                'phone' => '+7 900 100-10-03',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_PILGRIM,
                'birth_date' => '1992-02-08',
                'preferences' => [...$commonPreferences, 'privacy' => 'friends'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        return compact('demo', 'organizer', 'participant');
    }

    private function seedDirectories(): array
    {
        $vicariateRows = [
            'central' => ['name' => 'Центральное викариатство', 'slug' => 'central', 'description' => 'Храмы и монастыри исторического центра Москвы.'],
            'southern' => ['name' => 'Южное викариатство', 'slug' => 'southern', 'description' => 'Паломнические объекты южной части Москвы.'],
            'moscow-region' => ['name' => 'Московская область', 'slug' => 'moscow-region', 'description' => 'Монастыри, храмы и святые источники Подмосковья.'],
        ];

        $vicariates = [];
        foreach ($vicariateRows as $key => $row) {
            $vicariates[$key] = Vicariate::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        $deaneryRows = [
            'central' => ['vicariate' => 'central', 'name' => 'Центральное благочиние', 'slug' => 'central-deanery'],
            'khamovniki' => ['vicariate' => 'central', 'name' => 'Хамовническое благочиние', 'slug' => 'khamovniki'],
            'danilov' => ['vicariate' => 'southern', 'name' => 'Даниловское благочиние', 'slug' => 'danilov'],
            'sergiev-posad' => ['vicariate' => 'moscow-region', 'name' => 'Сергиево-Посадское благочиние', 'slug' => 'sergiev-posad'],
            'lyubertsy' => ['vicariate' => 'moscow-region', 'name' => 'Люберецкое благочиние', 'slug' => 'lyubertsy'],
            'chekhov' => ['vicariate' => 'moscow-region', 'name' => 'Чеховское благочиние', 'slug' => 'chekhov'],
        ];

        $deaneries = [];
        foreach ($deaneryRows as $key => $row) {
            $deaneries[$key] = Deanery::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'vicariate_id' => $vicariates[$row['vicariate']]->id,
                    'name' => $row['name'],
                    'description' => 'Демонстрационная запись справочника благочиний.',
                ]
            );
        }

        $sanctityRows = [
            'iveron-icon' => ['name' => 'Иверская икона Божией Матери', 'slug' => 'iveron-icon', 'type' => 'icon'],
            'kazan-icon' => ['name' => 'Казанская икона Божией Матери', 'slug' => 'kazan-icon', 'type' => 'icon'],
            'relics-sergius' => ['name' => 'Святыни, связанные с преподобным Сергием Радонежским', 'slug' => 'relics-sergius', 'type' => 'relics'],
            'relics-alexy' => ['name' => 'Святыни, связанные со святителем Алексием', 'slug' => 'relics-alexy', 'type' => 'relics'],
            'life-giving-cross' => ['name' => 'Крест и распятие', 'slug' => 'life-giving-cross', 'type' => 'relic'],
            'holy-spring' => ['name' => 'Святой источник', 'slug' => 'holy-spring', 'type' => 'spring'],
        ];

        $sanctities = [];
        foreach ($sanctityRows as $key => $row) {
            $sanctities[$key] = Sanctity::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'description' => 'Демонстрационное описание святыни для каталога и поиска.']
            );
        }

        return [$vicariates, $deaneries, $sanctities];
    }

    private function seedImages(): array
    {
        Storage::disk('public')->makeDirectory('demo');

        $images = [
            'cathedral-gold' => ['demo/cathedral-gold.svg', 'Храм Христа Спасителя', 'Москва', '#26443b', '#b58a32'],
            'red-square' => ['demo/red-square.svg', 'Покровский собор', 'Красная площадь', '#7c2d2d', '#d6a84f'],
            'convent-blue' => ['demo/convent-blue.svg', 'Новодевичий монастырь', 'Исторический ансамбль', '#34596b', '#d9c69b'],
            'monastery-green' => ['demo/monastery-green.svg', 'Данилов монастырь', 'Паломнический маршрут', '#294c3f', '#c5a45c'],
            'lavra' => ['demo/lavra.svg', 'Троице-Сергиева лавра', 'Сергиев Посад', '#356a83', '#d2a94f'],
            'ugresha' => ['demo/ugresha.svg', 'Николо-Угрешский монастырь', 'Подмосковье', '#394d38', '#c9a861'],
            'chapel' => ['demo/chapel.svg', 'Иверская часовня', 'Воскресенские ворота', '#5e3e32', '#d8b76a'],
            'spring' => ['demo/spring.svg', 'Святой источник', 'Талеж', '#27637a', '#8bc0cc'],
            'route-moscow' => ['demo/route-moscow.svg', 'Сердце православной Москвы', 'Однодневный маршрут', '#27453c', '#caa85b'],
            'route-monasteries' => ['demo/route-monasteries.svg', 'Древние обители Москвы', 'Тематический маршрут', '#4b3e36', '#d1b070'],
            'route-region' => ['demo/route-region.svg', 'Дорога к преподобному Сергию', 'Маршрут по Подмосковью', '#2d5667', '#d0ae5f'],
            'community-one' => ['demo/community-one.svg', 'Путевые заметки', 'Впечатления паломника', '#3c5144', '#c7a45e'],
            'community-two' => ['demo/community-two.svg', 'Семейное паломничество', 'Полезные советы', '#6a4d3c', '#d5b66e'],
        ];

        foreach ($images as [$path, $title, $subtitle, $from, $to]) {
            Storage::disk('public')->put($path, $this->svg($title, $subtitle, $from, $to));
        }

        return collect($images)->mapWithKeys(fn (array $value, string $key) => [$key => $value[0]])->all();
    }

    private function seedObjects(array $vicariates, array $deaneries, array $sanctities, array $images): array
    {
        $types = ObjectType::query()->get()->keyBy('slug');
        $rows = [
            'christ-saviour-cathedral' => [
                'type' => 'temple', 'vicariate' => 'central', 'deanery' => 'central',
                'name' => 'Храм Христа Спасителя',
                'short_description' => 'Крупный кафедральный собор в центре Москвы и удобная отправная точка для городского паломнического маршрута.',
                'description' => 'В демонстрационной карточке представлены расписание, контакты, сведения о доступности, святыни и связанные события. Текст можно заменить проверенной редакцией через административную панель.',
                'history' => 'Карточка подготовлена как содержательный пример для проверки каталога, карты, поиска, избранного и отзывов.',
                'address' => 'Москва, ул. Волхонка, 15', 'latitude' => 55.7446400, 'longitude' => 37.6054900,
                'phone' => '+7 495 000-00-01', 'email' => 'demo1@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => "Ежедневно: утреннее богослужение — 08:00; вечернее — 17:00.\nРасписание демонстрационное.",
                'parking_info' => 'Городские парковки поблизости.', 'accessibility_info' => 'Предусмотрен доступ для маломобильных посетителей.',
                'sanctities' => ['life-giving-cross', 'relics-alexy'], 'image' => 'cathedral-gold',
            ],
            'st-basil-cathedral' => [
                'type' => 'temple', 'vicariate' => 'central', 'deanery' => 'central',
                'name' => 'Покровский собор на Красной площади',
                'short_description' => 'Исторический собор в самом центре столицы.',
                'description' => 'Демонстрационная карточка объекта с координатами, фотографией, описанием и включением в несколько маршрутов.',
                'history' => 'Текст используется для проверки длинных описаний и отображения исторической справки.',
                'address' => 'Москва, Красная площадь, 7', 'latitude' => 55.7525220, 'longitude' => 37.6230870,
                'phone' => '+7 495 000-00-02', 'email' => 'demo2@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Демонстрационное время посещения: 10:00–18:00.',
                'parking_info' => 'Рекомендуется общественный транспорт.', 'accessibility_info' => 'Уточняйте условия посещения отдельных помещений.',
                'sanctities' => ['kazan-icon'], 'image' => 'red-square',
            ],
            'novodevichy-convent' => [
                'type' => 'monastery', 'vicariate' => 'central', 'deanery' => 'khamovniki',
                'name' => 'Новодевичий монастырь',
                'short_description' => 'Монастырский ансамбль для тематических и самостоятельных маршрутов.',
                'description' => 'Карточка демонстрирует объект типа «Монастырь», связь с календарём, маршрутами и медиаматериалами.',
                'history' => 'Демонстрационная историческая справка для проверки вёрстки страницы объекта.',
                'address' => 'Москва, Новодевичий проезд, 1', 'latitude' => 55.7261400, 'longitude' => 37.5563400,
                'phone' => '+7 495 000-00-03', 'email' => 'demo3@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Территория открыта ежедневно. Расписание богослужений демонстрационное.',
                'parking_info' => 'Ограниченная городская парковка.', 'accessibility_info' => 'Часть территории доступна без ступеней.',
                'sanctities' => ['iveron-icon'], 'image' => 'convent-blue',
            ],
            'danilov-monastery' => [
                'type' => 'monastery', 'vicariate' => 'southern', 'deanery' => 'danilov',
                'name' => 'Данилов монастырь',
                'short_description' => 'Московская обитель, включённая в однодневные и тематические маршруты.',
                'description' => 'Демонстрационная карточка с полной структурой данных для сайта и мобильного приложения.',
                'history' => 'Историческая справка подготовлена как пример и может редактироваться представителем объекта.',
                'address' => 'Москва, ул. Даниловский Вал, 22', 'latitude' => 55.7103300, 'longitude' => 37.6284200,
                'phone' => '+7 495 000-00-04', 'email' => 'demo4@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Утреннее богослужение — 07:00, вечернее — 17:00. Демонстрационные данные.',
                'parking_info' => 'Парковочные места на прилегающих улицах.', 'accessibility_info' => 'Доступность зависит от выбранного корпуса.',
                'sanctities' => ['relics-alexy'], 'image' => 'monastery-green',
            ],
            'nicholas-khamovniki' => [
                'type' => 'temple', 'vicariate' => 'central', 'deanery' => 'khamovniki',
                'name' => 'Храм святителя Николая в Хамовниках',
                'short_description' => 'Городской храм для пешего маршрута по Хамовникам.',
                'description' => 'Пример компактной карточки храма с контактами и расписанием.',
                'history' => 'Демонстрационная историческая заметка.',
                'address' => 'Москва, ул. Льва Толстого, 2', 'latitude' => 55.7339300, 'longitude' => 37.5848900,
                'phone' => '+7 495 000-00-05', 'email' => 'demo5@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Ежедневно: 08:00 и 17:00. Демонстрационное расписание.',
                'parking_info' => 'Платная парковка.', 'accessibility_info' => 'Вход с минимальным перепадом высот.',
                'sanctities' => ['kazan-icon'], 'image' => 'cathedral-gold',
            ],
            'trinity-sergius-lavra' => [
                'type' => 'monastery', 'vicariate' => 'moscow-region', 'deanery' => 'sergiev-posad',
                'name' => 'Троице-Сергиева лавра',
                'short_description' => 'Ключевая точка паломнических маршрутов в Сергиев Посад.',
                'description' => 'Карточка используется для демонстрации поездок, бронирований, карты и совместных паломничеств.',
                'history' => 'Демонстрационный текст исторической справки.',
                'address' => 'Московская область, Сергиев Посад, Красногорская площадь, 1', 'latitude' => 56.3105500, 'longitude' => 38.1305100,
                'phone' => '+7 496 000-00-06', 'email' => 'demo6@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Территория открыта ежедневно. Расписание демонстрационное.',
                'parking_info' => 'Городские парковки рядом с историческим центром.', 'accessibility_info' => 'Основные маршруты по территории частично доступны.',
                'sanctities' => ['relics-sergius'], 'image' => 'lavra',
            ],
            'nikolo-ugreshsky-monastery' => [
                'type' => 'monastery', 'vicariate' => 'moscow-region', 'deanery' => 'lyubertsy',
                'name' => 'Николо-Угрешский монастырь',
                'short_description' => 'Обитель в ближнем Подмосковье для самостоятельных и групповых поездок.',
                'description' => 'Пример объекта Московской области с маршрутом и календарными событиями.',
                'history' => 'Демонстрационная историческая справка.',
                'address' => 'Московская область, Дзержинский, площадь Святителя Николая, 1', 'latitude' => 55.6269200, 'longitude' => 37.8427500,
                'phone' => '+7 495 000-00-07', 'email' => 'demo7@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Демонстрационное расписание: ежедневно с 07:00.',
                'parking_info' => 'Гостевая парковка.', 'accessibility_info' => 'Доступность основных дорожек и собора.',
                'sanctities' => ['relics-alexy'], 'image' => 'ugresha',
            ],
            'iveron-chapel' => [
                'type' => 'chapel', 'vicariate' => 'central', 'deanery' => 'central',
                'name' => 'Иверская часовня у Воскресенских ворот',
                'short_description' => 'Небольшая часовня в центре Москвы — удобная точка пешего маршрута.',
                'description' => 'Карточка демонстрирует тип объекта «Часовня».',
                'history' => 'Демонстрационная историческая заметка.',
                'address' => 'Москва, Воскресенские ворота', 'latitude' => 55.7554500, 'longitude' => 37.6175400,
                'phone' => '+7 495 000-00-08', 'email' => 'demo8@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Открыта ежедневно. Время демонстрационное.',
                'parking_info' => 'Рекомендуется метро.', 'accessibility_info' => 'Небольшое помещение, возможна очередь.',
                'sanctities' => ['iveron-icon'], 'image' => 'chapel',
            ],
            'talezh-holy-spring' => [
                'type' => 'holy-spring', 'vicariate' => 'moscow-region', 'deanery' => 'chekhov',
                'name' => 'Святой источник в Талеже',
                'short_description' => 'Загородная точка для демонстрации объектов типа «Святой источник».',
                'description' => 'Перед реальным использованием сведения о режиме посещения и координатах следует проверить у ответственного представителя.',
                'history' => 'Демонстрационная запись для проверки фильтров и карты.',
                'address' => 'Московская область, городской округ Чехов, село Талеж', 'latitude' => 55.0678000, 'longitude' => 37.4896000,
                'phone' => '+7 496 000-00-09', 'email' => 'demo9@palomnik.local', 'website' => 'https://example.org',
                'schedule_text' => 'Демонстрационный режим посещения: 08:00–20:00.',
                'parking_info' => 'Открытая парковочная площадка.', 'accessibility_info' => 'Спуск к источнику может быть затруднён.',
                'sanctities' => ['holy-spring'], 'image' => 'spring',
            ],
        ];

        $objects = [];
        foreach ($rows as $slug => $row) {
            $object = PilgrimageObject::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'object_type_id' => $types[$row['type']]->id,
                    'vicariate_id' => $vicariates[$row['vicariate']]->id,
                    'deanery_id' => $deaneries[$row['deanery']]->id,
                    'name' => $row['name'],
                    'short_description' => $row['short_description'],
                    'description' => $row['description'],
                    'history' => $row['history'],
                    'address' => $row['address'],
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'website' => $row['website'],
                    'schedule_text' => $row['schedule_text'],
                    'parking_info' => $row['parking_info'],
                    'accessibility_info' => $row['accessibility_info'],
                    'is_published' => true,
                    'published_at' => now()->subDays(count($objects) + 1),
                ]
            );
            if ($object->trashed()) {
                $object->restore();
            }

            $object->sanctities()->sync(collect($row['sanctities'])->mapWithKeys(
                fn (string $key) => [$sanctities[$key]->id => ['note' => 'Демонстрационная связь со святыней.']]
            )->all());

            ObjectMedia::query()->updateOrCreate(
                ['pilgrimage_object_id' => $object->id, 'is_cover' => true],
                [
                    'type' => 'image',
                    'path' => $images[$row['image']],
                    'external_url' => null,
                    'title' => $row['name'],
                    'description' => 'Демонстрационное изображение, созданное автоматически.',
                    'sort_order' => 1,
                ]
            );

            $objects[$slug] = $object;
        }

        return $objects;
    }

    private function seedRoutesAndTrips(array $objects, array $images): array
    {
        $rows = [
            'heart-of-orthodox-moscow' => [
                'title' => 'Сердце православной Москвы', 'category' => 'one_day', 'difficulty' => 'easy',
                'duration_days' => 1, 'duration_minutes' => 270, 'base_price' => 0, 'is_group' => false,
                'short_description' => 'Пеший маршрут по главным святыням исторического центра.',
                'description' => 'Маршрут подходит для первого знакомства с платформой: объекты отображаются по порядку, открываются на карте и доступны в карточках.',
                'program' => "10:00 — Иверская часовня.\n11:00 — Покровский собор.\n13:00 — Храм Христа Спасителя.",
                'image' => 'route-moscow',
                'objects' => ['iveron-chapel', 'st-basil-cathedral', 'christ-saviour-cathedral'],
            ],
            'ancient-monasteries-moscow' => [
                'title' => 'Древние обители Москвы', 'category' => 'thematic', 'difficulty' => 'medium',
                'duration_days' => 1, 'duration_minutes' => 420, 'base_price' => 1500, 'is_group' => true,
                'short_description' => 'Тематический маршрут по монастырям и храмам Москвы.',
                'description' => 'Групповой маршрут с программой, остановками и возможностью бронирования ближайшей поездки.',
                'program' => "08:30 — сбор группы.\n09:30 — Данилов монастырь.\n12:00 — храм в Хамовниках.\n14:00 — Новодевичий монастырь.",
                'image' => 'route-monasteries',
                'objects' => ['danilov-monastery', 'nicholas-khamovniki', 'novodevichy-convent'],
            ],
            'road-to-sergius' => [
                'title' => 'Дорога к преподобному Сергию', 'category' => 'one_day', 'difficulty' => 'medium',
                'duration_days' => 1, 'duration_minutes' => 600, 'base_price' => 2900, 'is_group' => true,
                'short_description' => 'Однодневная поездка из Москвы в Сергиев Посад.',
                'description' => 'Маршрут показывает работу групповых дат, бронирования, QR-билета и совместного паломничества.',
                'program' => "07:00 — отправление из Москвы.\n09:00 — прибытие в Сергиев Посад.\n09:30–15:00 — программа на территории лавры.\n18:00 — возвращение.",
                'image' => 'route-region',
                'objects' => ['trinity-sergius-lavra'],
            ],
            'monasteries-near-moscow' => [
                'title' => 'Монастыри ближнего Подмосковья', 'category' => 'family', 'difficulty' => 'easy',
                'duration_days' => 1, 'duration_minutes' => 480, 'base_price' => 2200, 'is_group' => true,
                'short_description' => 'Спокойный маршрут выходного дня для семейной группы.',
                'description' => 'Демонстрационный маршрут по объектам Московской области.',
                'program' => "08:00 — сбор.\n10:00 — Николо-Угрешский монастырь.\n15:00 — возвращение в Москву.",
                'image' => 'route-region',
                'objects' => ['nikolo-ugreshsky-monastery'],
            ],
        ];

        $routes = [];
        foreach ($rows as $slug => $row) {
            $route = PilgrimageRoute::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $row['title'],
                    'category' => $row['category'],
                    'difficulty' => $row['difficulty'],
                    'duration_days' => $row['duration_days'],
                    'duration_minutes' => $row['duration_minutes'],
                    'short_description' => $row['short_description'],
                    'description' => $row['description'],
                    'program' => $row['program'],
                    'base_price' => $row['base_price'],
                    'is_group' => $row['is_group'],
                    'is_published' => true,
                    'published_at' => now()->subDays(10),
                    'cover_path' => $images[$row['image']],
                ]
            );
            if ($route->trashed()) {
                $route->restore();
            }

            $sync = [];
            foreach ($row['objects'] as $index => $objectSlug) {
                $sync[$objects[$objectSlug]->id] = [
                    'sort_order' => $index + 1,
                    'stay_minutes' => $index === 0 ? 45 : 60,
                    'note' => 'Демонстрационная остановка маршрута.',
                ];
            }
            $route->objects()->sync($sync);
            $routes[$slug] = $route;
        }

        $tripRows = [
            'sergius' => [
                'route' => 'road-to-sergius', 'title' => 'Субботняя поездка в Сергиев Посад',
                'starts_at' => now()->addDays(14)->setTime(7, 0), 'ends_at' => now()->addDays(14)->setTime(19, 0),
                'meeting_point' => 'Москва, станция метро ВДНХ, выход 1', 'capacity' => 30, 'booked_count' => 2,
                'price' => 2900, 'status' => 'open', 'notes' => 'Возьмите удобную обувь и документ, удостоверяющий личность.',
            ],
            'monasteries' => [
                'route' => 'ancient-monasteries-moscow', 'title' => 'Древние обители Москвы — воскресная группа',
                'starts_at' => now()->addDays(21)->setTime(8, 30), 'ends_at' => now()->addDays(21)->setTime(17, 30),
                'meeting_point' => 'Москва, Павелецкая площадь', 'capacity' => 20, 'booked_count' => 0,
                'price' => 1500, 'status' => 'open', 'notes' => 'Передвижение на заказном автобусе и пешком.',
            ],
            'family' => [
                'route' => 'monasteries-near-moscow', 'title' => 'Семейная поездка выходного дня',
                'starts_at' => now()->addDays(10)->setTime(8, 0), 'ends_at' => now()->addDays(10)->setTime(17, 0),
                'meeting_point' => 'Москва, станция метро Кузьминки', 'capacity' => 24, 'booked_count' => 4,
                'price' => 2200, 'status' => 'open', 'notes' => 'Детям рекомендуется взять воду и лёгкий перекус.',
            ],
        ];

        $trips = [];
        foreach ($tripRows as $key => $row) {
            $trips[$key] = Trip::query()->updateOrCreate(
                ['pilgrimage_route_id' => $routes[$row['route']]->id, 'title' => $row['title']],
                collect($row)->except('route')->all()
            );
        }

        return [$routes, $trips];
    }

    private function seedCalendar(array $objects, array $routes, array $trips, array $users): void
    {
        $rows = [
            'demo-liturgy-cathedral' => [
                'pilgrimage_object_id' => $objects['christ-saviour-cathedral']->id,
                'created_by' => $users['organizer']->id,
                'title' => 'Воскресная Божественная литургия', 'type' => 'service',
                'short_description' => 'Демонстрационное событие в календаре богослужений.',
                'description' => 'Событие создано для проверки календаря, карточки события и экспорта в формат ICS.',
                'starts_at' => now()->addDays(3)->setTime(9, 0), 'ends_at' => now()->addDays(3)->setTime(11, 0),
                'all_day' => false, 'location' => $objects['christ-saviour-cathedral']->name,
                'address' => $objects['christ-saviour-cathedral']->address,
                'latitude' => $objects['christ-saviour-cathedral']->latitude, 'longitude' => $objects['christ-saviour-cathedral']->longitude,
                'capacity' => null, 'contact_phone' => '+7 495 000-00-01', 'contact_email' => 'events@palomnik.local',
            ],
            'demo-history-lecture' => [
                'pilgrimage_object_id' => $objects['novodevichy-convent']->id,
                'created_by' => $users['organizer']->id,
                'title' => 'Встреча об истории московских обителей', 'type' => 'lecture',
                'short_description' => 'Открытая встреча для паломников и участников сообщества.',
                'description' => 'Демонстрационное мероприятие с ограничением количества участников и контактами организатора.',
                'starts_at' => now()->addDays(7)->setTime(18, 30), 'ends_at' => now()->addDays(7)->setTime(20, 0),
                'all_day' => false, 'location' => 'Паломнический центр', 'address' => $objects['novodevichy-convent']->address,
                'latitude' => $objects['novodevichy-convent']->latitude, 'longitude' => $objects['novodevichy-convent']->longitude,
                'capacity' => 60, 'registration_url' => url('/register'), 'contact_phone' => '+7 900 100-10-02', 'contact_email' => 'organizer@palomnik.local',
            ],
            'demo-family-day' => [
                'pilgrimage_object_id' => $objects['nicholas-khamovniki']->id,
                'created_by' => $users['organizer']->id,
                'title' => 'Семейный день паломника', 'type' => 'family',
                'short_description' => 'Встреча для родителей и детей с небольшой экскурсией.',
                'description' => 'Демонстрационное семейное мероприятие.',
                'starts_at' => now()->addDays(9)->setTime(12, 0), 'ends_at' => now()->addDays(9)->setTime(15, 0),
                'all_day' => false, 'location' => $objects['nicholas-khamovniki']->name, 'address' => $objects['nicholas-khamovniki']->address,
                'latitude' => $objects['nicholas-khamovniki']->latitude, 'longitude' => $objects['nicholas-khamovniki']->longitude,
                'capacity' => 40, 'contact_phone' => '+7 900 100-10-02', 'contact_email' => 'family@palomnik.local',
            ],
            'demo-trip-sergius' => [
                'pilgrimage_object_id' => $objects['trinity-sergius-lavra']->id,
                'pilgrimage_route_id' => $routes['road-to-sergius']->id,
                'trip_id' => $trips['sergius']->id,
                'created_by' => $users['organizer']->id,
                'title' => $trips['sergius']->title, 'type' => 'pilgrimage',
                'short_description' => 'Групповая поездка с бронированием и QR-билетом.',
                'description' => 'Событие связано с маршрутом и конкретной датой поездки.',
                'starts_at' => $trips['sergius']->starts_at, 'ends_at' => $trips['sergius']->ends_at,
                'all_day' => false, 'location' => 'Сергиев Посад', 'address' => $trips['sergius']->meeting_point,
                'latitude' => $objects['trinity-sergius-lavra']->latitude, 'longitude' => $objects['trinity-sergius-lavra']->longitude,
                'capacity' => $trips['sergius']->capacity, 'contact_phone' => '+7 900 100-10-02', 'contact_email' => 'trips@palomnik.local',
            ],
        ];

        foreach ($rows as $slug => $row) {
            $event = CalendarEvent::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [...$row, 'is_published' => true, 'published_at' => now()->subDay()]
            );
            if ($event->trashed()) {
                $event->restore();
            }
        }
    }

    private function seedCommunity(array $objects, array $routes, array $users, array $images): void
    {
        $postRows = [
            'first-pilgrimage-moscow-demo' => [
                'user_id' => $users['demo']->id,
                'title' => 'Моё первое паломничество по центру Москвы',
                'excerpt' => 'Как подготовиться к пешему маршруту, что взять с собой и как распределить время.',
                'body' => "Я начал маршрут у Иверской часовни, затем прошёл к Покровскому собору и завершил день у Храма Христа Спасителя.\n\nУдобнее заранее сохранить карточки объектов офлайн, проверить расписание и взять с собой воду. Эта публикация является демонстрационной и показывает работу раздела сообщества.",
                'image' => 'community-one',
            ],
            'family-trip-tips-demo' => [
                'user_id' => $users['organizer']->id,
                'title' => 'Семейная поездка: пять простых советов',
                'excerpt' => 'Практические рекомендации для поездки с детьми.',
                'body' => "Планируйте небольшое число остановок, оставляйте время на отдых, возьмите воду и лёгкий перекус. Заранее обсудите с детьми правила поведения и расскажите, какие места предстоит увидеть.\n\nДемонстрационный материал можно заменить реальной публикацией пользователя.",
                'image' => 'community-two',
            ],
            'sergiev-posad-notes-demo' => [
                'user_id' => $users['participant']->id,
                'title' => 'Заметки перед поездкой в Сергиев Посад',
                'excerpt' => 'Собираем небольшую группу и обсуждаем детали маршрута.',
                'body' => "В приложении удобно посмотреть маршрут, дату выезда, место сбора и сохранить карточку объекта. Участники совместного паломничества могут обсудить организационные вопросы в закрытом чате.\n\nЭто демонстрационная заметка.",
                'image' => 'community-one',
            ],
        ];

        $posts = [];
        foreach ($postRows as $slug => $row) {
            $post = BlogPost::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $row['user_id'], 'title' => $row['title'], 'excerpt' => $row['excerpt'], 'body' => $row['body'],
                    'status' => 'published', 'moderated_by' => $users['organizer']->id,
                    'moderated_at' => now()->subDays(2), 'published_at' => now()->subDays(count($posts) + 1),
                ]
            );
            if ($post->trashed()) {
                $post->restore();
            }

            UserMedia::query()->updateOrCreate(
                ['blog_post_id' => $post->id, 'path' => $images[$row['image']]],
                [
                    'user_id' => $row['user_id'], 'pilgrimage_object_id' => null, 'type' => 'image',
                    'title' => $row['title'], 'description' => 'Демонстрационная иллюстрация публикации.',
                    'status' => 'published', 'moderated_by' => $users['organizer']->id, 'moderated_at' => now()->subDay(),
                ]
            );
            $posts[$slug] = $post;
        }

        $gallery = [
            [$users['demo'], $objects['christ-saviour-cathedral'], $images['cathedral-gold'], 'Вечерняя Москва'],
            [$users['participant'], $objects['trinity-sergius-lavra'], $images['lavra'], 'Поездка в Сергиев Посад'],
            [$users['organizer'], $objects['novodevichy-convent'], $images['convent-blue'], 'Историческая обитель'],
        ];
        foreach ($gallery as [$user, $object, $path, $title]) {
            UserMedia::query()->updateOrCreate(
                ['user_id' => $user->id, 'pilgrimage_object_id' => $object->id, 'path' => $path],
                [
                    'type' => 'image', 'title' => $title, 'description' => 'Демонстрационная фотография сообщества.',
                    'latitude' => $object->latitude, 'longitude' => $object->longitude,
                    'status' => 'published', 'moderated_by' => $users['organizer']->id, 'moderated_at' => now()->subDay(),
                ]
            );
        }

        $jointRows = [
            'together-sergiev-posad-demo' => [
                'route' => 'road-to-sergius', 'title' => 'Вместе в Сергиев Посад',
                'description' => 'Собираем небольшую дружелюбную группу для однодневной поездки. Темп спокойный, предусмотрено время на обед и самостоятельное посещение.',
                'starts_at' => now()->addDays(18)->setTime(7, 30), 'ends_at' => now()->addDays(18)->setTime(19, 30),
                'meeting_place' => 'Москва, метро ВДНХ', 'max_participants' => 12,
                'transport_mode' => 'public', 'join_mode' => 'approval', 'contact_method' => 'in_app', 'contact_value' => null,
            ],
            'together-moscow-walk-demo' => [
                'route' => 'heart-of-orthodox-moscow', 'title' => 'Воскресная прогулка по святыням центра',
                'description' => 'Пешая прогулка без спешки. Маршрут подходит новичкам. Встречаемся у Воскресенских ворот и завершаем прогулку у Храма Христа Спасителя.',
                'starts_at' => now()->addDays(11)->setTime(10, 0), 'ends_at' => now()->addDays(11)->setTime(15, 0),
                'meeting_place' => 'Москва, Манежная площадь', 'max_participants' => 10,
                'transport_mode' => 'walk', 'join_mode' => 'auto', 'contact_method' => 'in_app', 'contact_value' => null,
            ],
        ];

        foreach ($jointRows as $slug => $row) {
            $joint = JointPilgrimage::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'organizer_id' => $users['organizer']->id,
                    'pilgrimage_route_id' => $routes[$row['route']]->id,
                    'title' => $row['title'], 'description' => $row['description'],
                    'starts_at' => $row['starts_at'], 'ends_at' => $row['ends_at'], 'meeting_place' => $row['meeting_place'],
                    'max_participants' => $row['max_participants'], 'transport_mode' => $row['transport_mode'],
                    'join_mode' => $row['join_mode'], 'contact_method' => $row['contact_method'], 'contact_value' => $row['contact_value'],
                    'status' => 'published', 'moderated_by' => $users['organizer']->id, 'moderated_at' => now()->subDay(),
                    'moderation_note' => 'Демонстрационная группа опубликована автоматически.',
                ]
            );
            if ($joint->trashed()) {
                $joint->restore();
            }

            JointPilgrimageMember::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $joint->id, 'user_id' => $users['participant']->id],
                ['status' => 'approved', 'message' => 'Буду рад присоединиться.', 'joined_at' => now()->subDays(2), 'responded_at' => now()->subDays(2)]
            );
            JointPilgrimageMember::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $joint->id, 'user_id' => $users['demo']->id],
                ['status' => $row['join_mode'] === 'auto' ? 'approved' : 'pending', 'message' => 'Подскажите, что взять с собой?', 'joined_at' => now()->subDay()]
            );

            JointPilgrimageMessage::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $joint->id, 'body' => 'Добро пожаловать! Здесь обсуждаем место встречи и организационные вопросы.'],
                ['user_id' => $users['organizer']->id, 'is_system' => false]
            );
            JointPilgrimageMessage::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $joint->id, 'body' => 'Спасибо, буду у места встречи за 15 минут до начала.'],
                ['user_id' => $users['participant']->id, 'is_system' => false]
            );
        }
    }

    private function seedUserCabinet(array $objects, array $trips, array $users): void
    {
        $reviews = [
            [$users['demo'], $objects['christ-saviour-cathedral'], 5, 'Очень удобная карточка: есть адрес, расписание и построение маршрута.'],
            [$users['participant'], $objects['trinity-sergius-lavra'], 5, 'Сохранил объект офлайн перед поездкой — полезная функция.'],
            [$users['organizer'], $objects['novodevichy-convent'], 4, 'Красивое место для спокойного тематического маршрута.'],
            [$users['demo'], $objects['nicholas-khamovniki'], 5, 'Удобно включить храм в личный пеший маршрут.'],
        ];
        foreach ($reviews as [$user, $object, $rating, $body]) {
            Review::query()->updateOrCreate(
                ['user_id' => $user->id, 'pilgrimage_object_id' => $object->id],
                ['rating' => $rating, 'body' => $body, 'status' => 'published', 'moderated_by' => $users['organizer']->id, 'moderated_at' => now()->subDay()]
            );
        }

        foreach (['christ-saviour-cathedral', 'st-basil-cathedral', 'nicholas-khamovniki'] as $index => $slug) {
            $object = $objects[$slug];
            Visit::query()->updateOrCreate(
                ['user_id' => $users['demo']->id, 'pilgrimage_object_id' => $object->id],
                [
                    'visited_at' => now()->subDays(($index + 1) * 5), 'verification_method' => 'geo', 'status' => 'verified',
                    'latitude' => $object->latitude, 'longitude' => $object->longitude, 'notes' => 'Демонстрационная подтверждённая отметка.',
                ]
            );
        }

        $favorites = FavoriteList::query()->updateOrCreate(
            ['user_id' => $users['demo']->id, 'is_default' => true],
            ['name' => 'Избранное']
        );
        $favorites->objects()->sync([
            $objects['christ-saviour-cathedral']->id,
            $objects['trinity-sergius-lavra']->id,
            $objects['novodevichy-convent']->id,
        ]);

        $plan = UserRoutePlan::query()->updateOrCreate(
            ['user_id' => $users['demo']->id, 'name' => 'Мой маршрут по центру'],
            ['transport_mode' => 'walk', 'estimated_minutes' => 240, 'notes' => 'Демонстрационный личный маршрут.']
        );
        $plan->objects()->sync([
            $objects['iveron-chapel']->id => ['sort_order' => 1, 'stay_minutes' => 20],
            $objects['st-basil-cathedral']->id => ['sort_order' => 2, 'stay_minutes' => 60],
            $objects['christ-saviour-cathedral']->id => ['sort_order' => 3, 'stay_minutes' => 60],
        ]);

        Booking::query()->updateOrCreate(
            ['ticket_code' => 'MP-DEMO-001'],
            [
                'trip_id' => $trips['sergius']->id, 'user_id' => $users['demo']->id,
                'contact_name' => $users['demo']->name, 'email' => $users['demo']->email, 'phone' => $users['demo']->phone,
                'participants_count' => 2, 'total_amount' => 5800, 'status' => 'pending', 'payment_status' => 'unpaid',
                'payment_provider' => null, 'payment_reference' => null, 'checked_in_at' => null, 'checked_in_by' => null,
                'checked_in_participants' => 0, 'notes' => 'Демонстрационное бронирование для проверки QR-билета.',
            ]
        );
    }

    private function svg(string $title, string $subtitle, string $from, string $to): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $subtitle = htmlspecialchars($subtitle, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000" viewBox="0 0 1600 1000" role="img" aria-label="{$title}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$from}"/>
      <stop offset="1" stop-color="{$to}"/>
    </linearGradient>
    <filter id="shadow"><feDropShadow dx="0" dy="18" stdDeviation="22" flood-opacity=".22"/></filter>
  </defs>
  <rect width="1600" height="1000" fill="url(#bg)"/>
  <circle cx="1300" cy="180" r="240" fill="#fff" opacity=".08"/>
  <circle cx="180" cy="850" r="300" fill="#fff" opacity=".06"/>
  <g transform="translate(800 430)" fill="#fff8e8" filter="url(#shadow)">
    <rect x="-330" y="90" width="660" height="300" rx="24"/>
    <rect x="-235" y="-15" width="180" height="405" rx="18"/>
    <rect x="55" y="-15" width="180" height="405" rx="18"/>
    <path d="M-250-15 Q-145-190 -40-15Z"/>
    <path d="M40-15 Q145-190 250-15Z"/>
    <circle cx="-145" cy="-78" r="36"/>
    <circle cx="145" cy="-78" r="36"/>
    <rect x="-156" y="-160" width="22" height="82" rx="5"/>
    <rect x="-186" y="-133" width="82" height="20" rx="5"/>
    <rect x="134" y="-160" width="22" height="82" rx="5"/>
    <rect x="104" y="-133" width="82" height="20" rx="5"/>
    <path d="M-74 390 V190 Q0 110 74 190 V390Z" fill="{$from}" opacity=".85"/>
  </g>
  <text x="800" y="850" text-anchor="middle" fill="#fff" font-family="Georgia, serif" font-size="68" font-weight="700">{$title}</text>
  <text x="800" y="920" text-anchor="middle" fill="#fff" opacity=".82" font-family="Arial, sans-serif" font-size="30" letter-spacing="4">{$subtitle}</text>
</svg>
SVG;
    }
}
