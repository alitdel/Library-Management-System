
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$errors = [];

$book_id = '';
$member_id = '';
$loan_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+14 days'));

// Use the ID of the currently logged-in staff member.
$issued_by = $_SESSION['user_id'] ?? null;

// Load available books.
$booksStmt = $conn->query("
    SELECT book_id, title, isbn, available_copies
    FROM books
    WHERE available_copies > 0
    ORDER BY title ASC
");
$books = $booksStmt->fetchAll(PDO::FETCH_ASSOC);

// Load active members.
$membersStmt = $conn->query("
    SELECT member_id, member_number, full_name
    FROM members
    WHERE status = 'active'
    ORDER BY full_name ASC
");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int) ($_POST['book_id'] ?? 0);
    $member_id = (int) ($_POST['member_id'] ?? 0);
    $loan_date = $_POST['loan_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';

    if ($book_id < 1) {
        $errors[] = 'Please select a book.';
    }

    if ($member_id < 1) {
        $errors[] = 'Please select a member.';
    }

    $validDate = function ($date) {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    };

    if (!$validDate($loan_date)) {
        $errors[] = 'Enter a valid borrowing date.';
    }

    if (!$validDate($due_date)) {
        $errors[] = 'Enter a valid due date.';
    } elseif ($validDate($loan_date) && $due_date < $loan_date) {
        $errors[] = 'The due date cannot be before the borrowing date.';
    }

    if (!$issued_by) {
        $errors[] = 'Your staff login could not be verified. Please log in again.';
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Lock the book record to prevent issuing the last copy twice.
            $bookStmt = $conn->prepare("
                SELECT available_copies
                FROM books
                WHERE book_id = ?
                FOR UPDATE
            ");
            $bookStmt->execute([$book_id]);
            $book = $bookStmt->fetch(PDO::FETCH_ASSOC);

            if (!$book || (int) $book['available_copies'] < 1) {
                throw new Exception('This book has no available copies.');
            }

            // Confirm that the selected member is still active.
            $memberStmt = $conn->prepare("
                SELECT member_id
                FROM members
                WHERE member_id = ? AND status = 'active'
            ");
            $memberStmt->execute([$member_id]);

            if (!$memberStmt->fetch()) {
                throw new Exception('The selected member is not active or does not exist.');
            }

            // Create the borrowing record.
            $insertStmt = $conn->prepare("
                INSERT INTO loans
                (book_id, member_id, issued_by, loan_date, due_date, status)
                VALUES (?, ?, ?, ?, ?, 'borrowed')
            ");

            $insertStmt->execute([
                $book_id,
                $member_id,
                $issued_by,
                $loan_date,
                $due_date
            ]);

            // Reduce the available copies.
            $updateStmt = $conn->prepare("
                UPDATE books
                SET available_copies = available_copies - 1
                WHERE book_id = ? AND available_copies > 0
            ");
            $updateStmt->execute([$book_id]);

            if ($updateStmt->rowCount() !== 1) {
                throw new Exception('Unable to update the available book copies.');
            }

            $conn->commit();

            header('Location: index.php?message=added');
            exit;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $errors[] = 'The borrowing record could not be saved. Please check the selected information and try again.';

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $errors[] = $e->getMessage();
        }
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue a Book | Library Management System</title>
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

        .heading {
            margin-bottom: 30px;
        }

        .heading h1 {
            color: #35135f;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .heading p {
            color: #756b80;
            font-size: 16px;
        }

        .form-card {
            width: 100%;
            max-width: 1450px;
            background: white;
            padding: 45px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(53, 19, 95, 0.07);
        }

        .form-card h2 {
            color: #51218a;
            font-size: 23px;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 11px;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            color: #35135f;
            font-weight: bold;
            font-size: 15px;
        }

        input, select {
            width: 100%;
            padding: 17px;
            min-height: 58px;
            border: 1px solid #d9cde8;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            outline: none;
        }

        input:focus, select:focus {
            border-color: #7546b8;
            box-shadow: 0 0 0 3px rgba(117, 70, 184, 0.1);
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .save-btn {
            background: #51218a;
            color: white;
            border: none;
            padding: 17px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .cancel-btn {
            background: #eee8f5;
            color: #51218a;
            padding: 17px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .errors {
            background: #ffe7e7;
            color: #a02020;
            padding: 18px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .errors ul {
            margin-left: 20px;
        }

        .errors li {
            margin: 6px 0;
        }

        .note {
            background: #f0eaf8;
            color: #51218a;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.6;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <h2>Library Management System</h2>
    <a href="../dashboard.php">Dashboard</a>
    <a href="../books/index.php">Books Management</a>
    <a href="../categories/index.php">Categories Management</a>
    <a href="../members/index.php">Members Management</a>
    <a href="index.php" class="active">Borrowing Records</a>
    <a href="../logout.php">Logout</a>
</aside>

<main class="main">

    <div class="heading">
        <h1>Issue a Book</h1>
        <p>Record a book borrowed by a registered library member.</p>
    </div>

    <section class="form-card">

        <h2>Borrowing Information</h2>

        <div class="note">
            Available book copies are updated automatically after a successful issue.
            The normal borrowing period is 14 days.
        </div>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="add.php">

            <div class="form-grid">

                <div class="form-group">
                    <label for="book_id">Select Book *</label>
                    <select id="book_id" name="book_id" required>
                        <option value="">Choose a book</option>

                        <?php foreach ($books as $book): ?>
                            <option value="<?= (int) $book['book_id'] ?>"
                                <?= (string) $book_id === (string) $book['book_id'] ? 'selected' : '' ?>>
                                <?= e($book['title']) ?>
                                (<?= e($book['isbn']) ?> -
                                <?= (int) $book['available_copies'] ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($books)): ?>
                        <small>No books currently have available copies.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="member_id">Select Library Member *</label>
                    <select id="member_id" name="member_id" required>
                        <option value="">Choose a member</option>

                        <?php foreach ($members as $member): ?>
                            <option value="<?= (int) $member['member_id'] ?>"
                                <?= (string) $member_id === (string) $member['member_id'] ? 'selected' : '' ?>>
                                <?= e($member['full_name']) ?>
                                (<?= e($member['member_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($members)): ?>
                        <small>No active library members are registered.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="loan_date">Borrowing Date *</label>
                    <input type="date" id="loan_date" name="loan_date"
                           value="<?= e($loan_date) ?>" required>
                </div>

                <div class="form-group">
                    <label for="due_date">Expected Return Date *</label>
                    <input type="date" id="due_date" name="due_date"
                           value="<?= e($due_date) ?>" required>
                </div>

            </div>

            <div class="buttons">
                <button type="submit" class="save-btn">Issue Book</button>
                <a href="index.php" class="cancel-btn">Cancel</a>
            </div>

        </form>

    </section>

</main>

</body>
</html>
