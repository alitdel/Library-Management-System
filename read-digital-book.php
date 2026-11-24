<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/digital_book_file.php';

$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bookId || $bookId < 1) {
    http_response_code(404);
    exit('Digital edition not found.');
}

$bookStmt = $conn->prepare('SELECT book_id FROM books WHERE book_id = ?');
$bookStmt->execute([$bookId]);
if (!$bookStmt->fetchColumn()) {
    http_response_code(404);
    exit('Digital edition not found.');
}

$digitalBooks = require __DIR__ . '/config/digital_books.php';
if (!is_array($digitalBooks)) {
    throw new UnexpectedValueException('The digital book catalog must return an array.');
}

$path = digitalBookPdfPath((int) $bookId, $digitalBooks);
if ($path === null) {
    http_response_code(404);
    exit('No verified digital edition is available for this book, try later.');
}

$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Unable to open the digital edition.');
}

if (fread($handle, 5) !== '%PDF-') {
    fclose($handle);
    http_response_code(404);
    exit('No verified digital edition is available for this book.');
}

rewind($handle);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="library-book-' . (int) $bookId . '.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Length: ' . filesize($path));
fpassthru($handle);
fclose($handle);
exit;
