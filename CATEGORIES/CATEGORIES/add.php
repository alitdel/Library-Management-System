
<?php
// Authentication protection.
require_once __DIR__ . '/../config/auth.php';

// Database connection.
require_once __DIR__ . '/../config/db.php';

// Form variables.
$category_name = '';
$description = '';
$error = '';

// Process form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate category name.
    if ($category_name === '') {

        $error = 'Please enter a category name.';

    } else {

        try {

            // Check whether the category already exists.
            $check = $conn->prepare("
                SELECT category_id
                FROM categories
                WHERE category_name = ?
                LIMIT 1
            ");

            $check->execute([$category_name]);

            if ($check->fetch(PDO::FETCH_ASSOC)) {

                $error = 'This category already exists.';

            } else {

                // Insert the new category.
                $insert = $conn->prepare("
                    INSERT INTO categories
                    (category_name, description)
                    VALUES (?, ?)
                ");

                $insert->execute([
                    $category_name,
                    $description !== '' ? $description : null
                ]);

                // Return to categories page.
                header("Location: index.php?message=added");
                exit;
            }

        } catch (PDOException $e) {

            $error = 'Unable to save the category. Please try again.';
        }
    }
}

// Escape output safely.
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Category | Library Management System</title>
    <link rel="stylesheet" href="../assets/css/library-theme.css">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f2fa;
            color: #302640;
        }

        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background: #35135f;
            color: white;
            padding: 35px 20px;
            overflow-y: auto;
        }

        .sidebar h2 {
            font-size: 23px;
            margin-bottom: 45px;
            line-height: 1.4;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #7546b8;
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
            margin-bottom: 10px;
        }

        .header p {
            color: #756b80;
            font-size: 16px;
        }

        .form-card {
            background: white;
            max-width: 1100px;
            padding: 45px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(53, 19, 95, 0.07);
        }

        .form-group {
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-weight: bold;
            color: #35135f;
            margin-bottom: 12px;
            font-size: 16px;
        }

        input,
        textarea {
            width: 100%;
            padding: 18px;
            border: 1px solid #d9cde8;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            background: #fff;
        }

        input {
            min-height: 58px;
        }

        textarea {
            min-height: 170px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            border-color: #7546b8;
            box-shadow: 0 0 0 3px rgba(117, 70, 184, 0.12);
        }

        .help-text {
            color: #756b80;
            font-size: 13px;
            margin-top: 9px;
        }

        .error-message {
            background: #ffe7e7;
            color: #a02020;
            padding: 17px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 35px;
        }

        .save-btn {
            background: #51218a;
            color: white;
            border: none;
            padding: 17px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .save-btn:hover {
            background: #7546b8;
        }

        .cancel-btn {
            background: #eee8f5;
            color: #51218a;
            text-decoration: none;
            padding: 17px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
        }

        .cancel-btn:hover {
            background: #dcd0ee;
        }

        @media (max-width: 800px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .sidebar h2 {
                margin-bottom: 15px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .form-card {
                padding: 22px;
            }

            .header h1 {
                font-size: 26px;
            }
        }

    </style>

</head>

<body>

<aside class="sidebar">

    <h2>Library Management System</h2>

    <a href="../dashboard.php">Dashboard</a>

    <a href="../books/index.php">Books Management</a>

    <a href="index.php" class="active">Categories</a>

    <a href="../members/index.php">Members Management</a>

    <a href="../loans/index.php">Borrowing Records</a>

    <a href="../logout.php">Logout</a>

</aside>

<main class="main">

    <div class="header">

        <h1>Add New Category</h1>

        <p>Create a category to organise books in your library.</p>

    </div>

    <section class="form-card">

        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="add.php">

            <div class="form-group">

                <label for="category_name">
                    Category Name
                </label>

                <input
                    type="text"
                    id="category_name"
                    name="category_name"
                    placeholder="Example: Computer Science"
                    value="<?= e($category_name) ?>"
                    maxlength="100"
                    required
                >

                <p class="help-text">
                    Enter a unique name for this book category.
                </p>

            </div>

            <div class="form-group">

                <label for="description">
                    Category Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe the types of books that belong to this category..."
                ><?= e($description) ?></textarea>

                <p class="help-text">
                    Explain briefly what this category contains.
                </p>

            </div>

            <div class="button-group">

                <button type="submit" class="save-btn">
                    Save Category
                </button>

                <a href="index.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>
