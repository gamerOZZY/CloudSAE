# ETL OLTP → OLAP con Azure Functions (Python 3.12)

## Objetivo

Este proceso ETL tiene como finalidad extraer información del sistema transaccional (**OLTP**) almacenado en Azure Database for MySQL Flexible Server, transformarla a un modelo dimensional y cargarla en una base de datos analítica (**OLAP**) para facilitar consultas, reportes y análisis de rendimiento académico.

La ejecución se realiza automáticamente mediante una **Azure Function con Timer Trigger**, programada para ejecutarse cada 7 días.

---

# Arquitectura General

```text
Azure Function (Timer Trigger)
            │
            ▼
     MySQL OLTP
            │
       Extracción
            │
            ▼
    Transformación
            │
            ▼
      MySQL OLAP
```

---

# Flujo General del ETL

El proceso sigue las siguientes etapas:

```text
1. Cargar Dim_Alumno
2. Cargar Dim_Ubicacion
3. Cargar Dim_Materia
4. Cargar Dim_Tiempo
5. Construir catálogos SK
6. Cargar Fact_Rendimiento
```

Este orden es obligatorio debido a que la tabla de hechos depende de las claves sustitutas generadas en las dimensiones.

---

# Dimensión Alumno

## Objetivo

Representar a cada alumno como una dimensión independiente dentro del Data Warehouse.

---

## Extracción

```sql
SELECT
    id_persona,
    boleta,
    edad
FROM Alumno;
```

---

## Transformación

Se calcula un rango de edad para facilitar análisis estadísticos.

Reglas:

| Edad | Rango |
|--------|--------|
| <16 | Menor de 16 |
| 16-18 | 16-18 |
| 19-21 | 19-21 |
| >21 | Mayor de 21 |

Ejemplo:

```text
Edad: 17
Resultado: 16-18
```

---

## Carga

```sql
INSERT INTO Dim_Alumno
(
    id_persona_oltp,
    boleta,
    edad,
    rango_edad
)
VALUES (...)

ON DUPLICATE KEY UPDATE
...
```

---

# Dimensión Ubicación

## Objetivo

Relacionar cada alumno con la escuela y región geográfica a la que pertenece.

---

## Extracción

```sql
SELECT DISTINCT
    e.id_escuela,
    e.nombre,
    rg.nombre
FROM Escuela e
JOIN Region_Geografica rg
    ON rg.id_region = e.id_region;
```

---

## Transformación

No requiere transformaciones complejas.

Simplemente se normaliza la información para la dimensión.

---

## Carga

```sql
INSERT INTO Dim_Ubicacion
(
    id_escuela_oltp,
    nombre_escuela,
    nombre_region
)
VALUES (...)
```

---

# Dimensión Materia

## Objetivo

Almacenar el catálogo de materias.

---

## Extracción

```sql
SELECT
    id_materia,
    nombre
FROM Materia;
```

---

## Transformación

No requiere transformaciones.

---

## Carga

```sql
INSERT INTO Dim_Materia
(
    id_materia_oltp,
    nombre_materia
)
VALUES (...)
```

---

# Dimensión Tiempo

## Objetivo

Permitir análisis temporales por:

- Día
- Mes
- Trimestre
- Semestre académico
- Año

---

## Extracción

Se obtienen todas las fechas utilizadas en las inscripciones.

```sql
SELECT DISTINCT fecha_inscripcion
FROM Tiene_Inscrita

UNION

SELECT DISTINCT fecha_finalizacion
FROM Tiene_Inscrita
WHERE fecha_finalizacion IS NOT NULL;
```

---

## Transformación

A partir de cada fecha se calculan:

| Campo | Ejemplo |
|---------|---------|
| día | 15 |
| mes | 6 |
| nombre_mes | June |
| trimestre | 2 |
| semestre_academico | 1 |
| año | 2025 |

---

## Carga

```sql
INSERT INTO Dim_Tiempo (...)
VALUES (...)
```

---

# Construcción de Claves Sustitutas (SK)

## Objetivo

La tabla de hechos no almacena identificadores del OLTP.

En su lugar utiliza claves sustitutas:

```text
sk_alumno
sk_ubicacion
sk_materia
sk_tiempo
```

Por esta razón, después de cargar las dimensiones se construyen diccionarios en memoria.

Ejemplo:

```python
alumnos = {
    1001: 1,
    1002: 2
}
```

Donde:

```text
id_persona_oltp -> sk_alumno
```

---

# Tabla de Hechos Fact_Rendimiento

## Objetivo

Representar el rendimiento académico de cada alumno.

---

## Extracción

```sql
SELECT

    ti.id_alumno,
    ti.id_materia,

    e.id_escuela,

    ti.fecha_inscripcion,
    ti.fecha_finalizacion,

    ti.parcial_1,
    ti.parcial_2,
    ti.parcial_3,

    ti.final

FROM Tiene_Inscrita ti

JOIN Alumno a
    ON a.id_persona = ti.id_alumno

JOIN Persona p
    ON p.id_persona = a.id_persona

JOIN Escuela e
    ON e.id_escuela = p.id_escuela;
```

---

## Transformación

Se reemplazan los identificadores OLTP por claves sustitutas.

Ejemplo:

```text
id_alumno = 10
↓
sk_alumno = 45
```

También se calcula el indicador:

```text
aprobado
```

Regla:

```text
final >= 6 → 1
final < 6 → 0
```

---

## Ejemplo

Registro OLTP:

```text
Alumno: 1001
Materia: Matemáticas
Final: 8.5
```

Registro OLAP:

```text
sk_alumno = 5
sk_materia = 3

calificacion_final = 8.5
aprobado = 1
```

---

## Carga

```sql
INSERT INTO Fact_Rendimiento
(
    sk_alumno,
    sk_ubicacion,
    sk_materia,

    sk_tiempo_inscripcion,
    sk_tiempo_finalizacion,

    parcial_1,
    parcial_2,
    parcial_3,

    calificacion_final,
    aprobado
)
VALUES (...)
```

---

# Prevención de Duplicados

Para evitar duplicados se utilizan restricciones únicas.

Dimensiones:

```sql
ALTER TABLE Dim_Alumno
ADD UNIQUE(id_persona_oltp);

ALTER TABLE Dim_Ubicacion
ADD UNIQUE(id_escuela_oltp);

ALTER TABLE Dim_Materia
ADD UNIQUE(id_materia_oltp);
```

Tabla de hechos:

```sql
ALTER TABLE Fact_Rendimiento
ADD UNIQUE
(
    sk_alumno,
    sk_materia,
    sk_tiempo_inscripcion
);
```

---

# Estrategia de Carga

Actualmente el modelo OLTP no posee columnas:

```text
updated_at
created_at
last_modified
```

Por esta razón no es posible identificar registros modificados desde la última ejecución.

---

## Estrategia utilizada

### Dimensiones

```text
Full UPSERT
```

Cada ejecución:

- Lee todos los registros.
- Inserta nuevos.
- Actualiza existentes.

---

### Tabla de hechos

```text
Full UPSERT
```

Cada ejecución:

- Lee todas las inscripciones.
- Inserta nuevas.
- Actualiza modificaciones.

---

# Optimización de Memoria

Para minimizar el consumo de memoria en Azure Functions se procesan registros por lotes.

Ejemplo:

```python
while True:

    batch = cursor.fetchmany(1000)

    if not batch:
        break

    process_batch(batch)
```

Beneficios:

- Memoria constante.
- Escalable.
- Compatible con Consumption Plan.

---

# Programación de la Ejecución

La Azure Function se ejecuta cada 7 días.

Expresión NCRONTAB:

```text
0 0 0 */7 * *
```

Interpretación:

| Campo | Valor |
|---------|---------|
| Segundo | 0 |
| Minuto | 0 |
| Hora | 0 |
| Día | Cada 7 días |
| Mes | Todos |
| Semana | Todos |

---

# Beneficios de la Solución

- Bajo costo operativo.
- Compatible con Azure Functions Consumption.
- Compatible con Azure Database for MySQL Flexible Server.
- Evita duplicados mediante UPSERT.
- Modelo dimensional optimizado para análisis.
- Bajo consumo de memoria mediante procesamiento por lotes.
- Escalable para crecimiento futuro.
- Fácil evolución hacia ETL incremental agregando columnas `updated_at`.

---

# Mejoras Futuras a considerar

Agregar en OLTP:

```sql
updated_at TIMESTAMP
DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP
```

en:

- Alumno
- Materia
- Escuela
- Tiene_Inscrita

Esto permitirá:

- ETL incremental real.
- Menor tiempo de ejecución.
- Menor costo computacional.
- Menor tráfico entre bases de datos.

---