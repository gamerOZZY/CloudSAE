# Modelo Analítico OLAP SAES

## Descripción General

Con el objetivo de transformar la información operativa del sistema SAES en conocimiento útil para la toma de decisiones, se diseñó un modelo multidimensional orientado al análisis histórico del rendimiento académico. La arquitectura se implementa mediante un esquema en estrella (*Star Schema*), optimizado para consultas analíticas de alto rendimiento y para su integración con herramientas de Business Intelligence, minería de datos y Machine Learning.

A diferencia del modelo OLTP, cuya finalidad principal es garantizar la integridad transaccional y la operación diaria del sistema, el modelo OLAP se enfoca en consolidar información histórica, reducir la complejidad de las consultas y facilitar la generación de indicadores institucionales.

El diseño utiliza dimensiones descriptivas y una tabla de hechos central, permitiendo analizar el desempeño académico desde múltiples perspectivas, incluyendo ubicación geográfica, materia, características del alumno y temporalidad de los eventos académicos.

---

## Componentes Utilizados

### 1. Dimensiones Analíticas

**Idea Central:** Proporcionar contextos de análisis que permitan segmentar y agrupar la información almacenada en la tabla de hechos.

#### Dim_Alumno

Almacena los atributos descriptivos de cada alumno utilizados para el análisis estadístico.

Incluye:

* Identificador del alumno proveniente del sistema OLTP.
* Boleta institucional.
* Edad.
* Rango de edad.

Esta dimensión permite identificar patrones de rendimiento asociados a grupos etarios específicos.

#### Dim_Ubicacion

Centraliza la información geográfica de procedencia de los registros académicos.

Incluye:

* Escuela de adscripción.
* Región geográfica asociada.

Su propósito es habilitar comparaciones entre escuelas, regiones y zonas académicas.

#### Dim_Materia

Representa el catálogo analítico de asignaturas.

Incluye:

* Identificador de materia del sistema transaccional.
* Nombre de la asignatura.

Permite analizar tendencias de aprobación, dificultad académica y comportamiento histórico por materia.

#### Dim_Tiempo

Proporciona la estructura temporal utilizada por las consultas analíticas.

Incluye:

* Fecha completa.
* Día.
* Mes.
* Nombre del mes.
* Trimestre.
* Semestre académico.
* Año.

La dimensión de tiempo constituye uno de los componentes fundamentales del modelo, permitiendo análisis históricos y comparativos entre periodos.

---

### 2. Tabla de Hechos

**Idea Central:** Concentrar las métricas cuantitativas que serán utilizadas para los procesos de análisis y generación de indicadores.

#### Fact_Rendimiento

Representa el núcleo del almacén de datos.

El grano de la tabla corresponde a:

> Una inscripción de un alumno en una materia durante un periodo académico específico.

La tabla almacena:

* Calificación parcial 1.
* Calificación parcial 2.
* Calificación parcial 3.
* Calificación final.
* Indicador de aprobación.
* Fecha de inscripción.
* Fecha de finalización.

Asimismo, mantiene referencias a todas las dimensiones analíticas mediante claves sustitutas (*Surrogate Keys*).

---

## Esquema Multidimensional (Star Schema)

El modelo sigue una arquitectura de estrella compuesta por una tabla de hechos central y cuatro dimensiones principales.

### Dimensiones

* Dim_Alumno
* Dim_Ubicacion
* Dim_Materia
* Dim_Tiempo

### Tabla de Hechos

* Fact_Rendimiento

La estructura permite realizar operaciones de:

* Slice.
* Dice.
* Drill-Down.
* Roll-Up.

sobre cualquiera de las dimensiones disponibles.

---

## Flujo de Alimentación (ETL)

Para poblar el almacén de datos se implementa un proceso ETL (Extract, Transform, Load).

### Flujo

1. Se extraen los registros históricos desde el modelo OLTP.
2. Se transforman los datos para adaptarlos a las dimensiones analíticas.
3. Se generan las claves sustitutas de cada dimensión.
4. Se construyen los registros de la tabla de hechos.
5. La información consolidada es cargada en el Data Warehouse.

Durante esta etapa se generan atributos derivados como:

* Rango de edad.
* Indicador de aprobación.
* Semestre académico.
* Trimestre.

Esto reduce el costo computacional de las consultas analíticas posteriores.

---

## Capacidades Analíticas

El modelo permite responder preguntas estratégicas relacionadas con el desempeño institucional.

### Análisis Académico

* Promedio general por materia.
* Tasa de aprobación por asignatura.
* Distribución de calificaciones.
* Evolución de los parciales durante el semestre.

### Análisis Temporal

* Rendimiento por mes.
* Rendimiento por semestre académico.
* Comparación anual de indicadores.
* Tendencias históricas de aprobación.

### Análisis Geográfico

* Comparación de desempeño entre escuelas.
* Comparación entre regiones geográficas.
* Identificación de zonas con mayores índices de aprobación.

### Análisis Demográfico

* Rendimiento por grupo de edad.
* Distribución de alumnos aprobados y reprobados.
* Tendencias académicas por segmento poblacional.

---

## Integración con Business Intelligence y Ciencia de Datos

La estructura dimensional fue diseñada para integrarse directamente con plataformas analíticas como:

* Power BI.
* Tableau.
* Apache Superset.
* Herramientas de Machine Learning basadas en Python.

La organización en dimensiones y hechos facilita la construcción de:

* Dashboards ejecutivos.
* Reportes institucionales.
* Modelos predictivos de rendimiento.
* Sistemas de detección temprana de riesgo académico.

---

## Justificación del Diseño

El modelo multidimensional propuesto busca maximizar la capacidad analítica de la información generada por el sistema SAES.

La adopción de un esquema en estrella simplifica considerablemente las consultas complejas, disminuye la cantidad de uniones necesarias y mejora el desempeño de las herramientas de análisis de datos.

Asimismo, la incorporación de dimensiones específicas para alumno, ubicación, materia y tiempo permite explorar el rendimiento académico desde múltiples perspectivas sin afectar la operación transaccional del sistema principal.

Finalmente, la inclusión de métricas históricas, fechas de inscripción y finalización, así como el almacenamiento de las calificaciones parciales y finales, garantiza la disponibilidad de información suficiente para la generación de indicadores institucionales, análisis estadísticos avanzados y futuros modelos de inteligencia artificial orientados a la educación.
