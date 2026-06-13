-- ==========================================
-- 1. TABLAS DE INFRAESTRUCTURA GEOGRÁFICA
-- ==========================================
CREATE TABLE Region_Geografica (
    id_region INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE Escuela (
    id_escuela INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    id_region INT NOT NULL,
    FOREIGN KEY (id_region) REFERENCES Region_Geografica(id_region) ON DELETE CASCADE
);

-- ==========================================
-- 2. SUPERCLASE (CON RELACIÓN A ESCUELA)
-- ==========================================
CREATE TABLE Persona (
    id_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(200) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    id_escuela INT NOT NULL,
    FOREIGN KEY (id_escuela) REFERENCES Escuela(id_escuela) ON DELETE CASCADE
);

-- ==========================================
-- 3. SUBCLASES DE ROLES
-- ==========================================
CREATE TABLE Directivo (
    id_persona INT PRIMARY KEY,
    numero_empleado VARCHAR(15) UNIQUE,
    cargo VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
);

CREATE TABLE Gestor (
    id_persona INT PRIMARY KEY,
    numero_empleado VARCHAR(15) UNIQUE,
    correo VARCHAR(150) UNIQUE NOT NULL,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
);

CREATE TABLE Profesor (
    id_persona INT PRIMARY KEY,
    numero_empleado VARCHAR(15) UNIQUE,
    tipo VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
);

CREATE TABLE Alumno (
    id_persona INT PRIMARY KEY,
    boleta VARCHAR(15) UNIQUE NOT NULL,
    edad INT NOT NULL,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
);

-- ==========================================
-- 4. CATÁLOGO ACADÉMICO
-- ==========================================
CREATE TABLE Materia (
    id_materia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

-- ==========================================
-- 5. TABLAS DE RELACIÓN (M:N) CON HISTORIAL OLAP
-- ==========================================
CREATE TABLE Tiene_Inscrita (
    id_alumno INT NOT NULL,
    id_materia INT NOT NULL,
    grado_semestre INT NOT NULL,
    fecha_inscripcion DATE NOT NULL DEFAULT (CURRENT_DATE),
    fecha_finalizacion DATE,
    parcial_1 DECIMAL(4,2) DEFAULT 0.0,
    parcial_2 DECIMAL(4,2) DEFAULT 0.0,
    parcial_3 DECIMAL(4,2) DEFAULT 0.0,
    final DECIMAL(4,2) DEFAULT 0.0,
    PRIMARY KEY (id_alumno, id_materia, fecha_inscripcion),
    FOREIGN KEY (id_alumno) REFERENCES Alumno(id_persona) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES Materia(id_materia) ON DELETE CASCADE
);

CREATE TABLE Profesor_Imparte_Materia (
    id_profesor INT NOT NULL,
    id_materia INT NOT NULL,
    PRIMARY KEY (id_profesor, id_materia),
    FOREIGN KEY (id_profesor) REFERENCES Profesor(id_persona) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES Materia(id_materia) ON DELETE CASCADE
);

-- ==========================================
-- 6. AUDITORÍA 
-- ==========================================
CREATE TABLE Gestor_Auditoria (
    id_operacion INT AUTO_INCREMENT PRIMARY KEY,
    id_gestor INT NOT NULL,
    entidad VARCHAR(20) NOT NULL CHECK (entidad IN ('ALUMNO', 'PROFESOR', 'MATERIA')),
    id_entidad INT NOT NULL,
    accion VARCHAR(50) NOT NULL CHECK (accion IN ('ALTA', 'BAJA', 'MODIFICAR_DATOS', 'ASIGNAR_MATERIA')),
    fecha_operacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_gestor) REFERENCES Gestor(id_persona) ON DELETE CASCADE
);

CREATE TEMPORARY TABLE Numeros (
    n INT PRIMARY KEY
);

INSERT INTO Numeros VALUES
(1),(2),(3),(4),(5),(6);