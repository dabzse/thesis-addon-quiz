# Edu_Jobs Thesis Addon Quiz Project

This project contains the backend (PHP) and frontend (Svelte) for the quiz application.
(A magyar nyelvű leírást a `README_hu.md` fájlban találod.)

[Video link, Hungarian only](https://youtube.com/watch?v=Ji7bOf5txgA)

Continue developing here: [FSF.hu SysAdminDay Quiz](https://gitlab.com/dabzse/fsfhu-quiz)

## File Structure and Configuration

The application's configuration is managed in a single location: the **`backend/.env`** file. (The `.env.example` file in the root directory serves as a template).

### Setting up Environment Variables (.env)

1. Copy the `.env.example` file to the `backend/` directory as `.env`!

   ```bash
   cp .env.example backend/.env
   ```

2. Open the `backend/.env` file and fill in your MariaDB / MySQL details.

| Variable      | Description                               | Default       |
| :------------ | :---------------------------------------- | :------------ |
| `DB_HOST`     | Database server address                   | `localhost`   |
| `DB_PORT`     | Database server port for TCP connections  | `3306`        |
| `DB_SOCKET`   | Unix socket path \*                       | *empty*       |
| `DB_NAME`     | Database name                             | -             |
| `DB_USER`     | Database username                         | -             |
| `DB_PASS`     | Database password \*\*                    | -             |

## How the Database Connection Works

The `backend/src/Database/Connection.php` file is responsible for the database connection.

1. It looks for the `backend/.env` file and loads its variables into the `$_ENV` superglobal array.
2. It attempts to build a PDO object.
3. If you filled out `DB_SOCKET`, the system establishes a "unix socket" based connection, which is structurally more stable and faster for local (same-machine) development on Linux.
4. If `DB_SOCKET` is empty, it bypasses it and creates a standard network (TCP) connection using `DB_HOST` and `DB_PORT`.

## Installation before running

### Backend

Since the backend is built on PHP, you need to run Composer first to install dependencies:

```bash
cd backend
composer install
```

Running in "normal" mode:

```bash
php -S localhost:8000
```

To start the built-in web server for testing or local development:

```bash
php -S localhost:8000 -t public
```

### Frontend

Install the Svelte application dependencies and run the server (using pnpm):

```bash
cd frontend
pnpm install
pnpm dev
```

> \* : (e.g., `/var/run/mysqld/mysqld.sock` or `/var/lib/mysql/mysql.sock`). **If you fill this in, `DB_HOST` and `DB_PORT` will be ignored**, overridden by the faster local socket.
>
> \*\* (if left empty, it tries to connect without a password)
>
> **Warning regarding database passwords:** MariaDB/MySQL natively denies *passwordless* local logins by default, even if no password is set for the specific user (except when using unix socket authentication). If you encounter a `500 Internal Server Error` or an `Access denied (using password: NO)` error, verify your `DB_PASS` value in the `backend/.env` file. Often, local database clients (like DbGate) connect using their own saved, hashed passwords!
