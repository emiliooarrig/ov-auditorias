-- Catálogos iniciales de carreras y edificios. Ajusta la lista a la oferta real del campus
-- antes de instalar en producción. Es idempotente: se puede ejecutar varias veces.
--
-- El administrador inicial NO se siembra aquí: se crea con  php bin/crear-admin.php
-- para que su contraseña nunca quede escrita en un archivo.

INSERT IGNORE INTO carreras (nombre) VALUES
  ('Actuaría'),
  ('Administración y Dirección de Empresas'),
  ('Arquitectura'),
  ('Comunicación'),
  ('Derecho'),
  ('Diseño Gráfico'),
  ('Economía'),
  ('Finanzas y Contaduría Pública'),
  ('Ingeniería Civil'),
  ('Ingeniería Industrial para la Dirección'),
  ('Ingeniería Mecatrónica'),
  ('Ingeniería en Sistemas y Tecnologías de la Información'),
  ('Médico Cirujano'),
  ('Mercadotecnia Estratégica'),
  ('Negocios Internacionales'),
  ('Nutrición'),
  ('Psicología'),
  ('Relaciones Internacionales'),
  ('Turismo Internacional');

-- Grupos de talleres y su horario fijo: el grupo que se elige para un taller define su hora.
INSERT IGNORE INTO grupos_taller (numero, nombre, hora_inicio, hora_fin) VALUES
  (1, 'Taller 1', '10:00:00', '11:00:00'),
  (2, 'Taller 2', '11:00:00', '12:00:00'),
  (3, 'Taller 3', '12:00:00', '13:00:00'),
  (4, 'Taller 4', '13:00:00', '14:00:00'),
  (5, 'Taller 5', '15:00:00', '16:00:00'),
  (6, 'Taller 6', '16:00:00', '17:00:00');

-- Edificios donde se imparten talleres, con el área que ocupa cada uno.
INSERT IGNORE INTO edificios (numero, nombre) VALUES
  (5, 'Derecho'),
  (6, 'Anáhuac Labs'),
  (7, 'Psicología'),
  (8, 'Medicina'),
  (9, 'Ingeniería'),
  (11, 'Economía'),
  (17, 'CAD'),
  (22, 'Artes');
