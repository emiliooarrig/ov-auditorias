-- 2026-10-10: los alumnos ya no ingresan al sistema. Cada auditor pasa a la tabla alumnos (solo nombre
-- y apellidos), las asignaciones apuntan a ella y guardan si fueron manuales o aleatorias.
-- Solo los administradores conservan cuenta.
-- Para bases instaladas antes de esta fecha. Es idempotente: se puede ejecutar varias veces, y cada paso
-- revisa el estado real de la base, así que también termina una corrida que se haya interrumpido.
--   php bin/migrar-bd.php
-- Compatible con MySQL 8.0.16+ y MariaDB 10.6+.

-- 1. Tabla de alumnos.
CREATE TABLE IF NOT EXISTS alumnos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(80)  NOT NULL,
  apellidos      VARCHAR(120) NOT NULL,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  creado_por     INT UNSIGNED NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_alumnos_nombre (apellidos, nombre),
  CONSTRAINT fk_alumnos_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mientras las asignaciones apunten a usuarios: cada auditor, y cualquier usuario con asignaciones
--    (p. ej. un auditor que después se hizo administrador), pasa a ser alumno con el mismo id.
--    Así las asignaciones conservan su número y solo cambia la tabla a la que apuntan.
SET @por_migrar := (SELECT COUNT(*) > 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones' AND COLUMN_NAME = 'usuario_id');
SET @paso := IF(@por_migrar,
  'INSERT IGNORE INTO alumnos (id, nombre, apellidos, activo, creado_en)
   SELECT u.id, u.nombre, u.apellidos, u.activo, u.creado_en
   FROM usuarios u JOIN roles r ON r.id = u.rol_id
   WHERE r.nombre = ''auditor'' OR u.id IN (SELECT s.usuario_id FROM asignaciones s)',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 3. Las asignaciones dejan de apuntar a usuarios: se quita la llave foránea vieja y se renombra la columna.
SET @falta := (SELECT COUNT(*) > 0 FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones'
    AND CONSTRAINT_NAME = 'fk_asignaciones_usuario');
SET @paso := IF(@falta, 'ALTER TABLE asignaciones DROP FOREIGN KEY fk_asignaciones_usuario', 'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @paso := IF(@por_migrar,
  'ALTER TABLE asignaciones CHANGE usuario_id alumno_id INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 4. Índice y llave foránea hacia alumnos.
SET @falta := (SELECT COUNT(*) > 0 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones' AND INDEX_NAME = 'idx_asignaciones_usuario');
SET @paso := IF(@falta, 'ALTER TABLE asignaciones DROP INDEX idx_asignaciones_usuario', 'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones' AND INDEX_NAME = 'idx_asignaciones_alumno');
SET @paso := IF(@falta, 'ALTER TABLE asignaciones ADD KEY idx_asignaciones_alumno (alumno_id)', 'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones'
    AND CONSTRAINT_NAME = 'fk_asignaciones_alumno');
SET @paso := IF(@falta,
  'ALTER TABLE asignaciones ADD CONSTRAINT fk_asignaciones_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos (id)',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 5. Origen de cada asignación. Las que ya existían se hicieron a mano.
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaciones' AND COLUMN_NAME = 'origen');
SET @paso := IF(@falta,
  'ALTER TABLE asignaciones ADD COLUMN origen ENUM(''manual'',''aleatoria'') NOT NULL DEFAULT ''manual'' AFTER alumno_id',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 6. Las cuentas de auditor ya no ingresan. Se desactivan en lugar de borrarse porque la bitácora de
--    estados conserva quién marcó cada taller.
UPDATE usuarios u
JOIN roles r ON r.id = u.rol_id
SET u.activo = 0
WHERE r.nombre = 'auditor' AND u.activo = 1;
