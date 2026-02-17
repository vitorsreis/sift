<?php

declare(strict_types=1);
use Sift\Console\Application;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sift\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $pharAlias = Phar::running(false) !== '' ? basename(Phar::running(false)) : 'sift.phar';
    $file = 'phar://'.$pharAlias.'/src/'.str_replace('\\', '/', $relativeClass).'.php';

    if (is_file($file)) {
        require $file;
    }
});

$pharFile = Phar::running(false);
$resolvedPharFile = realpath($pharFile);

if (is_string($resolvedPharFile)) {
    $pharFile = $resolvedPharFile;
} elseif (! str_contains($pharFile, DIRECTORY_SEPARATOR) && ! str_contains($pharFile, '/')) {
    $pharFile = getcwd().DIRECTORY_SEPARATOR.$pharFile;
}

$pharDirectory = dirname($pharFile);
$workingDirectory = getcwd() ?: $pharDirectory;
$autoloadCandidates = [
    $pharDirectory.'/vendor/autoload.php',
    dirname($pharDirectory).'/vendor/autoload.php',
    dirname(dirname($pharDirectory)).'/vendor/autoload.php',
    $workingDirectory.'/vendor/autoload.php',
    dirname($workingDirectory).'/vendor/autoload.php',
    dirname(dirname($workingDirectory)).'/vendor/autoload.php',
];

$autoloadLoaded = false;

foreach ($autoloadCandidates as $autoloadPath) {
    if (! is_file($autoloadPath)) {
        continue;
    }

    require $autoloadPath;
    $autoloadLoaded = true;

    break;
}

if (! $autoloadLoaded) {
    fwrite(STDERR, "Unable to locate vendor/autoload.php next to sift.phar or within its parent directories.\n");

    exit(1);
}

exit(Application::run($_SERVER['argv'] ?? []));
