# Library Management System

## 1. Project Description

The Library Management System is a web-based application developed using PHP and MySQL. It helps a library manage books, authors, categories, members, users and borrowing records.

The system makes it easier to record borrowed books, monitor returns and manage library information.

## 2. Technologies Used

- PHP
- MySQL / MariaDB
- HTML
- CSS
- JavaScript
- XAMPP
- phpMyAdmin
- Visual Studio Code

## 3. Main System Modules

1. **Dashboard** – Displays library statistics.
2. **Books Management** – Add, view, edit and manage books.
3. **Authors Management** – Manage book authors.
4. **Categories Management** – Organize books into categories.
5. **Members Management** – Register and manage library members.
6. **Borrowing Records** – Issue books and record returns.
7. **User Authentication** – Login and logout.
8. **Digital Reading Corner** – Browse catalog records and open verified, locally supplied PDF editions.

## 4. Database Structure

The database is named `library_management_system`.

It contains six main tables:

- `users`
- `books`
- `authors`
- `categories`
- `members`
- `loans`

The tables are connected using primary keys and foreign keys to maintain relationships between records.

## 5. Project Folder Structure

```text
Llbrarymanagementsystem/
│
├── admin/
├── assets/
├── authors/
├── books/
├── CATEGORIES/
├── config/
├── database/
│   ├── library_db.sql
│   └── queries.sql
├── includes/
├── loans/
├── members/
├── dashboard.php
├── index.php
├── login.php
├── logout.php
└── README.md
```

## 6. Installation Requirements

- Windows computer
- XAMPP
- PHP
- MySQL or MariaDB
- Web browser

## 7. How to Run the Project

1. Install and open XAMPP.
2. Start Apache and MariaDB.
3. Place the project folder inside `C:\xampp\htdocs`.
4. Open the browser.
5. Visit:

   `http://localhost/Llbrarymanagementsystem/`

6. Log in using an authorized system account.

## 8. Database Configuration

The database connection settings are stored in:

`config/db.php`

The current local configuration uses:

- Host: `localhost`
- Port: `3307`
- Database: `library_management_system`
- Username: `root`

The database SQL file is provided in the `database` folder.

**Important:** `library_db.sql` is intended for a fresh, empty database. Do not import it over an existing working database because it contains table deletion statements.

## 9. SQL Files

- `database/library_db.sql` – Creates the database tables and inserts sample records.
- `database/queries.sql` – Contains SQL queries demonstrating joins and aggregate reports.

## 10. Security Features

The project uses session-based authentication to protect system pages. PHP prepared statements are used for database operations involving user input, and output is escaped to reduce cross-site scripting risks.

## 11. Digital Reading Corner

The reading corner only offers an “Open authorized PDF” action for a catalog book that has a locally stored PDF mapped to its existing book ID. It does not treat physical-only books as digital editions, and it does not download or bundle copyrighted books.

To add a digital edition, confirm that the library has the rights to provide the file, place the PDF in `digital-books/`, and add its existing book ID and filename to the returned array in `config/digital_books.php`:

```php
return [
    123 => 'rights-cleared-study-guide.pdf',
];
```

Replace `123` with the matching ID from the `books` table and the filename with the locally supplied PDF. Only PDF files stored directly in the protected `digital-books/` directory are supported; direct web access to that directory is denied. The reading page and PDF endpoint require an authenticated session. No database changes are needed.

## 12. Conclusion

The Library Management System provides a centralized way to manage library resources, members and borrowing activities using a web-based application.