import logging
import datetime

from shared.mysql_cconnections import get_source_connection, get_target_connection
from shared.watermark import get_last_watermark, update_watermark

logger = logging.getLogger(__name__)


def _rango_edad(edad: int) -> str:
	if edad is None:
		return 'Desconocido'
	if edad < 16:
		return 'Menor de 16'
	if 16 <= edad <= 18:
		return '16-18'
	if 19 <= edad <= 21:
		return '19-21'
	return 'Mayor de 21'


def run_etl():
	"""
	Ejecuta el ETL desde el OLTP (source) hacia el OLAP (target).
	Realiza upserts en dimensiones y carga hechos incrementalmente usando watermark.
	"""
	source_conn = get_source_connection()
	target_conn = get_target_connection()

	try:
		last_watermark = get_last_watermark(target_conn)
		if last_watermark is None:
			last_watermark = datetime.datetime(2000, 1, 1)

		logger.info("ETL: last_watermark=%s", last_watermark)

		with source_conn.cursor() as src_cur, target_conn.cursor() as tgt_cur:
			# Dim_Alumno
			src_cur.execute("""
				SELECT DISTINCT a.id_persona, a.boleta, a.edad
				FROM Alumno a
				JOIN Tiene_Inscrita ti ON a.id_persona = ti.id_alumno
				WHERE ti.fecha_inscripcion > %s
				   OR (ti.fecha_finalizacion IS NOT NULL AND ti.fecha_finalizacion > %s)
			""", (last_watermark, last_watermark))
			alumnos = src_cur.fetchall()
			for a_row in alumnos:
				rango = _rango_edad(a_row.get('edad'))
				tgt_cur.execute("""
					INSERT INTO Dim_Alumno (id_persona_oltp, boleta, edad, rango_edad)
					VALUES (%s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE
						boleta=VALUES(boleta),
						edad=VALUES(edad),
						rango_edad=VALUES(rango_edad)
				""", (a_row['id_persona'], a_row.get('boleta'), a_row.get('edad'), rango))
			target_conn.commit()

			# Dim_Ubicacion
			src_cur.execute("""
				SELECT DISTINCT e.id_escuela, e.nombre AS nombre_escuela, rg.nombre AS nombre_region
				FROM Escuela e
				JOIN Region_Geografica rg ON rg.id_region = e.id_region
				JOIN Persona p ON p.id_escuela = e.id_escuela
				JOIN Alumno a ON a.id_persona = p.id_persona
				JOIN Tiene_Inscrita ti ON ti.id_alumno = a.id_persona
				WHERE ti.fecha_inscripcion > %s
				   OR (ti.fecha_finalizacion IS NOT NULL AND ti.fecha_finalizacion > %s)
			""", (last_watermark, last_watermark))
			ubicaciones = src_cur.fetchall()
			for u in ubicaciones:
				tgt_cur.execute("""
					INSERT INTO Dim_Ubicacion (id_escuela_oltp, nombre_escuela, nombre_region)
					VALUES (%s, %s, %s)
					ON DUPLICATE KEY UPDATE
						nombre_escuela=VALUES(nombre_escuela),
						nombre_region=VALUES(nombre_region)
				""", (u['id_escuela'], u['nombre_escuela'], u['nombre_region']))
			target_conn.commit()

			# Dim_Materia
			src_cur.execute("""
				SELECT DISTINCT m.id_materia, m.nombre
				FROM Materia m
				JOIN Tiene_Inscrita ti ON ti.id_materia = m.id_materia
				WHERE ti.fecha_inscripcion > %s
				   OR (ti.fecha_finalizacion IS NOT NULL AND ti.fecha_finalizacion > %s)
			""", (last_watermark, last_watermark))
			materias = src_cur.fetchall()
			for m in materias:
				tgt_cur.execute("""
					INSERT INTO Dim_Materia (id_materia_oltp, nombre_materia)
					VALUES (%s, %s)
					ON DUPLICATE KEY UPDATE
						nombre_materia=VALUES(nombre_materia)
				""", (m['id_materia'], m['nombre']))
			target_conn.commit()

			# Dim_Tiempo - inscripciones
			src_cur.execute("""
				SELECT DISTINCT fecha_inscripcion,
					DAY(fecha_inscripcion) AS dia,
					MONTH(fecha_inscripcion) AS mes,
					MONTHNAME(fecha_inscripcion) AS nombre_mes,
					QUARTER(fecha_inscripcion) AS trimestre,
					CASE WHEN MONTH(fecha_inscripcion) <= 6 THEN 1 ELSE 2 END AS semestre_academico,
					YEAR(fecha_inscripcion) AS anio
				FROM Tiene_Inscrita
				WHERE fecha_inscripcion > %s
			""", (last_watermark,))
			tiempos_ins = src_cur.fetchall()
			for t in tiempos_ins:
				tgt_cur.execute("""
					INSERT IGNORE INTO Dim_Tiempo (fecha, dia, mes, nombre_mes, trimestre, semestre_academico, anio)
					VALUES (%s, %s, %s, %s, %s, %s, %s)
				""", (t['fecha_inscripcion'], t['dia'], t['mes'], t['nombre_mes'], t['trimestre'], t['semestre_academico'], t['anio']))
			target_conn.commit()

			# Dim_Tiempo - finalizaciones
			src_cur.execute("""
				SELECT DISTINCT fecha_finalizacion,
					DAY(fecha_finalizacion) AS dia,
					MONTH(fecha_finalizacion) AS mes,
					MONTHNAME(fecha_finalizacion) AS nombre_mes,
					QUARTER(fecha_finalizacion) AS trimestre,
					CASE WHEN MONTH(fecha_finalizacion) <= 6 THEN 1 ELSE 2 END AS semestre_academico,
					YEAR(fecha_finalizacion) AS anio
				FROM Tiene_Inscrita
				WHERE fecha_finalizacion IS NOT NULL
				  AND fecha_finalizacion > %s
			""", (last_watermark,))
			tiempos_fin = src_cur.fetchall()
			for t in tiempos_fin:
				tgt_cur.execute("""
					INSERT IGNORE INTO Dim_Tiempo (fecha, dia, mes, nombre_mes, trimestre, semestre_academico, anio)
					VALUES (%s, %s, %s, %s, %s, %s, %s)
				""", (t['fecha_finalizacion'], t['dia'], t['mes'], t['nombre_mes'], t['trimestre'], t['semestre_academico'], t['anio']))
			target_conn.commit()

			# Fact_Rendimiento
			src_cur.execute("""
				SELECT ti.id_alumno, ti.id_materia, ti.fecha_inscripcion, ti.fecha_finalizacion,
					   ti.parcial_1, ti.parcial_2, ti.parcial_3, ti.final,
					   p.id_escuela
				FROM Tiene_Inscrita ti
				JOIN Alumno a ON a.id_persona = ti.id_alumno
				JOIN Persona p ON p.id_persona = a.id_persona
				WHERE ti.fecha_inscripcion > %s
				   OR (ti.fecha_finalizacion IS NOT NULL AND ti.fecha_finalizacion > %s)
			""", (last_watermark, last_watermark))
			facts = src_cur.fetchall()
			for f in facts:
				tgt_cur.execute("SELECT sk_alumno FROM Dim_Alumno WHERE id_persona_oltp = %s", (f['id_alumno'],))
				r = tgt_cur.fetchone()
				if not r:
					logger.warning("SK alumno not found: %s", f['id_alumno'])
					continue
				sk_alumno = r['sk_alumno']

				tgt_cur.execute("SELECT sk_ubicacion FROM Dim_Ubicacion WHERE id_escuela_oltp = %s", (f['id_escuela'],))
				r = tgt_cur.fetchone()
				if not r:
					logger.warning("SK ubicacion not found for escuela %s", f.get('id_escuela'))
					continue
				sk_ubicacion = r['sk_ubicacion']

				tgt_cur.execute("SELECT sk_materia FROM Dim_Materia WHERE id_materia_oltp = %s", (f['id_materia'],))
				r = tgt_cur.fetchone()
				if not r:
					logger.warning("SK materia not found: %s", f['id_materia'])
					continue
				sk_materia = r['sk_materia']

				tgt_cur.execute("SELECT sk_tiempo FROM Dim_Tiempo WHERE fecha = %s", (f['fecha_inscripcion'],))
				r = tgt_cur.fetchone()
				if not r:
					logger.warning("SK tiempo (inscripcion) not found for fecha %s", f['fecha_inscripcion'])
					continue
				sk_tiempo_ins = r['sk_tiempo']

				sk_tiempo_fin = None
				if f['fecha_finalizacion'] is not None:
					tgt_cur.execute("SELECT sk_tiempo FROM Dim_Tiempo WHERE fecha = %s", (f['fecha_finalizacion'],))
					r = tgt_cur.fetchone()
					if r:
						sk_tiempo_fin = r['sk_tiempo']

				aprobado = 1 if (f['final'] is not None and f['final'] >= 6) else 0

				tgt_cur.execute("""
					INSERT INTO Fact_Rendimiento (
						sk_alumno, sk_ubicacion, sk_materia, sk_tiempo_inscripcion, sk_tiempo_finalizacion,
						parcial_1, parcial_2, parcial_3, calificacion_final, aprobado
					) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE
						parcial_1=VALUES(parcial_1),
						parcial_2=VALUES(parcial_2),
						parcial_3=VALUES(parcial_3),
						calificacion_final=VALUES(calificacion_final),
						aprobado=VALUES(aprobado)
				""", (sk_alumno, sk_ubicacion, sk_materia, sk_tiempo_ins, sk_tiempo_fin,
					  f.get('parcial_1', 0), f.get('parcial_2', 0), f.get('parcial_3', 0), f.get('final', 0), aprobado))
			target_conn.commit()

			new_watermark = datetime.datetime.utcnow()
			update_watermark(target_conn, new_watermark)

	finally:
		try:
			source_conn.close()
		except Exception:
			pass
		try:
			target_conn.close()
		except Exception:
			pass

import logging
import datetime
from typing import Optional

from shared.mysql_cconnections import get_source_connection, get_target_connection
from shared.watermark import get_last_watermark, update_watermark


def _rango_edad(edad: Optional[int]) -> str:
	if edad is None:
		return "Desconocido"
	try:
		e = int(edad)
	except Exception:
		return "Desconocido"
	if e < 16:
		return "Menor de 16"
	if 16 <= e <= 18:
		return "16-18"
	if 19 <= e <= 21:
		return "19-21"
	return "Mayor de 21"


def run_etl():
	"""
	ETL que extrae desde la base OLTP (servidor fuente) y carga el OLAP (servidor destino).
	Diseño: extraer -> transformar en Python -> upsert en destino.
	"""
	logging.info("Iniciando ETL (fuente y destino en servidores distintos)")
	src_conn = get_source_connection()
	tgt_conn = get_target_connection()
	try:
		_upsert_dim_alumno(src_conn, tgt_conn)
		_upsert_dim_ubicacion(src_conn, tgt_conn)
		_upsert_dim_materia(src_conn, tgt_conn)
		_upsert_dim_tiempo(src_conn, tgt_conn)
		_load_fact_rendimiento(src_conn, tgt_conn)
		# actualizar watermark en destino (marca de tiempo actual)
		update_watermark(tgt_conn, datetime.datetime.now())
		logging.info("ETL finalizado correctamente")
	finally:
		try:
			src_conn.close()
		except Exception:
			pass
		try:
			tgt_conn.close()
		except Exception:
			pass


def _upsert_dim_alumno(src_conn, tgt_conn) -> None:
	with src_conn.cursor() as sc, tgt_conn.cursor() as tc:
		sc.execute("SELECT id_persona, boleta, edad FROM Alumno")
		rows = sc.fetchall()
		if not rows:
			return
		params = []
		for r in rows:
			edad = r.get("edad")
			params.append((
				r["id_persona"],
				r.get("boleta"),
				edad,
				_rango_edad(edad)
			))
		sql = """
		INSERT INTO Dim_Alumno (id_persona_oltp, boleta, edad, rango_edad)
		VALUES (%s, %s, %s, %s)
		ON DUPLICATE KEY UPDATE
			boleta = VALUES(boleta),
			edad = VALUES(edad),
			rango_edad = VALUES(rango_edad)
		"""
		tc.executemany(sql, params)
	tgt_conn.commit()


def _upsert_dim_ubicacion(src_conn, tgt_conn) -> None:
	with src_conn.cursor() as sc, tgt_conn.cursor() as tc:
		sc.execute("""
		SELECT DISTINCT
			e.id_escuela AS id_escuela,
			e.nombre AS nombre_escuela,
			rg.nombre AS nombre_region
		FROM Escuela e
		JOIN Region_Geografica rg ON rg.id_region = e.id_region
		""")
		rows = sc.fetchall()
		if not rows:
			return
		params = [(r["id_escuela"], r["nombre_escuela"], r["nombre_region"]) for r in rows]
		sql = """
		INSERT INTO Dim_Ubicacion (id_escuela_oltp, nombre_escuela, nombre_region)
		VALUES (%s, %s, %s)
		ON DUPLICATE KEY UPDATE
			nombre_escuela = VALUES(nombre_escuela),
			nombre_region = VALUES(nombre_region)
		"""
		tc.executemany(sql, params)
	tgt_conn.commit()


def _upsert_dim_materia(src_conn, tgt_conn) -> None:
	with src_conn.cursor() as sc, tgt_conn.cursor() as tc:
		sc.execute("SELECT id_materia, nombre FROM Materia")
		rows = sc.fetchall()
		if not rows:
			return
		params = [(r["id_materia"], r["nombre"]) for r in rows]
		sql = """
		INSERT INTO Dim_Materia (id_materia_oltp, nombre_materia)
		VALUES (%s, %s)
		ON DUPLICATE KEY UPDATE
			nombre_materia = VALUES(nombre_materia)
		"""
		tc.executemany(sql, params)
	tgt_conn.commit()


def _upsert_dim_tiempo(src_conn, tgt_conn) -> None:
	# Tomamos ambas fechas (inscripción y finalización) desde la tabla de relaciones
	dates = set()
	with src_conn.cursor() as sc:
		sc.execute("SELECT DISTINCT fecha_inscripcion AS fecha FROM Tiene_Inscrita WHERE fecha_inscripcion IS NOT NULL")
		for r in sc.fetchall():
			f = r.get("fecha")
			if f:
				if isinstance(f, datetime.datetime):
					dates.add(f.date())
				else:
					dates.add(f)
		sc.execute("SELECT DISTINCT fecha_finalizacion AS fecha FROM Tiene_Inscrita WHERE fecha_finalizacion IS NOT NULL")
		for r in sc.fetchall():
			f = r.get("fecha")
			if f:
				if isinstance(f, datetime.datetime):
					dates.add(f.date())
				else:
					dates.add(f)

	if not dates:
		return

	params = []
	for fecha in sorted(dates):
		dia = fecha.day
		mes = fecha.month
		nombre_mes = fecha.strftime("%B")
		trimestre = ((mes - 1) // 3) + 1
		semestre = 1 if mes <= 6 else 2
		anio = fecha.year
		params.append((fecha, dia, mes, nombre_mes, trimestre, semestre, anio))

	with tgt_conn.cursor() as tc:
		sql = """
		INSERT INTO Dim_Tiempo (fecha, dia, mes, nombre_mes, trimestre, semestre_academico, anio)
		VALUES (%s, %s, %s, %s, %s, %s, %s)
		ON DUPLICATE KEY UPDATE
			dia = VALUES(dia),
			mes = VALUES(mes),
			nombre_mes = VALUES(nombre_mes),
			trimestre = VALUES(trimestre),
			semestre_academico = VALUES(semestre_academico),
			anio = VALUES(anio)
		"""
		tc.executemany(sql, params)
	tgt_conn.commit()


def _load_fact_rendimiento(src_conn, tgt_conn) -> None:
	with tgt_conn.cursor() as tc:
		tc.execute("SELECT sk_alumno, id_persona_oltp FROM Dim_Alumno")
		alumno_map = {r["id_persona_oltp"]: r["sk_alumno"] for r in tc.fetchall()}
		tc.execute("SELECT sk_ubicacion, id_escuela_oltp FROM Dim_Ubicacion")
		ubic_map = {r["id_escuela_oltp"]: r["sk_ubicacion"] for r in tc.fetchall()}
		tc.execute("SELECT sk_materia, id_materia_oltp FROM Dim_Materia")
		materia_map = {r["id_materia_oltp"]: r["sk_materia"] for r in tc.fetchall()}
		tc.execute("SELECT sk_tiempo, fecha FROM Dim_Tiempo")
		tiempo_map = {}
		for r in tc.fetchall():
			f = r.get("fecha")
			if isinstance(f, datetime.datetime):
				key = f.date()
			else:
				key = f
			tiempo_map[key] = r["sk_tiempo"]

	with src_conn.cursor() as sc:
		sc.execute("""
		SELECT
			ti.id_alumno,
			p.id_escuela,
			ti.id_materia,
			ti.fecha_inscripcion,
			ti.fecha_finalizacion,
			ti.parcial_1,
			ti.parcial_2,
			ti.parcial_3,
			ti.final
		FROM Tiene_Inscrita ti
		JOIN Alumno a ON a.id_persona = ti.id_alumno
		JOIN Persona p ON p.id_persona = a.id_persona
		JOIN Escuela e ON e.id_escuela = p.id_escuela
		""")
		rows = sc.fetchall()

	params = []
	for r in rows:
		id_alumno = r["id_alumno"]
		id_escuela = r["id_escuela"]
		id_materia = r["id_materia"]
		fi = r.get("fecha_inscripcion")
		ff = r.get("fecha_finalizacion")
		fi_key = fi.date() if isinstance(fi, datetime.datetime) else fi
		ff_key = None
		if ff is not None:
			ff_key = ff.date() if isinstance(ff, datetime.datetime) else ff

		sk_al = alumno_map.get(id_alumno)
		sk_ub = ubic_map.get(id_escuela)
		sk_ma = materia_map.get(id_materia)
		sk_ti_ins = tiempo_map.get(fi_key)
		sk_ti_fin = tiempo_map.get(ff_key) if ff_key is not None else None

		if None in (sk_al, sk_ub, sk_ma, sk_ti_ins):
			logging.warning("Omitiendo fila de fact por dimensiones faltantes: alumno=%s escuela=%s materia=%s fecha_ins=%s",
							id_alumno, id_escuela, id_materia, fi_key)
			continue

		parcial_1 = r.get("parcial_1") or 0.0
		parcial_2 = r.get("parcial_2") or 0.0
		parcial_3 = r.get("parcial_3") or 0.0
		final = r.get("final") or 0.0
		aprobado = 1 if final >= 6 else 0

		params.append((
			sk_al, sk_ub, sk_ma, sk_ti_ins, sk_ti_fin,
			parcial_1, parcial_2, parcial_3, final, aprobado
		))

	if not params:
		return

	with tgt_conn.cursor() as tc:
		sql = """
		INSERT INTO Fact_Rendimiento (
			sk_alumno, sk_ubicacion, sk_materia,
			sk_tiempo_inscripcion, sk_tiempo_finalizacion,
			parcial_1, parcial_2, parcial_3,
			calificacion_final, aprobado
		) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
		ON DUPLICATE KEY UPDATE
			parcial_1 = VALUES(parcial_1),
			parcial_2 = VALUES(parcial_2),
			parcial_3 = VALUES(parcial_3),
			calificacion_final = VALUES(calificacion_final),
			aprobado = VALUES(aprobado),
			sk_tiempo_finalizacion = VALUES(sk_tiempo_finalizacion)
		"""
		tc.executemany(sql, params)
	tgt_conn.commit()

