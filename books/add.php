
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$error = "";

// Load categories
$categories = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Load authors
$authors = $conn->query("
    SELECT author_id, author_name
    FROM authors
    ORDER BY author_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $isbn = trim($_POST["isbn"] ?? "");
    $title = trim($_POST["title"] ?? "");
    $authorId = filter_var(
        $_POST["author_id"] ?? "",
        FILTER_VALIDATE_INT
    );
    $publisher = trim($_POST["publisher"] ?? "");
    $year = trim($_POST["publication_year"] ?? "");
    $category = $_POST["category_id"] ?? "";
    $total = filter_var($_POST["total_copies"] ?? "", FILTER_VALIDATE_INT);
    $available = filter_var($_POST["available_copies"] ?? "", FILTER_VALIDATE_INT);
    $shelf = trim($_POST["shelf_location"] ?? "");

    if (
        $isbn === "" ||
        $title === "" ||
        $authorId === false ||
        $authorId === null ||
        $authorId < 1 ||
        $total === false ||
        $available === false ||
        $total < 1 ||
        $available < 0 ||
        $available > $total
    ) {
        $error = "Please enter valid book details. Select an author and ensure available copies do not exceed total copies.";

    } elseif (
        $year !== "" &&
        (
            !ctype_digit($year) ||
            (int)$year < 1000 ||
            (int)$year > (int)date("Y")
        )
    ) {
        $error = "Please enter a valid publication year.";

    } else {

        try {

            // Confirm author exists
            $checkAuthor = $conn->prepare("
                SELECT author_id
                FROM authors
                WHERE author_id = ?
            ");

            $checkAuthor->execute([$authorId]);

            if (!$checkAuthor->fetch()) {
                throw new InvalidArgumentException(
                    "Please select a valid author."
                );
            }

            // Validate category
            $categoryId = null;

            if ($category !== "") {

                $categoryId = filter_var(
                    $category,
                    FILTER_VALIDATE_INT
                );

                if ($categoryId === false || $categoryId < 1) {
                    throw new InvalidArgumentException(
                        "Please select a valid book category."
                    );
                }

                $checkCategory = $conn->prepare("
                    SELECT category_id
                    FROM categories
                    WHERE category_id = ?
                ");

                $checkCategory->execute([$categoryId]);

                if (!$checkCategory->fetch()) {
                    throw new InvalidArgumentException(
                        "The selected category does not exist."
                    );
                }
            }

            // Get the selected author's name temporarily
            // to maintain compatibility with existing Books pages.
            $authorNameStmt = $conn->prepare("
                SELECT author_name
                FROM authors
                WHERE author_id = ?
            ");

            $authorNameStmt->execute([$authorId]);
            $authorName = $authorNameStmt->fetchColumn();

            $stmt = $conn->prepare("
                INSERT INTO books (
                    isbn,
                    title,
                    author,
                    author_id,
                    publisher,
                    publication_year,
                    category_id,
                    total_copies,
                    available_copies,
                    shelf_location
                )
                VALUES (
                    :isbn,
                    :title,
                    :author,
                    :author_id,
                    :publisher,
                    :publication_year,
                    :category_id,
                    :total_copies,
                    :available_copies,
                    :shelf_location
                )
            ");

            $stmt->execute([
                "isbn" => $isbn,
                "title" => $title,
                "author" => $authorName,
                "author_id" => $authorId,
                "publisher" => $publisher ?: null,
                "publication_year" => $year === "" ? null : (int)$year,
                "category_id" => $categoryId,
                "total_copies" => $total,
                "available_copies" => $available,
                "shelf_location" => $shelf ?: null
            ]);

            header("Location: index.php?message=added");
            exit();

        } catch (InvalidArgumentException $e) {

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($e->getCode() === "23000") {
                $error = "This ISBN already exists, or the selected author or category is invalid.";
            } else {
                $error = "Unable to save the book. Please check your database details.";
            }
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Book | Library Management System</title>
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
    line-height: 1.6;
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

.category-help {
    margin-top: 10px;
    font-size: 14px;
    color: #756b80;
}

.category-help a {
    color: #7546b8;
    font-weight: bold;
    text-decoration: none;
}

.category-help a:hover {
    text-decoration: underline;
}

.buttons {
    display: flex;
    gap: 18px;
    margin-top: 40px;
    flex-wrap: wrap;
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
        <h1>📚 Add New Book</h1>
        <p>
            Register a new book in the library management system.
            Complete the information below.
        </p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="add.php">

        <div class="form-grid">

            <div class="form-group">
                <label for="isbn">ISBN *</label>
                <input
                    type="text"
                    id="isbn"
                    name="isbn"
                    placeholder="Enter ISBN number"
                    value="<?= e($_POST["isbn"] ?? "") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="title">Book Title *</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="Enter book title"
                    value="<?= e($_POST["title"] ?? "") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="author_id">Author *</label>

                <select name="author_id" id="author_id" required>
                    <option value="">Select Author</option>

                    <?php foreach ($authors as $author): ?>
                        <option
                            value="<?= (int)$author["author_id"] ?>"
                            <?= (($_POST["author_id"] ?? "") == $author["author_id"]) ? "selected" : "" ?>
                        >
                            <?= e($author["author_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (count($authors) === 0): ?>
                    <p class="category-help">
                        No authors available.
                        <a href="../authors/add.php">Add an author here.</a>
                    </p>
                <?php else: ?>
                    <p class="category-help">
                        Select the author of this book.
                        <a href="../authors/index.php">Manage authors</a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="publisher">Publisher</label>
                <input
                    type="text"
                    id="publisher"
                    name="publisher"
                    placeholder="Enter publisher"
                    value="<?= e($_POST["publisher"] ?? "") ?>"
                >
            </div>

            <div class="form-group">
                <label for="publication_year">Publication Year</label>
                <input
                    type="number"
                    id="publication_year"
                    name="publication_year"
                    min="1000"
                    max="<?= date('Y') ?>"
                    placeholder="e.g. 2024"
                    value="<?= e($_POST["publication_year"] ?? "") ?>"
                >
            </div>

            <div class="form-group">
                <label for="category_id">Book Category</label>

                <select name="category_id" id="category_id">
                    <option value="">Select Category</option>

                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= (int)$cat["category_id"] ?>"
                            <?= (($_POST["category_id"] ?? "") == $cat["category_id"]) ? "selected" : "" ?>
                        >
                            <?= e($cat["category_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (count($categories) === 0): ?>
                    <p class="category-help">
                        No categories available.
                        <a href="../categories/add.php">Add a category here.</a>
                    </p>
                <?php else: ?>
                    <p class="category-help">
                        Select the category that best describes this book.
                        <a href="../categories/index.php">Manage categories</a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="total_copies">Total Copies *</label>
                <input
                    type="number"
                    id="total_copies"
                    name="total_copies"
                    min="1"
                    placeholder="Enter total copies"
                    value="<?= e($_POST["total_copies"] ?? "1") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="available_copies">Available Copies *</label>
                <input
                    type="number"
                    id="available_copies"
                    name="available_copies"
                    min="0"
                    placeholder="Enter available copies"
                    value="<?= e($_POST["available_copies"] ?? "1") ?>"
                    required
                >
            </div>

            <div class="form-group full-width">
                <label for="shelf_location">Shelf Location</label>
                <input
                    type="text"
                    id="shelf_location"
                    name="shelf_location"
                    placeholder="e.g. Shelf A1 or Section B"
                    value="<?= e($_POST["shelf_location"] ?? "") ?>"
                >
            </div>

        </div>

        <div class="buttons">
            <button type="submit" <?= count($authors) === 0 ? "disabled" : "" ?>>
                Save Book
            </button>

            <a href="index.php" class="cancel">Cancel</a>
        </div>

    </form>

    <div class="footer">
        Library Management System | Book Registration
    </div>

</div>
</div>

</body>
</html>
