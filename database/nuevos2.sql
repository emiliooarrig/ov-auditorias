-- =====================================================================
-- Alumnos auditores del Día OV 2026: 119 alumnos con su horario
-- Fuente: CORREOS_CITA_OV_202660.xlsx (hoja principal + Matutino y Vespertino)
--
-- Adaptado al esquema del 2026-10-12 (alumnos sin cuenta, con horario):
--   - Se cargan en alumnos (nombre, apellidos y horario); no tienen cuenta ni
--     correo. Su matrícula queda como comentario.
--   - El horario del Excel se lleva al catálogo horarios_alumno:
--       "7:00 a 14:00 hrs."  -> Matutino     07:00-14:00  (83 alumnos)
--       "13:00 a 19:00 hrs." -> Vespertino   14:00-19:00  (29 alumnos)
--       "7:00 a 19:00 hrs."  -> Todo el día  07:00-19:00  (7 alumnos)
--     El Excel dice 13:00 para el turno vespertino; se registra como Vespertino,
--     igual que en database/migraciones/2026-10-12_horarios_alumnos.sql.
--   - Un alumno se reconoce por su nombre completo (sin distinguir acentos ni
--     mayúsculas). El que ya existe no se duplica: conserva su id y sus
--     asignaciones. Su horario solo cambia si no tenía o si el nuevo lo amplía
--     (p. ej. Vespertino -> Todo el día); nunca se reduce, para no dejar talleres
--     programados fuera de su horario. Al final se listan los que difieren y
--     no se cambiaron.
--
-- Antes de correrlo: base al día (bin/migrar-bd.php) y el administrador ya
-- creado con bin/crear-admin.php (los alumnos quedan registrados por él).
-- Se puede correr más de una vez. Todo va en una transacción: si algo falla,
-- no se guarda nada.
--
-- Las filas marcadas con REVISAR tenían datos que el Excel no deja resolver
-- con certeza. Las de matrícula ya no afectan la carga (no se guarda).
-- =====================================================================
SET NAMES utf8mb4;
USE auditores_talleres;

-- ---------------------------------------------------------------------
-- CONFIGURACIÓN
-- ---------------------------------------------------------------------
SET @matutino = (SELECT id FROM horarios_alumno WHERE nombre = 'Matutino');
SET @vespertino = (SELECT id FROM horarios_alumno WHERE nombre = 'Vespertino');
SET @todo_el_dia = (SELECT id FROM horarios_alumno WHERE nombre = 'Todo el día');

SET @admin_id = (SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                 WHERE r.nombre = 'administrador' AND u.activo = 1 ORDER BY u.id LIMIT 1);

-- Guardia: si falta el administrador o algún horario del catálogo, el INSERT
-- viola la restricción y el script se detiene antes de cambiar nada.
CREATE TEMPORARY TABLE carga_alumnos_guardia (
  admin_id INT NULL,
  horarios INT NOT NULL,
  CONSTRAINT ck_carga_alumnos_admin CHECK (admin_id IS NOT NULL),
  CONSTRAINT ck_carga_alumnos_horarios CHECK (horarios = 3)
);
INSERT INTO carga_alumnos_guardia VALUES
  (@admin_id, (@matutino IS NOT NULL) + (@vespertino IS NOT NULL) + (@todo_el_dia IS NOT NULL));

CREATE TEMPORARY TABLE carga_alumnos (
  orden      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(80)  NOT NULL,
  apellidos  VARCHAR(120) NOT NULL,
  horario_id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Alumnos del Excel (119)
-- ---------------------------------------------------------------------
INSERT INTO carga_alumnos (nombre, apellidos, horario_id) VALUES
  ('Diego', 'García Lascurain García', @vespertino),  -- matrícula 00470951
  ('Emilio', 'Arriaga Guzman', @matutino),  -- matrícula 00478974
  ('Brenda', 'León Mendoza', @matutino),  -- matrícula 00480840
  ('Renée', 'Mena Martínez', @todo_el_dia),  -- matrícula 00481882
  ('Derek Alexander', 'Oliva Contreras', @vespertino),  -- matrícula 00486973
  ('Astrid', 'Pellico Osthoff', @matutino),  -- matrícula 00499421
  ('Ximena', 'Cortés Ramírez', @vespertino),  -- matrícula 00502841
  ('Antonio', 'Rodríguez Hernández', @matutino),  -- matrícula 00502906
  ('Ximena', 'Galván Reyna', @vespertino),  -- matrícula 00506753
  ('Marco Adrián', 'Macías Leal', @vespertino),  -- matrícula 00508095
  ('Estefanía', 'Quintero Rodríguez', @matutino),  -- matrícula 00508157
  ('María José', 'Mattig Martínez', @vespertino),  -- matrícula 00509127
  ('Saúl Emiliano', 'Barrios Dorantes', @matutino),  -- matrícula 00510568
  ('Ivanna', 'Sánchez Bellan', @todo_el_dia),  -- matrícula 00510759
  ('Natalia', 'Bernal', @matutino),  -- matrícula 00512137
  ('Erika', 'Mendoza Castillejos', @matutino),  -- matrícula 00513481
  ('Andrea Romero', 'Castany Bañuelos', @matutino),  -- matrícula 00513730
  ('Inés', 'Pérez Téllez Rojo', @vespertino),  -- matrícula 00514843
  ('Samantha Rachel', 'Vazquez Sanchez', @matutino),  -- matrícula 00518183
  ('Santiago', 'Aguilar Ramírez', @matutino),  -- matrícula 00520153
  ('Erick Leonardo', 'Mena Páez', @vespertino),  -- matrícula 00520822
  ('Jorge Darío', 'González López', @vespertino),  -- matrícula 00521631
  ('Elena', 'Rivera González', @matutino),  -- matrícula 00544864
  ('Eduardo', 'Abreu', @vespertino),  -- matrícula 00546196
  ('Mariana', 'Corral Piana', @matutino),  -- matrícula 00547377
  ('Ana Cristina', 'Dávila Domínguez', @vespertino),  -- matrícula 00548827
  ('Jose Carlos', 'Andrade Valle', @matutino),  -- matrícula 00552530. REVISAR: columna A = 00552520, columna C = 552530; se usó C (escrita a mano)
  ('Pedro', 'Guereschi', @vespertino),  -- matrícula 00554515
  ('Camila Anahí', 'Sifuentes Botello', @todo_el_dia),  -- matrícula 00557309
  ('Laura Yazmín', 'García Sánchez', @vespertino),  -- matrícula 00558094
  ('Joel', 'Sánchez Díaz', @matutino),  -- matrícula 00579433
  ('Roselyn', 'Flores Higuera', @matutino),  -- matrícula 00580565
  ('Mariano', 'Jelwan', @matutino),  -- matrícula 00584071
  ('Regina', 'Delgado Guerrero', @vespertino),  -- matrícula 00584201
  ('Azul Alessandra', 'Cedillo García', @vespertino),  -- matrícula 00584256
  ('Isabel', 'Montaño Guadarrama', @matutino),  -- matrícula 00585826
  ('Diego', 'Chávez Espinosa', @vespertino),  -- matrícula 00586083
  ('Victoria', 'Garza Chavez', @matutino),  -- matrícula 00586090
  ('Ximena', 'Rivera Bazán', @vespertino),  -- matrícula 00592337
  ('Renata', 'Galván Naranjo', @matutino),  -- matrícula 00592364
  ('Valeria', 'Garza Chavez', @matutino),  -- matrícula 00594717
  ('Gustavo', 'Castillo', @matutino),  -- matrícula 00598951
  ('Luis Eduardo', 'Castillo Feregrino', @matutino),  -- matrícula 00609879
  ('Sebastian', 'Diaz Aguero', @matutino),  -- matrícula 00610722
  ('Alitzel', 'Álvarez García', @matutino),  -- matrícula 00612962
  ('Leonardo', 'Meza Benitez', @vespertino),  -- matrícula 00613483
  ('Myranda Mariel', 'Hernandez Amezola', @matutino),  -- matrícula 00613710
  ('Manuel', 'Garcia Santaularia', @vespertino),  -- matrícula 00615825
  ('Karla', 'Gutiérrez Cordova', @matutino),  -- matrícula 00615874
  ('Regina', 'Malvaez Méndez', @matutino),  -- matrícula 00615928
  ('Mariana', 'Castañeda Menéndez', @matutino),  -- matrícula 00616176
  ('Gabriel', 'Ruiz Rivera', @matutino),  -- matrícula 00618382
  ('Gael', 'Santos Pérez', @matutino),  -- matrícula 00618663
  ('Oscar Adrian', 'Arango González', @matutino),  -- matrícula 00619123
  ('Sophia', 'Garcia Elias', @matutino),  -- matrícula 00619878
  ('Camila Sofía', 'Yáñez Hernández', @matutino),  -- matrícula 00620096
  ('Ricardo', 'Rodríguez Valencia', @matutino),  -- matrícula 00621118. REVISAR: columna A = 0062118, columna C = 621118; se usó C (escrita a mano)
  ('Diego', 'Almanza Repetto', @matutino),  -- matrícula 00621243
  ('Barbara', 'Becerril Villalobos', @matutino),  -- matrícula 00621267
  ('Karla Daniela', 'Camargo Medina', @matutino),  -- matrícula 00622018
  ('Irving', 'Martínez Nuñez', @vespertino),  -- matrícula 00622251
  ('Alan Eduardo', 'Esparza Alanís', @matutino),  -- matrícula 00622391
  ('Isabella', 'Rodríguez Martínez', @matutino),  -- matrícula 00622479
  ('Santiago', 'Cardosa García', @vespertino),  -- matrícula 00623289
  ('Regina', 'Aburto', @matutino),  -- matrícula 00623328
  ('Nadia', 'Molina', @matutino),  -- matrícula 00623520
  ('María Fernanda', 'Vargas Jimenez', @matutino),  -- matrícula 00623914
  ('Bruno Santino', 'Ibarra Camargo', @vespertino),  -- matrícula 00624095
  ('Karla Luciana', 'Valencia Victoria', @matutino),  -- matrícula 00624216
  ('Renée Sofia', 'Velázquez Lara', @todo_el_dia),  -- matrícula 00624274. REVISAR: figuraba en mañana (7:00 a 14:00) y tarde (13:00 a 19:00): se unió en 7:00 a 19:00
  ('Natalia', 'Santana Acevedo', @vespertino),  -- matrícula 00624276
  ('Santiago', 'Gómez Ancona', @matutino),  -- matrícula 00624329
  ('Patricio', 'Belaunzarán Leyva', @matutino),  -- matrícula 00624549
  ('Grecia Sofía', 'Torres Hernández', @matutino),  -- matrícula 00624591
  ('Juan Pablo', 'Aburto Luckie', @matutino),  -- matrícula 00624619
  ('Nicole', 'Suastegui Rosette', @matutino),  -- matrícula 00624687
  ('Carlos Alain', 'Correa Orozco', @vespertino),  -- matrícula 00624900
  ('Ana Sofía', 'Rodríguez Ramírez', @matutino),  -- matrícula 00625016
  ('Aranza', 'Espindola Castillo', @vespertino),  -- matrícula 00625031
  ('Monica Jazmin', 'Estela Rincon', @matutino),  -- matrícula 00625239
  ('Constanza Valeria', 'Cervantes Chanfreau', @vespertino),  -- matrícula 00625276
  ('Erika Vannya', 'Solís Mendizábal', @matutino),  -- matrícula 00625411
  ('Ivana', 'Reyes Querol', @vespertino),  -- matrícula 00625416
  ('Edgar', 'Barragán Hernández', @matutino),  -- matrícula 00625879
  ('Carolina', 'Morales Ortiz', @matutino),  -- matrícula 00625991
  ('Marcela', 'Gama Iracheta', @matutino),  -- matrícula 00626922
  ('Victoria', 'Viglione Brito', @vespertino),  -- matrícula 00626980
  ('Mario Eduardo', 'Mendoza Dolores', @matutino),  -- matrícula 00627883
  ('Astrid Dessireé', 'Bautista Almaraz', @matutino),  -- matrícula 00627996
  ('Vanessa', 'Grimaldo Paulin', @todo_el_dia),  -- matrícula 00629093
  ('Sebastián', 'De Jesús Ocegeda', @matutino),  -- matrícula 00629385
  ('Diego', 'Beirute Buerba', @matutino),  -- matrícula 00630255
  ('Bernardo', 'Orozco Riaño', @matutino),  -- matrícula 00630285
  ('Lucía', 'Galván Torres', @todo_el_dia),  -- matrícula 00630759
  ('Regina Estefanía', 'Cortés Enllanche', @matutino),  -- matrícula 00631249
  ('Carlo Bruno', 'Garcia Sanchez', @matutino),  -- matrícula 00631908
  ('Jimena', 'Arias', @matutino),  -- matrícula 00632812. REVISAR: columna A = 632812, columna C = 00632813; se usó A (escrita a mano)
  ('Nicolle Alejandra', 'Dorta', @matutino),  -- matrícula 00632865
  ('Renata', 'Ruiz Gómez', @matutino),  -- matrícula 00632869
  ('Rodrigo', 'Bravo López', @matutino),  -- matrícula 00633328
  ('Jorge Daniel', 'Álvarez Damián', @vespertino),  -- matrícula 00633715
  ('Juan Pablo', 'Solis Hernández', @matutino),  -- matrícula 00633989
  ('Jacqueline', 'Fernández Colin', @matutino),  -- matrícula 00634663
  ('Regina', 'Espinosa Reyes', @matutino),  -- matrícula 00635110
  ('Valeria', 'Cortés Arellano', @matutino),  -- matrícula 00635256
  ('Camila', 'Pérez García', @matutino),  -- matrícula 00635432
  ('María Fernanda', 'Gómez y Mora', @matutino),  -- matrícula 00635820
  ('Camila del Carmen', 'Salinas Villaseñor', @todo_el_dia),  -- matrícula 00636333
  ('Kamila Valentina', 'Vargas Corominas', @matutino),  -- matrícula 00636895
  ('Karen Nicole', 'Bertín Lobato', @matutino),  -- matrícula 00637489
  ('Estefania', 'Antunez', @matutino),  -- matrícula 00637563
  ('Alonso', 'Cuellar Sanchez', @matutino),  -- matrícula 00638012
  ('Isabella', 'Cano Guanilo', @matutino),  -- matrícula 00638397
  ('Santiago Emiliano', 'Terrón Casas', @matutino),  -- matrícula 00640027
  ('Leonardo', 'Monreal Astorga', @matutino),  -- matrícula 00640170
  ('Samara', 'Hernández Barrón', @matutino),  -- matrícula 00640978
  ('Danna Itzel', 'Castillo Ibarra', @matutino),  -- matrícula 00642295
  ('Javier', 'Navarro Isunza', @matutino),  -- matrícula 00642707
  ('Igal', 'Rosemberg Vincze', @matutino);  -- matrícula 00646026

-- ---------------------------------------------------------------------
-- 2. Los que ya existen: se llena su horario si no tenían o se amplía.
-- ---------------------------------------------------------------------
UPDATE alumnos al
JOIN carga_alumnos c ON CONCAT(c.nombre, ' ', c.apellidos) = CONCAT(al.nombre, ' ', al.apellidos)
JOIN horarios_alumno nuevo ON nuevo.id = c.horario_id
LEFT JOIN horarios_alumno actual ON actual.id = al.horario_id
SET al.horario_id = c.horario_id
WHERE al.horario_id IS NULL
   OR (al.horario_id <> c.horario_id
       AND nuevo.hora_inicio <= actual.hora_inicio AND actual.hora_fin <= nuevo.hora_fin);

-- ---------------------------------------------------------------------
-- 3. Los que faltan, en el orden del Excel.
-- ---------------------------------------------------------------------
INSERT INTO alumnos (nombre, apellidos, horario_id, creado_por)
SELECT c.nombre, c.apellidos, c.horario_id, @admin_id
  FROM carga_alumnos c
 WHERE NOT EXISTS (SELECT 1 FROM alumnos al
                    WHERE CONCAT(al.nombre, ' ', al.apellidos) = CONCAT(c.nombre, ' ', c.apellidos))
 ORDER BY c.orden;

-- Alumnos que ya existían con un horario que el del Excel no contiene: se
-- conservó el de la base. Cámbialo desde su panel si el Excel es el correcto.
SELECT al.id, al.nombre, al.apellidos, actual.nombre AS horario_en_base, nuevo.nombre AS horario_en_excel
  FROM alumnos al
  JOIN carga_alumnos c ON CONCAT(c.nombre, ' ', c.apellidos) = CONCAT(al.nombre, ' ', al.apellidos)
  JOIN horarios_alumno nuevo ON nuevo.id = c.horario_id
  JOIN horarios_alumno actual ON actual.id = al.horario_id
 WHERE al.horario_id <> c.horario_id;

COMMIT;

DROP TEMPORARY TABLE carga_alumnos;
DROP TEMPORARY TABLE carga_alumnos_guardia;

-- ---------------------------------------------------------------------
-- Verificación (esperado: los 119 del Excel con horario; si antes se corrió
-- database/nuevos.sql, 120 alumnos: Sofía Rodríguez Pérez no viene en el Excel)
-- SELECT h.nombre AS horario, COUNT(*) AS alumnos
--   FROM alumnos al LEFT JOIN horarios_alumno h ON h.id = al.horario_id
--  GROUP BY h.nombre WITH ROLLUP;
-- ---------------------------------------------------------------------
