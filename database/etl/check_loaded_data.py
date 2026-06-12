import os
import sys
import json
import logging
from pathlib import Path

script_dir = Path(__file__).resolve().parent
os.chdir(script_dir)
sys.path.insert(0, str(script_dir))

# Cargar variables de entorno desde local.settings.json si existe
settings_file = script_dir / "local.settings.json"
if settings_file.exists():
    try:
        with open(settings_file, "r", encoding="utf-8") as f:
            cfg = json.load(f)
        values = cfg.get("Values", {})
        for k, v in values.items():
            if k == "IsEncrypted":
                continue
            if v is None:
                continue
            os.environ.setdefault(k, str(v))
    except Exception as e:
        logging.warning("No se pudo cargar local.settings.json: %s", e)

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")

try:
    from shared.mysql_cconnections import get_target_connection
except Exception:
    logging.exception("No se pudo importar get_target_connection desde shared.mysql_cconnections")
    raise

QUERIES = [
    ("Dim_Alumno: count", "SELECT COUNT(*) AS cnt FROM Dim_Alumno"),
    ("Dim_Alumno: sample", "SELECT sk_alumno, id_persona_oltp, boleta, edad, rango_edad FROM Dim_Alumno ORDER BY sk_alumno LIMIT 5"),
    ("Dim_Ubicacion: count", "SELECT COUNT(*) AS cnt FROM Dim_Ubicacion"),
    ("Dim_Ubicacion: sample", "SELECT sk_ubicacion, id_escuela_oltp, nombre_escuela, nombre_region FROM Dim_Ubicacion ORDER BY sk_ubicacion LIMIT 5"),
    ("Dim_Materia: count", "SELECT COUNT(*) AS cnt FROM Dim_Materia"),
    ("Dim_Materia: sample", "SELECT sk_materia, id_materia_oltp, nombre_materia FROM Dim_Materia ORDER BY sk_materia LIMIT 5"),
    ("Dim_Tiempo: count", "SELECT COUNT(*) AS cnt FROM Dim_Tiempo"),
    ("Dim_Tiempo: sample", "SELECT sk_tiempo, fecha, dia, mes, anio FROM Dim_Tiempo ORDER BY fecha DESC LIMIT 5"),
    ("Fact_Rendimiento: count", "SELECT COUNT(*) AS cnt FROM Fact_Rendimiento"),
    ("Fact_Rendimiento: sample", "SELECT id_fact, sk_alumno, sk_ubicacion, sk_materia, calificacion_final, aprobado FROM Fact_Rendimiento ORDER BY id_fact DESC LIMIT 5"),
    ("etl_control", "SELECT process_name, last_processed FROM etl_control"),
]


def run_checks():
    conn = get_target_connection()
    try:
        with conn.cursor() as cur:
            for label, q in QUERIES:
                print("--- {} ---".format(label))
                try:
                    cur.execute(q)
                    rows = cur.fetchall()
                    if not rows:
                        print("(sin resultados)")
                    else:
                        # Si es conteo, mostrar el número; sino mostrar filas
                        if isinstance(rows, list) and len(rows) == 1 and 'cnt' in rows[0]:
                            print(rows[0]['cnt'])
                        else:
                            for r in rows:
                                print(r)
                except Exception as e:
                    print(f"Error ejecutando consulta: {e}")
                print()
    finally:
        try:
            conn.close()
        except Exception:
            pass


if __name__ == '__main__':
    print('Comprobando tablas en OLAP...')
    run_checks()
