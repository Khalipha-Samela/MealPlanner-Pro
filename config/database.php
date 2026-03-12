<?php
$host = 'db.fr-pari1.bengt.wasmernet.com';
$port = '10272';
$dbname = 'dbesjniLdeDSAvmQDjWj6dza';
$username = '2d607d237b0180002a27dd4647d6';
$password = '069b2d60-7d23-7c13-8000-50046a8d23d2';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>