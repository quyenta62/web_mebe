-- Separate database for PHPUnit so tests never touch development data.
CREATE DATABASE IF NOT EXISTS crawler_mebe_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci;
GRANT ALL PRIVILEGES ON crawler_mebe_test.* TO 'crawler'@'%';
FLUSH PRIVILEGES;
