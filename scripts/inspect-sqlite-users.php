<?php

$path = $argv[1] ?? 'database/database.sqlite';
$db = new SQLite3($path);
$result = $db->query("select id, email, name, is_active from users order by email");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo implode(' | ', [
        $row['id'],
        $row['email'],
        $row['name'],
        'active='.(int) $row['is_active'],
    ]).PHP_EOL;
}
