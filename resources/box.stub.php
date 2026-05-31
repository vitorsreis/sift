<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'error' => [
            'code' => 'php_version_unsupported',
            'message' => 'Sift requires PHP 8.3 or newer.',
            'hint' => 'Upgrade PHP or use a Sift release that supports your runtime.',
        ],
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);

    exit(3);
}

foreach (['json', 'simplexml'] as $extension) {
    if (extension_loaded($extension)) {
        continue;
    }

    fwrite(STDERR, json_encode([
        'status' => 'error',
        'error' => [
            'code' => 'extension_missing',
            'message' => sprintf('The "%s" PHP extension is required.', $extension),
            'hint' => sprintf('Install or enable ext-%s before running Sift.', $extension),
        ],
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);

    exit(3);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sift\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = 'phar://sift.phar/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

exit((new Sift\Console\Application())->run($argv ?? []));

__HALT_COMPILER();
