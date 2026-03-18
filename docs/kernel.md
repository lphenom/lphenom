# Kernel — Как собрать приложение

`lphenom/lphenom` — это ядро фреймворка LPhenom. Оно связывает все подпакеты
(core, http, log, db, cache, redis, queue, realtime, storage, media, migrate)
и предоставляет Laravel-like bootstrap.

## Быстрый старт

### 1. Установка

```bash
composer require lphenom/lphenom
```

### 2. Создание структуры проекта

```
myapp/
├── config/
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   ├── redis.php
│   ├── queue.php
│   ├── realtime.php
│   ├── storage.php
│   └── log.php
├── database/
│   └── migrations/
│       └── 20260313000001_create_users_table.php
├── public/
│   └── index.php
├── build/
│   ├── kphp-entrypoint.php
│   └── build-phar.php
├── .env
└── composer.json
```

### 3. Файл `.env`

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=myapp
DB_USER=root
DB_PASS=secret
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. Конфигурация `config/database.php`

```php
<?php
declare(strict_types=1);

use LPhenom\Core\EnvLoader\EnvLoader;

$env = new EnvLoader();

return [
    'driver'   => 'pdo_mysql',
    'host'     => $env->get('DB_HOST', '127.0.0.1'),
    'port'     => (int) ($env->get('DB_PORT', '3306')),
    'dbname'   => $env->get('DB_NAME', 'myapp'),
    'user'     => $env->get('DB_USER', 'root'),
    'password' => $env->get('DB_PASS', ''),
];
```

### 5. Точка входа `public/index.php`

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LPhenom\Http\Request;
use LPhenom\Http\Router;
use LPhenom\LPhenom\AppFactory;
use LPhenom\LPhenom\Http\HttpKernel;

// Загрузить конфиг и построить приложение
$config = AppFactory::loadConfig(dirname(__DIR__));
$app    = AppFactory::create(dirname(__DIR__), $config);

// Получить Router и зарегистрировать маршруты
$router = $app->getContainer()->get(Router::class);
// ... зарегистрировать свои маршруты ...

// Обработать запрос
$kernel   = $app->getContainer()->get(HttpKernel::class);
$request  = Request::fromGlobals();
$response = $kernel->handle($request);
$response->send();
```

## Основные сущности

### Application

Главный контейнер приложения. Содержит `Container`, `Config` и список провайдеров.

```php
use LPhenom\LPhenom\Application;

$app = new Application($container, $config, '/path/to/project');
$app->addProvider(new MyProvider());
$app->boot();
```

### ServiceProviderInterface

```php
use LPhenom\LPhenom\ServiceProviderInterface;

final class MyProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        // Зарегистрировать сервисы
    }

    public function boot(Container $container, Config $config): void
    {
        // Инициализация после регистрации всех провайдеров
    }
}
```

### AppFactory

```php
// Веб-приложение (все провайдеры)
$app = AppFactory::create($basePath, $config);

// Консольное приложение (без HTTP)
$app = AppFactory::createForConsole($basePath, $config);

// Загрузка конфига из файлов
$config = AppFactory::loadConfig($basePath);
```

### HttpKernel

```php
use LPhenom\LPhenom\Http\HttpKernel;

$kernel   = new HttpKernel($router, $middlewareStack);
$response = $kernel->handle($request);
```

## CLI Tool — `bin/lphenom`

```bash
# Показать помощь
php bin/lphenom --help

# Миграции
php bin/lphenom make:migration create_users_table
php bin/lphenom migrate
php bin/lphenom migrate:rollback
php bin/lphenom migrate:status

# Очередь
php bin/lphenom queue:work
php bin/lphenom queue:work --once
php bin/lphenom queue:work --max-jobs=10

# Сборка
php bin/lphenom build:phar           # → build/lphenom.phar
php bin/lphenom build:phar --output=dist/myapp.phar
php bin/lphenom build:kphp           # → build/kphp-out/
php bin/lphenom build:kphp --output=build/my-out

# Сервер разработки
php bin/lphenom serve
php bin/lphenom serve --port=9000
```

## Встроенные провайдеры

### PHP-провайдеры (shared hosting)

| Провайдер | Сервис | Драйвер | Конфиг |
|---|---|---|---|
| `DatabaseServiceProvider` | `ConnectionInterface` | ext-pdo_mysql | `database.*` |
| `RedisServiceProvider` | `RedisClientInterface` | ext-redis | `redis.*` |
| `MediaServiceProvider` | `ImageProcessorInterface`, `VideoProcessorInterface` | ext-gd + ffmpeg | `media.*` |

### KPHP-совместимые провайдеры

| Провайдер | Сервис | Драйвер | Конфиг |
|---|---|---|---|
| `FfiDatabaseServiceProvider` | `ConnectionInterface` | FFI + libmysqlclient | `database.*` |
| `RespRedisServiceProvider` | `RedisClientInterface` | TCP/RESP протокол | `redis.*` |
| `CliMediaServiceProvider` | `ImageProcessorInterface`, `VideoProcessorInterface` | ImageMagick CLI + ffmpeg | `media.*` |

### Общие провайдеры (работают везде)

| Провайдер | Сервис | Конфиг |
|---|---|---|
| `LogServiceProvider` | `LoggerInterface` | `log.*` |
| `CacheServiceProvider` | `CacheInterface` | `cache.*` |
| `StorageServiceProvider` | `StorageInterface` | `storage.*` |
| `QueueServiceProvider` | `QueueInterface`, `Worker` | `queue.*` |
| `RealtimeServiceProvider` | `RealtimeBusInterface` | `realtime.*` |
| `MigrateServiceProvider` | `Migrator`, `MigrationRegistry` | — |
| `HttpServiceProvider` | `Router`, `MiddlewareStack`, `HttpKernel` | — |

## Миграции

### Автообнаружение

`AppFactory` автоматически сканирует `database/migrations/` через `MigrationLoader`,
загружает каждый PHP-файл и регистрирует классы в `MigrationRegistry`.

Файлы миграций следуют конвенции именования:

```
database/migrations/
├── 20260313000001_create_realtime_events_table.php  → Migration20260313000001CreateRealtimeEventsTable
├── 20260313000002_create_jobs_table.php              → Migration20260313000002CreateJobsTable
└── 20260313000003_create_cache_table.php             → Migration20260313000003CreateCacheTable
```

Каждый класс реализует `MigrationInterface` и определяет `up()`, `down()`, `getVersion()`.

### Создание миграции

```bash
php bin/lphenom make:migration create_users_table
# → Created migration: database/migrations/20260318000001_create_users_table.php
```

Команда генерирует файл с правильным именем класса, версией и шаблоном.
Имя должно содержать только `[a-z0-9_]`. Порядковый номер автоинкрементируется
в рамках дня.

### Добавление своих миграций

Создайте файл в `database/migrations/` по конвенции:

```php
// database/migrations/20260318000001_create_users_table.php
<?php
declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

final class Migration20260318000001CreateUsersTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        $conn->execute('CREATE TABLE IF NOT EXISTS users (...)', []);
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS users', []);
    }

    public function getVersion(): string
    {
        return '20260318000001';
    }
}
```

Дополнительные миграции (не из директории) можно передать через `AppFactory`:

```php
$app = AppFactory::create($basePath, $config, [
    new CreateUsersTable(),
    new CreatePostsTable(),
]);
```

### KPHP-режим

В KPHP динамическая загрузка файлов невозможна. `MigrationLoader` помечен
`@lphenom-build none` и не входит в KPHP-сборку. Миграции передаются
явно через `KphpAppFactory`:

```php
$app = KphpAppFactory::create($basePath, $config, [
    new Migration20260313000001CreateRealtimeEventsTable(),
    new Migration20260313000002CreateJobsTable(),
    new Migration20260313000003CreateCacheTable(),
]);
```

Файлы миграций подключаются в KPHP-энтрипоинт статически через
`MigrationFileScanner` на этапе сборки (подробнее: [docs/build.md](build.md)).

## KPHP-совместимость

Весь код ядра совместим с KPHP:

- Нет reflection, eval, dynamic class loading
- Нет callable в массивах — используются интерфейсы (ServiceFactoryInterface)
- Нет constructor property promotion, readonly
- Нет match expressions
- Нет str_starts_with/str_ends_with/str_contains
- `declare(strict_types=1)` в каждом файле

### Две фабрики приложения

```php
// PHP runtime (shared hosting, ext-pdo_mysql + ext-redis + ext-gd)
$app = AppFactory::create($basePath, $config);

// KPHP binary (или PHP без расширений)
use LPhenom\LPhenom\KphpAppFactory;
$app = KphpAppFactory::create($basePath, $config);
```

`KphpAppFactory` использует KPHP-совместимые провайдеры:
FFI MySQL, RESP Redis, ImageMagick CLI.

> Подробнее: [docs/build.md](build.md) — аннотации `@lphenom-build`,
> резолвер зависимостей, порядок файлов, конвейер сборки.

