# Proyecto SAES

## Descripción General del Sistema

El proyecto SAES es una solución integral diseñada para la gestión operativa y el análisis histórico del rendimiento académico. La arquitectura está completamente alojada en Microsoft Azure, dividiendo sus responsabilidades en un entorno transaccional estructurado, un proceso de integración automatizado y un almacén de datos multidimensional.

El objetivo del sistema no es solo garantizar la integridad operativa diaria mediante controles estrictos de seguridad y reglas de negocio automatizadas, sino también habilitar la toma de decisiones basada en datos mediante herramientas de Business Intelligence (BI) y Machine Learning.

---

## Arquitectura de Infraestructura en la Nube

El sistema utiliza un modelo multicapa dentro de una Azure Virtual Network (VNet), garantizando alta disponibilidad, escalabilidad independiente y tolerancia a fallos.

* **Balanceo y Procesamiento:** Un Azure Load Balancer distribuye el tráfico entre múltiples Máquinas Virtuales distribuidas en diferentes zonas de disponibilidad.
* **Almacenamiento de Datos:** Emplea Azure Database for MySQL Flexible Server de manera separada: una instancia para el entorno OLTP y otra para el OLAP.
* **Gestión de Archivos:** Los recursos multimedia y documentos de los usuarios se almacenan en Azure Blob Storage, desacoplando esta carga de la base de datos principal.

---

## Entorno Transaccional (OLTP)

El núcleo operativo está desarrollado en MySQL 8, enfocado en la normalización avanzada, la seguridad granular y la trazabilidad.

* **Gestión de Identidades (Herencia):** Utiliza una superclase `Persona` para centralizar autenticaciones y adscripciones geográficas, mientras que los roles específicos (Directivo, Gestor, Profesor, Alumno) heredan de esta mediante relaciones 1:1.
* **Control de Acceso Basado en Roles (RBAC):** El motor SQL impone el principio de mínimo privilegio; por ejemplo, solo el rol de Profesor puede actualizar calificaciones parciales, mientras que el Alumno tiene acceso de solo lectura.
* **Automatización y Auditoría:** Los promedios finales se calculan automáticamente mediante Triggers al momento de insertar calificaciones, y una tabla de auditoría (`Gestor_Auditoria`) registra de forma inmutable cada modificación administrativa.

---

## Proceso de Integración (ETL)

Para mantener sincronizados los entornos sin afectar el rendimiento de la aplicación principal, se diseñó un canal de datos automatizado y de bajo costo.

* **Tecnología y Ejecución:** Construido en Python 3.12 sobre Azure Functions, se ejecuta automáticamente cada 7 días utilizando un Timer Trigger con expresión NCRONTAB.
* **Flujo de Carga (Full UPSERT):** El proceso extrae datos, genera rangos y claves sustitutas en memoria, y carga la información utilizando un enfoque de inserción/actualización masiva para evitar duplicados.
* **Optimización:** Utiliza procesamiento por lotes para mantener un consumo de memoria constante, haciéndolo altamente escalable y compatible con el plan de consumo de Azure.

---

## Almacén de Datos Analítico (OLAP)

El entorno analítico consolida la información transaccional transformándola en conocimiento útil para la institución académica.

* **Esquema en Estrella:** Su diseño centraliza las métricas en la tabla `Fact_Rendimiento`, la cual documenta cada inscripción y calificación.
* **Dimensiones Analíticas:** Se compone de las dimensiones `Dim_Alumno` (incluye rangos de edad), `Dim_Ubicacion`, `Dim_Materia` y `Dim_Tiempo`, permitiendo segmentar los datos desde múltiples perspectivas.
* **Capacidades Estratégicas:** Este modelo está optimizado para conectarse directamente a plataformas como Power BI o Tableau, facilitando la creación de tableros directivos, el análisis geográfico del desempeño y la detección temprana de riesgos académicos.