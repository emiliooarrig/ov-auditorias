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

INSERT IGNORE INTO edificios (numero) VALUES
  (1), (2), (3), (4), (5), (6), (7), (8), (9), (10);
