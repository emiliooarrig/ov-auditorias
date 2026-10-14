-- 2026-10-12: cada alumno tiene un horario en el que puede auditar (Matutino 07:00–14:00, Vespertino
-- 14:00–19:00 o Todo el día 07:00–19:00) y solo se le asignan talleres que caben en él.
-- Para bases instaladas antes de esta fecha. Es idempotente: se puede ejecutar varias veces.
--   php bin/migrar-bd.php
-- Compatible con MySQL 8.0.16+ y MariaDB 10.6+.

-- 1. Catálogo de horarios.
CREATE TABLE IF NOT EXISTS horarios_alumno (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30) NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_horarios_alumno_nombre (nombre),
  CONSTRAINT ck_horarios_alumno_horario CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO horarios_alumno (nombre, hora_inicio, hora_fin) VALUES
  ('Matutino', '07:00:00', '14:00:00'),
  ('Vespertino', '14:00:00', '19:00:00'),
  ('Todo el día', '07:00:00', '19:00:00');

-- 2. Columna horario_id en alumnos, con su índice y llave foránea. NULL = horario sin registrar.
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumnos' AND COLUMN_NAME = 'horario_id');
SET @paso := IF(@falta,
  'ALTER TABLE alumnos ADD COLUMN horario_id TINYINT UNSIGNED NULL AFTER apellidos',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'alumnos' AND CONSTRAINT_NAME = 'fk_alumnos_horario');
SET @paso := IF(@falta,
  'ALTER TABLE alumnos ADD KEY idx_alumnos_horario (horario_id),
   ADD CONSTRAINT fk_alumnos_horario FOREIGN KEY (horario_id) REFERENCES horarios_alumno (id)',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 3. Horario de los 34 auditores del Día OV 2026, según el "Horario de participación" del reporte de
--    asistencias. Los alumnos no guardan matrícula: se reconocen por su nombre completo (los que cargó
--    database/nuevos.sql) o, si vienen de una cuenta de auditor, por la matrícula de esa cuenta.
--    El reporte dice "13:00 a 19:00 hrs." para el turno vespertino; se registra como Vespertino
--    (14:00–19:00), que es el horario definido para ese turno.
--    Solo se llena a quien no tiene horario: no pisa lo que el administrador ya haya registrado.
--    Los demás alumnos quedan sin horario hasta que se registre en su panel.
-- Matutino: 16 alumnos ("7:00 a 14:00 hrs." en el reporte).
UPDATE alumnos al
LEFT JOIN usuarios u ON u.id = al.id AND al.creado_por IS NULL
SET al.horario_id = (SELECT h.id FROM horarios_alumno h WHERE h.nombre = 'Matutino')
WHERE al.horario_id IS NULL AND (
  CONCAT(al.nombre, ' ', al.apellidos) IN (
    'Santiago Aguilar Ramírez', 'Jose Carlos Andrade Valle', 'Emilio Arriaga Guzman',
    'Saúl Emiliano Barrios Dorantes', 'Mariana Corral Piana', 'Valeria Cortés Arellano',
    'Roselyn Flores Higuera', 'Mariano Jelwan', 'Brenda León Mendoza',
    'Erika Mendoza Castillejos', 'Isabel Montaño Guadarrama', 'Astrid Pellico Osthoff',
    'Estefanía Quintero Rodríguez', 'Elena Rivera González', 'Sofía Rodríguez Pérez',
    'Samantha Rachel Vazquez Sanchez'
  )
  OR u.matricula IN (
    '00478974', '00480840', '00499421', '00508157', '00510568', '00513481',
    '00518183', '00520153', '00544864', '00547377', '00552520', '00580565',
    '00584071', '00584407', '00585826', '00635256'
  )
);
-- Vespertino: 16 alumnos ("13:00 a 19:00 hrs." en el reporte).
UPDATE alumnos al
LEFT JOIN usuarios u ON u.id = al.id AND al.creado_por IS NULL
SET al.horario_id = (SELECT h.id FROM horarios_alumno h WHERE h.nombre = 'Vespertino')
WHERE al.horario_id IS NULL AND (
  CONCAT(al.nombre, ' ', al.apellidos) IN (
    'Eduardo Abreu', 'Azul Alessandra Cedillo García', 'Ximena Cortés Ramírez',
    'Ana Cristina Dávila Domínguez', 'Ximena Galván Reyna', 'Diego García Lascurain García',
    'Laura Yazmín García Sánchez', 'Jorge Darío González López', 'Pedro Guereschi',
    'Marco Adrián Macías Leal', 'María José Mattig Martínez', 'Renée Mena Martínez',
    'Erick Leonardo Mena Páez', 'Inés Pérez Téllez Rojo', 'Ximena Rivera Bazán',
    'Victoria Viglione Brito'
  )
  OR u.matricula IN (
    '00470951', '00481882', '00502841', '00506753', '00508095', '00509127',
    '00514843', '00520822', '00521631', '00548827', '00554515', '00558094',
    '00584256', '00592337', '00626980', '546196'
  )
);
-- Todo el día: 2 alumnos ("7:00 a 19:00 hrs." en el reporte).
UPDATE alumnos al
LEFT JOIN usuarios u ON u.id = al.id AND al.creado_por IS NULL
SET al.horario_id = (SELECT h.id FROM horarios_alumno h WHERE h.nombre = 'Todo el día')
WHERE al.horario_id IS NULL AND (
  CONCAT(al.nombre, ' ', al.apellidos) IN (
    'Camila Anahí Sifuentes Botello', 'Ivanna Sánchez Bellan'
  )
  OR u.matricula IN (
    '00510759', '00557309'
  )
);
