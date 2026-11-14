
<?php
// Protect this page: user must be logged in.
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Delete a borrowing record.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
    $loan_id = (int) ($_POST['loan_id'] ?? 0);

    if ($loan_id > 0) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("
                SELECT book_id, status
                FROM loans
                WHERE loan_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($loan) {
                // Restore a copy if the book has not yet been returned.
                if ($loan['status'] === 'borrowed') {
                    $restore = $conn->prepare("
                        UPDATE books
                        SET available_copies =
                            LEAST(total_copies, available_copies + 1)
                        WHERE book_id = ?
                    ");
                    $restore->execute([$loan['book_id']]);
                }

                $delete = $conn->prepare(
                    "DELETE FROM loans WHERE loan_id = ?"
                );
                $delete->execute([$loan_id]);
            }

            $conn->commit();

            header("Location: index.php?message=deleted");
            exit;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            header("Location: index.php?message=error");
            exit;
        }
    }

    header("Location: index.php?message=error");
    exit;
}

// Search borrowing records.
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        loans.loan_id,
        loans.loan_date,
        loans.due_date,
        loans.return_date,
        loans.status,
        books.title,
        books.isbn,
        members.member_number,
        members.full_name,
        users.full_name AS staff_name
    FROM loans
    INNER JOIN books
        ON loans.book_id = books.book_id
    INNER JOIN members
        ON loans.member_id = members.member_id
    INNER JOIN users
        ON loans.issued_by = users.user_id
";

if ($search !== '') {
    $sql .= "
        WHERE books.title LIKE ?
        OR books.isbn LIKE ?
        OR members.full_name LIKE ?
        OR members.member_number LIKE ?
    ";
}

$sql .= " ORDER BY loans.loan_id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term]);
} else {
    $stmt->execute();
}

$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count overdue books.
$overdueStmt = $conn->query("
    SELECT COUNT(*)
    FROM loans
    WHERE status = 'borrowed'
    AND due_date < CURDATE()
");

$overdueCount = (int) $overdueStmt->fetchColumn();

// Safely escape output.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Borrowing Records | Library Management System</title>
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
            padding: 30px;
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

        .warning {
            background: #fff0d9;
            color: #8a5100;
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

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        th {
            background: #f0eaf8;
            color: #35135f;
            text-align: left;
            padding: 18px 15px;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid #eee8f5;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .borrowed {
            background: #fff0d9;
            color: #8a5100;
        }

        .returned {
            background: #e4f6e9;
            color: #176b32;
        }

        .overdue {
            background: #ffe0e0;
            color: #a02020;
        }

        .edit-btn,
        .delete-btn {
            border: none;
            padding: 9px 13px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            display: inline-block;
        }

        .edit-btn {
            background: #eee8f5;
            color: #51218a;
        }

        .edit-btn:hover {
            background: #dcd0ee;
        }

        .delete-btn {
            background: #ffe7e7;
            color: #a02020;
        }

        .delete-btn:hover {
            background: #ffd0d0;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .empty {
            text-align: center;
            padding: 45px;
            color: #756b80;
        }

        .overdue-summary {
            margin-bottom: 22px;
            padding: 17px 20px;
            background: #fff0d9;
            color: #8a5100;
            border-radius: 8px;
            font-weight: bold;
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
    <a href="../members/index.php">Members Management</a>
    <a href="index.php" class="active">Borrowing Records</a>
    <a href="../logout.php">Logout</a>
</aside>

<main class="main">

    <div class="header">
        <div>
            <h1>Borrowing Records</h1>
            <p>Manage books borrowed and returned by library members.</p>
        </div>

        <a href="add.php" class="add-btn">+ Issue a Book</a>
    </div>

    <?php if (($_GET['message'] ?? '') === 'deleted'): ?>
        <div class="message success">
            Borrowing record deleted successfully.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'error'): ?>
        <div class="message error">
            Unable to delete the borrowing record.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'added'): ?>
        <div class="message success">
            Book issued successfully.
        </div>

    <?php elseif (($_GET['message'] ?? '') === 'returned'): ?>
        <div class="message success">
            Book returned successfully.
        </div>
    <?php endif; ?>

    <section class="content-card">

        <?php if ($overdueCount > 0): ?>
            <div class="overdue-summary">
                Overdue books: <?= $overdueCount ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="index.php" class="search-area">

            <input
                type="text"
                name="search"
                placeholder="Search by book title, ISBN or member..."
                value="<?= e($search) ?>"
            >

            <button type="submit">Search Records</button>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>Book Title</th>
                        <th>ISBN</th>
                        <th>Member Number</th>
                        <th>Member Name</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Issued By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($loans) > 0): ?>

                        <?php foreach ($loans as $loan): ?>

                            <?php
                            $isOverdue = (
                                $loan['status'] === 'borrowed'
                                && $loan['due_date'] < date('Y-m-d')
                            );
                            ?>

                            <tr>

                                <td><?= (int) $loan['loan_id'] ?></td>

                                <td><?= e($loan['title']) ?></td>

                                <td><?= e($loan['isbn']) ?></td>

                                <td><?= e($loan['member_number']) ?></td>

                                <td><?= e($loan['full_name']) ?></td>

                                <td><?= e($loan['loan_date']) ?></td>

                                <td><?= e($loan['due_date']) ?></td>

                                <td>
                                    <?= e($loan['return_date'] ?: 'Not returned') ?>
                                </td>

                                <td><?= e($loan['staff_name']) ?></td>

                                <td>

                                    <?php if ($isOverdue): ?>

                                        <span class="status overdue">
                                            Overdue
                                        </span>

                                    <?php elseif ($loan['status'] === 'returned'): ?>

                                        <span class="status returned">
                                            Returned
                                        </span>

                                    <?php else: ?>

                                        <span class="status borrowed">
                                            Borrowed
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="actions">

                                        <?php if ($loan['status'] === 'borrowed'): ?>

                                            <a
                                                href="return.php?id=<?= (int) $loan['loan_id'] ?>"
                                                class="edit-btn"
                                            >
                                                Return
                                            </a>

                                        <?php endif; ?>

                                        <form
                                            method="POST"
                                            action="index.php"
                                            onsubmit="return confirm('Delete this borrowing record?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="loan_id"
                                                value="<?= (int) $loan['loan_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_loan"
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
                            <td colspan="11" class="empty">
                                No borrowing records found.
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
