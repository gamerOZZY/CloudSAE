-- ==========================================
-- 1. REGIONES Y ESCUELAS
-- ==========================================

INSERT INTO Region_Geografica (nombre) VALUES
('Norte'),
('Centro'),
('Sur');

INSERT INTO Escuela (nombre, id_region) VALUES
('ESCOM', 2),
('ESIME Zacatenco', 2),
('UPIITA', 2),
('CECyT 9', 2),
('ESIA Tecamachalco', 2);

-- ==========================================
-- 2. PERSONAS
-- ==========================================

INSERT INTO Persona (nombre_completo, contrasena, foto_perfil, id_escuela) VALUES
('Juan Carlos Ramírez', 'pass123', 'juan.jpg', 1),      -- 1 Directivo
('María Fernanda López', 'pass123', 'maria.jpg', 1),    -- 2 Gestor
('Carlos Hernández', 'pass123', 'carlos.jpg', 1),       -- 3 Profesor
('Ana Torres', 'pass123', 'ana.jpg', 1),                -- 4 Profesor
('Luis Martínez', 'pass123', 'luis.jpg', 1),            -- 5 Alumno
('Sofía García', 'pass123', 'sofia.jpg', 1),            -- 6 Alumno
('Pedro Sánchez', 'pass123', 'pedro.jpg', 2),           -- 7 Alumno
('Laura Mendoza', 'pass123', 'laura.jpg', 3),           -- 8 Alumno
('Roberto Díaz', 'pass123', 'roberto.jpg', 2),          -- 9 Gestor
('Elena Cruz', 'pass123', 'elena.jpg', 3);              -- 10 Directivo

-- ==========================================
-- 3. ROLES
-- ==========================================

INSERT INTO Directivo (id_persona, numero_empleado, cargo) VALUES
(1, 'DIR001', 'Director General'),
(10, 'DIR002', 'Subdirector Académico');

INSERT INTO Gestor (id_persona, numero_empleado, correo) VALUES
(2, 'GES001', 'maria.lopez@ipn.mx'),
(9, 'GES002', 'roberto.diaz@ipn.mx');

INSERT INTO Profesor (id_persona, numero_empleado, tipo) VALUES
(3, 'PRO001', 'Tiempo Completo'),
(4, 'PRO002', 'Asignatura');

INSERT INTO Alumno (id_persona, boleta, edad) VALUES
(5, '20230001', 20),
(6, '20230002', 21),
(7, '20230003', 19),
(8, '20230004', 22);

-- ==========================================
-- 4. MATERIAS
-- ==========================================

INSERT INTO Materia (nombre) VALUES
('Bases de Datos'),
('Estructuras de Datos'),
('Probabilidad y Estadística'),
('Inteligencia Artificial'),
('Programación Web');

-- ==========================================
-- 5. PROFESORES IMPARTEN MATERIAS
-- ==========================================

INSERT INTO Profesor_Imparte_Materia (id_profesor, id_materia) VALUES
(3, 1),
(3, 2),
(3, 4),
(4, 3),
(4, 5);

-- ==========================================
-- 6. INSCRIPCIONES DE ALUMNOS
-- ==========================================

INSERT INTO Tiene_Inscrita (
    id_alumno,
    id_materia,
    grado_semestre,
    fecha_inscripcion,
    fecha_finalizacion,
    parcial_1,
    parcial_2,
    parcial_3,
    final
) VALUES
(5, 1, 4, '2025-01-15', NULL, 8.5, 9.0, 9.2, 9.0),
(5, 2, 4, '2025-01-15', NULL, 7.5, 8.0, 8.5, 8.0),

(6, 1, 4, '2025-01-15', NULL, 10.0, 9.8, 9.5, 9.8),
(6, 4, 4, '2025-01-15', NULL, 9.0, 8.8, 9.1, 9.0),

(7, 3, 2, '2025-01-15', NULL, 7.0, 7.5, 8.0, 7.5),
(7, 5, 2, '2025-01-15', NULL, 8.5, 8.7, 8.9, 8.7),

(8, 2, 6, '2025-01-15', NULL, 9.2, 9.3, 9.5, 9.3),
(8, 4, 6, '2025-01-15', NULL, 8.8, 9.0, 9.2, 9.0);

-- ==========================================
-- 7. AUDITORÍA
-- ==========================================

INSERT INTO Gestor_Auditoria (
    id_gestor,
    entidad,
    id_entidad,
    accion
) VALUES
(2, 'ALUMNO', 5, 'ALTA'),
(2, 'ALUMNO', 6, 'ALTA'),
(2, 'MATERIA', 4, 'ALTA'),
(2, 'PROFESOR', 3, 'ASIGNAR_MATERIA'),
(9, 'ALUMNO', 7, 'MODIFICAR_DATOS'),
(9, 'PROFESOR', 4, 'ASIGNAR_MATERIA'),
(2, 'ALUMNO', 8, 'ALTA');