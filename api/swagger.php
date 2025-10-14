<?php

declare(strict_types=1);

use OpenApi\Generator;

require __DIR__.'/../vendor/autoload.php';

$paths = [
    __DIR__.'/../app',
    __DIR__.'/../routes',
];

$outputPath = $argv[1] ?? null;

try {
    $openApi = Generator::scan($paths);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        sprintf(
            "Failed to generate OpenAPI specification: %s\n",
            $exception->getMessage()
        )
    );
    exit(1);
}

$json = $openApi->toJson(
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
).PHP_EOL;

if ($outputPath !== null) {
    if (@file_put_contents($outputPath, $json) === false) {
        fwrite(
            STDERR,
            sprintf(
                "Failed to write OpenAPI specification to %s\n",
                $outputPath
            )
        );

        exit(1);
    }

    exit(0);
}

echo $json;
