<?php

session_start();

if(!isset($_SESSION['id_profesor'])){
    header("Location: index.html");
    exit();
}

require_once "conexion.php";

$idProfesor = $_SESSION['id_profesor'];

$mensaje = "";

/* ======================
   ACTUALIZAR
====================== */

if($_SERVER["REQUEST_METHOD"] === "POST"){

    $idAlumno = $_POST['id_alumno'];
    $idMateria = $_POST['id_materia'];
    $fecha = $_POST['fecha_inscripcion'];

    $p1 = $_POST['parcial_1'];
    $p2 = $_POST['parcial_2'];
    $p3 = $_POST['parcial_3'];

    $sql = "
    UPDATE Tiene_Inscrita
    SET
        parcial_1 = ?,
        parcial_2 = ?,
        parcial_3 = ?
    WHERE
        id_alumno = ?
        AND id_materia = ?
        AND fecha_inscripcion = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $p1,
        $p2,
        $p3,
        $idAlumno,
        $idMateria,
        $fecha
    ]);

    $mensaje = "Calificaciones actualizadas correctamente.";
}

/* ======================
   MATERIAS PROFESOR
====================== */

$sql = "
SELECT
    m.id_materia,
    m.nombre
FROM Profesor_Imparte_Materia pim
INNER JOIN Materia m
    ON m.id_materia = pim.id_materia
WHERE pim.id_profesor = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idProfesor]);

$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================
   ALUMNOS
====================== */

$sql = "
SELECT
    a.id_persona,
    a.boleta,
    p.nombre_completo,
    m.id_materia,
    m.nombre AS materia,
    ti.fecha_inscripcion,
    ti.parcial_1,
    ti.parcial_2,
    ti.parcial_3,
    ti.final
FROM Profesor_Imparte_Materia pim
INNER JOIN Materia m
    ON m.id_materia = pim.id_materia
INNER JOIN Tiene_Inscrita ti
    ON ti.id_materia = m.id_materia
INNER JOIN Alumno a
    ON a.id_persona = ti.id_alumno
INNER JOIN Persona p
    ON p.id_persona = a.id_persona
WHERE pim.id_profesor = ?
ORDER BY m.nombre
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idProfesor]);

$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparamos los datos de los alumnos para inyectarlos en el JavaScript 
// Se utilizan inputs de HTML enlazados a formularios ocultos en la última celda.
$datosAlumnosJS = [];
foreach($alumnos as $i => $a) {
    $formId = "form_calif_" . $i;

    $formHtml = "
        <form id='{$formId}' method='POST' style='margin:0;'>
            <input type='hidden' name='id_alumno' value='{$a['id_persona']}'>
            <input type='hidden' name='id_materia' value='{$a['id_materia']}'>
            <input type='hidden' name='fecha_inscripcion' value='{$a['fecha_inscripcion']}'>
            <button type='submit' class='btn-save'>Guardar</button>
        </form>
    ";

    $datosAlumnosJS[] = [
        $a['boleta'],
        htmlspecialchars($a['nombre_completo']),
        htmlspecialchars($a['materia']),
        "<input form='{$formId}' type='number' step='0.01' name='parcial_1' value='{$a['parcial_1']}' class='input-grade'>",
        "<input form='{$formId}' type='number' step='0.01' name='parcial_2' value='{$a['parcial_2']}' class='input-grade'>",
        "<input form='{$formId}' type='number' step='0.01' name='parcial_3' value='{$a['parcial_3']}' class='input-grade'>",
        $a['final'],
        $formHtml
    ];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Profesor — ESCOM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

  <style>
    /* =============================================
       CSS VARIABLES — PALETA
       ============================================= */
    :root {
      /* Colores principales */
      --wine-dark:   #62152d;   
      --wine-mid:    #952f57;   
      --wine-light:  #ca668b;   
      --black-deep:  #040404;   
      --black-soft:  #0f0f0f;   

      /* Superficies derivadas */
      --surface-0:   #080306;
      --surface-1:   #120710;
      --surface-2:   #1c0d18;
      --surface-3:   #2a1422;

      /* Texto */
      --text-primary:   #f2e8ec;
      --text-secondary: #c9a0b2;
      --text-muted:     #7a5060;

      /* Acentos adicionales */
      --wine-pale:  #e8c0cf;
      --wine-glow:  rgba(202, 102, 139, 0.18);

      /* Bordes */
      --border-faint:  rgba(202, 102, 139, 0.12);
      --border-soft:   rgba(202, 102, 139, 0.25);
      --border-strong: rgba(202, 102, 139, 0.45);

      /* Transiciones */
      --ease: cubic-bezier(0.4, 0, 0.2, 1);
      --fast:   200ms;
      --normal: 340ms;
      --slow:   500ms;
    }

    /* =============================================
       RESET & BASE
       ============================================= */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--black-deep);
      color: var(--text-primary);
      height: 100vh;
      overflow: hidden;
      -webkit-font-smoothing: antialiased;
    }

    button { font-family: inherit; cursor: pointer; border: none; outline: none; }
    a { text-decoration: none; color: inherit; }

    /* =============================================
       SCROLLBAR
       ============================================= */
    ::-webkit-scrollbar { width: 3px; height: 3px; }
    ::-webkit-scrollbar-track { background: var(--surface-1); }
    ::-webkit-scrollbar-thumb { background: var(--wine-dark); border-radius: 3px; }

    /* =============================================
       TICKER / MARQUEE
       ============================================= */
    .ticker {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 200;
      height: 36px;
      background: var(--wine-mid);
      display: flex;
      align-items: center;
      overflow: hidden;
      border-bottom: 1px solid var(--wine-dark);
    }
    .ticker-track {
      display: flex;
      white-space: nowrap;
      animation: scroll-ticker 30s linear infinite;
    }
    .ticker:hover .ticker-track { animation-play-state: paused; }
    .ticker-item {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 0 28px;
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--wine-pale);
    }
    .ticker-sep {
      width: 3px; height: 3px; border-radius: 50%;
      background: var(--wine-light); flex-shrink: 0;
    }
    @keyframes scroll-ticker {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* =============================================
       TOAST NOTIFICATION (Para mostrar $mensaje)
       ============================================= */
    .toast-message {
      position: fixed;
      top: 50px;
      right: 20px;
      background: var(--wine-mid);
      color: var(--text-primary);
      padding: 14px 24px;
      border-radius: 12px;
      z-index: 1000;
      box-shadow: 0 8px 24px rgba(0,0,0,0.5);
      font-weight: 500;
      letter-spacing: 0.02em;
      animation: fadeOutToast 4s forwards;
    }
    @keyframes fadeOutToast {
      0% { opacity: 0; transform: translateY(-10px); }
      10% { opacity: 1; transform: translateY(0); }
      80% { opacity: 1; transform: translateY(0); }
      100% { opacity: 0; transform: translateY(-10px); pointer-events: none; }
    }

    /* =============================================
       APP SHELL & SIDEBAR
       ============================================= */
    .app { display: flex; height: 100vh; padding-top: 36px; }

    .sidebar {
      width: 268px; min-width: 268px;
      background: var(--surface-1);
      border-right: 1px solid var(--border-faint);
      display: flex; flex-direction: column; overflow: hidden;
    }
    .sidebar-logo { padding: 22px 22px 18px; border-bottom: 1px solid var(--border-faint); flex-shrink: 0; }
    .logo-row { display: flex; align-items: center; gap: 11px; }
    .logo-mark {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--wine-dark), var(--wine-mid));
      border-radius: 10px; display: flex; align-items: center; justify-content: center;
      font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 700;
      color: var(--wine-pale); flex-shrink: 0; box-shadow: 0 4px 14px rgba(98, 21, 45, 0.45);
    }
    .logo-text-wrap { flex: 1; }
    .logo-name { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; color: var(--text-primary); letter-spacing: -0.03em; line-height: 1; }
    .logo-tagline { font-size: 9.5px; font-weight: 300; letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-muted); margin-top: 3px; }

    .sidebar-nav { flex: 1; overflow-y: auto; padding: 14px 0 8px; }
    .nav-wrap { display: flex; flex-direction: column; gap: 6px; padding: 0 12px; }
    .nav-btn { position: relative; background: none; padding: 0; text-align: left; width: 100%; border-radius: 12px; transition: transform var(--fast) var(--ease); }
    .nav-btn:active { transform: scale(0.98); }
    .nav-btn-inner {
      display: flex; align-items: center; gap: 13px; padding: 14px 16px; border-radius: 12px;
      border: 1px solid transparent; background: var(--surface-2);
      transition: background var(--normal) var(--ease), border-color var(--normal) var(--ease), box-shadow var(--normal) var(--ease);
    }
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
    .btn-cta {
      display: block; width: 100%; padding: 13px;
      background: linear-gradient(135deg, var(--wine-mid), var(--wine-dark));
      border-radius: 12px; color: var(--wine-pale); font-size: 12px; font-weight: 500;
      letter-spacing: 0.06em; text-align: center;
      transition: background var(--normal) var(--ease), color var(--normal) var(--ease), box-shadow var(--normal) var(--ease), transform var(--fast) var(--ease);
      box-shadow: 0 4px 16px rgba(98,21,45,0.3);
    }
    .btn-cta:hover { background: var(--wine-light); color: var(--black-deep); box-shadow: 0 6px 24px rgba(202,102,139,0.35); }
    .btn-cta:active { transform: scale(0.98); }

    /* =============================================
       CONTENT AREA
       ============================================= */
    .content-area { flex: 1; display: flex; padding: 18px 18px 18px 0; overflow: hidden; }
    .content-panel { flex: 1; background: var(--surface-1); border-radius: 20px; border: 1px solid var(--border-faint); overflow: hidden; position: relative; }

    .panel-inner { display: flex; height: 100%; width: 100%; position: absolute; inset: 0; opacity: 0; pointer-events: none; }
    .panel-inner.state-enter { opacity: 0; transform: translateY(16px); pointer-events: none; }
    .panel-inner.state-active { opacity: 1; transform: translateY(0); pointer-events: auto; transition: opacity var(--slow) var(--ease), transform var(--slow) var(--ease); }
    .panel-inner.state-exit { opacity: 0; transform: translateY(-12px); pointer-events: none; transition: opacity var(--normal) var(--ease), transform var(--normal) var(--ease); }

    .panel-text {
      flex: 1; min-width: 0;
      padding: 44px; display: flex; flex-direction: column;
      overflow-y: auto; width: 100%;
    }

    .panel-tag {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 100px;
      padding: 5px 14px; font-size: 9.5px; font-weight: 500; letter-spacing: 0.14em;
      text-transform: uppercase; color: var(--wine-light); align-self: flex-start; margin-bottom: 22px;
    }
    .tag-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--wine-light); flex-shrink: 0; }
    .panel-title { font-family: 'Cormorant Garamond', serif; font-size: clamp(34px, 3.6vw, 58px); font-weight: 600; line-height: 1.04; letter-spacing: -0.025em; color: var(--text-primary); margin-bottom: 24px; }
    .panel-title em { font-style: italic; color: var(--wine-light); }

    /* =============================================
       TABLES & SQL STYLES 
       ============================================= */
    .sql-query-label {
      font-family: monospace;
      background: var(--surface-2);
      color: var(--wine-pale);
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 12px;
      margin-bottom: 24px;
      display: inline-block;
      border: 1px solid var(--border-soft);
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
    }

    .table-container {
      width: 100%;
      overflow-x: auto;
      border: 1px solid var(--border-soft);
      border-radius: 12px;
      background: var(--surface-0);
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      text-align: left;
    }
    
    .data-table th, .data-table td {
      padding: 16px;
      border-bottom: 1px solid var(--border-faint);
    }
    
    .data-table th {
      background: var(--surface-2);
      color: var(--wine-light);
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      font-size: 11px;
      white-space: nowrap;
    }
    
    .data-table td {
      color: var(--text-secondary);
      font-weight: 300;
      vertical-align: middle;
    }
    
    .data-table tr:hover td {
      background: var(--surface-3);
      color: var(--text-primary);
    }

    /* Formularios dentro de las tablas */
    .input-grade {
      width: 70px;
      background: var(--surface-1);
      border: 1px solid var(--border-strong);
      color: var(--text-primary);
      padding: 6px;
      border-radius: 6px;
      font-family: 'DM Sans', sans-serif;
      text-align: center;
      transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease);
    }
    .input-grade:focus {
      outline: none;
      border-color: var(--wine-light);
      box-shadow: 0 0 0 2px rgba(202, 102, 139, 0.2);
    }
    .btn-save {
      background: var(--wine-mid);
      color: var(--text-primary);
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 500;
      transition: background var(--fast) var(--ease), transform var(--fast) var(--ease);
      box-shadow: 0 2px 8px rgba(98, 21, 45, 0.4);
    }
    .btn-save:hover {
      background: var(--wine-light);
      color: var(--black-deep);
    }
    .btn-save:active { transform: scale(0.95); }

    /* =============================================
       RESPONSIVE
       ============================================= */
    @media (max-width: 1024px) {
      .sidebar { width: 220px; min-width: 220px; }
      .panel-text { padding: 30px; }
    }
    @media (max-width: 768px) {
      body { overflow: auto; }
      .app { flex-direction: column; height: auto; min-height: 100vh; }
      .sidebar { width: 100%; min-width: 0; border-right: none; border-bottom: 1px solid var(--border-faint); overflow: visible; }
      .nav-wrap { flex-direction: row; overflow-x: auto; padding: 0 12px 6px; scrollbar-width: none; -webkit-overflow-scrolling: touch; gap: 8px; }
      .nav-wrap::-webkit-scrollbar { display: none; }
      .nav-btn { flex-shrink: 0; min-width: 148px; width: auto; }
      .content-area { padding: 12px; flex: 1; min-height: 70vh; }
      .panel-text { padding: 26px 22px; overflow: visible; }
      .sidebar-cta { display: none; }
    }
  </style>
</head>

<body>
  <?php if($mensaje): ?>
    <div class="toast-message">
      <?= htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <div class="ticker" role="marquee" aria-label="Avisos importantes">
    <div class="ticker-track">
      <span class="ticker-item"><span class="ticker-sep"></span>Periodo de actas abierto</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Carga de calificaciones extraordinaria</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Cierre de sistema próximo</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Evaluación docente obligatoria</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Periodo de actas abierto</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Carga de calificaciones extraordinaria</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Cierre de sistema próximo</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Evaluación docente obligatoria</span>
    </div>
  </div>

  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-row">
          <div class="logo-mark">IPN</div>
          <div class="logo-text-wrap">
            <div class="logo-name">ESCOM</div>
            <div class="logo-tagline">Panel Profesor</div>
          </div>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Secciones">
        <div class="nav-wrap" id="navWrap">
          <button class="nav-btn active" data-color="1" data-section="0" aria-current="true">
            <div class="nav-btn-inner">
              <span class="nav-num">01</span>
              <span class="nav-label">Mis materias</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
          <button class="nav-btn" data-color="2" data-section="1">
            <div class="nav-btn-inner">
              <span class="nav-num">02</span>
              <span class="nav-label">Calificaciones</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
          <button class="nav-btn" data-color="3" data-section="2">
            <div class="nav-btn-inner">
              <span class="nav-num">03</span>
              <span class="nav-label">Acerca de</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
        </div>
      </nav>

      <div class="sidebar-cta">
        <a href="logout.php" class="btn-cta">Cerrar sesión</a>
      </div>
    </aside>

    <main class="content-area">
      <div class="content-panel" id="contentPanel" aria-live="polite">
      </div>
    </main>
  </div>

  <script>
    /* =============================================
       DATOS DE SECCIONES (PHP a Frontend)
       ============================================= */
    const SECTIONS = [
      {
        color: 1,
        tag: 'Academia',
        title: 'Materias que <em>imparto.</em>',
        sqlQuery: 'SELECT m.id_materia, m.nombre FROM Materia',
        type: 'table',
        columns: ['ID Materia', 'Nombre de la Materia'],
        data: <?= json_encode(array_map(fn($m) => [
            $m['id_materia'],
            htmlspecialchars($m['nombre'])
        ], $materias)) ?>
      },
      {
        color: 2,
        tag: 'Evaluación',
        title: 'Gestión de <em>calificaciones.</em>',
        sqlQuery: 'UPDATE Tiene_Inscrita SET parcial_1, parcial_2, parcial_3...',
        type: 'table',
        columns: ['Boleta', 'Alumno', 'Materia', 'P1', 'P2', 'P3', 'Final', 'Acción'],
        data: <?= json_encode($datosAlumnosJS) ?>
      },
      {
        color: 3,
        tag: 'Sistema',
        title: 'Acerca de <em>SAE.</em>',
        sqlQuery: 'Información de la sesión actual',
        type: 'table',
        columns: ['Propiedad', 'Valor'],
        data: [
            ['Plataforma', 'Sistema de Administración Escolar (SAE)'],
            ['Rol de Usuario', 'Profesor'],
            ['Nombre', '<?= htmlspecialchars($_SESSION['nombre'] ?? 'Desconocido') ?>'],
            ['ID Profesor', '<?= htmlspecialchars($_SESSION['id_profesor'] ?? 'Desconocido') ?>']
        ]
      }
    ];

    /* =============================================
       RENDER SECCIÓN
       ============================================= */
    function renderSection(idx) {
      const s = SECTIONS[idx];
      let contentHtml = '';

      const sqlBadge = `<div class="sql-query-label">> ${s.sqlQuery}</div>`;

      if (s.type === 'table') {
        const thead = s.columns.map(c => `<th>${c}</th>`).join('');
        const tbody = s.data.map(row => {
          const tds = row.map(cell => `<td>${cell}</td>`).join('');
          return `<tr>${tds}</tr>`;
        }).join('');
        
        contentHtml = `
          ${sqlBadge}
          <div class="table-container">
            <table class="data-table">
              <thead><tr>${thead}</tr></thead>
              <tbody>${tbody}</tbody>
            </table>
          </div>
        `;
      }

      return `
        <div class="panel-text">
          <div class="panel-tag"><span class="tag-dot"></span>${s.tag}</div>
          <h1 class="panel-title">${s.title}</h1>
          ${contentHtml}
        </div>
      `;
    }

    /* =============================================
       TRANSICIÓN DE SECCIÓN
       ============================================= */
    let currentIdx = 0;
    let isAnimating = false;

    function goToSection(idx) {
      if (idx === currentIdx || isAnimating) return;
      isAnimating = true;

      document.querySelectorAll('.nav-btn').forEach(b => {
        b.classList.remove('active');
        b.removeAttribute('aria-current');
      });
      const activeBtn = document.querySelector(`.nav-btn[data-section="${idx}"]`);
      if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-current', 'true');
      }

      const panel = document.getElementById('contentPanel');
      const inner = panel.querySelector('.panel-inner');

      if (!inner) {
        currentIdx = idx;
        mountSection(panel, idx);
        isAnimating = false;
        return;
      }

      inner.classList.remove('state-active');
      inner.classList.add('state-exit');

      setTimeout(() => {
        currentIdx = idx;
        mountSection(panel, idx);
        isAnimating = false;
      }, 280);
    }

    function mountSection(panel, idx) {
      const div = document.createElement('div');
      div.className = 'panel-inner state-enter';
      div.innerHTML = renderSection(idx);
      panel.innerHTML = '';
      panel.appendChild(div);

      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          div.classList.remove('state-enter');
          div.classList.add('state-active');
        });
      });
    }

    /* =============================================
       EVENT LISTENERS
       ============================================= */
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('navWrap').addEventListener('click', e => {
        const btn = e.target.closest('.nav-btn');
        if (!btn) return;
        const idx = parseInt(btn.dataset.section, 10);
        goToSection(idx);
      });
      mountSection(document.getElementById('contentPanel'), 0);
    });
  </script>
</body>
</html>