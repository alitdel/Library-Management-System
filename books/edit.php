
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$error = "";

// Secure output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get book ID
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookId || $bookId < 1) {
    die("Invalid book ID. Please return to Books Management.");
}

// Retrieve existing book
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
$stmt->execute([$bookId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    die("Book not found. Please return to Books Management.");
}

// Load categories
$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Load authors
$authors = $conn->query(
    "SELECT * FROM authors ORDER BY author_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Update book details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isbn = trim($_POST['isbn'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $authorId = filter_var(
        $_POST['author_id'] ?? '',
        FILTER_VALIDATE_INT
    );
    $publisher = trim($_POST['publisher'] ?? '');
    $year = trim($_POST['publication_year'] ?? '');
    $category = $_POST['category_id'] ?? '';
    $total = filter_var(
        $_POST['total_copies'] ?? '',
        FILTER_VALIDATE_INT
    );
    $available = filter_var(
        $_POST['available_copies'] ?? '',
        FILTER_VALIDATE_INT
    );
    $shelf = trim($_POST['shelf_location'] ?? '');

    // Find selected author
    $authorName = '';

    if ($authorId !== false && $authorId !== null && $authorId > 0) {
        $authorStmt = $conn->prepare(
            "SELECT author_name FROM authors WHERE author_id = ?"
        );
        $authorStmt->execute([$authorId]);
        $authorName = $authorStmt->fetchColumn() ?: '';
    }

    if (
        $isbn === '' ||
        $title === '' ||
        $authorName === '' ||
        $total === false ||
        $available === false ||
        $total < 1 ||
        $available < 0 ||
        $available > $total
    ) {
        $error = "Please enter valid details and select an author. Available copies cannot exceed total copies.";

    } elseif (
        $year !== '' &&
        (!ctype_digit($year) ||
        (int)$year < 1000 ||
        (int)$year > (int)date('Y'))
    ) {
        $error = "Please enter a valid publication year.";

    } elseif (
        $category !== '' &&
        !ctype_digit((string)$category)
    ) {
        $error = "Please select a valid category.";

    } else {

        try {

            $categoryId = $category === ''
                ? null
                : (int)$category;

            // Confirm selected category exists
            if ($categoryId !== null) {
                $categoryCheck = $conn->prepare(
                    "SELECT COUNT(*) FROM categories WHERE category_id = ?"
                );
                $categoryCheck->execute([$categoryId]);

                if (!$categoryCheck->fetchColumn()) {
                    throw new Exception("Please select a valid category.");
                }
            }

            // Update both the new author relationship
            // and the old author text field.
            $update = $conn->prepare("
                UPDATE books SET
                    isbn = :isbn,
                    title = :title,
                    author = :author,
                    author_id = :author_id,
                    publisher = :publisher,
                    publication_year = :publication_year,
                    category_id = :category_id,
                    total_copies = :total_copies,
                    available_copies = :available_copies,
                    shelf_location = :shelf_location
                WHERE book_id = :book_id
            ");

            $update->execute([
                'isbn' => $isbn,
                'title' => $title,
                'author' => $authorName,
                'author_id' => $authorId,
                'publisher' => $publisher ?: null,
                'publication_year' => $year === ''
                    ? null
                    : (int)$year,
                'category_id' => $categoryId,
                'total_copies' => $total,
                'available_copies' => $available,
                'shelf_location' => $shelf ?: null,
                'book_id' => $bookId
            ]);

            header("Location: index.php?message=updated");
            exit();

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $error = "This ISBN is already being used by another book.";
            } else {
                $error = "Unable to update the book. Please check your details.";
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Preserve entered values if an error occurs
    $book['isbn'] = $isbn;
    $book['title'] = $title;
    $book['author'] = $authorName;
    $book['author_id'] = $authorId;
    $book['publisher'] = $publisher;
    $book['publication_year'] = $year;
    $book['category_id'] = $category;
    $book['total_copies'] = $_POST['total_copies'] ?? '';
    $book['available_copies'] = $_POST['available_copies'] ?? '';
    $book['shelf_location'] = $shelf;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Book | Library Management System</title>
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
    min-height: 100vh;
}

.container {
    width: 96%;
    max-width: 1450px;
    margin: 25px auto;
    padding: 15px;
}

.form-card {
    background: white;
    width: 100%;
    padding: 50px 60px;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.07);
}

.header {
    margin-bottom: 35px;
    border-bottom: 1px solid #eee6f7;
    padding-bottom: 25px;
}

.header h1 {
    color: #35135f;
    font-size: 36px;
    margin-bottom: 12px;
}

.header p {
    color: #888;
    font-size: 17px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 35px;
    row-gap: 25px;
}

.form-group {
    width: 100%;
}

.full-width {
    grid-column: 1 / -1;
}

label {
    display: block;
    font-weight: 600;
    font-size: 17px;
    margin-bottom: 12px;
    color: #45256c;
}

input,
select {
    width: 100%;
    min-height: 60px;
    padding: 17px 20px;
    border: 1px solid #ded6e9;
    border-radius: 10px;
    outline: none;
    font-size: 17px;
    background: #faf8fd;
    color: #302344;
    transition: 0.3s;
}

input:focus,
select:focus {
    border-color: #7546b8;
    box-shadow: 0 0 0 3px rgba(117,70,184,0.12);
    background: white;
}

.buttons {
    display: flex;
    gap: 18px;
    margin-top: 40px;
}

button {
    border: none;
    background: linear-gradient(90deg, #51218a, #8050c0);
    color: white;
    padding: 18px 40px;
    border-radius: 10px;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #35135f;
    transform: translateY(-2px);
}

.cancel {
    background: #eee8f5;
    color: #35135f;
    text-decoration: none;
    padding: 18px 40px;
    border-radius: 10px;
    font-size: 17px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cancel:hover {
    background: #dfd0ef;
}

.error {
    background: #ffe8e8;
    color: #b42318;
    padding: 16px;
    border-radius: 9px;
    margin-bottom: 25px;
    font-size: 15px;
}

.footer {
    margin-top: 35px;
    padding-top: 20px;
    border-top: 1px solid #eee6f7;
    color: #999;
    text-align: center;
    font-size: 14px;
}

.author-help {
    margin-top: 9px;
    font-size: 14px;
    color: #777;
}

.author-help a {
    color: #51218a;
    font-weight: bold;
    text-decoration: none;
}

@media(max-width: 800px) {

    .container {
        width: 100%;
        margin: 10px auto;
        padding: 10px;
    }

    .form-card {
        padding: 30px 22px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full-width {
        grid-column: auto;
    }

    .header h1 {
        font-size: 28px;
    }

    .buttons {
        flex-direction: column;
    }

    button,
    .cancel {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="form-card">

        <div class="header">

            <h1>✏️ Edit Book Details</h1>

            <p>
                Update the information of an existing library book.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>ISBN *</label>

                    <input
                        type="text"
                        name="isbn"
                        value="<?= e($book['isbn']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Book Title *</label>

                    <input
                        type="text"
                        name="title"
                        value="<?= e($book['title']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Author *</label>

                    <select name="author_id" required>

                        <option value="">Select Author</option>

                        <?php foreach ($authors as $author): ?>

                            <option
                                value="<?= (int)$author['author_id'] ?>"
                                <?= ((string)($book['author_id'] ?? '') ===
                                    (string)$author['author_id']) ? 'selected' : '' ?>
                            >
                                <?= e($author['author_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <p class="author-help">
                        Need another author?
                        <a href="../authors/add.php">Add New Author</a>
                    </p>

                </div>

                <div class="form-group">

                    <label>Publisher</label>

                    <input
                        type="text"
                        name="publisher"
                        value="<?= e($book['publisher'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Publication Year</label>

                    <input
                        type="number"
                        name="publication_year"
                        min="1000"
                        max="<?= date('Y') ?>"
                        value="<?= e($book['publication_year'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Book Category</label>

                    <select name="category_id">

                        <option value="">Select Category</option>

                        <?php foreach ($categories as $cat): ?>

                            <option
                                value="<?= (int)$cat['category_id'] ?>"
                                <?= ((string)($book['category_id'] ?? '') ===
                                    (string)$cat['category_id']) ? 'selected' : '' ?>
                            >
                                <?= e($cat['category_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Total Copies *</label>

                    <input
                        type="number"
                        name="total_copies"
                        min="1"
                        value="<?= e($book['total_copies']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Available Copies *</label>

                    <input
                        type="number"
                        name="available_copies"
                        min="0"
                        value="<?= e($book['available_copies']) ?>"
                        required
                    >

                </div>

                <div class="form-group full-width">

                    <label>Shelf Location</label>

                    <input
                        type="text"
                        name="shelf_location"
                        value="<?= e($book['shelf_location'] ?? '') ?>"
                        placeholder="e.g. Shelf A1 or Section B"
                    >

                </div>

            </div>

            <div class="buttons">

                <button type="submit">
                    Update Book
                </button>

                <a href="index.php" class="cancel">
                    Cancel
                </a>

            </div>

        </form>

        <div class="footer">
            Library Management System | Book Management
        </div>

    </div>

</div>

</body>
</html>
