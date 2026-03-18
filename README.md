# ⚡ LPhenom — PHP framework kernel

**lphenom/lphenom** — ядро фреймворка LPhenom, которое связывает все подпакеты
и предоставляет Laravel-like bootstrap.

## Установка

```bash
composer require lphenom/lphenom
```

## Быстрый старт

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use LPhenom\Http\Request;
use LPhenom\LPhenom\AppFactory;
use LPhenom\LPhenom\Http\HttpKernel;

$config   = AppFactory::loadConfig(__DIR__);
$app      = AppFactory::create(__DIR__, $config);
$kernel   = $app->getContainer()->get(HttpKernel::class);
$response = $kernel->handle(Request::fromGlobals());
$response->send();
```

## CLI Tool

```bash
php bin/lphenom make:migration create_users_table  # Create migration file
php bin/lphenom migrate          # Run migrations
php bin/lphenom migrate:rollback # Rollback last batch
php bin/lphenom migrate:status   # Show migration status
php bin/lphenom queue:work       # Start queue worker
php bin/lphenom serve            # Development server
php bin/lphenom build:phar       # Build PHAR for shared hosting
php bin/lphenom build:kphp       # Build KPHP binary
```

## Включённые пакеты

| Пакет | Назначение |
|---|---|
| [lphenom/core](https://github.com/lphenom/core) | Container, Config, EnvLoader, Utils |
| [lphenom/http](https://github.com/lphenom/http) | Router, Middleware, Request/Response |
| [lphenom/log](https://github.com/lphenom/log) | Logging (File, Null, Stdout) |
| [lphenom/db](https://github.com/lphenom/db) | Database (PDO, FFI MySQL) |
| [lphenom/cache](https://github.com/lphenom/cache) | Caching (File, Redis, DB) |
| [lphenom/redis](https://github.com/lphenom/redis) | Redis client (ext-redis, RESP) |
| [lphenom/queue](https://github.com/lphenom/queue) | Queue (DB, Redis) |
| [lphenom/realtime](https://github.com/lphenom/realtime) | Realtime (Long polling, WebSocket) |
| [lphenom/storage](https://github.com/lphenom/storage) | File storage |
| [lphenom/media](https://github.com/lphenom/media) | Image/Video processing |
| [lphenom/migrate](https://github.com/lphenom/migrate) | Database migrations |

## Два режима работы

### 🏠 Shared Hosting (PHP)

- Apache/Nginx + PHP
- Очереди через cron
- Realtime через long polling
- Деплой через PHAR или FTP

### 🧱 Compiled (KPHP)

- Статический бинарник
- Встроенный queue worker
- WebSocket через Redis pub/sub
- Высокая производительность

## Документация

- [Kernel — Как собрать приложение](docs/kernel.md)
- [Hosting — Shared & KPHP](docs/hosting.md)

## KPHP-совместимость

Весь код совместим с [KPHP](https://github.com/VKCOM/kphp):

- ✅ `declare(strict_types=1)` в каждом файле
- ✅ Нет reflection, eval, dynamic class loading
- ✅ Все зависимости регистрируются явно через интерфейсы
- ✅ Нет callable в массивах — используются `ServiceFactoryInterface`
- ✅ Нет constructor property promotion, readonly, match
- ✅ Нет str_starts_with/str_ends_with/str_contains

## Разработка

```bash
make up        # Запуск MySQL + Redis
make install   # Установка зависимостей
make test      # Запуск тестов
make lint      # Проверка стиля кода
make analyse   # Статический анализ
make check     # Все проверки
```

## Лицензия

MIT — см. [LICENSE](LICENSE)
