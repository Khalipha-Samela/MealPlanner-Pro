<?php

$host = "db.xhfrlzamhwntvlvlhzpm.supabase.co";
$port = "5432";
$dbname = "postgres";
$username = "postgres";
$password = "Qu74pqPtLRf,Wa@";

try {

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    echo "Database connected";

} catch(PDOException $e) {

    die("Connection failed: " . $e->getMessage());

}

?>