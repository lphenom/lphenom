# Система сборки LPhenom

LPhenom поддерживает два режима сборки:

| Цель | Артефакт | Описание |
|---|---|---|
| **shared** | `lphenom.phar` | PHAR-архив для PHP shared hosting (ext-pdo_mysql, ext-redis, ext-gd) |
| **kphp** | Нативный бинарник | Компилируется KPHP в машинный код (FFI MySQL, RESP Redis, CLI tools) |

Оба артефакта собираются и проверяются через `docker build -f Dockerfile.check .`.

---

## 1. Аннотации `@lphenom-build`

Каждый `.php`-файл может содержать аннотацию в docblock-комментарии класса,
определяющую, в какие сборки он попадает:

```php
/**
 * @lphenom-build shared,kphp
 */
final class MyService { ... }
```

### Допустимые значения

| Аннотация | shared (PHAR) | kphp (бинарник) | Пример |
|---|---|---|---|
| _(нет аннотации)_ | ✅ | ✅ | `Application.php` — базовые классы |
| `@lphenom-build shared,kphp` | ✅ | ✅ | `FfiMySqlConnection.php` — работает везде |
| `@lphenom-build shared` | ✅ | ❌ | `GdImageProcessor.php` — нужен ext-gd |
| `@lphenom-build kphp` | ❌ | ✅ | _(специфичный для KPHP код)_ |
| `@lphenom-build none` | ❌ | ❌ | `DependencyResolver.php` — только dev-утилиты |

### Правила парсинга

- Аннотация ищется **только внутри docblock-комментариев** (`/** ... */`).
- Допустимы оба формата: `shared,kphp` (без пробела) и `shared, kphp` (с пробелом).
- Сканируются только первые **4 КБ** файла (для скорости).
- Файл без аннотации **включается везде** (эквивалент `all`).

### Какие файлы помечены `none`

Все build-time утилиты, которые используют PHP 8.1+ конструкции (closures с `&`,
dynamic `require`), не нужные в рантайме:

```
src/Build/BuildAnnotationScanner.php    — сканер аннотаций
src/Build/DependencyResolver.php        — граф зависимостей
src/Build/KphpEntrypointGenerator.php   — генератор entrypoint
src/Build/MigrationFileScanner.php      — сканер миграций (build-time)
src/Build/MigrationLoader.php           — загрузчик миграций (PHP runtime)
src/Build/DevPackageFilter.php          — фильтр dev-пакетов
src/Build/PharFileFilter.php            — фильтр PHAR-файлов
src/Console/Command/BuildKphpCommand.php
src/Console/Command/BuildManifestCommand.php
src/Console/Command/BuildPharCommand.php
```

---

## 2. Провайдеры: shared vs KPHP

Три сервиса зависят от PHP-расширений, недоступных в KPHP.
Для них созданы **парные провайдеры**:

| Сервис | PHP-версия (`@shared`) | KPHP-версия (`@shared,kphp`) |
|---|---|---|
| Database | `DatabaseServiceProvider` → `ConnectionFactory` → ext-pdo_mysql | `FfiDatabaseServiceProvider` → `FfiMySqlConnection` → FFI + libmysqlclient |
| Redis | `RedisServiceProvider` → `RedisConnector` → ext-redis | `RespRedisServiceProvider` → `RespRedisClient` → TCP/RESP протокол |
| Media | `MediaServiceProvider` → `GdImageProcessor` → ext-gd | `CliMediaServiceProvider` → `ImageMagickProcessor` → `convert` CLI |

### Фабрики приложения

```php
// PHP runtime (shared hosting, максимальная производительность)
$app = AppFactory::create($basePath, $config);
//   → DatabaseServiceProvider, RedisServiceProvider, MediaServiceProvider

// KPHP binary (или PHP без расширений)
$app = KphpAppFactory::create($basePath, $config);
//   → FfiDatabaseServiceProvider, RespRedisServiceProvider, CliMediaServiceProvider
```

KPHP-провайдеры помечены `@lphenom-build shared,kphp` — они работают
**в обоих режимах**, потому что используют драйверы без PHP-расширений:

- `FfiMySqlConnection` → FFI + libmysqlclient.so (работает и в PHP, и в KPHP)
- `RespRedisClient` → чистый TCP-сокет + RESP-протокол
- `ImageMagickProcessor` → вызов CLI `convert` через `exec()`

Общие провайдеры (Log, Cache, Storage, Queue, Realtime, Migrate, Http)
уже совместимы с KPHP и используются обеими фабриками.

---

## 3. Резолвер зависимостей и порядок файлов

KPHP не поддерживает Composer PSR-4 autoloading. Все файлы подключаются
через `require_once` в **строгом порядке зависимостей** — если класс `A`
использует класс `B`, файл `B.php` должен быть подключён раньше `A.php`.

### Как работает `DependencyResolver`

#### Шаг 1: Построение графа зависимостей

Для каждого файла парсятся (без рефлексии, чистым regex):

```
1. use-стейтменты:        use LPhenom\Http\Router;
2. extends/implements:     class Foo extends Bar implements Baz
3. type hints параметров:  public function call(Router $router): void
4. return type hints:      public function getRouter(): Router
```

**Критически важно**: парсер разрешает **короткие имена классов внутри одного
namespace**. Пример — файл `RouterGroupCallback.php`:

```php
namespace LPhenom\Http;

interface RouterGroupCallback
{
    public function call(Router $router): void;
    //                   ^^^^^^
    //  Нет use-импорта! Резолвится как LPhenom\Http\Router
    //  (тот же namespace)
}
```

Алгоритм разрешения имени типа:

```
1. Имя содержит `\` → это уже FQCN, используем как есть
2. Имя есть в import map (use-стейтменты) → берём из map
3. Иначе → текущий namespace + `\` + имя (same-namespace resolution)
```

Затем FQCN разрешается в файл через PSR-4 маппинг
(`vendor/composer/autoload_psr4.php`).

#### Шаг 2: Топологическая сортировка (DFS)

Стандартный алгоритм DFS с тремя состояниями:

```
0 = не посещён
1 = в процессе посещения (visiting)
2 = посещён (visited)
```

```
visit(node):
  if state[node] == 2 → skip (уже обработан)
  if state[node] == 1 → skip (циклическая зависимость)
  state[node] = 1
  for each dep in graph[node]:
      visit(dep)
  state[node] = 2
  sorted[] = node     ← зависимости добавляются ПЕРЕД зависимым
```

**Результат**: файлы в порядке «зависимости раньше зависимых».

#### Пример: пакет `lphenom/http`

Граф зависимостей (упрощённый):

```
Request.php       → (нет LPhenom-зависимостей)
Response.php      → (нет LPhenom-зависимостей)
HandlerInterface  → Request, Response
Next              → HandlerInterface, Request, Response
MiddlewareInterface → HandlerInterface, Request, Response
MiddlewareStack   → MiddlewareInterface, Next, Request, Response
RouterGroupCallback → Router            ← same-namespace type hint!
Router            → RouterGroupCallback, RouteMatch, Request, Response, HandlerInterface
```

`Router` ↔ `RouterGroupCallback` — **циклическая зависимость**:
- `RouterGroupCallback::call(Router $router)` → зависит от Router
- `Router::group($prefix, RouterGroupCallback $callback)` → зависит от RouterGroupCallback

DFS разрешает цикл: первый посещённый узел выходит первым. Оба файла
в итоге попадают в entrypoint. KPHP обрабатывает циклические зависимости между
классами корректно — он читает все файлы и разрешает классы многопроходно.

Итоговый порядок в entrypoint:

```php
// === lphenom/http ===
require_once __DIR__ . '/../vendor/lphenom/http/src/Request.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Response.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/HandlerInterface.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Next.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/MiddlewareInterface.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/MiddlewareStack.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/RouterGroupCallback.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/RouteMatch.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Router.php';
```

#### Шаг 3: Потоковые заголовки пакетов

Файлы НЕ группируются по пакетам — они идут в **глобальном** топологическом порядке.
Комментарий `// === lphenom/http ===` вставляется при смене пакета.
Это значит, что файлы одного пакета могут появляться в нескольких местах:

```php
// === lphenom/db ===
require_once '...ConnectionInterface.php';     // Нужен для Migrate

// === lphenom/migrate ===
require_once '...SchemaRepository.php';         // Зависит от ConnectionInterface

// === lphenom/db ===                           // Опять db!
require_once '...ParamBinder.php';              // Нужен позже
```

Это правильное поведение — гарантирует, что КАЖДЫЙ файл подключается
после ВСЕХ своих зависимостей.

---

## 4. Миграции в KPHP

KPHP не поддерживает динамический `require_once` с переменным путём:

```php
// ❌ Не компилируется в KPHP
$file = $this->path . '/' . $name;
require_once $file;
```

Поэтому `MigrationLoader` помечен `@lphenom-build shared` — он работает
только в PHP. Для KPHP миграции подключаются **статически** через entrypoint.

### Как это работает

1. `MigrationFileScanner` сканирует `database/migrations/` при **сборке**
2. Находит все `*.php` файлы, сортирует по имени (timestamp-prefix)
3. `KphpEntrypointGenerator` добавляет их в конец entrypoint:

```php
// === User migrations ===
require_once __DIR__ . '/../database/migrations/20260313000001_create_realtime_events_table.php';
require_once __DIR__ . '/../database/migrations/20260313000002_create_jobs_table.php';
require_once __DIR__ . '/../database/migrations/20260313000003_create_cache_table.php';
```

**Важно**: файлы миграций НЕ вызывают `MigrationAutoRegistrar::register()`
на уровне файла. Регистрация миграций происходит явно через `MigrateServiceProvider`.

---

## 5. Конвейер сборки (Dockerfile.check)

Три стадии Docker multi-stage build:

```
┌─────────────────────────────────────────────────────┐
│ Stage 0: entrypoint-gen (PHP 8.1)                   │
│                                                     │
│  1. composer install                                │
│  2. php check-kphp-compat.php   → синтаксис-чек    │
│  3. php generate-kphp-entrypoint.php → entrypoint   │
└──────────────────────┬──────────────────────────────┘
                       │ COPY kphp-entrypoint.generated.php
                       ▼
┌─────────────────────────────────────────────────────┐
│ Stage 1: kphp-build (vkcom/kphp)                    │
│                                                     │
│  1. COPY vendor/, src/, database/ from context      │
│  2. COPY entrypoint from Stage 0                    │
│  3. kphp -d /build/kphp-out -M cli entrypoint.php  │
│  4. Запуск бинарника → smoke test                   │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Stage 2: phar-build (PHP 8.1)                       │
│                                                     │
│  1. php build-phar.php → lphenom.phar               │
│     - Исключает dev-пакеты                          │
│     - Фильтрует @lphenom-build kphp / none          │
│     - Фильтрует autoload_files.php и                │
│       autoload_static.php (удаляет dev-записи)      │
│  2. php smoke-test-phar.php → smoke test            │
└─────────────────────────────────────────────────────┘
```

### Зачем Stage 0 отдельно?

KPHP-контейнер (`vkcom/kphp`) содержит PHP 7.4. Composer autoloader и
build-утилиты требуют PHP 8.1+. Поэтому генерация entrypoint выполняется
в PHP 8.1 контейнере, а результат копируется в KPHP-контейнер.

### Фильтрация autoloader для PHAR

Composer записывает **все** зависимости (включая dev) в `autoload_static.php`
и `autoload_files.php`. При сборке PHAR dev-пакеты исключаются, но autoloader
всё ещё пытается загрузить их файлы → Fatal Error.

Решение: `build-phar.php` перезаписывает оба файла в PHAR, удаляя записи
dev-пакетов (react/promise, phpunit, phpstan и т.д.).

---

## 6. Проверка KPHP-совместимости

Скрипт `build/check-kphp-compat.php` проверяет **все** `vendor/lphenom/*/src/`
и `src/` файлы на несовместимый синтаксис:

| Проверка | Пример |
|---|---|
| Trailing commas в параметрах функций | `function foo(int $a,) {}` |
| Constructor property promotion | `public function __construct(private string $x)` |
| `enum` декларации | `enum Status: string {}` |
| `readonly` свойства | `private readonly string $name;` |
| Intersection types | `Foo&Bar` |
| `Fiber` | `new Fiber(...)` |

> Проверка НЕ фейлит билд — это предупреждения. Файлы с проблемным синтаксисом
> должны иметь `@lphenom-build shared` или `none` чтобы не попасть в KPHP-сборку.

---

## 7. Запуск сборки

### Через Docker (рекомендуется для CI)

```bash
# Полная проверка: KPHP + PHAR
docker build -f Dockerfile.check -t myapp-check .

# Только KPHP
docker build --target kphp-build -f Dockerfile.check -t myapp-kphp .

# Только PHAR
docker build --target phar-build -f Dockerfile.check -t myapp-phar .
```

### Через CLI

```bash
# Генерация entrypoint
php build/generate-kphp-entrypoint.php

# Сборка KPHP (нужен kphp в PATH)
php bin/lphenom build:kphp

# Сборка PHAR
php -d phar.readonly=0 bin/lphenom build:phar

# Манифест — показать все файлы и их аннотации
php bin/lphenom build:manifest
php bin/lphenom build:manifest --target=kphp
```

---

## 8. Диагностика: build:manifest

```bash
$ php bin/lphenom build:manifest --target=kphp
```

Показывает все файлы, попадающие в указанную сборку, с их аннотациями
и зависимостями. Полезно для проверки: «почему мой файл не попал в KPHP?».

