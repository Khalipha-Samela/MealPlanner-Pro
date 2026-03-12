<?php

$host = getenv("postgres.railway.internal");
$port = getenv("5432");
$dbname = getenv("PGDATABASE");
$username = getenv("postgres");
$password = getenv("ACuFZzZcArgzMTLzEeSQeQBMSxAntduD");

try {

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

} catch(PDOException $e) {

    die("Connection failed: " . $e->getMessage());

}

?>