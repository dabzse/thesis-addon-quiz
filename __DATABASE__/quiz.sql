-- CREATE DATABASE <DB_NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE <DB_NAME>;

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


/**
    A type mező mondja meg:

        single — egy helyes válasz, radio button a frontenden
        multiple — több helyes válasz, checkbox a frontenden
        truefalse — igaz/hamis, automatikusan 2 válasz
*/

-- sorsolás miatt átmenetileg tároljuk a belépőjegy sorozatszámát
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

-- ADMIN

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` VARCHAR(255) NOT NULL
);
