-- CREATE DATABASE <DB_NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE <DB_NAME>;

-- Base tables first, so foreign keys can point to them later.

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE question_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` VARCHAR(255) NOT NULL
);

-- Domain tables that reference the base tables above.

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    question TEXT NOT NULL,
    type_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_year YEAR(4) NOT NULL DEFAULT 2026,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (type_id) REFERENCES question_types(id)
);

CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    correct_position INT NULL,
    match_answer TEXT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    ticket_number VARCHAR(8) NOT NULL,
    name VARCHAR(100) NULL,
    event_year YEAR(4) NOT NULL,
    email VARCHAR(255) NULL,
    score INT NOT NULL,
    max_score INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seed data

INSERT INTO categories (id, name, slug, created_at) VALUES
(1, 'PHP', 'php', '2026-03-29 16:51:00'),
(2, 'JavaScript', 'javascript', '2026-03-29 16:51:00'),
(3, 'Linux', 'linux', '2026-03-29 16:51:00'),
(4, 'Személyek', 'szemelyek', '2026-03-30 07:27:16'),
(5, 'IT', 'it', '2026-03-30 08:02:16');

INSERT INTO question_types (id, name, label) VALUES
(1, 'single', 'Egy helyes'),
(2, 'multiple', 'Több helyes'),
(3, 'truefalse', 'Igaz/Hamis'),
(4, 'ordering', 'Sorbarakós'),
(5, 'matching', 'Párosítós');

INSERT INTO users (id, name, email, password, created_at) VALUES
(1, 'Admin', 'coder@local.host', '$2y$12$2cka.H1Nnj28xhh5mErN9OxZmCSZLvvurc992tAhZbnnKOVJXpS3e', '2026-03-29 19:43:39');
-- passwd = localhost

INSERT INTO settings (id, `key`, `value`) VALUES
(1, 'question_timer', '0'),
(2, 'total_timer', '0'),
(3, 'active_year', '2026'),
(4, 'show_correct_answers', '1'),
(5, 'show_correct_during', '1'),
(6, 'show_correct_final', '1');

INSERT INTO questions (id, category_id, question, type_id, created_at, event_year) VALUES
(1, 1, 'Mi a PSR-12?', 1, '2026-03-29 16:51:00', 2026),
(2, 1, 'Igaz-e hogy a PHP gyengén típusos nyelv?', 3, '2026-03-29 16:51:00', 2026),
(3, 2, 'Melyik kulcsszóval deklarálunk konstanst JavaScriptben?', 2, '2026-03-29 16:51:00', 2026),
(4, 3, 'Melyik parancs listázza a fájlokat?', 1, '2026-03-29 17:30:19', 2026),
(5, 3, 'Igaz-e hogy a Linux case-sensitive?', 3, '2026-03-29 17:30:19', 2026),
(6, 4, 'Ki a Linux "apja"?', 1, '2026-03-30 07:27:24', 2026),
(8, 5, 'ki kihez-mihez párosul?', 5, '2026-03-31 09:45:43', 2026),
(11, 5, 'rakd sorba!\n1. változó létrehozása\n2. érték módosítása\n3. új érték kiírása', 4, '2026-03-31 20:24:45', 2026),
(12, 5, 'csökkenő!', 4, '2026-03-31 20:25:02', 2026);

INSERT INTO answers (id, question_id, answer, is_correct, correct_position, match_answer) VALUES
(1, 1, 'Kódstílus szabvány', 1, NULL, NULL),
(2, 1, 'Autoloading szabvány', 0, NULL, NULL),
(3, 1, 'Adatbázis szabvány', 0, NULL, NULL),
(10, 4, 'ls', 1, NULL, NULL),
(11, 4, 'dir', 0, NULL, NULL),
(12, 4, 'list', 0, NULL, NULL),
(15, 6, 'Steven Paul Jobs', 0, NULL, NULL),
(16, 6, 'William Henry Gates, III', 0, NULL, NULL),
(17, 6, 'Linus Torvalds', 1, NULL, NULL),
(18, 3, 'var', 0, NULL, NULL),
(19, 3, 'let', 0, NULL, NULL),
(20, 3, 'const', 1, NULL, NULL),
(21, 3, 'define', 1, NULL, NULL),
(22, 5, 'Hamis', 0, NULL, NULL),
(23, 5, 'Igaz', 1, NULL, NULL),
(24, 2, 'Hamis', 0, NULL, NULL),
(25, 2, 'Igaz', 1, NULL, NULL),
(44, 8, 'Nikola Tesla', 0, NULL, 'váltóáram'),
(45, 8, 'Dennis MacAlistair Ritchie', 0, NULL, 'Brian Kernighan'),
(46, 8, 'Linux kernel', 0, NULL, 'Git verziókezelő'),
(80, 11, 'num = 3', 0, 1, NULL),
(81, 11, 'num *= 16', 0, 2, NULL),
(82, 11, 'print(num)', 0, 3, NULL),
(83, 12, '13', 0, 1, NULL),
(84, 12, '7', 0, 2, NULL),
(85, 12, '0', 0, 3, NULL);
