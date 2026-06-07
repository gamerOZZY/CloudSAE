<?php

session_start();
require_once "conexion.php";

$boleta = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$sql = "
SELECT
    a.id_persona,
    a.boleta,
    p.nombre_completo,
    p.contrasena
FROM Alumno a
INNER JOIN Persona p
    ON a.id_persona = p.id_persona
WHERE a.boleta = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$boleta]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$usuario){
    // Redirige con error de usuario no encontrado
    header("Location: index.html?error=user_not_found");
    exit();
}

if($password === $usuario['contrasena']){

    $_SESSION['id_persona'] = $usuario['id_persona'];
    $_SESSION['boleta'] = $usuario['boleta'];
    $_SESSION['nombre'] = $usuario['nombre_completo'];

    header("Location: principal_alumnos.php");
    exit();
}

// Redirige con error de contraseña incorrecta
header("Location: index.html?error=wrong_password");
exit();