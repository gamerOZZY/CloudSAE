<?php
session_start();

// 1. Mostrar errores temporalmente (útil para depurar la pantalla blanca)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Validación de seguridad estricta
if(!isset($_SESSION['id_directivo']) || $_SESSION['rol'] !== 'directivo'){
    header("Location: index.html");
    exit();
}

// 3. CONEXIÓN EXCLUSIVA AL OLAP (AZURE)
$host = "baseanalitica.mysql.database.azure.com";
$user = "gamerOZZY";
$pass = "Password123";
$dbname = "olapsae";

try {
    // Instanciamos un nuevo PDO solo para el dashboard
    $pdoOlap = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdoOlap->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. CONSULTA DE KPIs GLOBALES
    $sqlKPI = "
        SELECT 
            COUNT(DISTINCT sk_alumno) AS total_alumnos,
            ROUND(AVG(calificacion_final), 2) AS promedio_global,
            ROUND((SUM(aprobado) / COUNT(*)) * 100, 2) AS pct_aprobacion
        FROM Fact_Rendimiento
    ";
    $stmt = $pdoOlap->query($sqlKPI);
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. CONSULTA DE EVOLUCIÓN TEMPORAL
    $sqlTemporal = "
        SELECT 
            CONCAT(t.anio, ' - T', t.trimestre) AS periodo,
            ROUND(AVG(f.calificacion_final), 2) AS promedio
        FROM Fact_Rendimiento f
        INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
        GROUP BY t.anio, t.trimestre
        ORDER BY t.anio ASC, t.trimestre ASC
    ";
    $stmt = $pdoOlap->query($sqlTemporal);
    $evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. CONSULTA DE MATERIAS CON MAYOR REPROBACIÓN
    $sqlMaterias = "
        SELECT 
            m.nombre_materia,
            ROUND((SUM(CASE WHEN f.aprobado = 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS pct_reprobacion
        FROM Fact_Rendimiento f
        INNER JOIN Dim_Materia m ON f.sk_materia = m.sk_materia
        GROUP BY m.nombre_materia
        ORDER BY pct_reprobacion DESC
        LIMIT 5
    ";
    $stmt = $pdoOlap->query($sqlMaterias);
    $materias_criticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Si falla la conexión a Azure, detiene todo y te muestra el error exacto
    die("Error al conectar con el Data Warehouse OLAP en Azure: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Directivo — SAES</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    /* =============================================
       VARIABLES Y RESET (TU DISEÑO ORIGINAL)
       ============================================= */
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
      --border-faint:  rgba(202, 102, 139, 0.12);
      --border-soft:   rgba(202, 102, 139, 0.25);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--black-deep);
      color: var(--text-primary);
      height: 100vh;
      overflow: hidden;
    }
    
    /* SHELL Y SIDEBAR */
    .app { display: flex; height: 100vh; padding-top: 36px; }
    .sidebar { width: 268px; background: var(--surface-1); border-right: 1px solid var(--border-faint); display: flex; flex-direction: column; }
    .sidebar-logo { padding: 22px; border-bottom: 1px solid var(--border-faint); }
    .logo-row { display: flex; align-items: center; gap: 11px; }
    .logo-mark { width: 38px; height: 38px; background: linear-gradient(135deg, var(--wine-dark), var(--wine-mid)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 700; color: var(--wine-pale); }
    .logo-name { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; }
    .logo-tagline { font-size: 9.5px; letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-muted); }
    
    /* BOTÓN DE CERRAR SESIÓN */
    .sidebar-cta { padding: 10px 12px; margin-top: auto; border-top: 1px solid var(--border-faint); }
    .btn-cta { display: block; width: 100%; padding: 13px; background: linear-gradient(135deg, var(--wine-mid), var(--wine-dark)); border-radius: 12px; color: var(--wine-pale); font-size: 12px; font-weight: 500; text-align: center; text-decoration: none; }
    
    /* CONTENT AREA */
    .content-area { flex: 1; padding: 18px 18px 18px 0; overflow: hidden; display: flex; }
    .content-panel { flex: 1; background: var(--surface-1); border-radius: 20px; border: 1px solid var(--border-faint); padding: 44px; overflow-y: auto; }
    .panel-tag { display: inline-flex; align-items: center; gap: 8px; background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 100px; padding: 5px 14px; font-size: 9.5px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--wine-light); margin-bottom: 22px; }
    .tag-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--wine-light); }
    .panel-title { font-family: 'Cormorant Garamond', serif; font-size: 48px; font-weight: 600; color: var(--text-primary); margin-bottom: 24px; }
    .panel-title em { font-style: italic; color: var(--wine-light); }

    /* DASHBOARD UI COMPONENTES */
    .dashboard-grid { display: grid; gap: 24px; margin-top: 20px; }
    
    .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
    .kpi-card { background: linear-gradient(145deg, var(--surface-2), var(--surface-1)); border: 1px solid var(--border-faint); border-radius: 16px; padding: 24px; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--wine-light); }
    .kpi-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 8px; }
    .kpi-value { font-family: 'Cormorant Garamond', serif; font-size: 42px; font-weight: 700; color: var(--text-primary); }

    .charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
    .chart-card { background: var(--surface-0); border: 1px solid var(--border-faint); border-radius: 16px; padding: 24px; height: 360px; }
    .chart-header { font-size: 14px; font-weight: 500; color: var(--text-secondary); margin-bottom: 16px; border-bottom: 1px solid var(--border-faint); padding-bottom: 10px; }
  </style>
</head>
<body>

  <div class="app">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-row">
          <div class="logo-mark">IPN</div>
          <div>
            <div class="logo-name">ESCOM</div>
            <div class="logo-tagline">Data Warehouse</div>
          </div>
        </div>
      </div>
      
      <div style="padding: 22px; color: var(--text-secondary); font-size: 13px;">
          Bienvenido, <br>
          <strong style="color: var(--text-primary);"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Directivo') ?></strong>
      </div>

      <div class="sidebar-cta">
        <a href="logout.php" class="btn-cta">Cerrar sesión</a>
      </div>
    </aside>

    <main class="content-area">
      <div class="content-panel">
        <div class="panel-tag"><span class="tag-dot"></span>Inteligencia</div>
        <h1 class="panel-title">Panel <em>Ejecutivo.</em></h1>

        <div class="dashboard-grid">
          
          <div class="kpi-row">
            <div class="kpi-card">
              <div class="kpi-title">Población Analizada</div>
              <div class="kpi-value"><?= number_format($kpis['total_alumnos'] ?? 0) ?></div>
            </div>
            <div class="kpi-card">
              <div class="kpi-title">Punto de Equilibrio (Promedio)</div>
              <div class="kpi-value"><?= number_format($kpis['promedio_global'] ?? 0, 2) ?></div>
            </div>
            <div class="kpi-card">
              <div class="kpi-title">Tasa de Aprobación</div>
              <div class="kpi-value"><?= number_format($kpis['pct_aprobacion'] ?? 0, 1) ?>%</div>
            </div>
          </div>

          <div class="charts-row">
            <div class="chart-card">
              <div class="chart-header">Evolución del Promedio Institucional</div>
              <canvas id="chartEvolucion"></canvas>
            </div>
            <div class="chart-card">
              <div class="chart-header">Alerta: Materias con Mayor Reprobación</div>
              <canvas id="chartMaterias"></canvas>
            </div>
          </div>

        </div>
      </div>
    </main>
  </div>

  <script>
    // Inyectamos los datos procesados en PHP directamente a JavaScript
    const dataEvolucion = <?= json_encode($evolucion) ?>;
    const dataMaterias = <?= json_encode($materias_criticas) ?>;

    // Configuración global de estilos para Chart.js
    Chart.defaults.color = '#c9a0b2';
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.scale.grid.color = 'rgba(202, 102, 139, 0.1)';

    // 1. Gráfica de Líneas (Evolución)
    if(dataEvolucion && dataEvolucion.length > 0) {
        const ctxEvolucion = document.getElementById('chartEvolucion').getContext('2d');
        new Chart(ctxEvolucion, {
          type: 'line',
          data: {
            labels: dataEvolucion.map(d => d.periodo),
            datasets: [{
              label: 'Promedio de Calificaciones',
              data: dataEvolucion.map(d => d.promedio),
              borderColor: '#ca668b',
              backgroundColor: 'rgba(202, 102, 139, 0.15)',
              borderWidth: 2,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: '#952f57'
            }]
          },
          options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
          }
        });
    }

    // 2. Gráfica de Barras (Materias Críticas)
    if(dataMaterias && dataMaterias.length > 0) {
        const ctxMaterias = document.getElementById('chartMaterias').getContext('2d');
        new Chart(ctxMaterias, {
          type: 'bar',
          data: {
            labels: dataMaterias.map(d => d.nombre_materia),
            datasets: [{
              label: '% de Reprobación',
              data: dataMaterias.map(d => d.pct_reprobacion),
              backgroundColor: '#952f57',
              borderRadius: 6
            }]
          },
          options: { 
            indexAxis: 'y', 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
          }
        });
    }
  </script>
</body>
</html>