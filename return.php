
<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$errors = [];
$loan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$loan_id || $loan_id < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT
        loans.loan_id,
        loans.book_id,
        loans.loan_date,
        loans.due_date,
        loans.status,
        books.title,
        books.isbn,
        members.full_name,
        members.member_number
    FROM loans
    INNER JOIN books ON loans.book_id = books.book_id
    INNER JOIN members ON loans.member_id = members.member_id
    WHERE loans.loan_id = ?
");
$stmt->execute([$loan_id]);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    header('Location: index.php?message=error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($loan['status'] === 'returned') {
        $errors[] = 'This book has already been returned.';
    } else {
        try {
            $conn->beginTransaction();

            // Lock the loan record to prevent duplicate returns.
            $lockStmt = $conn->prepare("
                SELECT book_id, status
                FROM loans
                WHERE loan_id = ?
                FOR UPDATE
            ");
            $lockStmt->execute([$loan_id]);
            $lockedLoan = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedLoan || $lockedLoan['status'] !== 'borrowed') {
                throw new Exception('This loan is no longer marked as borrowed.');
            }

            $returnDate = date('Y-m-d');

            // Mark the loan as returned.
            $updateLoan = $conn->prepare("
                UPDATE loans
                SET status = 'returned', return_date = ?
                WHERE loan_id = ? AND status = 'borrowed'
            ");
            $updateLoan->execute([$returnDate, $loan_id]);

            if ($updateLoan->rowCount() !== 1) {
                throw new Exception('Unable to update the borrowing record.');
            }

            // Restore one available copy.
            $updateBook = $conn->prepare("
                UPDATE books
                SET available_copies = LEAST(total_copies, available_copies + 1)
                WHERE book_id = ?
            ");
            $updateBook->execute([$lockedLoan['book_id']]);

            $conn->commit();

            header('Location: index.php?message=returned');
            exit;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $errors[] = 'The return could not be recorded. Please try again.';

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
    <title>Return Book | Library Management System</title>
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

        .card {
            max-width: 1100px;
            background: white;
            padding: 40px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(53, 19, 95, 0.07);
        }

        .card h2 {
            color: #51218a;
            margin-bottom: 25px;
            font-size: 24px;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .detail {
            background: #f8f5fc;
            padding: 20px;
            border-radius: 8px;
        }

        .detail strong {
            display: block;
            color: #756b80;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .detail span {
            color: #35135f;
            font-size: 17px;
            font-weight: bold;
        }

        .status {
            margin-top: 25px;
            padding: 15px;
            border-radius: 8px;
            background: #fff0d9;
            color: #8a5100;
        }

        .returned-status {
            background: #e4f6e9;
            color: #176b32;
        }

        .errors {
            background: #ffe7e7;
            color: #a02020;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .return-btn {
            background: #51218a;
            color: white;
            border: none;
            padding: 16px 26px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .cancel-btn {
            background: #eee8f5;
            color: #51218a;
            padding: 16px 26px;
            border-radius: 8px;
            text-decoration: none;
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

            .card {
                padding: 22px;
            }

            .details {
                grid-template-columns: 1fr;
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
        <h1>Return Book</h1>
        <p>Confirm the return of a borrowed library book.</p>
    </div>

    <section class="card">

        <h2>Borrowing Details</h2>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="details">

            <div class="detail">
                <strong>Loan Number</strong>
                <span><?= (int) $loan['loan_id'] ?></span>
            </div>

            <div class="detail">
                <strong>Book Title</strong>
                <span><?= e($loan['title']) ?></span>
            </div>

            <div class="detail">
                <strong>ISBN</strong>
                <span><?= e($loan['isbn']) ?></span>
            </div>

            <div class="detail">
                <strong>Member Name</strong>
                <span><?= e($loan['full_name']) ?></span>
            </div>

            <div class="detail">
                <strong>Member Number</strong>
                <span><?= e($loan['member_number']) ?></span>
            </div>

            <div class="detail">
                <strong>Borrowing Date</strong>
                <span><?= e($loan['loan_date']) ?></span>
            </div>

            <div class="detail">
                <strong>Expected Return Date</strong>
                <span><?= e($loan['due_date']) ?></span>
            </div>

        </div>

        <?php if ($loan['status'] === 'returned'): ?>

            <div class="status returned-status">
                This book has already been returned.
            </div>

        <?php else: ?>

            <div class="status">
                This book is currently borrowed and awaiting return.
            </div>

            <form method="POST" action="return.php?id=<?= (int) $loan_id ?>"
                  onsubmit="return confirm('Confirm that this book has been returned?');">

                <div class="buttons">
                    <button type="submit" class="return-btn">
                        Confirm Book Return
                    </button>

                    <a href="index.php" class="cancel-btn">Cancel</a>
                </div>

            </form>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
