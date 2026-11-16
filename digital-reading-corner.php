<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/digital_book_file.php';

$digitalBooks = require __DIR__ . '/config/digital_books.php';
if (!is_array($digitalBooks)) {
    throw new UnexpectedValueException('The digital book catalog must return an array.');
}

$books = $conn->query("
    SELECT
        books.book_id,
        books.title,
        COALESCE(authors.author_name, books.author) AS display_author,
        categories.category_name,
        books.publication_year,
        books.total_copies,
        books.available_copies
    FROM books
    LEFT JOIN authors
        ON books.author_id = authors.author_id
    LEFT JOIN categories
        ON books.category_id = categories.category_id
    ORDER BY books.title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$digitalBooksInCatalog = [];
foreach ($books as $book) {
    $bookId = (int) $book['book_id'];
    if (digitalBookPdfPath($bookId, $digitalBooks) !== null) {
        $digitalBooksInCatalog[] = $book;
    }
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Reading Corner | Royal Library</title>
    <link rel="stylesheet" href="assets/css/library-theme.css">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(ellipse at 92% 3%, rgba(16, 185, 129, .08), transparent 28rem),
                #F5F7F2;
        }

        .reading-page {
            width: calc(100% - 258px);
            max-width: 1600px;
            min-height: 100vh;
            margin-left: 258px;
            padding: 34px clamp(24px, 4vw, 58px);
        }

        .reading-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 3;
            display: flex;
            width: 258px;
            flex-direction: column;
            padding: 28px 18px 20px;
            color: #fff;
            background: linear-gradient(165deg, #064E3B, #087F5B 58%, #043A2C);
            box-shadow: 10px 0 32px rgba(6, 78, 59, .11);
        }

        .reading-brand {
            margin: 3px 9px 36px;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.5;
            text-decoration: none;
        }

        .reading-brand span {
            display: block;
            color: #C9E6D8;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .reading-nav {
            display: grid;
            gap: 7px;
        }

        .reading-nav a {
            padding: 13px;
            border-radius: 11px;
            color: #E5F2EC;
            font-size: 13px;
            text-decoration: none;
            transition: background .18s ease, transform .18s ease;
        }

        .reading-nav a:hover,
        .reading-nav a.active {
            color: #fff;
            background: rgba(255, 255, 255, .15);
        }

        .reading-nav a.active {
            box-shadow: inset 3px 0 #D4AF37;
        }

        .reading-header {
            margin-bottom: 27px;
        }

        .reading-header .eyebrow {
            margin: 0 0 6px;
            color: #087F5B;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: .15em;
            text-transform: uppercase;
        }

        .reading-header h1 {
            margin: 0;
            color: #064E3B;
            font-size: clamp(26px, 3vw, 36px);
            letter-spacing: -.035em;
        }

        .reading-header p {
            max-width: 700px;
            margin: 10px 0 0;
            color: #66746D;
            font-size: 14px;
            line-height: 1.7;
        }

        .reading-panel {
            margin-bottom: 28px;
            padding: 25px;
            border: 1px solid rgba(6, 78, 59, .09);
            border-radius: 19px;
            background: #fff;
            box-shadow: 0 12px 32px rgba(6, 78, 59, .07);
        }

        .reading-panel h2 {
            margin: 0;
            color: #064E3B;
            font-size: 18px;
        }

        .reading-panel > p {
            margin: 6px 0 19px;
            color: #66746D;
            font-size: 12px;
        }

        .digital-grid,
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 14px;
        }

        .digital-book,
        .catalog-book {
            min-width: 0;
            padding: 17px;
            border: 1px solid #E1E9E1;
            border-radius: 15px;
            background: #FBFCFA;
        }

        .digital-book {
            border-top: 3px solid #D4AF37;
        }

        .book-mark {
            display: grid;
            width: 39px;
            height: 39px;
            margin-bottom: 13px;
            place-items: center;
            border-radius: 12px;
            color: #087F5B;
            background: #E8F3EC;
        }

        .book-mark svg {
            width: 19px;
            height: 19px;
        }

        .digital-book h3,
        .catalog-book h3 {
            margin: 0;
            color: #064E3B;
            font-size: 13px;
            line-height: 1.45;
        }

        .digital-book p,
        .catalog-book p {
            margin: 6px 0 0;
            color: #66746D;
            font-size: 11px;
            line-height: 1.5;
        }

        .digital-meta {
            margin-top: 10px;
            color: #758178;
            font-size: 10px;
        }

        .read-link {
            display: inline-flex;
            min-height: 37px;
            align-items: center;
            margin-top: 14px;
            padding: 0 12px;
            border-radius: 9px;
            color: #fff;
            background: #087F5B;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .read-link:hover {
            background: #064E3B;
        }

        .empty-digital {
            padding: 22px;
            border: 1px dashed #D5E1D8;
            border-radius: 13px;
            color: #66746D;
            background: #FBFCFA;
            font-size: 12px;
            line-height: 1.7;
        }

        .physical-note {
            display: inline-block;
            margin-top: 11px;
            padding: 5px 8px;
            border-radius: 999px;
            color: #66746D;
            background: #EDF1EC;
            font-size: 9px;
        }

        @media (max-width: 900px) {
            .reading-sidebar {
                width: 225px;
            }

            .reading-page {
                width: calc(100% - 225px);
                margin-left: 225px;
                padding-right: 22px;
                padding-left: 22px;
            }
        }

        @media (max-width: 700px) {
            .reading-sidebar {
                position: relative;
                width: 100%;
                padding: 15px 17px;
            }

            .reading-brand {
                margin: 0 2px 12px;
            }

            .reading-nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .reading-nav a {
                padding: 10px;
                font-size: 11px;
            }

            .reading-page {
                width: 100%;
                margin: 0;
                padding: 24px 17px;
            }

            .reading-panel {
                padding: 18px;
            }

            .digital-grid,
            .catalog-grid {
                grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<aside class="reading-sidebar">
    <a class="reading-brand" href="dashboard.php">
        Royal Library
        <span>Knowledge, thoughtfully curated</span>
    </a>
    <nav class="reading-nav" aria-label="Main navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="books/index.php">Books Management</a>
        <a href="digital-reading-corner.php" class="active" aria-current="page">Digital Reading Corner</a>
        <a href="authors/index.php">Authors</a>
        <a href="members/index.php">Members</a>
        <a href="loans/index.php">Borrowing Records</a>
    </nav>
</aside>

<main class="reading-page">
    <header class="reading-header">
        <p class="eyebrow">Read, discover, grow</p>
        <h1>Digital Reading Corner</h1>
        <p>
            Browse the library catalog and open locally provided digital books when an authorized
            PDF is available. Physical holdings are clearly identified and are not presented as
            digital editions.
        </p>
    </header>

    <section class="reading-panel" aria-labelledby="digital-books-heading">
        <h2 id="digital-books-heading">Available digital editions</h2>
        <p>Only catalog entries with a verified local PDF are listed here.</p>

        <?php if (count($digitalBooksInCatalog) > 0): ?>
            <div class="digital-grid">
                <?php foreach ($digitalBooksInCatalog as $book): ?>
                    <article class="digital-book">
                        <span class="book-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z"/>
                                <path d="M4 6h13M8 7v10"/>
                            </svg>
                        </span>
                        <h3><?= e($book['title']) ?></h3>
                        <p><?= e($book['display_author']) ?></p>
                        <div class="digital-meta">
                            <?= e($book['category_name'] ?? 'Uncategorized') ?>
                            <?php if (!empty($book['publication_year'])): ?>
                                · <?= e($book['publication_year']) ?>
                            <?php endif; ?>
                        </div>
                        <a class="read-link" href="read-digital-book.php?id=<?= (int) $book['book_id'] ?>" target="_blank" rel="noopener">
                            Open authorized PDF
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-digital">
                There are no verified digital editions in the catalog yet. When a rights-cleared
                PDF is added to the protected digital-books folder and mapped to its existing book
                ID in the local manifest, it will appear here. No reading buttons are shown for
                physical-only books.
            </div>
        <?php endif; ?>
    </section>

    <section class="reading-panel" aria-labelledby="catalog-heading">
        <h2 id="catalog-heading">Browse the catalog</h2>
        <p>These are real library holdings; a digital edition is not implied by a catalog entry.</p>

        <?php if (count($books) > 0): ?>
            <div class="catalog-grid">
                <?php foreach ($books as $book): ?>
                    <?php $hasDigitalEdition = digitalBookPdfPath((int) $book['book_id'], $digitalBooks) !== null; ?>
                    <article class="catalog-book">
                        <h3><?= e($book['title']) ?></h3>
                        <p><?= e($book['display_author']) ?></p>
                        <div class="digital-meta"><?= e($book['category_name'] ?? 'Uncategorized') ?></div>
                        <span class="physical-note">
                            <?= $hasDigitalEdition ? 'Authorized PDF available' : 'Physical catalog record' ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-digital">No books have been added to the library catalog yet.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
