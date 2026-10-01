-- 2026-09-30: edificios con el nombre de su área y catálogo nuevo de ocho edificios.
-- Para bases instaladas antes de esta fecha. Es idempotente: se puede ejecutar varias veces.
--   php bin/migrar-bd.php
-- Compatible con MySQL 8.0.16+ y MariaDB 10.6+.

-- 1. Columna nombre, solo si todavía no existe.
SET @falta_nombre := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'edificios' AND COLUMN_NAME = 'nombre');
SET @paso := IF(@falta_nombre,
  'ALTER TABLE edificios ADD COLUMN nombre VARCHAR(60) NOT NULL DEFAULT '''' AFTER numero',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 2. Los ocho edificios vigentes: se crean si faltan y quedan activos con su nombre.
INSERT IGNORE INTO edificios (numero, nombre) VALUES
  (5, 'Derecho'), (6, 'Anáhuac Labs'), (7, 'Psicología'), (8, 'Medicina'),
  (9, 'Ingeniería'), (11, 'Economía'), (17, 'CAD'), (22, 'Artes');

UPDATE edificios
SET activo = 1,
    nombre = CASE numero
      WHEN 5 THEN 'Derecho'
      WHEN 6 THEN 'Anáhuac Labs'
      WHEN 7 THEN 'Psicología'
      WHEN 8 THEN 'Medicina'
      WHEN 9 THEN 'Ingeniería'
      WHEN 11 THEN 'Economía'
      WHEN 17 THEN 'CAD'
      WHEN 22 THEN 'Artes'
    END
WHERE numero IN (5, 6, 7, 8, 9, 11, 17, 22);

-- 3. Los demás edificios se desactivan, no se borran: dejan de aparecer en filtros y formularios,
--    y los talleres que ya los usan conservan su registro. Reasigna esos talleres desde "Editar".
UPDATE edificios SET activo = 0 WHERE numero NOT IN (5, 6, 7, 8, 9, 11, 17, 22);
