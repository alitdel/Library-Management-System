
<?php
// Authentication protection.
require_once __DIR__ . '/../config/auth.php';

// Database connection.
require_once __DIR__ . '/../config/db.php';

// Get category ID.
$category_id = (int) (
    $_GET['id'] ?? $_POST['category_id'] ?? 0
);

if ($category_id <= 0) {
    header("Location: index.php?message=error");
    exit;
}

// Retrieve category.
$stmt = $conn->prepare("
    SELECT category_id, category_name, description
    FROM categories
    WHERE category_id = ?
");

$stmt->execute([$category_id]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header("Location: index.php?message=error");
    exit;
}

$error = '';

// Process category update.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_name === '') {

        $error = 'Please enter the category name.';

    } elseif (mb_strlen($category_name) > 100) {

        $error = 'Category name must not exceed 100 characters.';

    } else {

        try {

            // Check whether another category uses this name.
            $check = $conn->prepare("
                SELECT category_id
                FROM categories
                WHERE category_name = ?
                AND category_id != ?
                LIMIT 1
            ");

            $check->execute([
                $category_name,
                $category_id
            ]);

            if ($check->fetch(PDO::FETCH_ASSOC)) {

                $error = 'This category name already exists.';

            } else {

                // Update category.
                $update = $conn->prepare("
                    UPDATE categories
                    SET category_name = ?, description = ?
                    WHERE category_id = ?
                ");

                $update->execute([
                    $category_name,
                    $description !== '' ? $description : null,
                    $category_id
                ]);

                header("Location: index.php?message=updated");
                exit;
            }

        } catch (PDOException $e) {

            $error = 'Unable to update category. Please try again.';
        }
    }

    // Preserve submitted values if validation fails.
    $category['category_name'] = $category_name;
    $category['description'] = $description;
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

    <title>Edit Category | Library Management System</title>
    <link rel="stylesheet" href="../assets/css/library-theme.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
            background: #f5f2fa;
            color: #29213a;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            padding: 45px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(53, 19, 95, 0.09);
        }

        h1 {
            color: #35135f;
            margin-top: 0;
            font-size: 30px;
        }

        .subtitle {
            color: #777;
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin: 22px 0 10px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 17px;
            border: 1px solid #ddd3e9;
            border-radius: 9px;
            font-size: 16px;
            font-family: Arial, sans-serif;
        }

        textarea {
            min-height: 160px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #7546b8;
            box-shadow: 0 0 0 3px rgba(117, 70, 184, 0.12);
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        button,
        .back {
            padding: 16px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #51218a;
            color: white;
        }

        button:hover {
            background: #35135f;
        }

        .back {
            background: #eee8f5;
            color: #35135f;
        }

        .error {
            background: #ffe7e7;
            color: #a32121;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {

            .container {
                padding: 25px;
            }

            .buttons {
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Edit Category</h1>

    <p class="subtitle">
        Update the category information below.
    </p>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?= $category_id ?>">

        <input
            type="hidden"
            name="category_id"
            value="<?= $category_id ?>"
        >

        <label for="category_name">
            Category Name
        </label>

        <input
            type="text"
            id="category_name"
            name="category_name"
            value="<?= e($category['category_name']) ?>"
            maxlength="100"
            required
        >

        <label for="description">
            Description
        </label>

        <textarea
            id="description"
            name="description"
            placeholder="Enter a description for this category"
        ><?= e($category['description'] ?? '') ?></textarea>

        <div class="buttons">

            <button type="submit">
                Update Category
            </button>

            <a class="back" href="index.php">
                Cancel
            </a>

        </div>

    </form>

</div>

</body>
</html>
