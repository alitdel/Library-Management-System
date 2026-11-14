
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Delete a book safely using POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_book"])) {
    $bookId = (int)($_POST["book_id"] ?? 0);

    if ($bookId > 0) {
        try {
            $stmt = $conn->prepare(
                "DELETE FROM books WHERE book_id = ?"
            );
            $stmt->execute([$bookId]);

            header("Location: index.php?message=deleted");
            exit();

        } catch (PDOException $e) {
            header("Location: index.php?message=error");
            exit();
        }
    }
}

// Search books
$search = trim($_GET["search"] ?? "");

// Retrieve books with their related authors and categories
$sql = "
    SELECT
        b.*,
        c.category_name,
        COALESCE(a.author_name, b.author) AS display_author
    FROM books b
    LEFT JOIN categories c
        ON b.category_id = c.category_id
    LEFT JOIN authors a
        ON b.author_id = a.author_id
";

if ($search !== "") {
    $sql .= "
        WHERE b.title LIKE ?
           OR a.author_name LIKE ?
           OR b.author LIKE ?
           OR b.isbn LIKE ?
    ";
}

$sql .= " ORDER BY b.book_id DESC";

$stmt = $conn->prepare($sql);

if ($search !== "") {
    $term = "%" . $search . "%";
    $stmt->execute([$term, $term, $term, $term]);
} else {
    $stmt->execute();
}

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalTitles = count($books);
$totalCopies = 0;
$availableCopies = 0;
$unavailableTitles = 0;

foreach ($books as $book) {
    $totalCopies += (int)$book["total_copies"];
    $availableCopies += (int)$book["available_copies"];

    if ((int)$book["available_copies"] === 0) {
        $unavailableTitles++;
    }
}

// Escape output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Books Management | Library Management System</title>
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

/* Sidebar */

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

/* Main area */

.main {
    margin-left: 250px;
    padding: 40px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.header h1 {
    color: #35135f;
    font-size: 32px;
}

.header p {
    color: #888;
    margin-top: 8px;
}

.add-button {
    background: #7546b8;
    color: white;
    text-decoration: none;
    padding: 15px 23px;
    border-radius: 9px;
    font-weight: bold;
}

.add-button:hover {
    background: #35135f;
}

/* Statistics */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 13px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.stat-card p {
    color: #80758d;
    font-size: 14px;
    margin-bottom: 12px;
}

.stat-card h2 {
    font-size: 29px;
    color: #51218a;
}

/* Content */

.content-card {
    background: white;
    padding: 30px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.message {
    padding: 15px;
    background: #e9f8ed;
    color: #23723a;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #ffe8e8;
    color: #b42318;
}

/* Search */

.search-form {
    display: flex;
    gap: 12px;
    margin-bottom: 25px;
}

.search-form input {
    flex: 1;
    padding: 16px;
    border: 1px solid #ded6e9;
    border-radius: 8px;
    font-size: 15px;
}

.search-form button {
    padding: 14px 25px;
    border: none;
    border-radius: 8px;
    background: #7546b8;
    color: white;
    cursor: pointer;
    font-weight: bold;
}

.search-form button:hover {
    background: #35135f;
}

/* Table */

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th {
    background: #f0e9fa;
    color: #35135f;
    text-align: left;
    padding: 17px 14px;
    font-size: 14px;
}

td {
    padding: 17px 14px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

tr:hover td {
    background: #faf8fd;
}

/* Availability */

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
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

/* Actions */

.actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.action-btn {
    display: inline-block;
    padding: 9px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.view {
    background: #e5efff;
    color: #2454a0;
}

.edit {
    background: #eee8f5;
    color: #60339a;
}

.delete {
    background: #ffe7e7;
    color: #d12c42;
}

.view:hover {
    background: #d3e3ff;
}

.edit:hover {
    background: #ddd0ef;
}

.delete:hover {
    background: #ffd0d0;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #888;
}

/* Responsive */

@media(max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 750px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

    .content-card {
        padding: 18px;
    }

    .search-form {
        flex-direction: column;
    }
}

@media(max-width: 450px) {
    .stats {
        grid-template-columns: 1fr;
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

        <a href="../authors/">✍️ Authors</a>

        <a href="../categories/">📁 Categories</a>

        <a href="../members/">👥 Members</a>

        <a href="../loans/">📖 Borrowing Records</a>

        <a href="../logout.php">🚪 Logout</a>

    </nav>

</aside>

<main class="main">

    <div class="header">

        <div>
            <h1>Books Management</h1>
            <p>View, search, update and manage library books.</p>
        </div>

        <a href="add.php" class="add-button">
            + Add New Book
        </a>

    </div>

    <!-- Statistics -->

    <section class="stats">

        <div class="stat-card">
            <p>Book Titles</p>
            <h2><?= $totalTitles ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Copies</p>
            <h2><?= $totalCopies ?></h2>
        </div>

        <div class="stat-card">
            <p>Available Copies</p>
            <h2><?= $availableCopies ?></h2>
        </div>

        <div class="stat-card">
            <p>Unavailable Titles</p>
            <h2><?= $unavailableTitles ?></h2>
        </div>

    </section>

    <section class="content-card">

        <?php if (($_GET["message"] ?? "") === "deleted"): ?>

            <div class="message">
                Book deleted successfully.
            </div>

        <?php elseif (($_GET["message"] ?? "") === "error"): ?>

            <div class="message error">
                This book could not be deleted. It may have existing borrowing records.
            </div>

        <?php elseif (($_GET["message"] ?? "") === "updated"): ?>

            <div class="message">
                Book information updated successfully.
            </div>

        <?php elseif (($_GET["message"] ?? "") === "added"): ?>

            <div class="message">
                New book added successfully.
            </div>

        <?php endif; ?>

        <!-- Search -->

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search by book title, author or ISBN..."
                value="<?= e($search) ?>"
            >

            <button type="submit">Search Books</button>

        </form>

        <!-- Books table -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>ISBN</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Total Copies</th>
                        <th>Available</th>
                        <th>Availability Status</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($books) > 0): ?>

                    <?php foreach ($books as $book): ?>

                        <?php
                            $available = (int)$book["available_copies"];

                            if ($available === 0) {
                                $status = "Unavailable";
                                $statusClass = "unavailable";
                            } elseif ($available <= 2) {
                                $status = "Low Stock";
                                $statusClass = "low-stock";
                            } else {
                                $status = "Available";
                                $statusClass = "available";
                            }
                        ?>

                        <tr>

                            <td><?= (int)$book["book_id"] ?></td>

                            <td><?= e($book["isbn"]) ?></td>

                            <td>
                                <div class="book-title-cell">
                                    <span class="book-mini-cover" aria-hidden="true"></span>
                                    <strong><?= e($book["title"]) ?></strong>
                                </div>
                            </td>

                            <td><?= e($book["display_author"]) ?></td>

                            <td><?= e($book["category_name"] ?? "Uncategorized") ?></td>

                            <td><?= (int)$book["total_copies"] ?></td>

                            <td><?= $available ?></td>

                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        class="action-btn view"
                                        href="view.php?id=<?= (int)$book["book_id"] ?>"
                                    >
                                        View
                                    </a>

                                    <a
                                        class="action-btn edit"
                                        href="edit.php?id=<?= (int)$book["book_id"] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="index.php"
                                        onsubmit="return confirm('Are you sure you want to delete this book? This action cannot be undone.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="book_id"
                                            value="<?= (int)$book["book_id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_book"
                                            class="action-btn delete"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="empty">
                            No books found. Click Add New Book to register your first book.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>
