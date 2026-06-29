<!-- Context: development/backend/performance | Priority: medium | Version: 2.0 | Updated: 2026-06-29 -->

# PHP Performance — ArrCal Production

> Runtime optimization for the PHP 8.5 + ReactPHP production stack. No Twig caching advice here because there's no Twig anymore.

## Quick Reference

| Concern | Mechanism | When |
|---------|-----------|------|
| Bytecode caching | OPcache | Always in production |
| Just-in-time compilation | JIT (PHP 8.0+) | CPU-bound workloads |
| Class preloading | `opcache.preload` | Long-running ReactPHP process |
| Event loop health | Non-blocking I/O | Always |

---

## OPcache Configuration

OPcache stores compiled PHP bytecode in shared memory, eliminating parse/compile overhead on every request. For a long-running ReactPHP process, this is essential.

```
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.enable_file_override=0
```

The full config lives at `docker/php.ini`.

---

## JIT Configuration

PHP 8.0+ JIT compiles hot code paths to native machine code:

```
opcache.jit_buffer_size=128M
opcache.jit=1255
```

Use `1255` (tracing) for production. Drop `jit_buffer_size` to `64M` if memory-constrained.

---

## Preloading

Preloading loads PHP classes into shared memory at server startup:

```php
<?php
// Preload the entire application namespace
$appDir = __DIR__ . '/src'; // src/ = Handler/, Service/, Domain/, Kernel/
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appDir),
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once $file->getPathname();
    }
}

// Preload key vendor classes
$vendorClasses = [
    React\Http\HttpServer::class,
    React\EventLoop\Loop::class,
    React\Http\Message\Response::class,
    FastRoute\Dispatcher::class,
];

foreach ($vendorClasses as $class) {
    class_exists($class); // Triggers autoloader, caches in OPcache
}
```

---

## ReactPHP Event Loop — The Golden Rule

**Never block the event loop.** The entire server runs on a single thread.

### Blocking (Never Do This)
```php
file_get_contents('https://api.example.com');  // ❌
sleep(5);                                      // ❌
$pdo->query('SELECT ...');                     // ❌
shell_exec('docker ps');                       // ❌
```

### Non-Blocking (Always Do This)
```php
$browser->get('https://radarr:7878/api/v3/calendar')
    ->then(fn (ResponseInterface $response) => json_decode((string) $response->getBody()));

Loop::addTimer(5.0, fn () => $this->collect());
```

---

## Performance Checklist

- [ ] OPcache enabled with `validate_timestamps=0` in production
- [ ] JIT enabled with tracing mode (`1255`)
- [ ] Preload script loads `src/` and key vendor classes
- [ ] No blocking I/O anywhere in the codebase
- [ ] `composer install --no-dev --optimize-autoloader` in production build
- [ ] Long-running ReactPHP process (not CGI/FPM)
