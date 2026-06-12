INSERT INTO Dim_Alumno (
    id_persona_oltp,
    boleta,
    edad,
    rango_edad
)
SELECT
    a.id_persona,
    a.boleta,
    a.edad,
    CASE
        WHEN a.edad < 16 THEN 'Menor de 16'
        WHEN a.edad BETWEEN 16 AND 18 THEN '16-18'
        WHEN a.edad BETWEEN 19 AND 21 THEN '19-21'
        ELSE 'Mayor de 21'
    END
FROM Alumno a;

INSERT INTO Dim_Ubicacion (
    id_escuela_oltp,
    nombre_escuela,
    nombre_region
)
SELECT DISTINCT
    e.id_escuela,
    e.nombre,
    rg.nombre
FROM Escuela e
JOIN Region_Geografica rg
    ON rg.id_region = e.id_region;


INSERT INTO Dim_Materia (
    id_materia_oltp,
    nombre_materia
)
SELECT
    id_materia,
    nombre
FROM Materia;

INSERT IGNORE INTO Dim_Tiempo (
    fecha,
    dia,
    mes,
    nombre_mes,
    trimestre,
    semestre_academico,
    anio
)
SELECT DISTINCT
    fecha_inscripcion,
    DAY(fecha_inscripcion),
    MONTH(fecha_inscripcion),
    MONTHNAME(fecha_inscripcion),
    QUARTER(fecha_inscripcion),
    CASE
        WHEN MONTH(fecha_inscripcion) <= 6 THEN 1
        ELSE 2
    END,
    YEAR(fecha_inscripcion)
FROM Tiene_Inscrita;


INSERT IGNORE INTO Dim_Tiempo (
    fecha,
    dia,
    mes,
    nombre_mes,
    trimestre,
    semestre_academico,
    anio
)
SELECT DISTINCT
    fecha_finalizacion,
    DAY(fecha_finalizacion),
    MONTH(fecha_finalizacion),
    MONTHNAME(fecha_finalizacion),
    QUARTER(fecha_finalizacion),
    CASE
        WHEN MONTH(fecha_finalizacion) <= 6 THEN 1
        ELSE 2
    END,
    YEAR(fecha_finalizacion)
FROM Tiene_Inscrita
WHERE fecha_finalizacion IS NOT NULL;



INSERT INTO Fact_Rendimiento (
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
SELECT

    da.sk_alumno,

    du.sk_ubicacion,

    dm.sk_materia,

    dti.sk_tiempo,

    dtf.sk_tiempo,

    ti.parcial_1,
    ti.parcial_2,
    ti.parcial_3,

    ti.final,

    CASE
        WHEN ti.final >= 6 THEN 1
        ELSE 0
    END

FROM Tiene_Inscrita ti

JOIN Alumno a
    ON a.id_persona = ti.id_alumno

JOIN Persona p
    ON p.id_persona = a.id_persona

JOIN Escuela e
    ON e.id_escuela = p.id_escuela

JOIN Region_Geografica rg
    ON rg.id_region = e.id_region

JOIN Dim_Alumno da
    ON da.id_persona_oltp = a.id_persona

JOIN Dim_Ubicacion du
    ON du.id_escuela_oltp = e.id_escuela

JOIN Dim_Materia dm
    ON dm.id_materia_oltp = ti.id_materia

JOIN Dim_Tiempo dti
    ON dti.fecha = ti.fecha_inscripcion

LEFT JOIN Dim_Tiempo dtf
    ON dtf.fecha = ti.fecha_finalizacion;

    