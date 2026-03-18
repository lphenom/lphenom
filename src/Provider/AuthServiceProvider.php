<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Auth\Contracts\AuditListenerInterface;
use LPhenom\Auth\Contracts\AuthManagerInterface;
use LPhenom\Auth\Contracts\LoginThrottleInterface;
use LPhenom\Auth\Contracts\PasswordHasherInterface;
use LPhenom\Auth\Contracts\TokenEncoderInterface;
use LPhenom\Auth\Contracts\TokenRepositoryInterface;
use LPhenom\Auth\Contracts\UserProviderInterface;
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Hashing\CryptPasswordHasher;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Auth\Support\CacheThrottle;
use LPhenom\Auth\Support\DbTokenRepository;
use LPhenom\Auth\Support\DefaultAuthManager;
use LPhenom\Auth\Support\HttpAuthBridge;
use LPhenom\Auth\Support\InMemoryTokenRepository;
use LPhenom\Auth\Support\LogAuditListener;
use LPhenom\Auth\Support\MemoryThrottle;
use LPhenom\Auth\Support\EmailSender\EmailCodeAuthenticator;
use LPhenom\Auth\Support\EmailSender\UniSenderEmailSender;
use LPhenom\Auth\Support\SmsSender\MirSmsSender;
use LPhenom\Auth\Support\SmsSender\SmsCodeAuthenticator;
use LPhenom\Auth\Tokens\OpaqueTokenEncoder;
use LPhenom\Cache\CacheInterface;
use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Log\Contract\LoggerInterface;
use LPhenom\LPhenom\ServiceProviderInterface;

/**
 * Registers authentication services from lphenom/auth.
 *
 * Config (config/auth.php shape):
 *   auth.token_ttl           — int, token TTL seconds, default 86400
 *   auth.max_attempts        — int, max login attempts, default 5
 *   auth.throttle_decay      — int, throttle decay seconds, default 60
 *   auth.password_iterations — int, password hash iterations, default 10000
 *   auth.token_driver        — 'database' | 'memory', default 'database'
 *   auth.throttle_driver     — 'cache' | 'memory', default 'cache'
 *   auth.mirsms.*            — MirSMS integration config
 *   auth.unisender.*         — UniSender email integration config
 *   auth.sms_code.*          — SMS code authenticator settings
 *   auth.email_code.*        — Email code authenticator settings
 *
 * NOTE: The application MUST register UserProviderInterface in the container
 * before resolving AuthManagerInterface. The auth package does not provide
 * a user storage implementation — that is application-specific.
 *
 * @lphenom-build shared,kphp
 */
final class AuthServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        // --- Password hasher ---
        $iterations = $config->get('auth.password_iterations', 10000);
        $iterInt    = is_int($iterations) ? $iterations : 10000;
        $container->set(PasswordHasherInterface::class, new AuthPasswordHasherFactory($iterInt));

        // --- Token encoder ---
        $container->set(TokenEncoderInterface::class, new AuthTokenEncoderFactory());

        // --- Token repository ---
        $tokenDriver = $config->get('auth.token_driver', 'database');
        if ($tokenDriver === 'memory') {
            $container->set(TokenRepositoryInterface::class, new AuthInMemoryTokenRepoFactory());
        } else {
            $container->set(TokenRepositoryInterface::class, new AuthDbTokenRepoFactory());
        }

        // --- Login throttle ---
        $throttleDriver = $config->get('auth.throttle_driver', 'cache');
        if ($throttleDriver === 'memory') {
            $container->set(LoginThrottleInterface::class, new AuthMemoryThrottleFactory());
        } else {
            $container->set(LoginThrottleInterface::class, new AuthCacheThrottleFactory());
        }

        // --- Audit listener ---
        $container->set(AuditListenerInterface::class, new AuthAuditListenerFactory());

        // --- Auth manager ---
        $tokenTtl      = $config->get('auth.token_ttl', 86400);
        $maxAttempts    = $config->get('auth.max_attempts', 5);
        $throttleDecay  = $config->get('auth.throttle_decay', 60);
        $ttlInt         = is_int($tokenTtl) ? $tokenTtl : 86400;
        $maxInt         = is_int($maxAttempts) ? $maxAttempts : 5;
        $decayInt       = is_int($throttleDecay) ? $throttleDecay : 60;
        $container->set(AuthManagerInterface::class, new AuthManagerFactory($ttlInt, $maxInt, $decayInt));

        // --- Guard, bridge, middleware ---
        $container->set(BearerTokenGuard::class, new AuthBearerTokenGuardFactory());
        $container->set(HttpAuthBridge::class, new AuthHttpBridgeFactory());
        $container->set(RequireAuthMiddleware::class, new AuthRequireAuthMiddlewareFactory());

        // --- MirSMS integration (optional) ---
        $mirEnabled = $config->get('auth.mirsms.enabled', false);
        if ($mirEnabled === true) {
            $mirUrl    = $config->get('auth.mirsms.api_url', 'https://api.mirsms.ru/message/send');
            $mirLogin  = $config->get('auth.mirsms.login', '');
            $mirPass   = $config->get('auth.mirsms.password', '');
            $mirSender = $config->get('auth.mirsms.sender', '');
            $mirUrlStr    = is_string($mirUrl) ? $mirUrl : 'https://api.mirsms.ru/message/send';
            $mirLoginStr  = is_string($mirLogin) ? $mirLogin : '';
            $mirPassStr   = is_string($mirPass) ? $mirPass : '';
            $mirSenderStr = is_string($mirSender) ? $mirSender : '';

            $container->set(MirSmsSender::class, new AuthMirSmsSenderFactory(
                $mirUrlStr,
                $mirLoginStr,
                $mirPassStr,
                $mirSenderStr
            ));

            $smsCodeLen = $config->get('auth.sms_code.length', 6);
            $smsCodeTtl = $config->get('auth.sms_code.ttl', 300);
            $smsLenInt  = is_int($smsCodeLen) ? $smsCodeLen : 6;
            $smsTtlInt  = is_int($smsCodeTtl) ? $smsCodeTtl : 300;
            $container->set(SmsCodeAuthenticator::class, new AuthSmsCodeAuthenticatorFactory(
                $smsLenInt,
                $smsTtlInt
            ));
        }

        // --- UniSender email integration (optional) ---
        $uniEnabled = $config->get('auth.unisender.enabled', false);
        if ($uniEnabled === true) {
            $uniKey     = $config->get('auth.unisender.api_key', '');
            $uniEmail   = $config->get('auth.unisender.sender_email', '');
            $uniName    = $config->get('auth.unisender.sender_name', '');
            $uniSubject = $config->get('auth.unisender.subject', 'Код подтверждения');
            $uniUrl     = $config->get('auth.unisender.api_url', 'https://api.unisender.com/ru/api/sendEmail');
            $uniKeyStr     = is_string($uniKey) ? $uniKey : '';
            $uniEmailStr   = is_string($uniEmail) ? $uniEmail : '';
            $uniNameStr    = is_string($uniName) ? $uniName : '';
            $uniSubjectStr = is_string($uniSubject) ? $uniSubject : 'Код подтверждения';
            $uniUrlStr     = is_string($uniUrl) ? $uniUrl : 'https://api.unisender.com/ru/api/sendEmail';

            $container->set(UniSenderEmailSender::class, new AuthUniSenderEmailSenderFactory(
                $uniKeyStr,
                $uniEmailStr,
                $uniNameStr,
                $uniSubjectStr,
                $uniUrlStr
            ));

            $emailCodeLen = $config->get('auth.email_code.length', 6);
            $emailCodeTtl = $config->get('auth.email_code.ttl', 300);
            $emailLenInt  = is_int($emailCodeLen) ? $emailCodeLen : 6;
            $emailTtlInt  = is_int($emailCodeTtl) ? $emailCodeTtl : 300;
            $container->set(EmailCodeAuthenticator::class, new AuthEmailCodeAuthenticatorFactory(
                $emailLenInt,
                $emailTtlInt
            ));
        }
    }

    public function boot(Container $container, Config $config): void
    {
        // Reset auth context for each request cycle
        AuthContextStorage::reset();
    }
}

// ─────────────────────────────────────────────────────────────────────
// Factories (ServiceFactoryInterface implementations)
// ─────────────────────────────────────────────────────────────────────

/**
 * @internal
 */
final class AuthPasswordHasherFactory implements ServiceFactoryInterface
{
    /** @var int */
    private int $iterations;

    public function __construct(int $iterations)
    {
        $this->iterations = $iterations;
    }

    public function create(Container $container): mixed
    {
        return new CryptPasswordHasher($this->iterations);
    }
}

/**
 * @internal
 */
final class AuthTokenEncoderFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new OpaqueTokenEncoder();
    }
}

/**
 * @internal
 */
final class AuthDbTokenRepoFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var ConnectionInterface $db */
        $db = $container->get(ConnectionInterface::class);
        return new DbTokenRepository($db);
    }
}

/**
 * @internal
 */
final class AuthInMemoryTokenRepoFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new InMemoryTokenRepository();
    }
}

/**
 * @internal
 */
final class AuthCacheThrottleFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);
        return new CacheThrottle($cache);
    }
}

/**
 * @internal
 */
final class AuthMemoryThrottleFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new MemoryThrottle();
    }
}

/**
 * @internal
 */
final class AuthAuditListenerFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        return new LogAuditListener($logger);
    }
}

/**
 * @internal
 */
final class AuthManagerFactory implements ServiceFactoryInterface
{
    /** @var int */
    private int $tokenTtl;

    /** @var int */
    private int $maxAttempts;

    /** @var int */
    private int $throttleDecay;

    public function __construct(int $tokenTtl, int $maxAttempts, int $throttleDecay)
    {
        $this->tokenTtl     = $tokenTtl;
        $this->maxAttempts  = $maxAttempts;
        $this->throttleDecay = $throttleDecay;
    }

    public function create(Container $container): mixed
    {
        /** @var UserProviderInterface $userProvider */
        $userProvider = $container->get(UserProviderInterface::class);

        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        /** @var TokenEncoderInterface $encoder */
        $encoder = $container->get(TokenEncoderInterface::class);

        /** @var TokenRepositoryInterface $tokenRepo */
        $tokenRepo = $container->get(TokenRepositoryInterface::class);

        /** @var LoginThrottleInterface $throttle */
        $throttle = $container->get(LoginThrottleInterface::class);

        /** @var AuditListenerInterface $audit */
        $audit = $container->get(AuditListenerInterface::class);

        return new DefaultAuthManager(
            $userProvider,
            $hasher,
            $encoder,
            $tokenRepo,
            $throttle,
            $audit,
            $this->tokenTtl,
            $this->maxAttempts,
            $this->throttleDecay
        );
    }
}

/**
 * @internal
 */
final class AuthBearerTokenGuardFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var AuthManagerInterface $authManager */
        $authManager = $container->get(AuthManagerInterface::class);
        return new BearerTokenGuard($authManager);
    }
}

/**
 * @internal
 */
final class AuthHttpBridgeFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var BearerTokenGuard $guard */
        $guard = $container->get(BearerTokenGuard::class);
        return new HttpAuthBridge($guard);
    }
}

/**
 * @internal
 */
final class AuthRequireAuthMiddlewareFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var BearerTokenGuard $guard */
        $guard = $container->get(BearerTokenGuard::class);
        return new RequireAuthMiddleware($guard);
    }
}

/**
 * @internal
 */
final class AuthMirSmsSenderFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $apiUrl;

    /** @var string */
    private string $login;

    /** @var string */
    private string $password;

    /** @var string */
    private string $sender;

    public function __construct(string $apiUrl, string $login, string $password, string $sender)
    {
        $this->apiUrl   = $apiUrl;
        $this->login    = $login;
        $this->password = $password;
        $this->sender   = $sender;
    }

    public function create(Container $container): mixed
    {
        return new MirSmsSender(
            $this->apiUrl,
            $this->login,
            $this->password,
            $this->sender
        );
    }
}

/**
 * @internal
 */
final class AuthSmsCodeAuthenticatorFactory implements ServiceFactoryInterface
{
    /** @var int */
    private int $codeLength;

    /** @var int */
    private int $codeTtl;

    public function __construct(int $codeLength, int $codeTtl)
    {
        $this->codeLength = $codeLength;
        $this->codeTtl    = $codeTtl;
    }

    public function create(Container $container): mixed
    {
        /** @var MirSmsSender $sender */
        $sender = $container->get(MirSmsSender::class);

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);

        return new SmsCodeAuthenticator(
            $sender,
            $cache,
            $this->codeLength,
            $this->codeTtl
        );
    }
}

/**
 * @internal
 */
final class AuthUniSenderEmailSenderFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $apiKey;

    /** @var string */
    private string $senderEmail;

    /** @var string */
    private string $senderName;

    /** @var string */
    private string $subject;

    /** @var string */
    private string $apiUrl;

    public function __construct(
        string $apiKey,
        string $senderEmail,
        string $senderName,
        string $subject,
        string $apiUrl
    ) {
        $this->apiKey      = $apiKey;
        $this->senderEmail = $senderEmail;
        $this->senderName  = $senderName;
        $this->subject     = $subject;
        $this->apiUrl      = $apiUrl;
    }

    public function create(Container $container): mixed
    {
        return new UniSenderEmailSender(
            $this->apiKey,
            $this->senderEmail,
            $this->senderName,
            $this->subject,
            $this->apiUrl
        );
    }
}

/**
 * @internal
 */
final class AuthEmailCodeAuthenticatorFactory implements ServiceFactoryInterface
{
    /** @var int */
    private int $codeLength;

    /** @var int */
    private int $codeTtl;

    public function __construct(int $codeLength, int $codeTtl)
    {
        $this->codeLength = $codeLength;
        $this->codeTtl    = $codeTtl;
    }

    public function create(Container $container): mixed
    {
        /** @var UniSenderEmailSender $sender */
        $sender = $container->get(UniSenderEmailSender::class);

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);

        return new EmailCodeAuthenticator(
            $sender,
            $cache,
            $this->codeLength,
            $this->codeTtl
        );
    }
}

