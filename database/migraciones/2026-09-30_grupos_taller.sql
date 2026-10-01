-- 2026-09-30: grupos de talleres (Taller 1 … Taller 6) con horario fijo; cada taller pertenece a uno
-- y su hora de inicio y fin son las del grupo.
-- Para bases instaladas antes de esta fecha. Es idempotente: se puede ejecutar varias veces.
--   php bin/migrar-bd.php
-- Compatible con MySQL 8.0.16+ y MariaDB 10.6+.

-- 1. Catálogo de grupos con su horario.
CREATE TABLE IF NOT EXISTS grupos_taller (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero      TINYINT UNSIGNED NOT NULL,
  nombre      VARCHAR(30) NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grupos_taller_numero (numero),
  CONSTRAINT ck_grupos_taller_horario CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO grupos_taller (numero, nombre, hora_inicio, hora_fin) VALUES
  (1, 'Taller 1', '10:00:00', '11:00:00'),
  (2, 'Taller 2', '11:00:00', '12:00:00'),
  (3, 'Taller 3', '12:00:00', '13:00:00'),
  (4, 'Taller 4', '13:00:00', '14:00:00'),
  (5, 'Taller 5', '15:00:00', '16:00:00'),
  (6, 'Taller 6', '16:00:00', '17:00:00');

UPDATE grupos_taller
SET nombre = CONCAT('Taller ', numero),
    activo = 1,
    hora_inicio = CASE numero WHEN 1 THEN '10:00:00' WHEN 2 THEN '11:00:00' WHEN 3 THEN '12:00:00'
                              WHEN 4 THEN '13:00:00' WHEN 5 THEN '15:00:00' WHEN 6 THEN '16:00:00' END,
    hora_fin    = CASE numero WHEN 1 THEN '11:00:00' WHEN 2 THEN '12:00:00' WHEN 3 THEN '13:00:00'
                              WHEN 4 THEN '14:00:00' WHEN 5 THEN '16:00:00' WHEN 6 THEN '17:00:00' END
WHERE numero BETWEEN 1 AND 6;

-- 2. Columna grupo_id en actividades, primero opcional para poder llenarla.
SET @falta_grupo := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'actividades' AND COLUMN_NAME = 'grupo_id');
SET @paso := IF(@falta_grupo,
  'ALTER TABLE actividades ADD COLUMN grupo_id TINYINT UNSIGNED NULL AFTER edificio_id',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- 3. Cada taller sin grupo recibe el grupo cuya hora de inicio es la misma o la más cercana
--    (en empate, el de número menor).
UPDATE actividades a
SET a.grupo_id = (
  SELECT g.id FROM grupos_taller g
  WHERE g.activo = 1
  ORDER BY ABS(TIME_TO_SEC(g.hora_inicio) - TIME_TO_SEC(a.hora_inicio)), g.numero
  LIMIT 1
)
WHERE a.grupo_id IS NULL;

-- 4. El horario del taller es el de su grupo.
UPDATE actividades a
JOIN grupos_taller g ON g.id = a.grupo_id
SET a.hora_inicio = g.hora_inicio, a.hora_fin = g.hora_fin
WHERE a.hora_inicio <> g.hora_inicio OR a.hora_fin <> g.hora_fin;

-- 5. Ya con todos los talleres asignados: grupo obligatorio, índice y llave foránea.
SET @grupo_opcional := (SELECT IS_NULLABLE = 'YES' FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'actividades' AND COLUMN_NAME = 'grupo_id');
SET @paso := IF(@grupo_opcional,
  'ALTER TABLE actividades MODIFY grupo_id TINYINT UNSIGNED NOT NULL',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @falta_fk := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'actividades'
    AND CONSTRAINT_NAME = 'fk_actividades_grupo');
SET @paso := IF(@falta_fk,
  'ALTER TABLE actividades ADD KEY idx_actividades_grupo (grupo_id), ADD CONSTRAINT fk_actividades_grupo FOREIGN KEY (grupo_id) REFERENCES grupos_taller (id)',
  'DO 0');
PREPARE migracion FROM @paso;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;
