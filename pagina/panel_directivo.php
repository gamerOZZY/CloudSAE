<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Validación de seguridad
if(!isset($_SESSION['id_directivo']) || $_SESSION['rol'] !== 'directivo'){
    header("Location: index.html");
    exit();
}

// 1. CONEXIÓN AL OLAP
$host = "baseanalitica.mysql.database.azure.com";
$user = "gamerOZZY";
$pass = "Password123#";
$dbname = "olapsae";

try {
    $pdoOlap = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdoOlap->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error crítico al conectar al OLAP en Azure: " . $e->getMessage());
}

// ======================================================================
// 2. MODO API: PROCESAMIENTO ANALÍTICO (OLAP)
// ======================================================================
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');

    $where = "WHERE 1=1";
    $params = [];

    // CAMBIO: Si viene vacío, es "Histórico Total"
    $anio_actual = $_GET['anio'] ?? ''; 
    $region = $_GET['region'] ?? '';
    $escuela = $_GET['escuela'] ?? '';

    // Si seleccionó un año específico, aplicamos el filtro
    if ($anio_actual !== '') {
        $where .= " AND t.anio = ?";
        $params[] = (int)$anio_actual;
    }

    if (!empty($escuela)) {
        $where .= " AND u.nombre_escuela = ?";
        $params[] = $escuela;
    } else if (!empty($region)) {
        $where .= " AND u.nombre_region = ?";
        $params[] = $region;
    }

    try {
        $data = [];

        // A. KPIs GLOBALES
        $stmt = $pdoOlap->prepare("
            SELECT 
                COUNT(DISTINCT f.sk_alumno) AS total_alumnos,
                ROUND(AVG(f.calificacion_final), 2) AS promedio_global,
                ROUND((SUM(f.aprobado) / COUNT(*)) * 100, 1) AS pct_aprobacion
            FROM Fact_Rendimiento f
            INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
            INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
            $where
        ");
        $stmt->execute($params);
        $kpi_actual = $stmt->fetch(PDO::FETCH_ASSOC);

        // B. CÁLCULO DE SEMÁFOROS (Deltas) - Solo si NO es Histórico Total
        if ($anio_actual !== '') {
            $where_prev = str_replace("t.anio = ?", "t.anio = ?", $where);
            $params_prev = $params;
            $params_prev[0] = (int)$anio_actual - 1;

            $stmt_prev = $pdoOlap->prepare("
                SELECT 
                    COUNT(DISTINCT f.sk_alumno) AS total_alumnos,
                    ROUND(AVG(f.calificacion_final), 2) AS promedio_global,
                    ROUND((SUM(f.aprobado) / COUNT(*)) * 100, 1) AS pct_aprobacion
                FROM Fact_Rendimiento f
                INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
                INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
                $where_prev
            ");
            $stmt_prev->execute($params_prev);
            $kpi_prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);

            $data['kpi'] = [
                'alumnos' => number_format($kpi_actual['total_alumnos']),
                'alumnos_dif' => $kpi_actual['total_alumnos'] - ($kpi_prev['total_alumnos'] ?? 0),
                'promedio' => $kpi_actual['promedio_global'] ?? '0.00',
                'promedio_dif' => round(($kpi_actual['promedio_global'] ?? 0) - ($kpi_prev['promedio_global'] ?? 0), 2),
                'aprobacion' => ($kpi_actual['pct_aprobacion'] ?? '0.0') . '%',
                'aprobacion_dif' => round(($kpi_actual['pct_aprobacion'] ?? 0) - ($kpi_prev['pct_aprobacion'] ?? 0), 1)
            ];
        } else {
            // Histórico Total: Apagamos los semáforos
            $data['kpi'] = [
                'alumnos' => number_format($kpi_actual['total_alumnos']),
                'alumnos_dif' => 0,
                'promedio' => $kpi_actual['promedio_global'] ?? '0.00',
                'promedio_dif' => 0,
                'aprobacion' => ($kpi_actual['pct_aprobacion'] ?? '0.0') . '%',
                'aprobacion_dif' => 0
            ];
        }

        // C. SISTEMA DE DETECCIÓN TEMPRANA (Predictivo)
        $stmt = $pdoOlap->prepare("
            SELECT COUNT(DISTINCT f.sk_alumno) AS alumnos_en_riesgo
            FROM Fact_Rendimiento f
            INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
            INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
            $where AND f.parcial_1 < 6 AND f.parcial_2 < 6
        ");
        $stmt->execute($params);
        $riesgo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($riesgo['alumnos_en_riesgo'] > 0) {
            $data['insight'] = "🔴 <b>Detección Temprana:</b> <b>{$riesgo['alumnos_en_riesgo']} alumnos</b> están en riesgo inminente de deserción por haber reprobado los parciales 1 y 2.";
        } else {
            $data['insight'] = "✅ <b>Estabilidad:</b> No se detectan anomalías masivas en la cohorte actual de estudiantes.";
        }

        // D. EMBUDO DE DESEMPEÑO (Evolución de Parciales)
        $stmt = $pdoOlap->prepare("
            SELECT 
                ROUND(AVG(f.parcial_1), 2) AS p1,
                ROUND(AVG(f.parcial_2), 2) AS p2,
                ROUND(AVG(f.parcial_3), 2) AS p3,
                ROUND(AVG(f.calificacion_final), 2) AS final
            FROM Fact_Rendimiento f
            INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
            INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
            $where
        ");
        $stmt->execute($params);
        $data['embudo'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // E. DRILL-DOWN GEOGRÁFICO
        if(empty($region) && empty($escuela)){
            $stmt = $pdoOlap->prepare("
                SELECT u.nombre_region AS nombre, ROUND((SUM(f.aprobado) / COUNT(*)) * 100, 1) AS valor
                FROM Fact_Rendimiento f
                INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
                INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
                $where GROUP BY u.nombre_region ORDER BY valor DESC
            ");
            $data['geo_nivel'] = 'region';
        } else {
            $stmt = $pdoOlap->prepare("
                SELECT u.nombre_escuela AS nombre, ROUND((SUM(f.aprobado) / COUNT(*)) * 100, 1) AS valor
                FROM Fact_Rendimiento f
                INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
                INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
                $where GROUP BY u.nombre_escuela ORDER BY valor DESC LIMIT 7
            ");
            $data['geo_nivel'] = 'escuela';
        }
        $stmt->execute($params);
        $data['geografia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // F. MATERIAS CRÍTICAS
        $stmt = $pdoOlap->prepare("
            SELECT m.nombre_materia, ROUND((SUM(CASE WHEN f.aprobado = 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) AS reprobacion
            FROM Fact_Rendimiento f
            INNER JOIN Dim_Materia m ON f.sk_materia = m.sk_materia
            INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
            INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
            $where GROUP BY m.nombre_materia ORDER BY reprobacion DESC LIMIT 5
        ");
        $stmt->execute($params);
        $data['materias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // G. MATRIZ DEMOGRÁFICA
        $stmt = $pdoOlap->prepare("
            SELECT a.rango_edad, ROUND((SUM(f.aprobado) / COUNT(*)) * 100, 1) AS aprobacion
            FROM Fact_Rendimiento f
            INNER JOIN Dim_Alumno a ON f.sk_alumno = a.sk_alumno
            INNER JOIN Dim_Tiempo t ON f.sk_tiempo_inscripcion = t.sk_tiempo
            INNER JOIN Dim_Ubicacion u ON f.sk_ubicacion = u.sk_ubicacion
            $where GROUP BY a.rango_edad ORDER BY aprobacion DESC
        ");
        $stmt->execute($params);
        $data['edades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error OLAP: " . $e->getMessage()]);
        exit();
    }
}

// ======================================================================
// 3. MODO VISTA: CARGAMOS CATÁLOGOS HTML
// ======================================================================
$anios = $pdoOlap->query("SELECT DISTINCT anio FROM Dim_Tiempo ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
$regiones = $pdoOlap->query("SELECT DISTINCT nombre_region FROM Dim_Ubicacion ORDER BY nombre_region")->fetchAll(PDO::FETCH_COLUMN);
$escuelas = $pdoOlap->query("SELECT DISTINCT nombre_escuela FROM Dim_Ubicacion ORDER BY nombre_escuela")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BI Directivo — SAES</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,600&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root {
      --wine-dark:   #62152d;   
      --wine-mid:    #952f57;   
      --wine-light:  #ca668b;   
      --black-deep:  #040404;   
      --surface-0:   #080306;
      --surface-1:   #120710;
      --surface-2:   #1c0d18;
      --text-primary:   #f2e8ec;
      --text-secondary: #c9a0b2;
      --text-muted:     #7a5060;
      --border-faint:  rgba(202, 102, 139, 0.2);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--black-deep); color: var(--text-primary); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    
    .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--surface-1); padding: 15px 30px; border-bottom: 1px solid var(--border-faint); z-index: 10; }
    .nav-brand { display: flex; align-items: center; gap: 15px; }
    .logo-mark { width: 40px; height: 40px; background: linear-gradient(135deg, var(--wine-dark), var(--wine-mid)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-family: 'Cormorant Garamond', serif; font-weight: 700; color: #fff; }
    .nav-filters { display: flex; gap: 10px; flex-wrap: wrap; }
    .filter-select { background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--wine-dark); padding: 8px 16px; border-radius: 6px; outline: none; font-size: 13px; font-family: inherit; }
    .logout-btn { color: var(--wine-light); text-decoration: none; font-size: 14px; padding: 8px 16px; border: 1px solid var(--wine-dark); border-radius: 6px; transition: 0.3s; }
    .logout-btn:hover { background: var(--wine-dark); color: #fff; }

    .content-area { flex: 1; overflow-y: auto; padding: 30px; }
    
    .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .kpi-card { background: var(--surface-1); padding: 20px; border-radius: 12px; border: 1px solid var(--border-faint); border-left: 4px solid var(--wine-light); }
    .kpi-title { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.1em; margin-bottom: 5px; }
    .kpi-val { font-family: 'Cormorant Garamond', serif; font-size: 38px; font-weight: 600; line-height: 1; margin-bottom: 8px; }
    
    /* Variables y clases dinámicas para el JS */
    .kpi-trend { font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 5px; }
    .kpi-trend.positive { color: #34d399; }
    .kpi-trend.negative { color: #f87171; }
    .kpi-trend.neutral { color: var(--text-muted); }
    
    .insight-panel { background: rgba(202, 102, 139, 0.1); border: 1px solid var(--wine-mid); padding: 16px 24px; border-radius: 8px; margin-bottom: 25px; font-size: 15px; color: var(--text-primary); display: flex; align-items: center; gap: 15px; }
    
    .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding-bottom: 40px; }
    .chart-box { background: var(--surface-1); padding: 20px; border-radius: 12px; border: 1px solid var(--border-faint); height: 320px; display: flex; flex-direction: column; }
    .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .chart-title { font-size: 14px; font-weight: 500; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
    .chart-canvas-container { flex: 1; position: relative; min-height: 0; }
    
    .btn-back { background: var(--surface-2); border: 1px solid var(--wine-dark); color: var(--text-primary); padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; display: none; }

    @media (max-width: 768px) {
      .navbar { flex-direction: column; align-items: stretch; gap: 15px; padding: 20px; }
      .nav-brand { justify-content: space-between; }
      .nav-filters { flex-direction: column; }
      .content-area { padding: 15px; }
      .charts-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <nav class="navbar">
    <div class="nav-brand">
      <div class="logo-mark">BI</div>
      <div style="font-family: 'Cormorant Garamond', serif; font-size: 24px;">Data Warehouse</div>
      <a href="logout.php" class="logout-btn" style="margin-left: auto;">Salir</a>
    </div>
    <div class="nav-filters">
      <!-- Filtro Modificado para incluir Histórico Total -->
      <select id="filtroAnio" class="filter-select" onchange="triggerFilter('anio')">
        <option value="">Histórico Total (Todos los años)</option>
        <?php foreach($anios as $a): ?><option value="<?= $a ?>"><?= $a ?></option><?php endforeach; ?>
      </select>
      
      <select id="filtroRegion" class="filter-select" onchange="triggerFilter('region')">
        <option value="">Nacional (Todas las Regiones)</option>
        <?php foreach($regiones as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
      </select>
      
      <select id="filtroEscuela" class="filter-select" onchange="triggerFilter('escuela')">
        <option value="">Todas las Escuelas</option>
        <?php foreach($escuelas as $e): ?><option value="<?= $e ?>"><?= $e ?></option><?php endforeach; ?>
      </select>
    </div>
  </nav>

  <main class="content-area">
    <div class="insight-panel" id="insightBox">Analizando cubo OLAP...</div>

    <!-- KPIs -->
    <div class="kpi-row">
      <div class="kpi-card">
        <div class="kpi-title">Población Analizada</div>
        <div class="kpi-val" id="kTotal">0</div>
        <div class="kpi-trend" id="kTotalTrend">--</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">Promedio Institucional</div>
        <div class="kpi-val" id="kPromedio">0.00</div>
        <div class="kpi-trend" id="kPromedioTrend">--</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">Tasa de Aprobación</div>
        <div class="kpi-val" id="kAprobacion">0%</div>
        <div class="kpi-trend" id="kAprobacionTrend">--</div>
      </div>
    </div>

    <!-- GRÁFICAS -->
    <div class="charts-grid">
      <div class="chart-box">
        <div class="chart-header">
          <span class="chart-title">Desempeño Geográfico</span>
          <button id="btnBackGeo" class="btn-back" onclick="clearGeoDrill()">← Subir Nivel</button>
        </div>
        <div class="chart-canvas-container"><canvas id="cGeografia"></canvas></div>
      </div>

      <div class="chart-box">
        <div class="chart-header"><span class="chart-title">Embudo de Desempeño (Parciales)</span></div>
        <div class="chart-canvas-container"><canvas id="cEmbudo"></canvas></div>
      </div>

      <div class="chart-box">
        <div class="chart-header"><span class="chart-title">Top 5 Materias Críticas (% Reprobación)</span></div>
        <div class="chart-canvas-container"><canvas id="cMaterias"></canvas></div>
      </div>

      <div class="chart-box">
        <div class="chart-header"><span class="chart-title">Madurez vs Rendimiento (Tasa Aprobación)</span></div>
        <div class="chart-canvas-container"><canvas id="cEdad"></canvas></div>
      </div>
    </div>
  </main>

  <script>
    Chart.defaults.color = '#c9a0b2';
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.scale.grid.color = 'rgba(202, 102, 139, 0.1)';
    let charts = {};
    let currentGeoNivel = 'region';

    function triggerFilter(source) {
        if(source === 'escuela' && document.getElementById('filtroEscuela').value !== "") {
            document.getElementById('filtroRegion').value = ""; 
        } else if (source === 'region' && document.getElementById('filtroRegion').value !== "") {
            document.getElementById('filtroEscuela').value = ""; 
        }
        fetchOLAP();
    }

    function updateTrend(elId, diff, isHigherBetter = true) {
      const el = document.getElementById(elId);
      el.className = 'kpi-trend';
      
      // Si el filtro de año está vacío (Histórico Total), ocultamos el texto de comparación
      if(document.getElementById('filtroAnio').value === "") {
          el.innerHTML = `<span class="neutral">▬ Acumulado total</span>`;
          return;
      }

      if(diff === 0) { el.innerHTML = `<span class="neutral">▬ 0</span> vs año ant.`; return; }
      
      const isPositive = diff > 0;
      const isGood = isHigherBetter ? isPositive : !isPositive;
      
      el.classList.add(isGood ? 'positive' : 'negative');
      const arrow = isPositive ? '▲ +' : '▼ ';
      el.innerHTML = `<span>${arrow}${diff}</span> vs año ant.`;
    }

    async function fetchOLAP() {
      const anio = document.getElementById('filtroAnio').value;
      const region = document.getElementById('filtroRegion').value;
      const escuela = document.getElementById('filtroEscuela').value;

      try {
        const res = await fetch(`panel_directivo.php?api=1&anio=${anio}&region=${region}&escuela=${escuela}`);
        const data = await res.json();
        if(data.error) { console.error("Error SQL:", data.error); return; }

        document.getElementById('kTotal').innerText = data.kpi.alumnos || 0;
        updateTrend('kTotalTrend', data.kpi.alumnos_dif);
        
        document.getElementById('kPromedio').innerText = data.kpi.promedio || '0.00';
        updateTrend('kPromedioTrend', data.kpi.promedio_dif);
        
        document.getElementById('kAprobacion').innerText = (data.kpi.aprobacion || 0);
        updateTrend('kAprobacionTrend', data.kpi.aprobacion_dif);

        document.getElementById('insightBox').innerHTML = data.insight;

        Object.values(charts).forEach(c => c.destroy());

        currentGeoNivel = data.geo_nivel;
        document.getElementById('btnBackGeo').style.display = (currentGeoNivel === 'escuela' && region === '') ? 'inline-block' : 'none';
        
        const geoLabels = data.geografia.map(d=>d.nombre);
        charts['cGeografia'] = new Chart(document.getElementById('cGeografia').getContext('2d'), {
          type: 'bar',
          data: { labels: geoLabels, datasets: [{ label: '% Aprobación', data: data.geografia.map(d=>d.valor), backgroundColor: '#ca668b', borderRadius: 4 }] },
          options: { 
            indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            onClick: (e, elements) => {
              if (elements.length > 0 && currentGeoNivel === 'region') {
                document.getElementById('filtroRegion').value = geoLabels[elements[0].index];
                document.getElementById('filtroEscuela').value = "";
                fetchOLAP();
              }
            }
          }
        });

        if(data.embudo) {
            charts['cEmbudo'] = new Chart(document.getElementById('cEmbudo').getContext('2d'), {
              type: 'line',
              data: { 
                  labels: ['Parcial 1', 'Parcial 2', 'Parcial 3', 'Calif. Final'], 
                  datasets: [{ label: 'Promedio Institucional', data: [data.embudo.p1, data.embudo.p2, data.embudo.p3, data.embudo.final], borderColor: '#ca668b', backgroundColor: 'rgba(202, 102, 139, 0.15)', borderWidth: 3, fill: true, tension: 0.4 }] 
              },
              options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 10 } } }
            });
        }

        charts['cMaterias'] = new Chart(document.getElementById('cMaterias').getContext('2d'), {
          type: 'bar',
          data: { labels: data.materias.map(d=>d.nombre_materia), datasets: [{ label: '% Reprobación', data: data.materias.map(d=>d.reprobacion), backgroundColor: '#952f57', borderRadius: 4 }] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        charts['cEdad'] = new Chart(document.getElementById('cEdad').getContext('2d'), {
          type: 'bar',
          data: { labels: data.edades.map(d=>d.rango_edad), datasets: [{ label: '% Aprobación', data: data.edades.map(d=>d.aprobacion), backgroundColor: '#62152d', borderRadius: 4 }] },
          options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

      } catch(err) { console.error("Error OLAP: ", err); }
    }

    function clearGeoDrill() {
      document.getElementById('filtroRegion').value = "";
      document.getElementById('filtroEscuela').value = "";
      fetchOLAP();
    }

    window.onload = () => setTimeout(fetchOLAP, 200);
  </script>
</body>
</html> 