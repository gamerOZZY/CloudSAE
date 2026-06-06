-- ==========================================
-- 8. CREACIÓN DE USUARIOS
-- ==========================================
CREATE USER IF NOT EXISTS 'rol_directivo'@'localhost' IDENTIFIED BY 'DirectivoPass123!';
CREATE USER IF NOT EXISTS 'rol_gestor'@'localhost' IDENTIFIED BY 'GestorPass123!';
CREATE USER IF NOT EXISTS 'rol_profesor'@'localhost' IDENTIFIED BY 'ProfePass123!';
CREATE USER IF NOT EXISTS 'rol_alumno'@'localhost' IDENTIFIED BY 'AlumnoPass123!';

-- ==========================================
-- 9. PERMISOS: DIRECTIVO (ADMINISTRADOR)
-- ==========================================
-- RESTRICCIÓN APP: Validar que el ID a modificar pertenezca a un Gestor.
GRANT SELECT, INSERT, UPDATE, DELETE ON Persona TO 'rol_directivo'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Gestor TO 'rol_directivo'@'localhost';
GRANT SELECT ON Escuela TO 'rol_directivo'@'localhost';
GRANT SELECT ON Region_Geografica TO 'rol_directivo'@'localhost';
GRANT SELECT ON Gestor_Auditoria TO 'rol_directivo'@'localhost';

-- ==========================================
-- 10. PERMISOS: GESTIÓN
-- ==========================================
-- RESTRICCIÓN APP: Validar que el ID a modificar pertenezca a Alumno o Profesor.
GRANT SELECT, INSERT, UPDATE, DELETE ON Persona TO 'rol_gestor'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Alumno TO 'rol_gestor'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Profesor TO 'rol_gestor'@'localhost';

-- Gestor solo inscribe/da de baja, NO actualiza calificaciones
GRANT SELECT, INSERT, DELETE ON Tiene_Inscrita TO 'rol_gestor'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON Profesor_Imparte_Materia TO 'rol_gestor'@'localhost';
GRANT SELECT ON Materia TO 'rol_gestor'@'localhost';
GRANT SELECT ON Escuela TO 'rol_gestor'@'localhost';
GRANT SELECT ON Region_Geografica TO 'rol_gestor'@'localhost';
GRANT INSERT ON Gestor_Auditoria TO 'rol_gestor'@'localhost';

-- ==========================================
-- 11. PERMISOS: PROFESOR
-- ==========================================
-- RESTRICCIÓN APP: Validar que el update de notas sea sobre alumnos inscritos a su clase.
GRANT SELECT ON Persona TO 'rol_profesor'@'localhost';
GRANT SELECT ON Profesor TO 'rol_profesor'@'localhost';
GRANT SELECT ON Profesor_Imparte_Materia TO 'rol_profesor'@'localhost';
GRANT SELECT ON Materia TO 'rol_profesor'@'localhost';
GRANT SELECT ON Alumno TO 'rol_profesor'@'localhost';
GRANT SELECT ON Tiene_Inscrita TO 'rol_profesor'@'localhost';

-- Único rol que puede asentar las notas (parciales) y cambiar su propia foto
GRANT UPDATE (parcial_1, parcial_2, parcial_3) ON Tiene_Inscrita TO 'rol_profesor'@'localhost';
GRANT UPDATE (foto_perfil) ON Persona TO 'rol_profesor'@'localhost';

-- ==========================================
-- 12. PERMISOS: ALUMNO
-- ==========================================
-- RESTRICCIÓN CRÍTICA APP: Añadir "WHERE id_alumno = ?" en las consultas.
GRANT SELECT ON Persona TO 'rol_alumno'@'localhost';
GRANT SELECT ON Alumno TO 'rol_alumno'@'localhost';
GRANT SELECT ON Tiene_Inscrita TO 'rol_alumno'@'localhost';
GRANT SELECT ON Materia TO 'rol_alumno'@'localhost';

-- Sólo puede modificar su foto de perfil
GRANT UPDATE (foto_perfil) ON Persona TO 'rol_alumno'@'localhost';

-- ==========================================
-- 13. APLICAR CAMBIOS
-- ==========================================
FLUSH PRIVILEGES;