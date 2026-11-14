
<?php
// Protect this page: only logged-in users can access it.
require_once __DIR__ . '/../config/auth.php';

// Connect to the database.
require_once __DIR__ . '/../config/db.php';

// Delete a category using POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {

    $category_id = (int) ($_POST['category_id'] ?? 0);

    if ($category_id > 0) {
        try {
            $stmt = $conn->prepare(
                "DELETE FROM categories WHERE category_id = ?"
            );

            $stmt->execute([$category_id]);

            header("Location: index.php?message=deleted");
            exit;

        } catch (PDOException $e) {
            header("Location: index.php?message=error");
            exit;
        }
    }
}

// Search categories.
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        categories.category_id,
        categories.category_name,
        categories.description,
        COUNT(books.book_id) AS book_count
    FROM categories
    LEFT JOIN books
        ON categories.category_id = books.category_id
";

if ($search !== '') {
    $sql .= "
        WHERE categories.category_name LIKE ?
        OR categories.description LIKE ?
    ";
}

$sql .= "
    GROUP BY
        categories.category_id,
        categories.category_name,
        categories.description
    ORDER BY categories.category_name ASC
";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->execute([$term, $term]);
} else {
    $stmt->execute();
}

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Safely display text.
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

    <title>Categories Management | Library Management System</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 35px;
        }

        .header h1 {
            color: #35135f;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .header p {
            color: #756b80;
            font-size: 16px;
        }

        .add-btn {
            background: #51218a;
            color: white;
            text-decoration: none;
            padding: 16px 24px;
            border-radius: 9px;
            font-weight: bold;
        }

        .add-btn:hover {
            background: #7546b8;
        }

        .content-card {
            background: white;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(53, 19, 95, 0.07);
            width: 100%;
        }

        .message {
            padding: 17px 20px;
            border-radius: 8px;
            margin-bottom: 22px;
        }

        .success {
            background: #e4f6e9;
            color: #176b32;
        }

        .error {
            background: #ffe7e7;
            color: #a02020;
        }

        .search-area {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .search-area input {
            flex: 1;
            min-width: 230px;
            padding: 16px;
            border: 1px solid #d9cde8;
            border-radius: 8px;
            font-size: 16px;
        }

        .search-area button {
            background: #7546b8;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 15px 25px;
            cursor: pointer;
            font-size: 15px;
        }

        .search-area button:hover {
            background: #51218a;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        th {
            background: #f0eaf8;
            color: #35135f;
            text-align: left;
            padding: 18px 15px;
        }

        td {
            padding: 20px 15px;
            border-bottom: 1px solid #eee8f5;
            font-size: 15px;
        }

        .count {
            display: inline-block;
            background: #eee8f5;
            color: #51218a;
            padding: 7px 13px;
            border-radius: 20px;
            font-weight: bold;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .edit-btn {
            display: inline-block;
            background: #eee8f5;
            color: #51218a;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
        }

        .edit-btn:hover {
            background: #ddd0ef;
        }

        .delete-btn {
            background: #ffe7e7;
            color: #a02020;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #ffd0d0;
        }

        .empty {
            text-align: center;
            padding: 45px;
            color: #756b80;
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

            .content-card {
                padding: 18px;
            }

            .header h1 {
                font-size: 25px;
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

        <div>
            <h1>Categories Management</h1>
            <p>Organise and manage the categories of library books.</p>
        </div>

        <a href="add.php" class="add-btn">
            + Add New Category
        </a>

    </div>

    <?php if (($_GET['message'] ?? '') === 'deleted'): ?>

        <div class="message success">
            Category deleted successfully.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'updated'): ?>

        <div class="message success">
            Category updated successfully.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'added'): ?>

        <div class="message success">
            Category added successfully.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'error'): ?>

        <div class="message error">
            Unable to delete this category.
        </div>

    <?php endif; ?>

    <section class="content-card">

        <form method="GET" action="index.php" class="search-area">

            <input
                type="text"
                name="search"
                placeholder="Search category name or description..."
                value="<?= e($search) ?>"
            >

            <button type="submit">
                Search Categories
            </button>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Category ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Number of Books</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($categories) > 0): ?>

                        <?php foreach ($categories as $category): ?>

                            <tr>

                                <td>
                                    <?= (int) $category['category_id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($category['category_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($category['description'] ?: 'No description') ?>
                                </td>

                                <td>
                                    <span class="count">
                                        <?= (int) $category['book_count'] ?> Books
                                    </span>
                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            class="edit-btn"
                                            href="edit.php?id=<?= (int) $category['category_id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="index.php"
                                            onsubmit="return confirm('Delete this category? Books in this category will remain, but their category will become empty.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="category_id"
                                                value="<?= (int) $category['category_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_category"
                                                class="delete-btn"
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
                            <td colspan="5" class="empty">
                                No categories found. Add your first book category.
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
