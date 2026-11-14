<?php

function digitalBookPdfPath(int $bookId, array $digitalBooks): ?string
{
    if (!isset($digitalBooks[$bookId]) || !is_string($digitalBooks[$bookId])) {
        return null;
    }

    $filename = $digitalBooks[$bookId];
    if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.pdf\z/i', $filename)) {
        return null;
    }

    $directory = realpath(__DIR__ . '/../digital-books');
    if ($directory === false) {
        return null;
    }

    $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
    if (
        $path === false ||
        strtolower(dirname($path)) !== strtolower($directory) ||
        !is_file($path)
    ) {
        return null;
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    $signature = fread($handle, 5);
    fclose($handle);

    return $signature === '%PDF-' ? $path : null;
}
