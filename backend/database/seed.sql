INSERT INTO categories (name, slug) VALUES
('PHP', 'php'),
('JavaScript', 'javascript'),
('Linux', 'linux');

INSERT INTO questions (category_id, question, type) VALUES
(1, 'Mi a PSR-12?', 'single'),
(1, 'Igaz-e hogy a PHP gyengén típusos nyelv?', 'truefalse'),
(2, 'Melyik kulcsszóval deklarálunk konstanst JavaScriptben?', 'multiple');

INSERT INTO answers (question_id, answer, is_correct) VALUES
(1, 'Kódstílus szabvány', 1),
(1, 'Autoloading szabvány', 0),
(1, 'Adatbázis szabvány', 0),
(2, 'Igaz', 1),
(2, 'Hamis', 0),
(3, 'const', 1),
(3, 'let', 0),
(3, 'var', 0),
(3, 'define', 1);

INSERT INTO questions (category_id, question, type) VALUES
(3, 'Melyik parancs listázza a fájlokat?', 'single'),
(3, 'Igaz-e hogy a Linux case-sensitive?', 'truefalse');

INSERT INTO answers (question_id, answer, is_correct) VALUES
(4, 'ls', 1),
(4, 'dir', 0),
(4, 'list', 0),
(5, 'Igaz', 1),
(5, 'Hamis', 0);
