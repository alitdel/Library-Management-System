
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Get book ID
$book_id = (int)($_GET['id'] ?? 0);

if ($book_id <= 0) {
    die("Invalid book ID. Please return to Books Management.");
}

// Retrieve book details, including the linked author
$stmt = $conn->prepare("
    SELECT
        b.*,
        c.category_name,
        COALESCE(a.author_name, b.author) AS display_author
    FROM books b
    LEFT JOIN categories c
        ON b.category_id = c.category_id
    LEFT JOIN authors a
        ON b.author_id = a.author_id
    WHERE b.book_id = ?
");

$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    die("Book not found.");
}

// Secure output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Determine availability
$available = (int)$book['available_copies'];

if ($available === 0) {
    $status = 'Unavailable';
    $statusClass = 'unavailable';
} elseif ($available <= 2) {
    $status = 'Low Stock';
    $statusClass = 'low-stock';
} else {
    $status = 'Available';
    $statusClass = 'available';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Book | Library Management System</title>
<link rel="stylesheet" href="../assets/css/library-theme.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    background: #f5f2fa;
    color: #302344;
}

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    background: linear-gradient(180deg, #35135f, #7546b8);
    color: white;
    padding: 30px 20px;
}

.logo {
    font-size: 21px;
    font-weight: bold;
    margin-bottom: 45px;
}

.logo span {
    display: block;
    font-size: 13px;
    color: #dfcaff;
    margin-top: 7px;
    font-weight: normal;
}

.menu-title {
    font-size: 12px;
    color: #d7c2f1;
    margin-bottom: 15px;
    text-transform: uppercase;
}

.menu a {
    display: block;
    padding: 14px 15px;
    color: white;
    text-decoration: none;
    border-radius: 9px;
    margin-bottom: 8px;
}

.menu a:hover,
.menu a.active {
    background: rgba(255,255,255,0.2);
}

.main {
    margin-left: 250px;
    padding: 45px;
}

.header {
    margin-bottom: 30px;
}

.header h1 {
    color: #35135f;
    font-size: 32px;
    margin-bottom: 8px;
}

.header p {
    color: #888;
}

.book-card {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 5px 22px rgba(53,19,95,0.07);
    max-width: 1200px;
}

.book-heading {
    border-bottom: 1px solid #eee8f5;
    padding-bottom: 25px;
    margin-bottom: 25px;
}

.book-heading h2 {
    color: #35135f;
    font-size: 27px;
    margin-bottom: 12px;
}

.book-heading p {
    color: #777;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
}

.detail {
    background: #faf8fd;
    padding: 22px;
    border-radius: 10px;
    border: 1px solid #eee8f5;
}

.detail label {
    display: block;
    color: #80758d;
    font-size: 13px;
    margin-bottom: 10px;
}

.detail strong {
    color: #35135f;
    font-size: 17px;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.available {
    background: #e4f6e9;
    color: #176b32;
}

.low-stock {
    background: #fff2d6;
    color: #956000;
}

.unavailable {
    background: #ffe7e7;
    color: #a02020;
}

.buttons {
    display: flex;
    gap: 15px;
    margin-top: 35px;
    flex-wrap: wrap;
}

.buttons a {
    text-decoration: none;
    padding: 15px 25px;
    border-radius: 8px;
    font-weight: bold;
}

.back-btn {
    background: #eee8f5;
    color: #35135f;
}

.edit-btn {
    background: #51218a;
    color: white;
}

.edit-btn:hover {
    background: #35135f;
}

.back-btn:hover {
    background: #ddd0ef;
}

@media(max-width: 800px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .book-card {
        padding: 22px;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }

    .header h1 {
        font-size: 26px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">
        📚 Library System
        <span>Management Portal</span>
    </div>

    <div class="menu-title">Main Menu</div>

    <nav class="menu">
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="index.php" class="active">📚 Books</a>
        <a href="../categories/">📁 Categories</a>
        <a href="../members/">👥 Members</a>
        <a href="../loans/">📖 Borrowing Records</a>
        <a href="../logout.php">🚪 Logout</a>
    </nav>
</aside>

<main class="main">

    <div class="header">
        <h1>Book Details</h1>
        <p>View complete information about this library book.</p>
    </div>

    <section class="book-card">

        <div class="book-heading">
            <h2><?= e($book['title']) ?></h2>
            <p>Book ID: <?= (int)$book['book_id'] ?></p>
        </div>

        <div class="details-grid">

            <div class="detail">
                <label>ISBN</label>
                <strong><?= e($book['isbn']) ?></strong>
            </div>

            <div class="detail">
                <label>Book Title</label>
                <strong><?= e($book['title']) ?></strong>
            </div>

            <div class="detail">
                <label>Author</label>
                <strong><?= e($book['display_author']) ?></strong>
            </div>

            <div class="detail">
                <label>Publisher</label>
                <strong><?= e($book['publisher'] ?: 'Not provided') ?></strong>
            </div>

            <div class="detail">
                <label>Publication Year</label>
                <strong><?= e($book['publication_year'] ?: 'Not provided') ?></strong>
            </div>

            <div class="detail">
                <label>Category</label>
                <strong><?= e($book['category_name'] ?? 'Uncategorized') ?></strong>
            </div>

            <div class="detail">
                <label>Total Copies</label>
                <strong><?= (int)$book['total_copies'] ?></strong>
            </div>

            <div class="detail">
                <label>Available Copies</label>
                <strong><?= $available ?></strong>
            </div>

            <div class="detail">
                <label>Shelf Location</label>
                <strong><?= e($book['shelf_location'] ?: 'Not provided') ?></strong>
            </div>

            <div class="detail">
                <label>Availability Status</label>
                <span class="status <?= $statusClass ?>">
                    <?= e($status) ?>
                </span>
            </div>

        </div>

        <div class="buttons">
            <a href="index.php" class="back-btn">
                ← Back to Books
            </a>

            <a href="edit.php?id=<?= (int)$book['book_id'] ?>" class="edit-btn">
                Edit Book
            </a>
        </div>

    </section>

</main>

</body>
</html>
