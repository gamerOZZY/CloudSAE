<?php

session_start();
require_once "conexion.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'alumno';

/* ==========================
   LOGIN ALUMNO
========================== */

if($role === "alumno"){

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
    $stmt->execute([$username]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$usuario){
        header("Location: index.html?error=user_not_found");
        exit();
    }

    if($password === $usuario['contrasena']){

        $_SESSION['rol'] = 'alumno';
        $_SESSION['id_persona'] = $usuario['id_persona'];
        $_SESSION['boleta'] = $usuario['boleta'];
        $_SESSION['nombre'] = $usuario['nombre_completo'];

        header("Location: principal_alumnos.php");
        exit();
    }

    header("Location: index.html?error=wrong_password");
    exit();
}

/* ==========================
   LOGIN PROFESOR
========================== */

if($role === "profesor"){

    $sql = "
    SELECT
        pr.id_persona,
        pr.numero_empleado,
        p.nombre_completo,
        p.contrasena
    FROM Profesor pr
    INNER JOIN Persona p
        ON p.id_persona = pr.id_persona
    WHERE pr.numero_empleado = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$usuario){
        header("Location: index.html?error=user_not_found");
        exit();
    }

    if($password === $usuario['contrasena']){

        $_SESSION['rol'] = 'profesor';
        $_SESSION['id_profesor'] = $usuario['id_persona'];
        $_SESSION['nombre'] = $usuario['nombre_completo'];

        header("Location: dashboard_profesor.php");
        exit();
    }

    header("Location: index.html?error=wrong_password");
    exit();
}

/* ==========================
   LOGIN GESTOR
========================== */

if($role === "gestor"){

    $sql = "
    SELECT
        g.id_persona,
        g.numero_empleado,
        p.nombre_completo,
        p.contrasena
    FROM Gestor g
    INNER JOIN Persona p
        ON p.id_persona = g.id_persona
    WHERE g.numero_empleado = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$usuario){
        header("Location: index.html?error=user_not_found");
        exit();
    }

    if($password === $usuario['contrasena']){

        $_SESSION['rol'] = 'gestor';
        $_SESSION['id_gestor'] = $usuario['id_persona'];
        $_SESSION['nombre'] = $usuario['nombre_completo'];

        header("Location: dashboard_gestor.php");
        exit();
    }

    header("Location: index.html?error=wrong_password");
    exit();
}

/* ==========================
   LOGIN DIRECTIVO
========================== */

if($role === "directivo"){

    $sql = "
    SELECT
        d.id_persona,
        d.numero_empleado,
        p.nombre_completo,
        p.contrasena
    FROM Directivo d
    INNER JOIN Persona p
        ON p.id_persona = d.id_persona
    WHERE d.numero_empleado = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$usuario){
        header("Location: index.html?error=user_not_found");
        exit();
    }

    if($password === $usuario['contrasena']){

        $_SESSION['rol'] = 'directivo';
        $_SESSION['id_directivo'] = $usuario['id_persona'];
        $_SESSION['nombre'] = $usuario['nombre_completo'];

        header("Location: dashboard_directivo.php");
        exit();
    }

    header("Location: index.html?error=wrong_password");
    exit();
}