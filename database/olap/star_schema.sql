-- ==========================================
-- DIMENSIONES
-- ==========================================

CREATE TABLE Dim_Alumno (
    sk_alumno INT AUTO_INCREMENT PRIMARY KEY,
    id_persona_oltp INT NOT NULL,
    boleta VARCHAR(15) NOT NULL,
    edad INT NOT NULL,
    rango_edad VARCHAR(20) NOT NULL
);

CREATE TABLE Dim_Ubicacion (
    sk_ubicacion INT AUTO_INCREMENT PRIMARY KEY,
    id_escuela_oltp INT NOT NULL,
    nombre_escuela VARCHAR(150) NOT NULL,
    nombre_region VARCHAR(100) NOT NULL
);

CREATE TABLE Dim_Materia (
    sk_materia INT AUTO_INCREMENT PRIMARY KEY,
    id_materia_oltp INT NOT NULL,
    nombre_materia VARCHAR(100) NOT NULL
);

CREATE TABLE Dim_Tiempo (
    sk_tiempo INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,

    dia INT NOT NULL,
    mes INT NOT NULL,
    nombre_mes VARCHAR(20) NOT NULL,

    trimestre INT NOT NULL,
    semestre_academico INT NOT NULL,

    anio INT NOT NULL
);

-- ==========================================
-- TABLA DE HECHOS
-- ==========================================

CREATE TABLE Fact_Rendimiento (

    id_fact BIGINT AUTO_INCREMENT PRIMARY KEY,

    sk_alumno INT NOT NULL,
    sk_ubicacion INT NOT NULL,
    sk_materia INT NOT NULL,

    sk_tiempo_inscripcion INT NOT NULL,
    sk_tiempo_finalizacion INT NULL,

    parcial_1 DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    parcial_2 DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    parcial_3 DECIMAL(4,2) NOT NULL DEFAULT 0.00,

    calificacion_final DECIMAL(4,2) NOT NULL,

    aprobado TINYINT(1) NOT NULL,

    FOREIGN KEY (sk_alumno)
        REFERENCES Dim_Alumno(sk_alumno),

    FOREIGN KEY (sk_ubicacion)
        REFERENCES Dim_Ubicacion(sk_ubicacion),

    FOREIGN KEY (sk_materia)
        REFERENCES Dim_Materia(sk_materia),

    FOREIGN KEY (sk_tiempo_inscripcion)
        REFERENCES Dim_Tiempo(sk_tiempo),

    FOREIGN KEY (sk_tiempo_finalizacion)
        REFERENCES Dim_Tiempo(sk_tiempo)
);