<?php

$host = "pruebabase.mysql.database.azure.com";
$dbname = "cloudsae";
$user = "gamerOZZY";
$password = "Password123#";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}