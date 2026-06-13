<?php
session_start();
require_once "conexion.php";

if(!isset($_SESSION['id_gestor'])){
    header("Location: index.html");
    exit();
}

// ==========================================
// DECLARACIÓN DE VARIABLES (ESTRICTAMENTE ARRIBA)
// ==========================================
$idGestor = $_SESSION['id_gestor'];
$idEscuelaGestor = null;
$mensaje = "";

$storage_account = 'cuentalol'; 
$container = 'contenedorlol';    
$clave = 'clavelol';            

$materias = [];
$optsMateria = "";
$listaAlumnosRecientes = [];
$listaProfesoresRecientes = [];
$listaAlumnosBuscados = [];
$listaProfesoresBuscados = [];
$terminoBusquedaAlumno = "";
$terminoBusquedaProfesor = "";

$htmlFormAlumno = "";
$htmlFormProfesor = "";
$htmlModificaciones = "";

// ==========================================
// 0. VERIFICACIÓN DE IDENTIDAD Y ESCUELA
// ==========================================
$stmt = $pdo->prepare("SELECT id_escuela FROM Persona WHERE id_persona = ?");
$stmt->execute([$idGestor]);
$idEscuelaGestor = $stmt->fetchColumn();

if(!$idEscuelaGestor) {
    die("Error crítico de seguridad: El gestor no está asociado a ninguna escuela válida.");
}

// ==========================================
// 1. LÓGICA DE BLOB STORAGE (AZURE)
// ==========================================
function subirImagenAzure($archivoTmp, $nombreArchivo, $storage_account, $container, $clave){
    $blob_url_base = "https://$storage_account.blob.core.windows.net/$container";
    $nombre = uniqid() . "_" . basename($nombreArchivo);
    $url = "$blob_url_base/$nombre";
    $contenido = file_get_contents($archivoTmp);

    $date = gmdate("D, d M Y H:i:s T");
    $length = strlen($contenido);

    $headers = [
        "x-ms-blob-type: BlockBlob",
        "x-ms-date: $date",
        "x-ms-version: 2020-10-02",
        "Content-Length: $length"
    ];

    $resource = "/$storage_account/$container/$nombre";
    $stringToSign = "PUT\n\n\n$length\n\nimage/jpeg\n\n\n\n\n\n\n" .
        "x-ms-blob-type:BlockBlob\n" .
        "x-ms-date:$date\n" .
        "x-ms-version:2020-10-02\n" .
        $resource;

    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($clave), true));
    $authorization = "SharedKey $storage_account:$signature";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
        "Authorization: $authorization",
        "Content-Type: image/jpeg"
    ]));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $contenido);
    
    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
  
    if($httpCode == 201) {
        return $url;
    } else {
        die("<div style='background:#fff; color:#000; padding:20px; z-index:9999; position:relative;'><h3>Error Azure: $httpCode</h3><p>$curlError</p></div>");
    }
}

// ==========================================
// 2. PROCESAMIENTO DE FORMULARIOS (POST)
// ==========================================

/* --- ALTA ALUMNO --- */
if(isset($_POST['alta_alumno'])){
    try {
        $pdo->beginTransaction();
        $fotoURL = !empty($_FILES['foto']['tmp_name']) ? subirImagenAzure($_FILES['foto']['tmp_name'], $_FILES['foto']['name'], $storage_account, $container, $clave) : "";

        // Insertar forzando la escuela del gestor
        $stmt = $pdo->prepare("INSERT INTO Persona (nombre_completo, contrasena, foto_perfil, id_escuela) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['nombre'], $_POST['contrasena'], $fotoURL, $idEscuelaGestor]);
        $idPersona = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO Alumno (id_persona, boleta, edad) VALUES (?,?,?)");
        $stmt->execute([$idPersona, $_POST['boleta'], $_POST['edad']]);

        $stmt = $pdo->prepare("INSERT INTO Tiene_Inscrita (id_alumno, id_materia, grado_semestre) VALUES (?,?,?)");
        $stmt->execute([$idPersona, $_POST['id_materia'], $_POST['grado_semestre']]);

        $stmt = $pdo->prepare("INSERT INTO Gestor_Auditoria (id_gestor, entidad, id_entidad, accion) VALUES (?,?,?,?)");
        $stmt->execute([$idGestor, 'ALUMNO', $idPersona, 'ALTA']);

        $pdo->commit();
        $mensaje = "Alumno registrado en tu escuela.";
    } catch(Throwable $e) {
        $pdo->rollBack();
        $mensaje = "Error al registrar alumno: " . $e->getMessage();
    }
}

/* --- BAJA ALUMNO --- */
if(isset($_POST['baja_alumno'])){
    // Validar que el alumno pertenece a la misma escuela
    $stmt = $pdo->prepare("SELECT id_escuela FROM Persona WHERE id_persona=?");
    $stmt->execute([$_POST['id_alumno']]);
    if($stmt->fetchColumn() == $idEscuelaGestor){
        $stmt = $pdo->prepare("DELETE FROM Persona WHERE id_persona=?");
        $stmt->execute([$_POST['id_alumno']]);
        $mensaje = "Alumno eliminado.";
    } else {
        $mensaje = "Acceso denegado: Alumno no pertenece a esta escuela.";
    }
}

/* --- ALTA PROFESOR --- */
if(isset($_POST['alta_profesor'])){
    try {
        $pdo->beginTransaction();
        $fotoURL = !empty($_FILES['foto']['tmp_name']) ? subirImagenAzure($_FILES['foto']['tmp_name'], $_FILES['foto']['name'], $storage_account, $container, $clave) : "";

        $stmt = $pdo->prepare("INSERT INTO Persona (nombre_completo, contrasena, foto_perfil, id_escuela) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['nombre'], $_POST['contrasena'], $fotoURL, $idEscuelaGestor]);
        $idPersona = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO Profesor (id_persona, numero_empleado, tipo) VALUES (?,?,?)");
        $stmt->execute([$idPersona, $_POST['numero_empleado'], $_POST['tipo']]);

        $stmt = $pdo->prepare("INSERT INTO Profesor_Imparte_Materia (id_profesor, id_materia) VALUES (?,?)");
        $stmt->execute([$idPersona, $_POST['id_materia']]);

        $pdo->commit();
        $mensaje = "Profesor registrado en tu escuela.";
    } catch(Throwable $e) {
        $pdo->rollBack();
        $mensaje = "Error al registrar profesor: " . $e->getMessage();
    }
}

/* --- BAJA PROFESOR --- */
if(isset($_POST['baja_profesor'])){
    $stmt = $pdo->prepare("SELECT id_escuela FROM Persona WHERE id_persona=?");
    $stmt->execute([$_POST['id_profesor']]);
    if($stmt->fetchColumn() == $idEscuelaGestor){
        $stmt = $pdo->prepare("DELETE FROM Persona WHERE id_persona=?");
        $stmt->execute([$_POST['id_profesor']]);
        $mensaje = "Profesor eliminado.";
    }
}

/* --- ACTUALIZAR ALUMNO --- */
if(isset($_POST['actualizar_alumno'])){
    $idAlumno = $_POST['id_alumno'];
    
    $stmtVal = $pdo->prepare("SELECT id_escuela FROM Persona WHERE id_persona=?");
    $stmtVal->execute([$idAlumno]);
    
    if($stmtVal->fetchColumn() == $idEscuelaGestor){
        $pdo->beginTransaction();
        try {
            $updateFotoStr = "";
            $paramsPersona = [$_POST['nombre']];
            if(!empty($_FILES['foto_nueva']['tmp_name'])){
                $nuevaFotoURL = subirImagenAzure($_FILES['foto_nueva']['tmp_name'], $_FILES['foto_nueva']['name'], $storage_account, $container, $clave);
                $updateFotoStr = ", foto_perfil=?";
                $paramsPersona[] = $nuevaFotoURL;
            }
            $paramsPersona[] = $idAlumno;

            $stmt = $pdo->prepare("UPDATE Persona SET nombre_completo=? $updateFotoStr WHERE id_persona=?");
            $stmt->execute($paramsPersona);
            
            $stmt = $pdo->prepare("UPDATE Alumno SET boleta=?, edad=? WHERE id_persona=?");
            $stmt->execute([$_POST['boleta'], $_POST['edad'], $idAlumno]);

            // Actualizar Materia y Semestre
            if($_POST['id_materia_nueva'] != $_POST['id_materia_original']){
                // Intentar actualizar la llave foránea (precaución con dependencias)
                $stmt = $pdo->prepare("UPDATE Tiene_Inscrita SET id_materia=?, grado_semestre=? WHERE id_alumno=? AND id_materia=?");
                $stmt->execute([$_POST['id_materia_nueva'], $_POST['grado_semestre'], $idAlumno, $_POST['id_materia_original']]);
            } else {
                $stmt = $pdo->prepare("UPDATE Tiene_Inscrita SET grado_semestre=? WHERE id_alumno=? AND id_materia=?");
                $stmt->execute([$_POST['grado_semestre'], $idAlumno, $_POST['id_materia_original']]);
            }
            $pdo->commit();
            $mensaje = "Alumno actualizado correctamente.";
        } catch(Throwable $e){
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage();
        }
    } else {
        $mensaje = "Infracción de seguridad detectada.";
    }
}

/* --- ACTUALIZAR PROFESOR --- */
if(isset($_POST['actualizar_profesor'])){
    $idProfesor = $_POST['id_profesor'];
    $stmtVal = $pdo->prepare("SELECT id_escuela FROM Persona WHERE id_persona=?");
    $stmtVal->execute([$idProfesor]);
    
    if($stmtVal->fetchColumn() == $idEscuelaGestor){
        $pdo->beginTransaction();
        try {
            $updateFotoStr = "";
            $paramsPersona = [$_POST['nombre']];
            if(!empty($_FILES['foto_nueva']['tmp_name'])){
                $nuevaFotoURL = subirImagenAzure($_FILES['foto_nueva']['tmp_name'], $_FILES['foto_nueva']['name'], $storage_account, $container, $clave);
                $updateFotoStr = ", foto_perfil=?";
                $paramsPersona[] = $nuevaFotoURL;
            }
            $paramsPersona[] = $idProfesor;

            $stmt = $pdo->prepare("UPDATE Persona SET nombre_completo=? $updateFotoStr WHERE id_persona=?");
            $stmt->execute($paramsPersona);
            
            $stmt = $pdo->prepare("UPDATE Profesor SET numero_empleado=?, tipo=? WHERE id_persona=?");
            $stmt->execute([$_POST['numero_empleado'], $_POST['tipo'], $idProfesor]);

            if($_POST['id_materia_nueva'] != $_POST['id_materia_original']){
                $stmt = $pdo->prepare("UPDATE Profesor_Imparte_Materia SET id_materia=? WHERE id_profesor=? AND id_materia=?");
                $stmt->execute([$_POST['id_materia_nueva'], $idProfesor, $_POST['id_materia_original']]);
            }

            $pdo->commit();
            $mensaje = "Profesor actualizado correctamente.";
        } catch(Throwable $e){
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}

// ==========================================
// 3. CONSULTAS RESTRINGIDAS POR ESCUELA (GET & BUSCADOR)
// ==========================================
$materias = $pdo->query("SELECT id_materia, nombre FROM Materia")->fetchAll(PDO::FETCH_ASSOC);
foreach($materias as $m) $optsMateria .= "<option value='{$m['id_materia']}'>{$m['nombre']}</option>";

// Carga ultra-ligera (Solo los 10 más recientes de la escuela) para las pantallas de inicio
$stmt = $pdo->prepare("
    SELECT a.id_persona, a.boleta, a.edad, p.nombre_completo, p.foto_perfil, 
           t.grado_semestre, t.id_materia, m.nombre as materia_nombre
    FROM Alumno a
    INNER JOIN Persona p ON p.id_persona=a.id_persona
    LEFT JOIN Tiene_Inscrita t ON t.id_alumno=a.id_persona
    LEFT JOIN Materia m ON m.id_materia = t.id_materia
    WHERE p.id_escuela = ? ORDER BY p.id_persona DESC LIMIT 10
");
$stmt->execute([$idEscuelaGestor]);
$listaAlumnosRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT pr.id_persona, pr.numero_empleado, pr.tipo, p.nombre_completo, p.foto_perfil
    FROM Profesor pr
    INNER JOIN Persona p ON p.id_persona=pr.id_persona
    WHERE p.id_escuela = ? ORDER BY p.id_persona DESC LIMIT 10
");
$stmt->execute([$idEscuelaGestor]);
$listaProfesoresRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// BÚSQUEDA DE ALUMNOS
if(isset($_POST['buscar_alumno'])){
    $terminoBusquedaAlumno = $_POST['termino_boleta'];
    $stmt = $pdo->prepare("
        SELECT a.id_persona, a.boleta, a.edad, p.nombre_completo, p.foto_perfil, 
               t.grado_semestre, t.id_materia, m.nombre as materia_nombre
        FROM Alumno a
        INNER JOIN Persona p ON p.id_persona=a.id_persona
        LEFT JOIN Tiene_Inscrita t ON t.id_alumno=a.id_persona
        LEFT JOIN Materia m ON m.id_materia = t.id_materia
        WHERE p.id_escuela = ? AND a.boleta = ?
    ");
    $stmt->execute([$idEscuelaGestor, $terminoBusquedaAlumno]);
    $listaAlumnosBuscados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// BÚSQUEDA DE PROFESORES
if(isset($_POST['buscar_profesor'])){
    $terminoBusquedaProfesor = $_POST['termino_profesor'];
    $likeQuery = "%" . $terminoBusquedaProfesor . "%";
    $stmt = $pdo->prepare("
        SELECT pr.id_persona, pr.numero_empleado, pr.tipo, p.nombre_completo, p.foto_perfil,
               pim.id_materia, m.nombre as materia_nombre
        FROM Profesor pr
        INNER JOIN Persona p ON p.id_persona=pr.id_persona
        LEFT JOIN Profesor_Imparte_Materia pim ON pim.id_profesor=pr.id_persona
        LEFT JOIN Materia m ON m.id_materia = pim.id_materia
        WHERE p.id_escuela = ? AND (pr.numero_empleado = ? OR p.nombre_completo LIKE ?)
    ");
    $stmt->execute([$idEscuelaGestor, $terminoBusquedaProfesor, $likeQuery]);
    $listaProfesoresBuscados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==========================================
// 4. GENERACIÓN DE FORMULARIOS HTML
// ==========================================
// NOTA: Eliminado el select de escuelas. Se infiere del Gestor.
$htmlFormAlumno = "
<div class='form-container'>
    <h3 style='margin-bottom:15px; color:var(--wine-pale); font-family:\'Cormorant Garamond\', serif; font-size:20px;'>Registrar Nuevo Alumno (Asignado a tu institución)</h3>
    <form method='POST' enctype='multipart/form-data'>
        <div class='form-grid'>
            <div class='form-group'><label>Nombre Completo</label><input type='text' name='nombre' required></div>
            <div class='form-group'><label>Contraseña</label><input type='password' name='contrasena' required></div>
            <div class='form-group'><label>Boleta</label><input type='text' name='boleta' required></div>
            <div class='form-group'><label>Edad</label><input type='number' name='edad' required></div>
            <div class='form-group'><label>Materia Inicial</label><select name='id_materia' required>$optsMateria</select></div>
            <div class='form-group'><label>Grado/Semestre</label><input type='number' name='grado_semestre' required></div>
            <div class='form-group'><label>Foto (Azure Blob)</label><input type='file' name='foto' accept='image/*' style='padding:6px'></div>
        </div>
        <button type='submit' name='alta_alumno' class='btn-submit'>Registrar Alumno</button>
    </form>
</div>";

$htmlFormProfesor = "
<div class='form-container'>
    <h3 style='margin-bottom:15px; color:var(--wine-pale); font-family:\'Cormorant Garamond\', serif; font-size:20px;'>Registrar Nuevo Profesor (Asignado a tu institución)</h3>
    <form method='POST' enctype='multipart/form-data'>
        <div class='form-grid'>
            <div class='form-group'><label>Nombre Completo</label><input type='text' name='nombre' required></div>
            <div class='form-group'><label>Contraseña</label><input type='password' name='contrasena' required></div>
            <div class='form-group'><label>Núm. Empleado</label><input type='text' name='numero_empleado' required></div>
            <div class='form-group'><label>Tipo (Ej. Base)</label><input type='text' name='tipo' required></div>
            <div class='form-group'><label>Materia que Imparte</label><select name='id_materia' required>$optsMateria</select></div>
            <div class='form-group'><label>Foto (Azure Blob)</label><input type='file' name='foto' accept='image/*' style='padding:6px'></div>
        </div>
        <button type='submit' name='alta_profesor' class='btn-submit'>Registrar Profesor</button>
    </form>
</div>";

// CONSTRUCCIÓN DE LA VISTA DE MODIFICACIONES (CON BÚSQUEDA)
$htmlModificaciones = "
<div style='display:flex; gap:20px; margin-bottom:20px;'>
    <div class='form-container' style='flex:1; margin-bottom:0;'>
        <h4 style='color:var(--wine-pale);'>Buscar Alumno para Modificar</h4>
        <form method='POST' style='display:flex; gap:10px; margin-top:10px;'>
            <input type='text' name='termino_boleta' placeholder='Ingresa la boleta exacta' class='input-grade' style='flex:1;' value='".htmlspecialchars($terminoBusquedaAlumno)."'>
            <button type='submit' name='buscar_alumno' class='btn-save'>Buscar</button>
        </form>
    </div>
    <div class='form-container' style='flex:1; margin-bottom:0;'>
        <h4 style='color:var(--wine-pale);'>Buscar Profesor para Modificar</h4>
        <form method='POST' style='display:flex; gap:10px; margin-top:10px;'>
            <input type='text' name='termino_profesor' placeholder='Num. Empleado o Nombre' class='input-grade' style='flex:1;' value='".htmlspecialchars($terminoBusquedaProfesor)."'>
            <button type='submit' name='buscar_profesor' class='btn-save'>Buscar</button>
        </form>
    </div>
</div>";

// Renderizar tabla de Alumnos Buscados
if(!empty($listaAlumnosBuscados)){
    $htmlModificaciones .= "<h3 style='color:var(--wine-pale); margin-bottom:15px; margin-top:30px;'>Resultados Alumnos</h3>
    <div class='table-container' style='margin-bottom:30px; overflow:visible;'>
    <table class='data-table'><thead><tr><th>Nueva Foto</th><th>Boleta</th><th>Nombre</th><th>Edad</th><th>Materia</th><th>Semestre</th><th>Acción</th></tr></thead><tbody>";
    foreach($listaAlumnosBuscados as $a){
        $optsMat = str_replace("value='{$a['id_materia']}'", "value='{$a['id_materia']}' selected", $optsMateria);
        $htmlModificaciones .= "<tr><form method='POST' enctype='multipart/form-data'>
            <input type='hidden' name='id_alumno' value='{$a['id_persona']}'>
            <input type='hidden' name='id_materia_original' value='{$a['id_materia']}'>
            <td><input type='file' name='foto_nueva' accept='image/*' style='width:100px; font-size:9px;'></td>
            <td><input type='text' name='boleta' class='input-grade' value='{$a['boleta']}' style='width:80px;'></td>
            <td><input type='text' name='nombre' class='input-grade' value='{$a['nombre_completo']}' style='width:130px; text-align:left;'></td>
            <td><input type='number' name='edad' class='input-grade' value='{$a['edad']}' style='width:50px;'></td>
            <td><select name='id_materia_nueva' class='input-grade' style='width:100px;'>$optsMat</select></td>
            <td><input type='number' name='grado_semestre' class='input-grade' value='{$a['grado_semestre']}' style='width:50px;'></td>
            <td><button type='submit' name='actualizar_alumno' class='btn-save'>Guardar</button></td>
        </form></tr>";
    }
    $htmlModificaciones .= "</tbody></table></div>";
} else if(isset($_POST['buscar_alumno'])) {
    $htmlModificaciones .= "<p style='color:var(--text-muted); margin-bottom:30px;'>No se encontraron alumnos con esa boleta en tu institución.</p>";
}

// Renderizar tabla de Profesores Buscados
if(!empty($listaProfesoresBuscados)){
    $htmlModificaciones .= "<h3 style='color:var(--wine-pale); margin-bottom:15px;'>Resultados Profesores</h3>
    <div class='table-container' style='overflow:visible;'>
    <table class='data-table'><thead><tr><th>Nueva Foto</th><th>No. Emp</th><th>Nombre</th><th>Tipo</th><th>Materia</th><th>Acción</th></tr></thead><tbody>";
    foreach($listaProfesoresBuscados as $p){
        $optsMat = str_replace("value='{$p['id_materia']}'", "value='{$p['id_materia']}' selected", $optsMateria);
        $htmlModificaciones .= "<tr><form method='POST' enctype='multipart/form-data'>
            <input type='hidden' name='id_profesor' value='{$p['id_persona']}'>
            <input type='hidden' name='id_materia_original' value='{$p['id_materia']}'>
            <td><input type='file' name='foto_nueva' accept='image/*' style='width:100px; font-size:9px;'></td>
            <td><input type='text' name='numero_empleado' class='input-grade' value='{$p['numero_empleado']}' style='width:80px;'></td>
            <td><input type='text' name='nombre' class='input-grade' value='{$p['nombre_completo']}' style='width:130px; text-align:left;'></td>
            <td><input type='text' name='tipo' class='input-grade' value='{$p['tipo']}' style='width:80px;'></td>
            <td><select name='id_materia_nueva' class='input-grade' style='width:100px;'>$optsMat</select></td>
            <td><button type='submit' name='actualizar_profesor' class='btn-save'>Guardar</button></td>
        </form></tr>";
    }
    $htmlModificaciones .= "</tbody></table></div>";
} else if(isset($_POST['buscar_profesor'])) {
    $htmlModificaciones .= "<p style='color:var(--text-muted);'>No se encontraron profesores con esos datos en tu institución.</p>";
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Gestor — ESCOM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    /* ... [AQUÍ VA EXACTAMENTE EL MISMO CSS DE TU ARCHIVO ORIGINAL] ... */
    :root {
      --wine-dark:   #62152d;   
      --wine-mid:    #952f57;   
      --wine-light:  #ca668b;   
      --black-deep:  #040404;   
      --black-soft:  #0f0f0f;   
      --surface-0:   #080306;
      --surface-1:   #120710;
      --surface-2:   #1c0d18;
      --surface-3:   #2a1422;
      --text-primary:   #f2e8ec;
      --text-secondary: #c9a0b2;
      --text-muted:     #7a5060;
      --wine-pale:  #e8c0cf;
      --ease: cubic-bezier(0.4, 0, 0.2, 1);
      --fast:   200ms;
      --normal: 340ms;
      --slow:   500ms;
      --border-faint:  rgba(202, 102, 139, 0.12);
      --border-soft:   rgba(202, 102, 139, 0.25);
      --border-strong: rgba(202, 102, 139, 0.45);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'DM Sans', sans-serif; background: var(--black-deep); color: var(--text-primary); height: 100vh; overflow: hidden; -webkit-font-smoothing: antialiased; }
    button { font-family: inherit; cursor: pointer; border: none; outline: none; }
    a { text-decoration: none; color: inherit; }
    ::-webkit-scrollbar { width: 3px; height: 3px; }
    ::-webkit-scrollbar-track { background: var(--surface-1); }
    ::-webkit-scrollbar-thumb { background: var(--wine-dark); border-radius: 3px; }
    .ticker { position: fixed; top: 0; left: 0; right: 0; z-index: 200; height: 36px; background: var(--wine-mid); display: flex; align-items: center; overflow: hidden; border-bottom: 1px solid var(--wine-dark); }
    .ticker-track { display: flex; white-space: nowrap; animation: scroll-ticker 30s linear infinite; }
    .ticker:hover .ticker-track { animation-play-state: paused; }
    .ticker-item { display: inline-flex; align-items: center; gap: 10px; padding: 0 28px; font-size: 10px; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: var(--wine-pale); }
    .ticker-sep { width: 3px; height: 3px; border-radius: 50%; background: var(--wine-light); flex-shrink: 0; }
    @keyframes scroll-ticker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
    .toast-message { position: fixed; top: 50px; right: 20px; background: var(--wine-mid); color: var(--text-primary); padding: 14px 24px; border-radius: 12px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,0.5); font-weight: 500; letter-spacing: 0.02em; animation: fadeOutToast 4s forwards; }
    @keyframes fadeOutToast { 0% { opacity: 0; transform: translateY(-10px); } 10% { opacity: 1; transform: translateY(0); } 80% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-10px); pointer-events: none; } }
    .app { display: flex; height: 100vh; padding-top: 36px; }
    .sidebar { width: 268px; min-width: 268px; background: var(--surface-1); border-right: 1px solid var(--border-faint); display: flex; flex-direction: column; overflow: hidden; }
    .sidebar-logo { padding: 22px 22px 18px; border-bottom: 1px solid var(--border-faint); flex-shrink: 0; }
    .logo-row { display: flex; align-items: center; gap: 11px; }
    .logo-mark { width: 38px; height: 38px; background: linear-gradient(135deg, var(--wine-dark), var(--wine-mid)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 700; color: var(--wine-pale); flex-shrink: 0; box-shadow: 0 4px 14px rgba(98, 21, 45, 0.45); }
    .logo-text-wrap { flex: 1; }
    .logo-name { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; color: var(--text-primary); letter-spacing: -0.03em; line-height: 1; }
    .logo-tagline { font-size: 9.5px; font-weight: 300; letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-muted); margin-top: 3px; }
    .sidebar-nav { flex: 1; overflow-y: auto; padding: 14px 0 8px; }
    .nav-wrap { display: flex; flex-direction: column; gap: 6px; padding: 0 12px; }
    .nav-btn { position: relative; background: none; padding: 0; text-align: left; width: 100%; border-radius: 12px; transition: transform var(--fast) var(--ease); }
    .nav-btn:active { transform: scale(0.98); }
    .nav-btn-inner { display: flex; align-items: center; gap: 13px; padding: 14px 16px; border-radius: 12px; border: 1px solid transparent; background: var(--surface-2); transition: background var(--normal) var(--ease), border-color var(--normal) var(--ease), box-shadow var(--normal) var(--ease); }
    .nav-btn:hover:not(.active) .nav-btn-inner { background: var(--surface-3); border-color: var(--border-faint); }
    .nav-btn.active[data-color="1"] .nav-btn-inner { background: var(--wine-dark); border-color: rgba(202,102,139,0.22); box-shadow: 0 4px 18px rgba(98,21,45,0.35); }
    .nav-btn.active[data-color="2"] .nav-btn-inner { background: var(--wine-mid); border-color: rgba(242,232,236,0.18); box-shadow: 0 4px 18px rgba(149,47,87,0.4); }
    .nav-btn.active[data-color="3"] .nav-btn-inner { background: var(--wine-light); border-color: rgba(242,232,236,0.25); box-shadow: 0 4px 18px rgba(202,102,139,0.4); }
    .nav-btn.active[data-color="3"] .nav-num, .nav-btn.active[data-color="3"] .nav-title, .nav-btn.active[data-color="3"] .nav-arrow { color: #1a0610 !important; }
    .nav-num { font-size: 9px; font-weight: 500; letter-spacing: 0.1em; color: var(--text-muted); flex-shrink: 0; width: 16px; transition: color var(--normal) var(--ease); }
    .nav-btn.active .nav-num { color: var(--wine-pale); }
    .nav-label { flex: 1; font-size: 12.5px; font-weight: 400; color: var(--text-secondary); letter-spacing: 0.01em; transition: color var(--normal) var(--ease); }
    .nav-btn:hover .nav-label, .nav-btn.active .nav-label { color: var(--text-primary); }
    .nav-arrow { font-size: 13px; color: var(--text-muted); transition: color var(--normal) var(--ease), transform var(--normal) var(--ease); flex-shrink: 0; }
    .nav-btn:hover .nav-arrow, .nav-btn.active .nav-arrow { color: var(--wine-pale); transform: translate(2px, -2px); }
    .sidebar-cta { padding: 10px 12px; flex-shrink: 0; }
    .btn-cta { display: block; width: 100%; padding: 13px; background: linear-gradient(135deg, var(--wine-mid), var(--wine-dark)); border-radius: 12px; color: var(--wine-pale); font-size: 12px; font-weight: 500; letter-spacing: 0.06em; text-align: center; transition: background var(--normal) var(--ease), color var(--normal) var(--ease), box-shadow var(--normal) var(--ease), transform var(--fast) var(--ease); box-shadow: 0 4px 16px rgba(98,21,45,0.3); }
    .btn-cta:hover { background: var(--wine-light); color: var(--black-deep); box-shadow: 0 6px 24px rgba(202,102,139,0.35); }
    .btn-cta:active { transform: scale(0.98); }
    .content-area { flex: 1; display: flex; padding: 18px 18px 18px 0; overflow: hidden; }
    .content-panel { flex: 1; background: var(--surface-1); border-radius: 20px; border: 1px solid var(--border-faint); overflow: hidden; position: relative; }
    .panel-inner { display: flex; height: 100%; width: 100%; position: absolute; inset: 0; opacity: 0; pointer-events: none; }
    .panel-inner.state-enter { opacity: 0; transform: translateY(16px); pointer-events: none; }
    .panel-inner.state-active { opacity: 1; transform: translateY(0); pointer-events: auto; transition: opacity var(--slow) var(--ease), transform var(--slow) var(--ease); }
    .panel-inner.state-exit { opacity: 0; transform: translateY(-12px); pointer-events: none; transition: opacity var(--normal) var(--ease), transform var(--normal) var(--ease); }
    .panel-text { flex: 1; min-width: 0; padding: 44px; display: flex; flex-direction: column; overflow-y: auto; width: 100%; }
    .panel-tag { display: inline-flex; align-items: center; gap: 8px; background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 100px; padding: 5px 14px; font-size: 9.5px; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: var(--wine-light); align-self: flex-start; margin-bottom: 22px; }
    .tag-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--wine-light); flex-shrink: 0; }
    .panel-title { font-family: 'Cormorant Garamond', serif; font-size: clamp(34px, 3.6vw, 58px); font-weight: 600; line-height: 1.04; letter-spacing: -0.025em; color: var(--text-primary); margin-bottom: 24px; }
    .panel-title em { font-style: italic; color: var(--wine-light); }
    .sql-query-label { font-family: monospace; background: var(--surface-2); color: var(--wine-pale); padding: 10px 14px; border-radius: 8px; font-size: 12px; margin-bottom: 24px; display: inline-block; border: 1px solid var(--border-soft); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); }
    .table-container { width: 100%; overflow-x: auto; border: 1px solid var(--border-soft); border-radius: 12px; background: var(--surface-0); }
    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
    .data-table th, .data-table td { padding: 16px; border-bottom: 1px solid var(--border-faint); }
    .data-table th { background: var(--surface-2); color: var(--wine-light); font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 11px; white-space: nowrap; }
    .data-table td { color: var(--text-secondary); font-weight: 300; vertical-align: middle; }
    .data-table tr:hover td { background: var(--surface-3); color: var(--text-primary); }
    .input-grade { background: var(--surface-1); border: 1px solid var(--border-strong); color: var(--text-primary); padding: 6px; border-radius: 6px; font-family: inherit; text-align: center; transition: 0.2s; }
    .input-grade:focus { outline: none; border-color: var(--wine-light); box-shadow: 0 0 0 2px rgba(202, 102, 139, 0.2); }
    .btn-save { background: var(--wine-mid); color: var(--text-primary); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 500; transition: 0.2s; box-shadow: 0 2px 8px rgba(98, 21, 45, 0.4); border:none;}
    .btn-save:hover { background: var(--wine-light); color: var(--black-deep); }
    .form-container { background: var(--surface-2); padding: 24px; border-radius: 12px; margin-bottom: 30px; border: 1px solid var(--border-soft); box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.08em; font-weight:600;}
    .form-group input, .form-group select { background: var(--surface-1); border: 1px solid var(--border-strong); color: var(--text-primary); padding: 10px 14px; border-radius: 8px; font-family: inherit; font-size:13px; transition:0.2s;}
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--wine-light); box-shadow: 0 0 0 2px rgba(202, 102, 139, 0.2); }
    .btn-submit { background: linear-gradient(135deg, var(--wine-mid), var(--wine-dark)); color: var(--wine-pale); padding: 12px 24px; border-radius: 8px; font-weight: 500; letter-spacing: 0.05em; cursor: pointer; transition: 0.2s; border:none; box-shadow: 0 4px 12px rgba(98, 21, 45, 0.4); width:100%;}
    .btn-submit:hover { background: var(--wine-light); color: var(--black-deep); }
    .btn-danger { background: #62152d; border: 1px solid #952f57; color: var(--text-primary); padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight:600; cursor: pointer; transition:0.2s; }
    .btn-danger:hover { background: #a81c3e; }
  </style>
</head>

<body>
  <?php if($mensaje): ?>
    <div class="toast-message"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <div class="ticker" role="marquee">
    <div class="ticker-track">
      <span class="ticker-item"><span class="ticker-sep"></span>Operación Restringida al Entorno Institucional</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Alta de ciclo operativo (Local)</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Protección Anti-IDOR Activa</span>
    </div>
  </div>

  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-row">
          <div class="logo-mark">IPN</div>
          <div class="logo-text-wrap">
            <div class="logo-name">ESCOM</div>
            <div class="logo-tagline">Panel Gestor Local</div>
          </div>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Secciones">
        <div class="nav-wrap" id="navWrap">
          <button class="nav-btn active" data-section="0" data-color="1"><div class="nav-btn-inner"><span class="nav-num">01</span><span class="nav-label">Alumnos</span><span class="nav-arrow">↗</span></div></button>
          <button class="nav-btn" data-section="1" data-color="2"><div class="nav-btn-inner"><span class="nav-num">02</span><span class="nav-label">Profesores</span><span class="nav-arrow">↗</span></div></button>
          <button class="nav-btn" data-section="2" data-color="3"><div class="nav-btn-inner"><span class="nav-num">03</span><span class="nav-label">Búsqueda / Modificación</span><span class="nav-arrow">↗</span></div></button>
        </div>
      </nav>
      <div class="sidebar-cta"><a href="logout.php" class="btn-cta">Cerrar sesión</a></div>
    </aside>

    <main class="content-area">
      <div class="content-panel" id="contentPanel" aria-live="polite"></div>
    </main>
  </div>

  <script>
    const SECTIONS = [
      {
          tag: 'Alumnos (Vista Local)', title: 'Gestión de <em>alumnos.</em>', sqlQuery: 'INSERT / DELETE Alumno WHERE id_escuela = [Sesión]',
          type: 'form_table', formHtml: <?= json_encode($htmlFormAlumno) ?>,
          columns: ['Foto', 'Boleta', 'Nombre', 'Semestre', 'Materia', 'Acciones'],
          data: <?= json_encode(array_map(fn($a)=>[
              '<img src="'.($a["foto_perfil"]?:'https://via.placeholder.com/60').'" width="50" style="border-radius:6px; height:50px; object-fit:cover;">',
              htmlspecialchars($a["boleta"]), htmlspecialchars($a["nombre_completo"]), htmlspecialchars($a["grado_semestre"]), htmlspecialchars($a["materia_nombre"]),
              "<form method='POST'><input type='hidden' name='id_alumno' value='{$a["id_persona"]}'><button type='submit' name='baja_alumno' class='btn-danger'>Eliminar</button></form>"
          ], $listaAlumnosRecientes)) ?>
      },
      {
          tag: 'Profesores (Vista Local)', title: 'Gestión de <em>profesores.</em>', sqlQuery: 'INSERT / DELETE Profesor WHERE id_escuela = [Sesión]',
          type: 'form_table', formHtml: <?= json_encode($htmlFormProfesor) ?>,
          columns: ['Foto', 'Empleado', 'Nombre', 'Tipo', 'Acciones'],
          data: <?= json_encode(array_map(fn($p)=>[
              '<img src="'.($p["foto_perfil"]?:'https://via.placeholder.com/60').'" width="50" style="border-radius:6px; height:50px; object-fit:cover;">',
              htmlspecialchars($p["numero_empleado"]), htmlspecialchars($p["nombre_completo"]), htmlspecialchars($p["tipo"]),
              "<form method='POST'><input type='hidden' name='id_profesor' value='{$p["id_persona"]}'><button type='submit' name='baja_profesor' class='btn-danger'>Eliminar</button></form>"
          ], $listaProfesoresRecientes)) ?>
      },
      {
          tag: 'Motor de Búsqueda', title: 'Edición <em>profunda.</em>', sqlQuery: 'SELECT / UPDATE WHERE id_escuela = [Sesión] AND [Filtro]',
          type: 'custom', customHtml: <?= json_encode($htmlModificaciones) ?>
      }
    ];

    function renderSection(idx) {
      const s = SECTIONS[idx];
      let contentHtml = '';
      const sqlBadge = `<div class="sql-query-label">> ${s.sqlQuery}</div>`;

      if (s.type === 'form_table') {
        const thead = s.columns.map(c => `<th>${c}</th>`).join('');
        const tbody = s.data.length > 0 
            ? s.data.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('') 
            : '<tr><td colspan="6" style="text-align:center;">No hay registros recientes.</td></tr>';
        
        contentHtml = `${s.formHtml} ${sqlBadge} <h4 style='color:var(--wine-pale); margin-bottom:15px;'>10 Registros más recientes</h4> <div class="table-container"><table class="data-table"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table></div>`;
      } else if (s.type === 'custom') {
        contentHtml = `${sqlBadge} ${s.customHtml}`;
      }

      return `
        <div class="panel-text">
          <div class="panel-tag"><span class="tag-dot"></span>${s.tag}</div>
          <h1 class="panel-title">${s.title}</h1>
          ${contentHtml}
        </div>
      `;
    }

    // Inicializar siempre en la pestaña 2 si hubo una búsqueda POST para no perder el contexto UI
    let currentIdx = <?= (isset($_POST['buscar_alumno']) || isset($_POST['buscar_profesor']) || isset($_POST['actualizar_alumno']) || isset($_POST['actualizar_profesor'])) ? 2 : 0 ?>; 
    let isAnimating = false;
    
    function goToSection(idx) {
      if (idx === currentIdx && document.querySelector('.state-active')) return;
      isAnimating = true;
      document.querySelectorAll('.nav-btn').forEach(b => { b.classList.remove('active'); b.removeAttribute('aria-current'); });
      const activeBtn = document.querySelector(`.nav-btn[data-section="${idx}"]`);
      if (activeBtn) { activeBtn.classList.add('active'); activeBtn.setAttribute('aria-current', 'true'); }

      const panel = document.getElementById('contentPanel');
      const inner = panel.querySelector('.panel-inner');
      if (!inner) { currentIdx = idx; mountSection(panel, idx); isAnimating = false; return; }

      inner.classList.remove('state-active');
      inner.classList.add('state-exit');
      setTimeout(() => { currentIdx = idx; mountSection(panel, idx); isAnimating = false; }, 280);
    }

    function mountSection(panel, idx) {
      const div = document.createElement('div');
      div.className = 'panel-inner state-enter';
      div.innerHTML = renderSection(idx);
      panel.innerHTML = ''; panel.appendChild(div);
      requestAnimationFrame(() => requestAnimationFrame(() => { div.classList.remove('state-enter'); div.classList.add('state-active'); }));
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('navWrap').addEventListener('click', e => {
        const btn = e.target.closest('.nav-btn');
        if (!btn) return;
        goToSection(parseInt(btn.dataset.section, 10));
      });
      // Forza el renderizado inicial respetando si venimos de un POST de búsqueda
      document.querySelectorAll('.nav-btn').forEach(b => { b.classList.remove('active'); });
      document.querySelector(`.nav-btn[data-section="${currentIdx}"]`).classList.add('active');
      mountSection(document.getElementById('contentPanel'), currentIdx);
    });
  </script>
</body>
</html>