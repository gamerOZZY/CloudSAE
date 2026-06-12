import os
import sys
import json
import logging
from pathlib import Path

# Asegurar que el paquete `shared` sea importable (estando en database/etl)
script_dir = Path(__file__).resolve().parent
os.chdir(script_dir)
sys.path.insert(0, str(script_dir))

# Cargar variables de entorno desde local.settings.json (si existe)
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
else:
    logging.warning("local.settings.json no encontrado en %s", script_dir)

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")

try:
    from shared.etl_service import run_etl
except Exception:
    logging.exception("Error importando run_etl desde shared.etl_service")
    raise

if __name__ == "__main__":
    logging.info("Ejecutando ETL localmente usando local.settings.json")
    run_etl()
