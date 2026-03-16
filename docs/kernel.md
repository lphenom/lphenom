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

| Провайдер | Сервис | Конфиг |
|---|---|---|
| `LogServiceProvider` | `LoggerInterface` | `log.*` |
| `DatabaseServiceProvider` | `ConnectionInterface` | `database.*` |
| `RedisServiceProvider` | `RedisClientInterface` | `redis.*` |
| `CacheServiceProvider` | `CacheInterface` | `cache.*` |
| `StorageServiceProvider` | `StorageInterface` | `storage.*` |
| `QueueServiceProvider` | `QueueInterface`, `Worker` | `queue.*` |
| `RealtimeServiceProvider` | `RealtimeBusInterface` | `realtime.*` |
| `MigrateServiceProvider` | `Migrator`, `MigrationRegistry` | — |
| `HttpServiceProvider` | `Router`, `MiddlewareStack`, `HttpKernel` | — |

## Миграции

Ядро автоматически регистрирует миграции из подпакетов:

- **realtime_events** — таблица для realtime событий (`lphenom/realtime`)
- **jobs** — таблица очередей (`lphenom/queue`)

Пользовательские миграции передаются через `AppFactory`:

```php
$app = AppFactory::create($basePath, $config, [
    new CreateUsersTable(),
    new CreatePostsTable(),
]);
```

## KPHP-совместимость

Весь код ядра совместим с KPHP:

- Нет reflection, eval, dynamic class loading
- Нет callable в массивах — используются интерфейсы (ServiceFactoryInterface)
- Нет constructor property promotion, readonly
- Нет match expressions
- Нет str_starts_with/str_ends_with/str_contains
- `declare(strict_types=1)` в каждом файле

