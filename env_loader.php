<?php


$envFilePath = __DIR__ . '/.env';

if (is_readable($envFilePath)) {
    foreach (file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        // Skip comments and blank lines.
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);

        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove optional surrounding quotes.
            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
        }
    }
}
?>