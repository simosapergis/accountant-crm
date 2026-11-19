<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $result = $db->query(
        "SELECT COUNT(*) AS non_utf8_columns
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND CHARACTER_SET_NAME IS NOT NULL
           AND CHARACTER_SET_NAME <> 'utf8mb4'"
    );
    $row = $result->fetch_assoc();
    $result->free();

    if ((int)($row['non_utf8_columns'] ?? 0) === 0) {
        return;
    }

    $db->query('ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
};
