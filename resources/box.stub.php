<?php

declare(strict_types=1);

use Sift\Console\Kernel;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sift\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $pharAlias = Phar::running(false) !== '' ? basename(Phar::running(false)) : 'sift.phar';
    $file = 'phar://' . $pharAlias . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$pharFile = Phar::running(false);
$resolvedPharFile = realpath($pharFile);

if (is_string($resolvedPharFile)) {
    $pharFile = $resolvedPharFile;
} elseif (!str_contains($pharFile, '\\') && !str_contains($pharFile, '/')) {
    $pharFile = getcwd() . DIRECTORY_SEPARATOR . $pharFile;
}

$autoloadSuffix = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$pharDirectory = dirname($pharFile);
$workingDirectory = getcwd() ?: $pharDirectory;
$autoloadCandidates = [
    $pharDirectory . $autoloadSuffix,
    dirname($pharDirectory) . $autoloadSuffix,
    dirname($pharDirectory, 2) . $autoloadSuffix,
    $workingDirectory . $autoloadSuffix,
    dirname($workingDirectory) . $autoloadSuffix,
    dirname($workingDirectory, 2) . $autoloadSuffix,
];

$autoloadLoaded = false;

foreach ($autoloadCandidates as $autoloadPath) {
    if (!is_file($autoloadPath)) {
        continue;
    }

    require $autoloadPath;
    $autoloadLoaded = true;

    break;
}

if (!$autoloadLoaded) {
    fwrite(STDERR, "Unable to locate vendor/autoload.php next to sift.phar or within its parent directories.\n");

    exit(1);
}

exit(Kernel::run($argv ?? []));
