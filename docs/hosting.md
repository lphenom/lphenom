# Hosting Guide — Shared & KPHP

LPhenom поддерживает два режима развёртывания из одной кодовой базы.

---

## Режим 1: Shared Hosting (PHP)

### Архитектура

```
[Client] → [Apache/Nginx + PHP] → public/index.php → HttpKernel
                                 → cron → bin/lphenom queue:work --once
                                 → JS long-polling → /realtime/poll
```

### Установка

1. Загрузить проект на хостинг (FTP/Git/PHAR)
2. Указать document root на `public/`
3. Настроить `.env`
4. Запустить миграции: `php bin/lphenom migrate`

### Очереди через cron

Добавить в crontab:

```cron
# Обрабатывать одну задачу каждую минуту
* * * * * cd /path/to/app && php bin/lphenom queue:work --once >> /tmp/queue.log 2>&1
```

Или обрабатывать несколько задач за запуск:

```cron
* * * * * cd /path/to/app && php bin/lphenom queue:work --max-jobs=5 >> /tmp/queue.log 2>&1
```

### Realtime через Long Polling

В shared hosting режиме WebSocket недоступен. Используется long-polling.

#### Серверная часть

```php
// routes.php
use LPhenom\Realtime\Http\PollHandler;
use LPhenom\Realtime\RealtimeBusInterface;

/** @var RealtimeBusInterface $bus */
$bus = $container->get(RealtimeBusInterface::class);
$router->get('/realtime/poll', new PollHandler($bus));
```

#### Публикация событий

```php
use LPhenom\Realtime\Message;
use LPhenom\Realtime\RealtimeBusInterface;

/** @var RealtimeBusInterface $bus */
$bus = $container->get(RealtimeBusInterface::class);

$msg = new Message(0, 'chat', '{"text":"Hello!"}', new \DateTimeImmutable());
$bus->publish('chat', $msg);
```

#### Клиентская часть (JavaScript)

```javascript
let lastId = 0;

async function poll() {
    try {
        const res = await fetch(`/realtime/poll?topic=chat&since=${lastId}`);
        const data = await res.json();

        for (const msg of data.messages) {
            console.log('New message:', msg.payload);
        }

        if (data.last_id > lastId) {
            lastId = data.last_id;
        }
    } catch (e) {
        console.error('Poll error:', e);
    }

    // Poll every 3 seconds
    setTimeout(poll, 3000);
}

poll();
```

### Сборка PHAR

```bash
php bin/lphenom build:phar
# Результат: build/lphenom.phar

# Запуск на shared hosting:
php lphenom.phar serve
php lphenom.phar migrate
php lphenom.phar queue:work --once
```

---

## Режим 2: KPHP Compiled Binary

### Архитектура

```
[Client] → [KPHP binary (HTTP server)] → routes → handlers
            ├── WebSocket server (built-in)
            └── Queue worker (built-in event loop)
```

### Сборка

```bash
# Через Docker
docker build -f Dockerfile.check -t myapp-check .

# Или через CLI (если KPHP установлен)
php bin/lphenom build:kphp
```

### Запуск бинарника

```bash
# HTTP сервер (порт 8080)
./build/kphp-out/server --port 8080

# Очередь (встроенный worker loop)
# В KPHP режиме worker запускается как часть сервера через fork()
```

### WebSocket в KPHP

В KPHP режиме realtime работает через WebSocket + Redis pub/sub:

#### Конфигурация

```php
// config/realtime.php
return [
    'driver'  => 'websocket',  // использовать WebSocketBus
    'enabled' => true,
];

// config/redis.php
return [
    'host' => '127.0.0.1',
    'port' => 6379,
];
```

#### Серверная часть (KPHP entrypoint)

```php
// В KPHP бинарнике WebSocket сервер запускается из event loop
use LPhenom\Realtime\Bus\WebSocketBus;
use LPhenom\Realtime\Bus\DbEventStoreBus;
use LPhenom\Redis\PubSub\RedisPublisher;
use LPhenom\Redis\PubSub\RedisSubscriber;
use LPhenom\Redis\Client\RespRedisClient;
use LPhenom\Redis\Resp\RespClient;

// Redis подключение (KPHP-совместимо — чистый TCP RESP)
$resp = new RespClient('127.0.0.1', 6379);
$redis = new RespRedisClient($resp);

// Подписка на realtime события
$subscriber = new RedisSubscriber($redis);
$subscriber->subscribe('realtime:chat', new class implements \LPhenom\Redis\PubSub\MessageHandlerInterface {
    public function handle(string $channel, string $message): void
    {
        // Отправить клиентам через WebSocket
        echo 'WS broadcast: ' . $message . PHP_EOL;
    }
});
```

#### Клиентская часть (JavaScript WebSocket)

```javascript
const ws = new WebSocket('ws://myapp.com:8080/ws');

ws.onopen = () => {
    ws.send(JSON.stringify({ action: 'subscribe', topic: 'chat' }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Realtime message:', data);
};

ws.onclose = () => {
    console.log('Connection closed, reconnecting...');
    setTimeout(() => { /* reconnect logic */ }, 1000);
};
```

### Очереди в KPHP

```php
use LPhenom\Queue\Worker;
use LPhenom\Queue\Driver\RedisQueue;
use LPhenom\Queue\Retry\RetryPolicy;
use LPhenom\Redis\Client\RespRedisClient;
use LPhenom\Redis\Resp\RespClient;

$resp = new RespClient('127.0.0.1', 6379);
$redis = new RespRedisClient($resp);

$queue  = new RedisQueue($redis, 'queue:jobs', new RetryPolicy(3, 1));
$worker = new Worker($queue);

// Зарегистрировать обработчики
$worker->register('send_email', new SendEmailHandler());
$worker->register('process_order', new ProcessOrderHandler());

// KPHP: встроенный event loop, блокирующий (BLPOP)
$worker->run(5, 0);  // run forever
```

---

## Сравнение режимов

| | Shared Hosting (PHP) | KPHP Binary |
|---|---|---|
| **Runtime** | Apache/Nginx + PHP | Статический бинарник |
| **Очереди** | Cron (`queue:work --once`) | Встроенный worker (BLPOP) |
| **Realtime** | Long polling (`/realtime/poll`) | WebSocket + Redis pub/sub |
| **Масштабируемость** | Ограничена хостингом | Высокая производительность |
| **Деплой** | FTP / Git / PHAR | Docker / бинарник |
| **БД** | PDO MySQL | FFI MySQL / PDO MySQL |
| **Redis** | ext-redis (если есть) | RespRedisClient (чистый TCP) |

---

## Docker-окружение разработки

```bash
# Запуск MySQL + Redis
make up

# Установка зависимостей
make install

# Тесты
make test

# Линтер
make lint

# Статический анализ
make analyse

# Сборка PHAR
make build-phar

# Проверка KPHP + PHAR
make kphp-check
```

