<?php

$host = "aws-1-eu-west-1.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$username = "postgres.xhfrlzamhwntvlvlhzpm";
$password = "Qu74pqPtLRf,Wa@";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}