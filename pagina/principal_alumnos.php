<?php

session_start();

if(!isset($_SESSION['id_persona'])){
    header("Location: index.html");
    exit();
}

require_once "conexion.php";

$idAlumno = $_SESSION['id_persona'];
$boleta = $_SESSION['boleta'];


// DATOS PERSONALES

$sql = "
SELECT
    a.boleta,
    a.edad,
    p.nombre_completo,
    p.foto_perfil,
    e.nombre AS escuela,
    r.nombre AS region
FROM Alumno a
INNER JOIN Persona p
    ON a.id_persona = p.id_persona
INNER JOIN Escuela e
    ON p.id_escuela = e.id_escuela
INNER JOIN Region_Geografica r
    ON e.id_region = r.id_region
WHERE a.id_persona = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idAlumno]);

$alumno = $stmt->fetch(PDO::FETCH_ASSOC);


// MATERIAS

$sql = "
SELECT
    m.nombre,
    ti.grado_semestre,
    ti.fecha_inscripcion
FROM Tiene_Inscrita ti
INNER JOIN Materia m
    ON m.id_materia = ti.id_materia
WHERE ti.id_alumno = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idAlumno]);

$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// CALIFICACIONES

$sql = "
SELECT
    m.nombre,
    ti.parcial_1,
    ti.parcial_2,
    ti.parcial_3,
    ti.final
FROM Tiene_Inscrita ti
INNER JOIN Materia m
    ON m.id_materia = ti.id_materia
WHERE ti.id_alumno = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idAlumno]);

$calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Administración Escolar — ESCOM</title>
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
    .nav-btn.active[data-color="4"] .nav-btn-inner { background: linear-gradient(135deg, var(--wine-dark), #3d0d1c); border-color: rgba(202,102,139,0.22); box-shadow: 0 4px 18px rgba(98,21,45,0.35); }
    
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

    .sidebar-footer { padding: 12px 22px; border-top: 1px solid var(--border-faint); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .lang-btn { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 400; color: var(--text-muted); background: none; padding: 5px 10px; border-radius: 8px; border: 1px solid transparent; transition: all var(--fast) var(--ease); }
    .lang-btn:hover { color: var(--text-secondary); background: var(--surface-2); border-color: var(--border-faint); }

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
       TABLES & SQL STYLES (NEW)
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
    .sql-query-label span { color: var(--wine-light); font-weight: bold; }

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
    }
    
    .data-table tr:hover td {
      background: var(--surface-3);
      color: var(--text-primary);
    }

    /* Vertical table for profile */
    .table-vertical th { width: 30%; background: transparent; color: var(--wine-pale); }
    .table-vertical td { width: 70%; font-weight: 400; color: var(--text-primary); }
    .table-vertical tr:hover td, .table-vertical tr:hover th { background: transparent; }

    /* Profile Container */
    .profile-container {
      display: flex;
      gap: 32px;
      align-items: center;
      justify-content: center;

    }
    
    .profile-photo {
      width: 180px;
      height: 220px;
      border-radius: 12px;
      object-fit: cover;
      border: 2px solid var(--wine-mid);
      box-shadow: 0 8px 24px rgba(98, 21, 45, 0.4);
      flex-shrink: 0;
    }
    
    .profile-table-wrapper {
      flex: 1;
      min-width: 0;
    }

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
      .profile-container { flex-direction: column; align-items: center; }
      .profile-photo { width: 140px; height: 140px; border-radius: 50%; }
      .sidebar-cta { display: none; }
    }
  </style>
</head>

<body>
  <div class="ticker" role="marquee" aria-label="Avisos importantes">
    <div class="ticker-track">
      <span class="ticker-item"><span class="ticker-sep"></span>Periodo de reinscripción abierto</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Alta de materias optativas</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Revisión de calificaciones disponible</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Convocatoria de becas institucionales</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Periodo de reinscripción abierto</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Alta de materias optativas</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Revisión de calificaciones disponible</span>
      <span class="ticker-item"><span class="ticker-sep"></span>Convocatoria de becas institucionales</span>
    </div>
  </div>

  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-row">
          <div class="logo-mark">IPN</div>
          <div class="logo-text-wrap">
            <div class="logo-name">ESCOM</div>
            <div class="logo-tagline">Control Escolar</div>
          </div>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Secciones">
        <div class="nav-wrap" id="navWrap">
          <button class="nav-btn active" data-color="1" data-section="0" aria-current="true">
            <div class="nav-btn-inner">
              <span class="nav-num">01</span>
              <span class="nav-label">Datos personales</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
          <button class="nav-btn" data-color="2" data-section="1">
            <div class="nav-btn-inner">
              <span class="nav-num">02</span>
              <span class="nav-label">Tira de materias</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
          <button class="nav-btn" data-color="3" data-section="2">
            <div class="nav-btn-inner">
              <span class="nav-num">03</span>
              <span class="nav-label">Calificaciones</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
          <button class="nav-btn" data-color="4" data-section="3">
            <div class="nav-btn-inner">
              <span class="nav-num">04</span>
              <span class="nav-label">Acerca de</span>
              <span class="nav-arrow">↗</span>
            </div>
          </button>
        </div>
      </nav>

      <div class="sidebar-cta">
        <a href="logout.php" class="btn-cta">Cerrar sesion</a>
      </div>
    </aside>

    <main class="content-area">
      <div class="content-panel" id="contentPanel" aria-live="polite">
        </div>
    </main>
  </div>

  <script>
    /* =============================================
       DATOS DE SECCIONES (Simulación SQL a Frontend)
       ============================================= */
    <?php

$datosPerfil = [
    [
        "label" => "Boleta",
        "value" => $alumno["boleta"]
    ],
    [
        "label" => "Nombre Completo",
        "value" => $alumno["nombre_completo"]
    ],
    [
        "label" => "Edad",
        "value" => $alumno["edad"]
    ],
    [
        "label" => "Escuela",
        "value" => $alumno["escuela"]
    ],
    [
        "label" => "Region",
        "value" => $alumno["region"]
    ]
];

?>
const SECTIONS = [

{
    color: 1,
    tag: 'Registro',
    title: 'Datos <em>personales.</em>',
    sqlQuery: 'SELECT datos del alumno',
    type: 'profile',
    photo: 'https://via.placeholder.com/300x400',
    data: <?= json_encode($datosPerfil) ?>
},

{
    color: 2,
    tag: 'Academia',
    title: 'Tira de <em>materias.</em>',
    sqlQuery: 'SELECT materias inscritas',
    type: 'table',
    columns: ['Materia','Semestre','Fecha Inscripción'],
    data: <?= json_encode(
        array_map(
            fn($m)=>[
                $m["nombre"],
                $m["grado_semestre"],
                $m["fecha_inscripcion"]
            ],
            $materias
        )
    ) ?>
},

{
    color: 3,
    tag: 'Historial',
    title: 'Reporte de <em>calificaciones.</em>',
    sqlQuery: 'SELECT calificaciones',
    type: 'table',
    columns: [
        'Materia',
        'Parcial 1',
        'Parcial 2',
        'Parcial 3',
        'Final'
    ],
    data: <?= json_encode(
        array_map(
            fn($c)=>[
                $c["nombre"],
                $c["parcial_1"],
                $c["parcial_2"],
                $c["parcial_3"],
                $c["final"]
            ],
            $calificaciones
        )
    ) ?>
},

{
    color: 4,
    tag: 'Sistema',
    title: 'Acerca de <em>SAE.</em>',
    sqlQuery: 'Información del sistema',
    type: 'table',
    columns: ['Propiedad','Valor'],
    data: [
        ['Base de Datos','Azure MySQL Flexible Server'],
        ['Alumno','<?= $_SESSION["nombre"] ?>'],
        ['Boleta','<?= $_SESSION["boleta"] ?>']
    ]
}

];

    /* =============================================
       RENDER SECCIÓN
       ============================================= */
    function renderSection(idx) {
      const s = SECTIONS[idx];
      let contentHtml = '';

      // Generar cajita de SQL
      const sqlBadge = `<div class="sql-query-label">> ${s.sqlQuery}</div>`;

      if (s.type === 'profile') {
        // Formato para Perfil (Foto + Tabla Vertical)
        const rows = s.data.map(r => `<tr><th>${r.label}</th><td>${r.value}</td></tr>`).join('');
        contentHtml = `
          ${sqlBadge}
          <div class="profile-container">
            <img src="${s.photo}" alt="Foto del alumno" class="profile-photo" />
            <div class="profile-table-wrapper table-container">
              <table class="data-table table-vertical">
                <tbody>${rows}</tbody>
              </table>
            </div>
          </div>
        `;
      } else if (s.type === 'table') {
        // Formato para Tira de materias / Calificaciones (Tabla Completa)
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

      // En este rediseño, eliminamos el panel visual (el SVG de la derecha)
      // para que la tabla ocupe todo el ancho disponible y luzca como un dashboard real.
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