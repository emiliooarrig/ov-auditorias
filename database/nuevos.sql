-- =====================================================================
-- Carga inicial: Día OV 2026 (jueves 15 de octubre de 2026)
-- Fuentes: registro_de_taller_ov_2026.xlsx
--          Reporte_Asistencias_de_Auditoria_de_Talleres_OV_2026.xlsx
--
-- Adaptado al esquema del 2026-10-10 (alumnos sin cuenta):
--   - Los 34 auditores se cargan como alumnos (nombre, apellidos y horario);
--     no tienen cuenta ni correo. Su matrícula queda como comentario.
--   - Cada taller va en su grupo: "Taller N" es el grupo N y los OPCIONALES
--     de 16:00 a 17:00 son el Taller 6. El horario lo fija el grupo
--     (catálogo grupos_taller), igual que en la aplicación.
--   - Las carreras que ya existen se conservan (INSERT IGNORE).
--
-- Antes de correrlo:
--   1. Base instalada y al día (bin/instalar-bd.php o bin/migrar-bd.php).
--   2. El administrador ya creado con bin/crear-admin.php
--      (talleres y alumnos quedan registrados por ese administrador).
-- Se corre UNA sola vez: si ya hay talleres del 15 de octubre, se detiene sin
-- cambiar nada. Todo va en una transacción: si algo falla, no se guarda nada.
--
-- NO se insertan asignaciones: las hace el administrador desde la aplicación.
--
-- AJUSTAR: el edificio no viene en el registro; los talleres quedan en el
-- edificio 0 "Por asignar" hasta que se edite cada uno.
-- =====================================================================
SET NAMES utf8mb4;
USE auditores_talleres;

-- ---------------------------------------------------------------------
-- CONFIGURACIÓN
-- ---------------------------------------------------------------------
SET @fecha_ov = '2026-10-15';

-- Grupo de cada bloque con su horario fijo (Taller 1 … Taller 6).
SELECT id, hora_inicio, hora_fin INTO @g1, @g1_ini, @g1_fin FROM grupos_taller WHERE numero = 1;
SELECT id, hora_inicio, hora_fin INTO @g2, @g2_ini, @g2_fin FROM grupos_taller WHERE numero = 2;
SELECT id, hora_inicio, hora_fin INTO @g3, @g3_ini, @g3_fin FROM grupos_taller WHERE numero = 3;
SELECT id, hora_inicio, hora_fin INTO @g4, @g4_ini, @g4_fin FROM grupos_taller WHERE numero = 4;
SELECT id, hora_inicio, hora_fin INTO @g5, @g5_ini, @g5_fin FROM grupos_taller WHERE numero = 5;
-- Talleres "OPCIONAL DE 4:00 A 5:00PM": ese es el horario del Taller 6.
SELECT id, hora_inicio, hora_fin INTO @g6, @g6_ini, @g6_fin FROM grupos_taller WHERE numero = 6;

-- Horario de participación de cada alumno (catálogo horarios_alumno).
SET @matutino = (SELECT id FROM horarios_alumno WHERE nombre = 'Matutino');
SET @vespertino = (SELECT id FROM horarios_alumno WHERE nombre = 'Vespertino');
SET @todo_el_dia = (SELECT id FROM horarios_alumno WHERE nombre = 'Todo el día');

SET @admin_id = (SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                 WHERE r.nombre = 'administrador' AND u.activo = 1 ORDER BY u.id LIMIT 1);

-- Guardia: si ya hay talleres de ese día (o falta el administrador), el INSERT
-- viola la restricción y el script se detiene antes de cambiar nada.
CREATE TEMPORARY TABLE carga_ov_guardia (
  talleres_del_dia INT NOT NULL,
  admin_id INT NULL,
  CONSTRAINT ck_carga_una_vez CHECK (talleres_del_dia = 0),
  CONSTRAINT ck_carga_admin CHECK (admin_id IS NOT NULL)
);
INSERT INTO carga_ov_guardia VALUES ((SELECT COUNT(*) FROM actividades WHERE fecha = @fecha_ov), @admin_id);

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Edificio
-- AJUSTAR: el registro no indica el edificio. El número 0 es un marcador
-- "Por asignar"; cambia el edificio de cada taller desde la aplicación.
-- ---------------------------------------------------------------------
INSERT INTO edificios (numero, nombre) VALUES (0, 'Por asignar')
  ON DUPLICATE KEY UPDATE activo = 1;
SET @edificio_pendiente = (SELECT id FROM edificios WHERE numero = 0);


-- ---------------------------------------------------------------------
-- 2. Carreras (44). Las que ya estaban en el catálogo se conservan.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO carreras (nombre) VALUES
  ('Actuaría'),
  ('Administración Turística'),
  ('Administración y Dirección de Empresas'),
  ('Arquitectura'),
  ('Arquitectura de interiores'),
  ('Artes Visuales'),
  ('Biotecnología'),
  ('Ciencia de Datos'),
  ('Comunicación'),
  ('Comunicación, Dirección de Empresas de Entretenimiento'),
  ('Derecho'),
  ('Dirección de Empresas de Entretenimiento'),
  ('Dirección de Restaurantes'),
  ('Dirección Financiera'),
  ('Dirección Internacional de Hoteles'),
  ('Dirección y Administración del Deporte'),
  ('Diseño de Moda e Innovación y Tendecia'),
  ('Diseño Gráfico'),
  ('Diseño Industrial'),
  ('Diseño Multimedia'),
  ('Engineering Management'),
  ('Finanzas y Contaduría Pública'),
  ('Gastronomía'),
  ('Global Development'),
  ('Global Management'),
  ('Historia'),
  ('Ingeniería Ambiental'),
  ('Ingeniería Biomédica'),
  ('Ingeniería Civil'),
  ('Ingeniería en Sistemas y Tecnologías de la Información'),
  ('Ingeniería Industrial para la Dirección'),
  ('Ingeniería Mecatrónica'),
  ('Ingeniería Química'),
  ('Médico Cirujano'),
  ('Médico Cirujano Dentista'),
  ('Mercadotecnia Estratégica'),
  ('Música Contemporánea'),
  ('Negocios Internacionales'),
  ('Nutrición'),
  ('Pedagogía Organizacional y Educativa'),
  ('Psicología'),
  ('Relaciones Internacionales'),
  ('Teatro y Actuación'),
  ('Terapia Física y Rehabilitación');

-- ---------------------------------------------------------------------
-- 3. Alumnos auditores (34). No tienen cuenta: nombre, apellidos y horario.
-- El comentario de cada fila es su matrícula y su horario de participación en el reporte.
-- "13:00 a 19:00 hrs." se registra como Vespertino (14:00 a 19:00).
-- ---------------------------------------------------------------------
INSERT INTO alumnos (nombre, apellidos, horario_id, creado_por) VALUES
  ('Mariano', 'Jelwan', @matutino, @admin_id),  -- 00584071, 7:00 a 14:00 hrs.
  ('Marco Adrián', 'Macías Leal', @vespertino, @admin_id),  -- 00508095, 13:00 a 19:00 hrs.
  ('Brenda', 'León Mendoza', @matutino, @admin_id),  -- 00480840, 7:00 a 14:00 hrs.
  ('Jose Carlos', 'Andrade Valle', @matutino, @admin_id),  -- 00552520, 7:00 a 14:00 hrs.
  ('Santiago', 'Aguilar Ramírez', @matutino, @admin_id),  -- 00520153, 7:00 a 14:00 hrs.
  ('Ivanna', 'Sánchez Bellan', @todo_el_dia, @admin_id),  -- 00510759, 7:00 a 19:00 hrs.
  ('Emilio', 'Arriaga Guzman', @matutino, @admin_id),  -- 00478974, 7:00 a 14:00 hrs.
  ('Saúl Emiliano', 'Barrios Dorantes', @matutino, @admin_id),  -- 00510568, 7:00 a 14:00 hrs.
  ('Ximena', 'Cortés Ramírez', @vespertino, @admin_id),  -- 00502841, 13:00 a 19:00 hrs.
  ('María José', 'Mattig Martínez', @vespertino, @admin_id),  -- 00509127, 13:00 a 19:00 hrs.
  ('Laura Yazmín', 'García Sánchez', @vespertino, @admin_id),  -- 00558094, 13:00 a 19:00 hrs.
  ('Erika', 'Mendoza Castillejos', @matutino, @admin_id),  -- 00513481, 7:00 a 14:00 hrs.
  ('Erick Leonardo', 'Mena Páez', @vespertino, @admin_id),  -- 00520822, 13:00 a 19:00 hrs.
  ('Inés', 'Pérez Téllez Rojo', @vespertino, @admin_id),  -- 00514843, 13:00 a 19:00 hrs.
  ('Astrid', 'Pellico Osthoff', @matutino, @admin_id),  -- 00499421, 7:00 a 14:00 hrs.
  ('Victoria', 'Viglione Brito', @vespertino, @admin_id),  -- 00626980, 13:00 a 19:00 hrs.
  ('Sofía', 'Rodríguez Pérez', @matutino, @admin_id),  -- 00584407, 7:00 a 14:00 hrs.
  ('Camila Anahí', 'Sifuentes Botello', @todo_el_dia, @admin_id),  -- 00557309, 7:00 a 19:00 hrs.
  ('Azul Alessandra', 'Cedillo García', @vespertino, @admin_id),  -- 00584256, 13:00 a 19:00 hrs.
  ('Mariana', 'Corral Piana', @matutino, @admin_id),  -- 00547377, 7:00 a 14:00 hrs.
  ('Ximena', 'Galván Reyna', @vespertino, @admin_id),  -- 00506753, 13:00 a 19:00 hrs.
  ('Ana Cristina', 'Dávila Domínguez', @vespertino, @admin_id),  -- 00548827, 13:00 a 19:00 hrs.
  ('Pedro', 'Guereschi', @vespertino, @admin_id),  -- 00554515, 13:00 a 19:00 hrs.
  ('Roselyn', 'Flores Higuera', @matutino, @admin_id),  -- 00580565, 7:00 a 14:00 hrs.
  ('Diego', 'García Lascurain García', @vespertino, @admin_id),  -- 00470951, 13:00 a 19:00 hrs.
  ('Jorge Darío', 'González López', @vespertino, @admin_id),  -- 00521631, 13:00 a 19:00 hrs.
  ('Estefanía', 'Quintero Rodríguez', @matutino, @admin_id),  -- 00508157, 7:00 a 14:00 hrs.
  ('Isabel', 'Montaño Guadarrama', @matutino, @admin_id),  -- 00585826, 7:00 a 14:00 hrs.
  ('Renée', 'Mena Martínez', @vespertino, @admin_id),  -- 00481882, 13:00 a 19:00 hrs.
  ('Ximena', 'Rivera Bazán', @vespertino, @admin_id),  -- 00592337, 13:00 a 19:00 hrs.
  ('Samantha Rachel', 'Vazquez Sanchez', @matutino, @admin_id),  -- 00518183, 7:00 a 14:00 hrs.
  ('Valeria', 'Cortés Arellano', @matutino, @admin_id),  -- 00635256, 7:00 a 14:00 hrs.
  ('Eduardo', 'Abreu', @vespertino, @admin_id),  -- 546196, 13:00 a 19:00 hrs.
  ('Elena', 'Rivera González', @matutino, @admin_id);  -- 00544864, 7:00 a 14:00 hrs.

-- ---------------------------------------------------------------------
-- 4. Talleres (200): estado 'programado'. Agrupados por carrera;
-- el comentario de cada fila es el responsable que figura en el registro.
-- Los OPCIONALES llevan el sufijo " (Opcional)" y van en el Taller 6 (16:00 a 17:00).
-- ---------------------------------------------------------------------
INSERT INTO actividades (nombre, carrera_id, edificio_id, grupo_id, fecha, hora_inicio, hora_fin, creado_por) VALUES
  -- Actuaría
  ('Wall Street: conviértete en inversionista por 45 minutos', (SELECT id FROM carreras WHERE nombre = 'Actuaría'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Jennifer Rangel Madariaga
  ('Detectives de funciones: resolviendo un misterio matemático', (SELECT id FROM carreras WHERE nombre = 'Actuaría'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Jennifer Rangel Madariaga
  ('Programando en R: así trabaja un actuario con datos reales', (SELECT id FROM carreras WHERE nombre = 'Actuaría'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Jennifer Rangel Madariaga
  ('Wall Street: conviértete en inversionista por 45 minutos', (SELECT id FROM carreras WHERE nombre = 'Actuaría'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Jennifer Rangel Madariaga
  -- Administración Turística
  ('Mario Kart: Carrera por el mundo', (SELECT id FROM carreras WHERE nombre = 'Administración Turística'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Blanca Estela Correa Guevara
  ('Escape Room: en busca de Harry Styles', (SELECT id FROM carreras WHERE nombre = 'Administración Turística'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Blanca Estela Correa Guevara
  ('Influencers de destinos: el reto del marketing turístico', (SELECT id FROM carreras WHERE nombre = 'Administración Turística'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Blanca Estela Correa Guevara
  ('world hospitality challenge', (SELECT id FROM carreras WHERE nombre = 'Administración Turística'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Blanca Estela Correa Guevara
  ('World Hospitality Challenge', (SELECT id FROM carreras WHERE nombre = 'Administración Turística'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Blanca Estela Correa Guevara
  -- Administración y Dirección de Empresas
  ('IA Challenge: Crea el futuro hoy.', (SELECT id FROM carreras WHERE nombre = 'Administración y Dirección de Empresas'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Pilar Madrazo Lemarroy
  ('WISTAR EN LA NIEVE', (SELECT id FROM carreras WHERE nombre = 'Administración y Dirección de Empresas'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Pilar Madrazo Lemarroy
  ('Laboratorio de Ideas de Negocio', (SELECT id FROM carreras WHERE nombre = 'Administración y Dirección de Empresas'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Pilar Madrazo Lemarroy
  ('Business workshop', (SELECT id FROM carreras WHERE nombre = 'Administración y Dirección de Empresas'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Pilar Madrazo Lemarroy
  ('Business workshop', (SELECT id FROM carreras WHERE nombre = 'Administración y Dirección de Empresas'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Pilar Madrazo Lemarroy
  -- Arquitectura
  ('Dibujo Arquitectónico', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  ('Maquetas volumétricas en arquitectura', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  ('Estructuras Arquitectónicas', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  ('Misión Verde: Diseña la ciudad del futuro.', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  ('Arquitecto por una hora', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  ('Estructuras arquitectónicas (Opcional)', (SELECT id FROM carreras WHERE nombre = 'Arquitectura'), @edificio_pendiente, @g6, @fecha_ov, @g6_ini, @g6_fin, @admin_id),  -- RODRIGO SHIORDIA LOPEZ
  -- Arquitectura de interiores
  ('El color en en el espacio interior', (SELECT id FROM carreras WHERE nombre = 'Arquitectura de interiores'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Liliana Naranjo Merino
  ('Maquetas para interiorismo', (SELECT id FROM carreras WHERE nombre = 'Arquitectura de interiores'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Liliana Naranjo Merino
  ('Diseñando un espacio con IA', (SELECT id FROM carreras WHERE nombre = 'Arquitectura de interiores'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Liliana Naranjo Merino
  ('El color en el espacio interior', (SELECT id FROM carreras WHERE nombre = 'Arquitectura de interiores'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Liliana Naranjo Merino
  ('Maquetas para interiorismo', (SELECT id FROM carreras WHERE nombre = 'Arquitectura de interiores'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Liliana Naranjo Merino
  -- Artes Visuales
  ('Clase muestra: Apreciación artística del cine', (SELECT id FROM carreras WHERE nombre = 'Artes Visuales'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Nicolás Vargas Lebert
  ('Plantas tintoreas', (SELECT id FROM carreras WHERE nombre = 'Artes Visuales'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Nicolas Vargas Lebert
  ('Plantas tintoreas', (SELECT id FROM carreras WHERE nombre = 'Artes Visuales'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Nicolas Vargas Lebert
  ('Acuarela', (SELECT id FROM carreras WHERE nombre = 'Artes Visuales'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Nicolas Vargas Lebert
  ('Acuarela', (SELECT id FROM carreras WHERE nombre = 'Artes Visuales'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Nicolas Vargas Lebert
  -- Biotecnología
  ('Gram Challenge', (SELECT id FROM carreras WHERE nombre = 'Biotecnología'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Ana Elena Huerta González
  ('Conociendo a los Hongos', (SELECT id FROM carreras WHERE nombre = 'Biotecnología'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Ana Elena Huerta González
  ('ADN a la Vista', (SELECT id FROM carreras WHERE nombre = 'Biotecnología'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Ana Elena Huerta González
  ('ADN a la Vista', (SELECT id FROM carreras WHERE nombre = 'Biotecnología'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Ana Elena Huerta González
  ('Protein Hunters', (SELECT id FROM carreras WHERE nombre = 'Biotecnología'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Ana Elena Huerta González
  -- Ciencia de Datos
  ('Pronósticos futboleros para actuarios y científicos de datos', (SELECT id FROM carreras WHERE nombre = 'Ciencia de Datos'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Jennifer Rangel Madariaga
  ('Modelando el futuro con patrones y datos', (SELECT id FROM carreras WHERE nombre = 'Ciencia de Datos'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Jennifer Rangel Madariaga
  ('Dados, canicas y magia', (SELECT id FROM carreras WHERE nombre = 'Ciencia de Datos'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Jennifer Rangel Madariaga
  -- Comunicación
  ('Cómo hacer un Product Shot', (SELECT id FROM carreras WHERE nombre = 'Comunicación'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Del Play al Podcast, Vive la experiencia de producir en Radio Anáhuac.', (SELECT id FROM carreras WHERE nombre = 'Comunicación'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Cómo hacer un Product Shot', (SELECT id FROM carreras WHERE nombre = 'Comunicación'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Tu Voz, Tu Marca', (SELECT id FROM carreras WHERE nombre = 'Comunicación'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Ana Pamela Flores Herrera
  -- Comunicación, Dirección de Empresas de Entretenimiento
  ('Investigando la comunicación con Realidad extendida', (SELECT id FROM carreras WHERE nombre = 'Comunicación, Dirección de Empresas de Entretenimiento'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Branding 101: crea una marca que todos recuerden', (SELECT id FROM carreras WHERE nombre = 'Comunicación, Dirección de Empresas de Entretenimiento'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Ana Pamela Flores Herrera
  -- Derecho
  ('Simulación de juicio oral', (SELECT id FROM carreras WHERE nombre = 'Derecho'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- María Concepción de Lourdes González González
  ('El Arte de la Justicia: ¿Cómo piensa un abogado?', (SELECT id FROM carreras WHERE nombre = 'Derecho'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- María Concepción de Lourdes González González
  ('Diseñando una política pública', (SELECT id FROM carreras WHERE nombre = 'Derecho'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- María Concepción de Lourdes González González
  ('Simulacion de audiencia oral (Opcional)', (SELECT id FROM carreras WHERE nombre = 'Derecho'), @edificio_pendiente, @g6, @fecha_ov, @g6_ini, @g6_fin, @admin_id),  -- María Concepción de Lourdes González González
  -- Dirección de Empresas de Entretenimiento
  ('El Pitch del Entretenimiento', (SELECT id FROM carreras WHERE nombre = 'Dirección de Empresas de Entretenimiento'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Héroes, villanos y personajes inolvidables', (SELECT id FROM carreras WHERE nombre = 'Dirección de Empresas de Entretenimiento'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Ana Pamela Flores Herrera
  ('Branding 101: crea una marca que todos recuerden', (SELECT id FROM carreras WHERE nombre = 'Dirección de Empresas de Entretenimiento'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Ana Pamela Flores Herrera
  -- Dirección de Restaurantes
  ('Preparación de mocktails', (SELECT id FROM carreras WHERE nombre = 'Dirección de Restaurantes'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Juan José Gómez Díaz
  ('Lego Serious Play para la creación de un concepto restaurantero', (SELECT id FROM carreras WHERE nombre = 'Dirección de Restaurantes'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Juan José Gómez Díaz
  ('Lego Serious Play para la creación de concepto restaurantero.', (SELECT id FROM carreras WHERE nombre = 'Dirección de Restaurantes'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Juan José Gómez Díaz
  ('World Hospitality Challenge', (SELECT id FROM carreras WHERE nombre = 'Dirección de Restaurantes'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Juan José Gómez Díaz
  ('World Hospitality Challenge', (SELECT id FROM carreras WHERE nombre = 'Dirección de Restaurantes'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Juan José Gómez Díaz
  -- Dirección Financiera
  ('Money Lab', (SELECT id FROM carreras WHERE nombre = 'Dirección Financiera'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Selene Rocío Gaxiola Laso
  ('Money Lab', (SELECT id FROM carreras WHERE nombre = 'Dirección Financiera'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Selene Rocío Gaxiola Laso
  ('Money Lab', (SELECT id FROM carreras WHERE nombre = 'Dirección Financiera'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Selene Rocío Gaxiola Laso
  -- Dirección Internacional de Hoteles
  ('Lego Serious Play para creación de Restaurante', (SELECT id FROM carreras WHERE nombre = 'Dirección Internacional de Hoteles'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Juan Jose Gomez Diaz
  ('Tendencias en hotelería y servicios de hospedaje', (SELECT id FROM carreras WHERE nombre = 'Dirección Internacional de Hoteles'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Juan José Gómez Díaz
  ('Servicios de lujo y vanguardia en hospitalidad', (SELECT id FROM carreras WHERE nombre = 'Dirección Internacional de Hoteles'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Juan José Gómez Díaz
  ('World Hospitality Challenge', (SELECT id FROM carreras WHERE nombre = 'Dirección Internacional de Hoteles'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Juan José Gómez Díaz
  ('World Hospitality Challenge', (SELECT id FROM carreras WHERE nombre = 'Dirección Internacional de Hoteles'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Juan José Gómez Díaz
  -- Dirección y Administración del Deporte
  ('Sports Marketing - controla el juego!', (SELECT id FROM carreras WHERE nombre = 'Dirección y Administración del Deporte'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Francisco Javier Ruiz Pérez
  ('Sports Marketing- Controla el juego', (SELECT id FROM carreras WHERE nombre = 'Dirección y Administración del Deporte'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Francisco Javier Ruiz Pérez
  ('Organización de eventos deportivos', (SELECT id FROM carreras WHERE nombre = 'Dirección y Administración del Deporte'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Francisco Javier Ruiz Pérez
  ('Organización de eventos deportivos', (SELECT id FROM carreras WHERE nombre = 'Dirección y Administración del Deporte'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Francisco Javier Ruiz Pérez
  -- Diseño de Moda e Innovación y Tendecia
  ('Moda Inteligente: Explorando el Diseño de Moda con Inteligencia Artificial', (SELECT id FROM carreras WHERE nombre = 'Diseño de Moda e Innovación y Tendecia'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Ana María López Balbín
  ('Coolhunting / Cazando tendencias', (SELECT id FROM carreras WHERE nombre = 'Diseño de Moda e Innovación y Tendecia'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Ana Maria Lopez Balbin
  ('Arquitectura vestible', (SELECT id FROM carreras WHERE nombre = 'Diseño de Moda e Innovación y Tendecia'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Ana Maria López Balbín
  ('Photoshoot de Moda', (SELECT id FROM carreras WHERE nombre = 'Diseño de Moda e Innovación y Tendecia'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Ana María López Balbín
  -- Diseño Gráfico
  ('Make your Mark / Diseña una firma que hable por ti', (SELECT id FROM carreras WHERE nombre = 'Diseño Gráfico'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- María del Carmen Razo Aguilar
  ('Make it Iconic / Convierte un objeto en algo extraordinario', (SELECT id FROM carreras WHERE nombre = 'Diseño Gráfico'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- María del Carmen Razo Aguilar
  ('Build it. Bind it / Crea tu propio sketchbook', (SELECT id FROM carreras WHERE nombre = 'Diseño Gráfico'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- María del Carmen Razo Aguilar
  ('The Cover Edit / Conviértete en Director de Arte por un día', (SELECT id FROM carreras WHERE nombre = 'Diseño Gráfico'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- María del Carmen Razo Aguilar
  ('Pop your Brand / Diseña el próximo refresco', (SELECT id FROM carreras WHERE nombre = 'Diseño Gráfico'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- María del Carmen Razo Aguilar
  -- Diseño Industrial
  ('Design LAB', (SELECT id FROM carreras WHERE nombre = 'Diseño Industrial'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Adrián Rosado López
  ('ART TOY', (SELECT id FROM carreras WHERE nombre = 'Diseño Industrial'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Adrián Rosado López
  ('Design Lab', (SELECT id FROM carreras WHERE nombre = 'Diseño Industrial'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Adrián Rosado López
  ('Art Toy', (SELECT id FROM carreras WHERE nombre = 'Diseño Industrial'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Adrián Rosado López
  -- Diseño Multimedia
  ('Técnicas de Animación en medio profesional', (SELECT id FROM carreras WHERE nombre = 'Diseño Multimedia'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Jorge Erick Campos Baigts
  ('Diseño de Personajes', (SELECT id FROM carreras WHERE nombre = 'Diseño Multimedia'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Jorge Erick Campos Baigts
  ('Anima a un personaje en 3D', (SELECT id FROM carreras WHERE nombre = 'Diseño Multimedia'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Jorge Erick Campos Baigts
  ('Diseño de personajes y ambientes con RA e inteligencia Artificial', (SELECT id FROM carreras WHERE nombre = 'Diseño Multimedia'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Jeyvan Sánchez Mendoza
  ('Creación de videojuegos y entornos virutales', (SELECT id FROM carreras WHERE nombre = 'Diseño Multimedia'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- jeyvan.sanchezme@anahuac.mx
  -- Engineering Management
  ('LaunchLab AI :From Idea to Impact', (SELECT id FROM carreras WHERE nombre = 'Engineering Management'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Norma Lorena Diego González
  ('LaunchLab AI : From Idea to Impact', (SELECT id FROM carreras WHERE nombre = 'Engineering Management'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Norma Lorena Diego González
  ('LaunchLab AI: From Idea to Impact', (SELECT id FROM carreras WHERE nombre = 'Engineering Management'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Norma Lorena Diego González
  -- Finanzas y Contaduría Pública
  ('EL JUEGO DE LAS FINANZAS', (SELECT id FROM carreras WHERE nombre = 'Finanzas y Contaduría Pública'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Selene Rocío Gaxiola Laso
  ('El juego de las finanzas', (SELECT id FROM carreras WHERE nombre = 'Finanzas y Contaduría Pública'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Selene Rocío Gaxiola Laso
  -- Gastronomía
  ('El Arte de la Focaccia: de la Masa al Horno', (SELECT id FROM carreras WHERE nombre = 'Gastronomía'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- María Teresa Hernández del Pando
  ('Aguachile Lab: Frescura, Fuego y Sabor', (SELECT id FROM carreras WHERE nombre = 'Gastronomía'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- María Teresa Hernández del Pando
  ('Choux Magic: El Secreto de la Pastelería Francesa', (SELECT id FROM carreras WHERE nombre = 'Gastronomía'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- María Teresa Hernández del Pando
  ('El Arte de la Focaccia: De la Masa al Horno', (SELECT id FROM carreras WHERE nombre = 'Gastronomía'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- María Teresa Hernández del Pando
  ('Aguachile Lab: Frescura, Fuego y Sabor', (SELECT id FROM carreras WHERE nombre = 'Gastronomía'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- María Teresa Hernández del Pando
  -- Global Development
  ('Gabinete Nova', (SELECT id FROM carreras WHERE nombre = 'Global Development'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Gerardo Trujano
  ('GABINETE NOVA', (SELECT id FROM carreras WHERE nombre = 'Global Development'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Gerardo Trujano
  ('GABINETE NOVA', (SELECT id FROM carreras WHERE nombre = 'Global Development'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Gerardo Trujano
  ('Misión Rediseñar el Mundo', (SELECT id FROM carreras WHERE nombre = 'Global Development'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Gerardo Trujano
  ('¿Qué país quieres construir?', (SELECT id FROM carreras WHERE nombre = 'Global Development'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Gerardo Trujano
  -- Global Management
  ('Global Bids for Business Startup', (SELECT id FROM carreras WHERE nombre = 'Global Management'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Nicole Goldberg Dalma
  ('Global Bids for Business Startup', (SELECT id FROM carreras WHERE nombre = 'Global Management'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Nicole Goldberg Dalma
  ('Global Bids for Business Startup', (SELECT id FROM carreras WHERE nombre = 'Global Management'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Nicole Goldberg Dalma
  ('Global Bids for Business Startup', (SELECT id FROM carreras WHERE nombre = 'Global Management'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Nicole Goldberg Dalma
  -- Historia
  ('¿Qué hace el historiador? Detectives del Pasado', (SELECT id FROM carreras WHERE nombre = 'Historia'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Karla Sandoval Téllez
  ('Historia en acción: El trabajo del historiador en la gestión cultural', (SELECT id FROM carreras WHERE nombre = 'Historia'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Karla Sandoval Téllez
  ('Historia en la pantalla: el historiador detrás de las películas y los videojuegos. Divulgación histórica.', (SELECT id FROM carreras WHERE nombre = 'Historia'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Karla Sandoval Téllez
  ('Reconstruir el pasado a partir de la evidencia arqueológica', (SELECT id FROM carreras WHERE nombre = 'Historia'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Karla Sandoval Téllez
  ('Descifrando el arte: el trabajo del historiador en el mundo del arte', (SELECT id FROM carreras WHERE nombre = 'Historia'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Karla Sandoval Téllez
  -- Ingeniería Ambiental
  ('Conviértete en Ingenier@ Ambiental: Resuelve un caso real', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Ambiental'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Alejandra Maribel Barragán Martínez
  ('Basura que genera combustible: El poder del hidrógeno', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Ambiental'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Alejandra Maribel Barragán Martínez
  ('Energía en el mapa: Planeando un parque solar', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Ambiental'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Alejandra Maribel Barragán Martínez
  ('Ingeniería del viento: Optimizando un aerogenerador con el kit KidWind', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Ambiental'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Alejandra Maribel Barragán Martínez
  ('Energía del futuro: Del viento al hidrógeno', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Ambiental'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Alejandra Maribel Barragán Martínez
  -- Ingeniería Biomédica
  ('Laparoscopía: un método no muy invasivo para intervenir quirúrgicamente', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Biomédica'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- José Ramón Alvarez Bada
  ('¿Qué hace la electricidad con nuestros cuerpos?', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Biomédica'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- José Ramón Alvarez Bada
  ('Simuladores médicos', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Biomédica'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- José Ramón Alvarez Bada
  ('Osciloscopios: señales chiquitas para divertirse bastante', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Biomédica'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- José Ramón Alvarez Bada
  ('Bioinformática: bases de datos, gusanitos y antepasados distantes', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Biomédica'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- José Ramón Alvarez Bada
  -- Ingeniería Civil
  ('Muros antisísmicos', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Civil'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Joseph Salvador Guevara Flores
  ('Presas y control de inundaciones', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Civil'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Joseph Guevara Flores
  ('RETO PUENTE', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Civil'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- JOSEPH SALVADOR GUEVARA FLORES
  ('Ciudad resiliente', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Civil'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Joseph Salvador Guevara Flores
  ('Diseño de Pavimentos', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Civil'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Joseph Salvador Guevara Flores
  -- Ingeniería en Sistemas y Tecnologías de la Información
  ('Mi primera Base de Datos con SQL', (SELECT id FROM carreras WHERE nombre = 'Ingeniería en Sistemas y Tecnologías de la Información'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Miguel Ángel Méndez Méndez
  ('Arduino como controlador de luz de colores', (SELECT id FROM carreras WHERE nombre = 'Ingeniería en Sistemas y Tecnologías de la Información'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Miguel Ángel Méndez Méndez
  ('Programación de un fotomosaico en Python', (SELECT id FROM carreras WHERE nombre = 'Ingeniería en Sistemas y Tecnologías de la Información'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Miguel Ángel Méndez Méndez
  ('¡Conéctate! Arma y prueba tu cable de red', (SELECT id FROM carreras WHERE nombre = 'Ingeniería en Sistemas y Tecnologías de la Información'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Miguel Ángel Méndez Méndez
  ('Arduino como controlador de luz de colores', (SELECT id FROM carreras WHERE nombre = 'Ingeniería en Sistemas y Tecnologías de la Información'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Miguel Ángel Méndez Méndez
  -- Ingeniería Industrial para la Dirección
  ('Snack Lab', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Industrial para la Dirección'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Brenda María Retana Blanco
  ('VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Industrial para la Dirección'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Brenda María Retana Blanco
  ('Snack Lab', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Industrial para la Dirección'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Brenda María retana Blanco
  ('VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Industrial para la Dirección'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Brenda María Retana Blanco
  ('Snack Lab', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Industrial para la Dirección'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Brenda María Retana Blanco
  -- Ingeniería Mecatrónica
  ('Taller Práctico de Electrónica Modular', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Mecatrónica'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Dr. Leon Hamui Balas
  ('Taller Práctico de electrónica Modular con Impresión 3D', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Mecatrónica'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Leon Hamui
  ('Taller Práctico de Electrónica Modular', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Mecatrónica'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Dr. Leon Hamui Balas
  ('Taller Práctico de electrónica Modular con Impresión 3D', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Mecatrónica'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Leon Hamui
  ('Taller Práctico de electrónica Modular con Impresión 3D', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Mecatrónica'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- leon Hamui
  -- Ingeniería Química
  ('¡Crea, Formula y Emprende! Diseña tu pasta de dientes', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Química'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Alma Delia Rojas Rodríguez
  ('¡Crea, Formula y Emprende! Diseña tu pasta de dientes', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Química'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Alma Delia Rojas Rodríguez
  ('¡Crea, Formula y Emprende! Diseña tu pasta de dientes', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Química'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Alma Delia Rojas Rodríguez
  ('¡Crea, Formula y Emprende! Diseña tu pasta de dientes', (SELECT id FROM carreras WHERE nombre = 'Ingeniería Química'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Alma Delia Rojas Rodríguez
  -- Médico Cirujano
  ('OperandoAndo', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Mariana Alvarez Sanchez
  ('OperandoAndo', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Mariana Alvarez Sanchez
  ('ER: Ralidad o Ficción', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Mariana Alvarez Sanchez
  ('OperandoAndo', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Mariana Alvarez Sánchez
  ('OperandoAndo', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Mariana Alvarez Sanchez
  ('ER:Realidad o Ficción (Opcional)', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano'), @edificio_pendiente, @g6, @fecha_ov, @g6_ini, @g6_fin, @admin_id),  -- Mariana Alvarez Sanchez
  -- Médico Cirujano Dentista
  ('Toma de impresiones', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano Dentista'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Antonio copin
  ('Modelado y creatividad', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano Dentista'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Antonio copin
  ('Simulación en odontologia', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano Dentista'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Antonio copin
  ('Simulación en odontologia', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano Dentista'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Antonio copin
  ('Toma de impresiones', (SELECT id FROM carreras WHERE nombre = 'Médico Cirujano Dentista'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Antonio Copin
  -- Mercadotecnia Estratégica
  ('BrandLab: Análisis Estratégico de Lego con IA', (SELECT id FROM carreras WHERE nombre = 'Mercadotecnia Estratégica'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Luz Cecilia Revilla Soriano
  ('BrandLab: Análisis Estratégico de Nike con IA', (SELECT id FROM carreras WHERE nombre = 'Mercadotecnia Estratégica'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Luz Cecilia Revilla Soriano
  ('BrandLab: Análisis Estratégico de Coca Cola con IA', (SELECT id FROM carreras WHERE nombre = 'Mercadotecnia Estratégica'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Luz Cecilia Revilla Soriano
  ('BrandLab: Análisis Estratégico de L’Oreal con IA', (SELECT id FROM carreras WHERE nombre = 'Mercadotecnia Estratégica'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Luz Cecilia Revilla Soriano
  ('BrandLab: Análisis Estratégico de Cinépolis con IA (Opcional)', (SELECT id FROM carreras WHERE nombre = 'Mercadotecnia Estratégica'), @edificio_pendiente, @g6, @fecha_ov, @g6_ini, @g6_fin, @admin_id),  -- Luz Cecilia Revilla Soriano
  -- Música Contemporánea
  ('Clase abierta: Técnicas de producción digital', (SELECT id FROM carreras WHERE nombre = 'Música Contemporánea'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Itia Domínguez Rosales
  ('Workshop ensamble musical', (SELECT id FROM carreras WHERE nombre = 'Música Contemporánea'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Itia Domínguez Rosales
  ('Workshop ensamble musical', (SELECT id FROM carreras WHERE nombre = 'Música Contemporánea'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Itia Domínguez Rosales
  ('Clase muestra de piano', (SELECT id FROM carreras WHERE nombre = 'Música Contemporánea'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Itia Dominguez Rosales
  ('Workshop ensamble musical', (SELECT id FROM carreras WHERE nombre = 'Música Contemporánea'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Itia Domínguez Rosales
  -- Negocios Internacionales
  ('Construyendo mi negocio internacional', (SELECT id FROM carreras WHERE nombre = 'Negocios Internacionales'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Natalia Rodríguez Avendaño
  ('Construyendo mi negocio internacional', (SELECT id FROM carreras WHERE nombre = 'Negocios Internacionales'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Natalia Rodríguez Avendaño
  ('Construyendo mi negocio internacional', (SELECT id FROM carreras WHERE nombre = 'Negocios Internacionales'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Natalia Rodríguez Avendaño
  ('Construyendo mi negocio internacional', (SELECT id FROM carreras WHERE nombre = 'Negocios Internacionales'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Natalia Rodríguez Avendaño
  ('Construyendo mi negocio internacional', (SELECT id FROM carreras WHERE nombre = 'Negocios Internacionales'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Natalia Rodriguez Avendaño
  -- Nutrición
  ('Azúcar invisible: ¿te atreves a adivinar?', (SELECT id FROM carreras WHERE nombre = 'Nutrición'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Magdalena Urrutia San Vicente
  ('Sal oculta: el reto de las etiquetas', (SELECT id FROM carreras WHERE nombre = 'Nutrición'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Magdalena Urrutia
  ('Fuel your performance: taller práctico de nutrición deportiva', (SELECT id FROM carreras WHERE nombre = 'Nutrición'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Magdalena Urrutia
  ('Azúcar invisible: ¿te atreves a adivinar?', (SELECT id FROM carreras WHERE nombre = 'Nutrición'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Magdalena Urrutia
  ('Sal oculta: el reto de las etiquetas', (SELECT id FROM carreras WHERE nombre = 'Nutrición'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Magdalena Urrutia
  -- Pedagogía Organizacional y Educativa
  ('Pedagogía: Una Experiencia Inmersiva', (SELECT id FROM carreras WHERE nombre = 'Pedagogía Organizacional y Educativa'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Susana Memun Zaga
  ('Escenarios Pedagógicos', (SELECT id FROM carreras WHERE nombre = 'Pedagogía Organizacional y Educativa'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Susana Memun Zaga
  ('Pedagogía: Una Experiencia Inmersiva', (SELECT id FROM carreras WHERE nombre = 'Pedagogía Organizacional y Educativa'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Susana Memun Zaga
  ('Escenarios Pedagógicos', (SELECT id FROM carreras WHERE nombre = 'Pedagogía Organizacional y Educativa'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Susana Memun Zaga
  ('Pedagogía: Una Experiencia Inmersiva', (SELECT id FROM carreras WHERE nombre = 'Pedagogía Organizacional y Educativa'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Susana Memun Zaga
  -- Psicología
  ('Neurolab: Descifrando el Sueño', (SELECT id FROM carreras WHERE nombre = 'Psicología'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Ana Paula Sánchez Alegría
  ('Detrás del Espejo: Psicología en acción', (SELECT id FROM carreras WHERE nombre = 'Psicología'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Ana Paula Sánchez
  ('Neurolab: Descifrando el sueño', (SELECT id FROM carreras WHERE nombre = 'Psicología'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Ana Paula Sánchez
  ('Detrás del espejo: Psicología en acción', (SELECT id FROM carreras WHERE nombre = 'Psicología'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Ana Paula Sánchez Alegría
  ('Neurolab: Descifrando el sueño', (SELECT id FROM carreras WHERE nombre = 'Psicología'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Ana Paula Sánchez Alegría
  -- Relaciones Internacionales
  ('Secuestro de Futbolistas en el Mundial', (SELECT id FROM carreras WHERE nombre = 'Relaciones Internacionales'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- YOANNA SHUBICH
  ('Tiktok Geopolítico: Convierte una crisis mundial en un video viral', (SELECT id FROM carreras WHERE nombre = 'Relaciones Internacionales'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Yoanna Shubich Green
  ('Guerra por los minerales críticos: ¿qué hay detrás de tu celular?', (SELECT id FROM carreras WHERE nombre = 'Relaciones Internacionales'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Yoanna Shubich Green
  ('Secuestro de Futbolistas en el Mundial', (SELECT id FROM carreras WHERE nombre = 'Relaciones Internacionales'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- YOANNA SHUBICH
  ('¿Qué país quieres construir?', (SELECT id FROM carreras WHERE nombre = 'Relaciones Internacionales'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Ximena Castellanos
  -- Teatro y Actuación
  ('Training teatral', (SELECT id FROM carreras WHERE nombre = 'Teatro y Actuación'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Mtro. Antonio Rojas Villarreal
  ('Teatro musical', (SELECT id FROM carreras WHERE nombre = 'Teatro y Actuación'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Antonio Rojas Villarreal
  ('Mecanismos de comedia', (SELECT id FROM carreras WHERE nombre = 'Teatro y Actuación'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Antonio Rojas Villarreal
  ('Teatro, cuerpo e improvisación', (SELECT id FROM carreras WHERE nombre = 'Teatro y Actuación'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Antonio Rojas Villarreal
  ('El juego teatral', (SELECT id FROM carreras WHERE nombre = 'Teatro y Actuación'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id),  -- Antonio Rojas Villarreal
  -- Terapia Física y Rehabilitación
  ('Fisioterapia para el cuidado de los pacientes hospitalizados.', (SELECT id FROM carreras WHERE nombre = 'Terapia Física y Rehabilitación'), @edificio_pendiente, @g1, @fecha_ov, @g1_ini, @g1_fin, @admin_id),  -- Moisés Magos
  ('Fisioterapia para el cuidado de los pacientes hospitalizados', (SELECT id FROM carreras WHERE nombre = 'Terapia Física y Rehabilitación'), @edificio_pendiente, @g2, @fecha_ov, @g2_ini, @g2_fin, @admin_id),  -- Moisés Magos
  ('Vocación en movimiento', (SELECT id FROM carreras WHERE nombre = 'Terapia Física y Rehabilitación'), @edificio_pendiente, @g3, @fecha_ov, @g3_ini, @g3_fin, @admin_id),  -- Moisés Magos
  ('Vocación en movimiento', (SELECT id FROM carreras WHERE nombre = 'Terapia Física y Rehabilitación'), @edificio_pendiente, @g4, @fecha_ov, @g4_ini, @g4_fin, @admin_id),  -- Moisés Magos
  ('Vocación en movimiento', (SELECT id FROM carreras WHERE nombre = 'Terapia Física y Rehabilitación'), @edificio_pendiente, @g5, @fecha_ov, @g5_ini, @g5_fin, @admin_id);  -- Moisés Magos

COMMIT;

DROP TEMPORARY TABLE carga_ov_guardia;

-- ---------------------------------------------------------------------
-- Verificación (esperado: talleres 200 y alumnos 34 de la carga; las 44 carreras presentes)
-- SELECT (SELECT COUNT(*) FROM actividades WHERE fecha = '2026-10-15') AS talleres,
--        (SELECT COUNT(*) FROM alumnos)                                AS alumnos,
--        (SELECT COUNT(*) FROM carreras)                               AS carreras;
-- ---------------------------------------------------------------------