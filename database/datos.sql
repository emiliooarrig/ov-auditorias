-- ====================================================================================================
-- DML: datos de la base auditores_talleres, generado desde la base en uso el 2026-10-14 15:44.
-- Unifica lo que antes cargaban seeds.sql, nuevos.sql, nuevos2.sql y las migraciones: aquí está el
-- estado final, no los pasos para llegar a él.
--
-- Se carga después de schema.sql (DDL) en una base vacía:
--   php bin/instalar-bd.php            (crea la base, ejecuta schema.sql y este archivo)
--   php bin/crear-admin.php --correo=earriaga@anahuac.mx    (asigna la contraseña del administrador)
-- Manual:
--   mysql -u root -e "CREATE DATABASE auditores_talleres CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u root --default-character-set=utf8mb4 auditores_talleres < database/schema.sql
--   mysql -u root --default-character-set=utf8mb4 auditores_talleres < database/datos.sql
--
-- Los ids se escriben explícitos para que las llaves foráneas apunten a lo mismo que en la base.
-- No incluye:
--   - La contraseña del administrador (password_hash va en NULL): se asigna con bin/crear-admin.php.
--   - La tabla accesos (bitácora de ingresos con IP y navegador): es registro de seguridad, no catálogo.
--
-- AUDITORÍA DE ALUMNOS (123 alumnos: Matutino 86, Todo el día 7, Vespertino 30)
-- Conciliados contra las dos fuentes originales, ya retiradas de la carpeta database:
--   - Reporte de asistencias de auditoría (Reporte_Asistencias_de_Auditoria_de_Talleres_OV_2026.xlsx,
--     cargado por nuevos.sql): 34 alumnos.
--   - Excel de citas (CORREOS_CITA_OV_202660.xlsx, cargado por nuevos2.sql): 119 alumnos.
--   - En ambas: 33 · solo en el reporte: 1 (Sofía Rodríguez Pérez) · solo en el Excel: 86.
--     Ningún alumno de las fuentes falta en la base.
--   - Sin fuente (dados de alta después en la aplicación): 3: Erik Escobedo (#162), Antonio Rodrigez Hernandez (#163), Samantha Hernandez (#164).
--   - REVISAR, nombres casi iguales (¿el mismo alumno dado de alta dos veces?): Antonio Rodríguez Hernández (#36) y Antonio Rodrigez Hernandez (#163).
--   - El comentario de cada alumno dice su horario, matrícula (de las fuentes; la base no la guarda) y en
--     qué fuente aparece. Las marcas REVISAR señalan matrículas o turnos que el Excel no dejaba claros y
--     nombres casi iguales.
-- Asignaciones: 438 entre 200 talleres; 121 alumnos con al menos un taller, 2 sin taller (Brenda León Mendoza, Ivana Reyes Querol).
-- ====================================================================================================
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------------------------------
-- Roles
-- --------------------------------------------------------------------------------------------------
INSERT INTO roles (id, nombre) VALUES
  (1, 'administrador')
;

-- --------------------------------------------------------------------------------------------------
-- Administradores (sin contraseña: asígnala con bin/crear-admin.php)
-- --------------------------------------------------------------------------------------------------
INSERT INTO usuarios (id, rol_id, matricula, nombre, apellidos, correo, password_hash, activo, ultimo_acceso, creado_en, actualizado_en) VALUES
  (1, 1, NULL, 'Emilio', 'Guzman', 'earriaga@anahuac.mx', NULL, 1, '2026-10-14 14:13:00', '2026-10-14 12:05:07', '2026-10-14 14:13:00')
;

-- --------------------------------------------------------------------------------------------------
-- Horarios en los que un alumno puede auditar
-- Un alumno solo audita talleres que empiezan y terminan dentro del suyo.
-- Vespertino es de 13:00 a 19:00 desde el 2026-10-14 (antes 14:00–19:00).
-- --------------------------------------------------------------------------------------------------
INSERT INTO horarios_alumno (id, nombre, hora_inicio, hora_fin) VALUES
  (1, 'Matutino', '07:00:00', '14:00:00'),
  (2, 'Vespertino', '13:00:00', '19:00:00'),
  (3, 'Todo el día', '07:00:00', '19:00:00')
;

-- --------------------------------------------------------------------------------------------------
-- Carreras (46)
-- --------------------------------------------------------------------------------------------------
INSERT INTO carreras (id, nombre, activo) VALUES
  (1, 'Actuaría', 1),
  (2, 'Administración y Dirección de Empresas', 1),
  (3, 'Arquitectura', 1),
  (4, 'Comunicación', 1),
  (5, 'Derecho', 1),
  (6, 'Diseño Gráfico', 1),
  (7, 'Economía', 1),
  (8, 'Finanzas y Contaduría Pública', 1),
  (9, 'Ingeniería Civil', 1),
  (10, 'Ingeniería Industrial para la Dirección', 1),
  (11, 'Ingeniería Mecatrónica', 1),
  (12, 'Ingeniería en Sistemas y Tecnologías de la Información', 1),
  (13, 'Médico Cirujano', 1),
  (14, 'Mercadotecnia Estratégica', 1),
  (15, 'Negocios Internacionales', 1),
  (16, 'Nutrición', 1),
  (17, 'Psicología', 1),
  (18, 'Relaciones Internacionales', 1),
  (19, 'Turismo Internacional', 1),
  (20, 'Administración Turística', 1),
  (21, 'Arquitectura de interiores', 1),
  (22, 'Artes Visuales', 1),
  (23, 'Biotecnología', 1),
  (24, 'Ciencia de Datos', 1),
  (25, 'Comunicación, Dirección de Empresas de Entretenimiento', 1),
  (26, 'Dirección de Empresas de Entretenimiento', 1),
  (27, 'Dirección de Restaurantes', 1),
  (28, 'Dirección Financiera', 1),
  (29, 'Dirección Internacional de Hoteles', 1),
  (30, 'Dirección y Administración del Deporte', 1),
  (31, 'Diseño de Moda e Innovación y Tendecia', 1),
  (32, 'Diseño Industrial', 1),
  (33, 'Diseño Multimedia', 1),
  (34, 'Engineering Management', 1),
  (35, 'Gastronomía', 1),
  (36, 'Global Development', 1),
  (37, 'Global Management', 1),
  (38, 'Historia', 1),
  (39, 'Ingeniería Ambiental', 1),
  (40, 'Ingeniería Biomédica', 1),
  (41, 'Ingeniería Química', 1),
  (42, 'Médico Cirujano Dentista', 1),
  (43, 'Música Contemporánea', 1),
  (44, 'Pedagogía Organizacional y Educativa', 1),
  (45, 'Teatro y Actuación', 1),
  (46, 'Terapia Física y Rehabilitación', 1)
;

-- --------------------------------------------------------------------------------------------------
-- Edificios
-- El número 0 "Por asignar" es el marcador con que se cargaron los talleres del Día OV 2026.
-- --------------------------------------------------------------------------------------------------
INSERT INTO edificios (id, numero, nombre, activo) VALUES
  (1, 5, 'Derecho', 1),
  (2, 6, 'Anáhuac Labs', 1),
  (3, 7, 'Psicología', 1),
  (4, 8, 'Medicina', 1),
  (5, 9, 'Ingeniería', 1),
  (6, 11, 'Economía', 1),
  (7, 17, 'CAD', 1),
  (8, 22, 'Artes', 1),
  (17, 0, 'Por asignar', 0)
;

-- --------------------------------------------------------------------------------------------------
-- Grupos de talleres con su horario fijo
-- --------------------------------------------------------------------------------------------------
INSERT INTO grupos_taller (id, numero, nombre, hora_inicio, hora_fin, activo) VALUES
  (1, 1, 'Taller 1', '10:00:00', '11:00:00', 1),
  (2, 2, 'Taller 2', '11:00:00', '12:00:00', 1),
  (3, 3, 'Taller 3', '12:00:00', '13:00:00', 1),
  (4, 4, 'Taller 4', '13:00:00', '14:00:00', 1),
  (5, 5, 'Taller 5', '15:00:00', '16:00:00', 1),
  (6, 6, 'Taller 6', '16:00:00', '17:00:00', 1)
;

-- --------------------------------------------------------------------------------------------------
-- Alumnos auditores (123)
-- Comentario: horario · matrícula · fuente · notas.
-- --------------------------------------------------------------------------------------------------
INSERT INTO alumnos (id, nombre, apellidos, horario_id, activo, creado_por, creado_en, actualizado_en) VALUES
  (1, 'Mariano', 'Jelwan', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00584071 · reporte y Excel
  (2, 'Marco Adrián', 'Macías Leal', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00508095 · reporte y Excel
  (3, 'Brenda', 'León Mendoza', 1, 0, 1, '2026-10-14 18:05:39', '2026-10-14 15:03:48'),  -- Matutino · matrícula 00480840 · reporte y Excel
  (4, 'Jose Carlos', 'Andrade Valle', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00552520 (reporte) / 00552530 (Excel) · reporte y Excel · REVISAR: columna A = 00552520, columna C = 552530; se usó C (escrita a mano)
  (5, 'Santiago', 'Aguilar Ramírez', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00520153 · reporte y Excel
  (6, 'Ivanna', 'Sánchez Bellan', 3, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Todo el día · matrícula 00510759 · reporte y Excel
  (7, 'Emilio', 'Arriaga Guzman', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:27:22'),  -- Matutino · matrícula 00478974 · reporte y Excel
  (8, 'Saúl Emiliano', 'Barrios Dorantes', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00510568 · reporte y Excel
  (9, 'Ximena', 'Cortés Ramírez', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00502841 · reporte y Excel
  (10, 'María José', 'Mattig Martínez', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00509127 · reporte y Excel
  (11, 'Laura Yazmín', 'García Sánchez', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00558094 · reporte y Excel
  (12, 'Erika', 'Mendoza Castillejos', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00513481 · reporte y Excel
  (13, 'Erick Leonardo', 'Mena Páez', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00520822 · reporte y Excel
  (14, 'Inés', 'Pérez Téllez Rojo', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00514843 · reporte y Excel
  (15, 'Astrid', 'Pellico Osthoff', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00499421 · reporte y Excel
  (16, 'Victoria', 'Viglione Brito', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00626980 · reporte y Excel
  (17, 'Sofía', 'Rodríguez Pérez', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00584407 · solo reporte
  (18, 'Camila Anahí', 'Sifuentes Botello', 3, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Todo el día · matrícula 00557309 · reporte y Excel
  (19, 'Azul Alessandra', 'Cedillo García', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00584256 · reporte y Excel
  (20, 'Mariana', 'Corral Piana', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00547377 · reporte y Excel
  (21, 'Ximena', 'Galván Reyna', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00506753 · reporte y Excel
  (22, 'Ana Cristina', 'Dávila Domínguez', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00548827 · reporte y Excel
  (23, 'Pedro', 'Guereschi', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00554515 · reporte y Excel
  (24, 'Roselyn', 'Flores Higuera', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00580565 · reporte y Excel
  (25, 'Diego', 'García Lascurain García', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00470951 · reporte y Excel
  (26, 'Jorge Darío', 'González López', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00521631 · reporte y Excel
  (27, 'Estefanía', 'Quintero Rodríguez', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00508157 · reporte y Excel
  (28, 'Isabel', 'Montaño Guadarrama', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00585826 · reporte y Excel
  (29, 'Renée', 'Mena Martínez', 3, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:42'),  -- Todo el día · matrícula 00481882 · reporte y Excel · Vespertino en el reporte, Todo el día en el Excel
  (30, 'Ximena', 'Rivera Bazán', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00592337 · reporte y Excel
  (31, 'Samantha Rachel', 'Vazquez Sanchez', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00518183 · reporte y Excel
  (32, 'Valeria', 'Cortés Arellano', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00635256 · reporte y Excel
  (33, 'Eduardo', 'Abreu', 2, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Vespertino · matrícula 00546196 · reporte y Excel
  (34, 'Elena', 'Rivera González', 1, 1, 1, '2026-10-14 18:05:39', '2026-10-14 18:05:39'),  -- Matutino · matrícula 00544864 · reporte y Excel
  (35, 'Derek Alexander', 'Oliva Contreras', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00486973 · solo Excel
  (36, 'Antonio', 'Rodríguez Hernández', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00502906 · solo Excel · REVISAR: ¿es el mismo que Antonio Rodrigez Hernandez (#163)?
  (37, 'Natalia', 'Bernal', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00512137 · solo Excel
  (38, 'Andrea Romero', 'Castany Bañuelos', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00513730 · solo Excel
  (39, 'Joel', 'Sánchez Díaz', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00579433 · solo Excel
  (40, 'Regina', 'Delgado Guerrero', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00584201 · solo Excel
  (41, 'Diego', 'Chávez Espinosa', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00586083 · solo Excel
  (42, 'Victoria', 'Garza Chavez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00586090 · solo Excel
  (43, 'Renata', 'Galván Naranjo', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00592364 · solo Excel
  (44, 'Valeria', 'Garza Chavez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00594717 · solo Excel
  (45, 'Gustavo', 'Castillo', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00598951 · solo Excel
  (46, 'Luis Eduardo', 'Castillo Feregrino', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00609879 · solo Excel
  (47, 'Sebastian', 'Diaz Aguero', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00610722 · solo Excel
  (48, 'Alitzel', 'Álvarez García', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00612962 · solo Excel
  (49, 'Leonardo', 'Meza Benitez', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00613483 · solo Excel
  (50, 'Myranda Mariel', 'Hernandez Amezola', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00613710 · solo Excel
  (51, 'Manuel', 'Garcia Santaularia', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00615825 · solo Excel
  (52, 'Karla', 'Gutiérrez Cordova', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00615874 · solo Excel
  (53, 'Regina', 'Malvaez Méndez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00615928 · solo Excel
  (54, 'Mariana', 'Castañeda Menéndez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00616176 · solo Excel
  (55, 'Gabriel', 'Ruiz Rivera', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00618382 · solo Excel
  (56, 'Gael', 'Santos Pérez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00618663 · solo Excel
  (57, 'Oscar Adrian', 'Arango González', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00619123 · solo Excel
  (58, 'Sophia', 'Garcia Elias', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00619878 · solo Excel
  (59, 'Camila Sofía', 'Yáñez Hernández', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00620096 · solo Excel
  (60, 'Ricardo', 'Rodríguez Valencia', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00621118 · solo Excel · REVISAR: columna A = 0062118, columna C = 621118; se usó C (escrita a mano)
  (61, 'Diego', 'Almanza Repetto', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00621243 · solo Excel
  (62, 'Barbara', 'Becerril Villalobos', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00621267 · solo Excel
  (63, 'Karla Daniela', 'Camargo Medina', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00622018 · solo Excel
  (64, 'Irving', 'Martínez Nuñez', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00622251 · solo Excel
  (65, 'Alan Eduardo', 'Esparza Alanís', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00622391 · solo Excel
  (66, 'Isabella', 'Rodríguez Martínez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00622479 · solo Excel
  (67, 'Santiago', 'Cardosa García', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00623289 · solo Excel
  (68, 'Regina', 'Aburto', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00623328 · solo Excel
  (69, 'Nadia', 'Molina', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00623520 · solo Excel
  (70, 'María Fernanda', 'Vargas Jimenez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00623914 · solo Excel
  (71, 'Bruno Santino', 'Ibarra Camargo', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00624095 · solo Excel
  (72, 'Karla Luciana', 'Valencia Victoria', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624216 · solo Excel
  (73, 'Renée Sofia', 'Velázquez Lara', 3, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Todo el día · matrícula 00624274 · solo Excel · REVISAR: figuraba en mañana (7:00 a 14:00) y tarde (13:00 a 19:00): se unió en 7:00 a 19:00
  (74, 'Natalia', 'Santana Acevedo', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00624276 · solo Excel
  (75, 'Santiago', 'Gómez Ancona', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624329 · solo Excel
  (76, 'Patricio', 'Belaunzarán Leyva', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624549 · solo Excel
  (77, 'Grecia Sofía', 'Torres Hernández', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624591 · solo Excel
  (78, 'Juan Pablo', 'Aburto Luckie', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624619 · solo Excel
  (79, 'Nicole', 'Suastegui Rosette', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00624687 · solo Excel
  (80, 'Carlos Alain', 'Correa Orozco', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00624900 · solo Excel
  (81, 'Ana Sofía', 'Rodríguez Ramírez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00625016 · solo Excel
  (82, 'Aranza', 'Espindola Castillo', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00625031 · solo Excel
  (83, 'Monica Jazmin', 'Estela Rincon', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00625239 · solo Excel
  (84, 'Constanza Valeria', 'Cervantes Chanfreau', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00625276 · solo Excel
  (85, 'Erika Vannya', 'Solís Mendizábal', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00625411 · solo Excel
  (86, 'Ivana', 'Reyes Querol', 2, 0, 1, '2026-10-14 18:05:42', '2026-10-14 15:04:05'),  -- Vespertino · matrícula 00625416 · solo Excel
  (87, 'Edgar', 'Barragán Hernández', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00625879 · solo Excel
  (88, 'Carolina', 'Morales Ortiz', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00625991 · solo Excel
  (89, 'Marcela', 'Gama Iracheta', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00626922 · solo Excel
  (90, 'Mario Eduardo', 'Mendoza Dolores', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00627883 · solo Excel
  (91, 'Astrid Dessireé', 'Bautista Almaraz', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00627996 · solo Excel
  (92, 'Vanessa', 'Grimaldo Paulin', 3, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Todo el día · matrícula 00629093 · solo Excel
  (93, 'Sebastián', 'De Jesús Ocegeda', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00629385 · solo Excel
  (94, 'Diego', 'Beirute Buerba', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00630255 · solo Excel
  (95, 'Bernardo', 'Orozco Riaño', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00630285 · solo Excel
  (96, 'Lucía', 'Galván Torres', 3, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Todo el día · matrícula 00630759 · solo Excel
  (97, 'Regina Estefanía', 'Cortés Enllanche', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00631249 · solo Excel
  (98, 'Carlo Bruno', 'Garcia Sanchez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00631908 · solo Excel
  (99, 'Jimena', 'Arias', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00632812 · solo Excel · REVISAR: columna A = 632812, columna C = 00632813; se usó A (escrita a mano)
  (100, 'Nicolle Alejandra', 'Dorta', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00632865 · solo Excel
  (101, 'Renata', 'Ruiz Gómez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00632869 · solo Excel
  (102, 'Rodrigo', 'Bravo López', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00633328 · solo Excel
  (103, 'Jorge Daniel', 'Álvarez Damián', 2, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Vespertino · matrícula 00633715 · solo Excel
  (104, 'Juan Pablo', 'Solis Hernández', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00633989 · solo Excel
  (105, 'Jacqueline', 'Fernández Colin', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00634663 · solo Excel
  (106, 'Regina', 'Espinosa Reyes', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00635110 · solo Excel
  (107, 'Camila', 'Pérez García', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00635432 · solo Excel
  (108, 'María Fernanda', 'Gómez y Mora', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00635820 · solo Excel
  (109, 'Camila del Carmen', 'Salinas Villaseñor', 3, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Todo el día · matrícula 00636333 · solo Excel
  (110, 'Kamila Valentina', 'Vargas Corominas', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00636895 · solo Excel
  (111, 'Karen Nicole', 'Bertín Lobato', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00637489 · solo Excel
  (112, 'Estefania', 'Antunez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00637563 · solo Excel
  (113, 'Alonso', 'Cuellar Sanchez', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00638012 · solo Excel
  (114, 'Isabella', 'Cano Guanilo', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00638397 · solo Excel
  (115, 'Santiago Emiliano', 'Terrón Casas', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00640027 · solo Excel
  (116, 'Leonardo', 'Monreal Astorga', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00640170 · solo Excel
  (117, 'Samara', 'Hernández Barrón', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00640978 · solo Excel
  (118, 'Danna Itzel', 'Castillo Ibarra', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00642295 · solo Excel
  (119, 'Javier', 'Navarro Isunza', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00642707 · solo Excel
  (120, 'Igal', 'Rosemberg Vincze', 1, 1, 1, '2026-10-14 18:05:42', '2026-10-14 18:05:42'),  -- Matutino · matrícula 00646026 · solo Excel
  (162, 'Erik', 'Escobedo', 2, 1, 1, '2026-10-14 15:04:37', '2026-10-14 15:04:37'),  -- Vespertino · sin matrícula · SIN FUENTE: dado de alta en la aplicación el 2026-10-14 15:04:37
  (163, 'Antonio', 'Rodrigez Hernandez', 1, 1, 1, '2026-10-14 15:04:53', '2026-10-14 15:04:53'),  -- Matutino · sin matrícula · SIN FUENTE: dado de alta en la aplicación el 2026-10-14 15:04:53 · REVISAR: ¿es el mismo que Antonio Rodríguez Hernández (#36)?
  (164, 'Samantha', 'Hernandez', 1, 1, 1, '2026-10-14 15:05:19', '2026-10-14 15:05:19')  -- Matutino · sin matrícula · SIN FUENTE: dado de alta en la aplicación el 2026-10-14 15:05:19
;

-- --------------------------------------------------------------------------------------------------
-- Talleres (200)
-- Comentario: carrera · grupo · responsable (del registro de talleres del Día OV 2026).
-- --------------------------------------------------------------------------------------------------
INSERT INTO actividades (id, nombre, carrera_id, edificio_id, grupo_id, fecha, hora_inicio, hora_fin, estado, motivo_no_realizado, activo, creado_por, creado_en, actualizado_en) VALUES
  (1, 'Wall Street: conviértete en inversionista por 45 minutos', 1, 5, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Actuaría · Taller 1 · responsable: Jennifer Rangel Madariaga
  (2, 'Detectives de funciones: resolviendo un misterio matemático', 1, 5, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Actuaría · Taller 2 · responsable: Jennifer Rangel Madariaga
  (3, 'Programando en R: así trabaja un actuario con datos reales', 1, 5, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Actuaría · Taller 3 · responsable: Jennifer Rangel Madariaga
  (4, 'Wall Street: conviértete en inversionista por 45 minutos', 1, 5, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Actuaría · Taller 5 · responsable: Jennifer Rangel Madariaga
  (5, 'Mario Kart: Carrera por el mundo', 20, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Administración Turística · Taller 1 · responsable: Blanca Estela Correa Guevara
  (6, 'Escape Room: en busca de Harry Styles', 20, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Administración Turística · Taller 2 · responsable: Blanca Estela Correa Guevara
  (7, 'Influencers de destinos: el reto del marketing turístico', 20, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Administración Turística · Taller 3 · responsable: Blanca Estela Correa Guevara
  (8, 'world hospitality challenge', 20, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Administración Turística · Taller 4 · responsable: Blanca Estela Correa Guevara
  (9, 'World Hospitality Challenge', 20, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Administración Turística · Taller 5 · responsable: Blanca Estela Correa Guevara
  (10, 'IA Challenge: Crea el futuro hoy.', 2, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:15:27'),  -- Administración y Dirección de Empresas · Taller 1 · responsable: Pilar Madrazo Lemarroy
  (11, 'WISTAR EN LA NIEVE', 2, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:15:45'),  -- Administración y Dirección de Empresas · Taller 2 · responsable: Pilar Madrazo Lemarroy
  (12, 'Laboratorio de Ideas de Negocio', 2, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:15:59'),  -- Administración y Dirección de Empresas · Taller 3 · responsable: Pilar Madrazo Lemarroy
  (13, 'Business workshop', 2, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:17:20'),  -- Administración y Dirección de Empresas · Taller 4 · responsable: Pilar Madrazo Lemarroy
  (14, 'Business workshop', 2, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 14:17:33'),  -- Administración y Dirección de Empresas · Taller 5 · responsable: Pilar Madrazo Lemarroy
  (15, 'Dibujo Arquitectónico', 3, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 1 · responsable: RODRIGO SHIORDIA LOPEZ
  (16, 'Maquetas volumétricas en arquitectura', 3, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 2 · responsable: RODRIGO SHIORDIA LOPEZ
  (17, 'Estructuras Arquitectónicas', 3, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 3 · responsable: RODRIGO SHIORDIA LOPEZ
  (18, 'Misión Verde: Diseña la ciudad del futuro.', 3, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 4 · responsable: RODRIGO SHIORDIA LOPEZ
  (19, 'Arquitecto por una hora', 3, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 5 · responsable: RODRIGO SHIORDIA LOPEZ
  (20, 'Estructuras arquitectónicas (Opcional)', 3, 7, 6, '2026-10-15', '16:00:00', '17:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura · Taller 6 · responsable: RODRIGO SHIORDIA LOPEZ
  (21, 'El color en en el espacio interior', 21, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura de interiores · Taller 1 · responsable: Liliana Naranjo Merino
  (22, 'Maquetas para interiorismo', 21, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura de interiores · Taller 2 · responsable: Liliana Naranjo Merino
  (23, 'Diseñando un espacio con IA', 21, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura de interiores · Taller 3 · responsable: Liliana Naranjo Merino
  (24, 'El color en el espacio interior', 21, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura de interiores · Taller 4 · responsable: Liliana Naranjo Merino
  (25, 'Maquetas para interiorismo', 21, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Arquitectura de interiores · Taller 5 · responsable: Liliana Naranjo Merino
  (26, 'Clase muestra: Apreciación artística del cine', 22, 8, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Artes Visuales · Taller 1 · responsable: Nicolás Vargas Lebert
  (27, 'Plantas tintoreas', 22, 8, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Artes Visuales · Taller 2 · responsable: Nicolas Vargas Lebert
  (28, 'Plantas tintoreas', 22, 8, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Artes Visuales · Taller 3 · responsable: Nicolas Vargas Lebert
  (29, 'Acuarela', 22, 8, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Artes Visuales · Taller 4 · responsable: Nicolas Vargas Lebert
  (30, 'Acuarela', 22, 8, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Artes Visuales · Taller 5 · responsable: Nicolas Vargas Lebert
  (31, 'Gram Challenge', 23, 4, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Biotecnología · Taller 1 · responsable: Ana Elena Huerta González
  (32, 'Conociendo a los Hongos', 23, 4, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Biotecnología · Taller 2 · responsable: Ana Elena Huerta González
  (33, 'ADN a la Vista', 23, 4, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Biotecnología · Taller 3 · responsable: Ana Elena Huerta González
  (34, 'ADN a la Vista', 23, 4, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Biotecnología · Taller 4 · responsable: Ana Elena Huerta González
  (35, 'Protein Hunters', 23, 4, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Biotecnología · Taller 5 · responsable: Ana Elena Huerta González
  (36, 'Pronósticos futboleros para actuarios y científicos de datos', 24, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ciencia de Datos · Taller 1 · responsable: Jennifer Rangel Madariaga
  (37, 'Modelando el futuro con patrones y datos', 24, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ciencia de Datos · Taller 2 · responsable: Jennifer Rangel Madariaga
  (38, 'Dados, canicas y magia', 24, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ciencia de Datos · Taller 4 · responsable: Jennifer Rangel Madariaga
  (39, 'Cómo hacer un Product Shot', 4, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación · Taller 2 · responsable: Ana Pamela Flores Herrera
  (40, 'Del Play al Podcast, Vive la experiencia de producir en Radio Anáhuac.', 4, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación · Taller 2 · responsable: Ana Pamela Flores Herrera
  (41, 'Cómo hacer un Product Shot', 4, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación · Taller 3 · responsable: Ana Pamela Flores Herrera
  (42, 'Tu Voz, Tu Marca', 4, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación · Taller 3 · responsable: Ana Pamela Flores Herrera
  (43, 'Investigando la comunicación con Realidad extendida', 25, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación, Dirección de Empresas de Entretenimiento · Taller 1 · responsable: Ana Pamela Flores Herrera
  (44, 'Branding 101: crea una marca que todos recuerden', 25, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Comunicación, Dirección de Empresas de Entretenimiento · Taller 4 · responsable: Ana Pamela Flores Herrera
  (45, 'Simulación de juicio oral', 5, 1, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Derecho · Taller 1 · responsable: María Concepción de Lourdes González González
  (46, 'El Arte de la Justicia: ¿Cómo piensa un abogado?', 5, 1, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Derecho · Taller 3 · responsable: María Concepción de Lourdes González González
  (47, 'Diseñando una política pública', 5, 1, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Derecho · Taller 4 · responsable: María Concepción de Lourdes González González
  (48, 'Simulacion de audiencia oral (Opcional)', 5, 1, 6, '2026-10-15', '16:00:00', '17:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Derecho · Taller 6 · responsable: María Concepción de Lourdes González González
  (49, 'El Pitch del Entretenimiento', 26, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Empresas de Entretenimiento · Taller 1 · responsable: Ana Pamela Flores Herrera
  (50, 'Héroes, villanos y personajes inolvidables', 26, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Empresas de Entretenimiento · Taller 4 · responsable: Ana Pamela Flores Herrera
  (51, 'Branding 101: crea una marca que todos recuerden', 26, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Empresas de Entretenimiento · Taller 5 · responsable: Ana Pamela Flores Herrera
  (52, 'Preparación de mocktails', 27, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Restaurantes · Taller 1 · responsable: Juan José Gómez Díaz
  (53, 'Lego Serious Play para la creación de un concepto restaurantero', 27, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Restaurantes · Taller 2 · responsable: Juan José Gómez Díaz
  (54, 'Lego Serious Play para la creación de concepto restaurantero.', 27, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Restaurantes · Taller 3 · responsable: Juan José Gómez Díaz
  (55, 'World Hospitality Challenge', 27, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Restaurantes · Taller 4 · responsable: Juan José Gómez Díaz
  (56, 'World Hospitality Challenge', 27, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección de Restaurantes · Taller 5 · responsable: Juan José Gómez Díaz
  (57, 'Money Lab', 28, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Financiera · Taller 3 · responsable: Selene Rocío Gaxiola Laso
  (58, 'Money Lab', 28, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Financiera · Taller 4 · responsable: Selene Rocío Gaxiola Laso
  (59, 'Money Lab', 28, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Financiera · Taller 5 · responsable: Selene Rocío Gaxiola Laso
  (60, 'Lego Serious Play para creación de Restaurante', 29, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Internacional de Hoteles · Taller 1 · responsable: Juan Jose Gomez Diaz
  (61, 'Tendencias en hotelería y servicios de hospedaje', 29, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Internacional de Hoteles · Taller 2 · responsable: Juan José Gómez Díaz
  (62, 'Servicios de lujo y vanguardia en hospitalidad', 29, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Internacional de Hoteles · Taller 3 · responsable: Juan José Gómez Díaz
  (63, 'World Hospitality Challenge', 29, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Internacional de Hoteles · Taller 4 · responsable: Juan José Gómez Díaz
  (64, 'World Hospitality Challenge', 29, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección Internacional de Hoteles · Taller 5 · responsable: Juan José Gómez Díaz
  (65, 'Sports Marketing - controla el juego!', 30, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección y Administración del Deporte · Taller 1 · responsable: Francisco Javier Ruiz Pérez
  (66, 'Sports Marketing- Controla el juego', 30, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección y Administración del Deporte · Taller 2 · responsable: Francisco Javier Ruiz Pérez
  (67, 'Organización de eventos deportivos', 30, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección y Administración del Deporte · Taller 4 · responsable: Francisco Javier Ruiz Pérez
  (68, 'Organización de eventos deportivos', 30, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Dirección y Administración del Deporte · Taller 5 · responsable: Francisco Javier Ruiz Pérez
  (69, 'Moda Inteligente: Explorando el Diseño de Moda con Inteligencia Artificial', 31, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño de Moda e Innovación y Tendecia · Taller 1 · responsable: Ana María López Balbín
  (70, 'Coolhunting / Cazando tendencias', 31, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño de Moda e Innovación y Tendecia · Taller 2 · responsable: Ana Maria Lopez Balbin
  (71, 'Arquitectura vestible', 31, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño de Moda e Innovación y Tendecia · Taller 3 · responsable: Ana Maria López Balbín
  (72, 'Photoshoot de Moda', 31, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño de Moda e Innovación y Tendecia · Taller 5 · responsable: Ana María López Balbín
  (73, 'Make your Mark / Diseña una firma que hable por ti', 6, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Gráfico · Taller 1 · responsable: María del Carmen Razo Aguilar
  (74, 'Make it Iconic / Convierte un objeto en algo extraordinario', 6, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Gráfico · Taller 2 · responsable: María del Carmen Razo Aguilar
  (75, 'Build it. Bind it / Crea tu propio sketchbook', 6, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Gráfico · Taller 3 · responsable: María del Carmen Razo Aguilar
  (76, 'The Cover Edit / Conviértete en Director de Arte por un día', 6, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Gráfico · Taller 4 · responsable: María del Carmen Razo Aguilar
  (77, 'Pop your Brand / Diseña el próximo refresco', 6, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Gráfico · Taller 5 · responsable: María del Carmen Razo Aguilar
  (78, 'Design LAB', 32, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Industrial · Taller 1 · responsable: Adrián Rosado López
  (79, 'ART TOY', 32, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Industrial · Taller 3 · responsable: Adrián Rosado López
  (80, 'Design Lab', 32, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Industrial · Taller 4 · responsable: Adrián Rosado López
  (81, 'Art Toy', 32, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Industrial · Taller 5 · responsable: Adrián Rosado López
  (82, 'Técnicas de Animación en medio profesional', 33, 7, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Multimedia · Taller 1 · responsable: Jorge Erick Campos Baigts
  (83, 'Diseño de Personajes', 33, 7, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Multimedia · Taller 2 · responsable: Jorge Erick Campos Baigts
  (84, 'Anima a un personaje en 3D', 33, 7, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Multimedia · Taller 3 · responsable: Jorge Erick Campos Baigts
  (85, 'Diseño de personajes y ambientes con RA e inteligencia Artificial', 33, 7, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Multimedia · Taller 4 · responsable: Jeyvan Sánchez Mendoza
  (86, 'Creación de videojuegos y entornos virutales', 33, 7, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Diseño Multimedia · Taller 5 · responsable: jeyvan.sanchezme@anahuac.mx
  (87, 'LaunchLab AI :From Idea to Impact', 34, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Engineering Management · Taller 1 · responsable: Norma Lorena Diego González
  (88, 'LaunchLab AI : From Idea to Impact', 34, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Engineering Management · Taller 2 · responsable: Norma Lorena Diego González
  (89, 'LaunchLab AI: From Idea to Impact', 34, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Engineering Management · Taller 3 · responsable: Norma Lorena Diego González
  (90, 'EL JUEGO DE LAS FINANZAS', 8, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Finanzas y Contaduría Pública · Taller 1 · responsable: Selene Rocío Gaxiola Laso
  (91, 'El juego de las finanzas', 8, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Finanzas y Contaduría Pública · Taller 2 · responsable: Selene Rocío Gaxiola Laso
  (92, 'El Arte de la Focaccia: de la Masa al Horno', 35, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Gastronomía · Taller 1 · responsable: María Teresa Hernández del Pando
  (93, 'Aguachile Lab: Frescura, Fuego y Sabor', 35, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Gastronomía · Taller 2 · responsable: María Teresa Hernández del Pando
  (94, 'Choux Magic: El Secreto de la Pastelería Francesa', 35, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Gastronomía · Taller 3 · responsable: María Teresa Hernández del Pando
  (95, 'El Arte de la Focaccia: De la Masa al Horno', 35, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Gastronomía · Taller 4 · responsable: María Teresa Hernández del Pando
  (96, 'Aguachile Lab: Frescura, Fuego y Sabor', 35, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Gastronomía · Taller 5 · responsable: María Teresa Hernández del Pando
  (97, 'Gabinete Nova', 36, 1, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Development · Taller 1 · responsable: Gerardo Trujano
  (98, 'GABINETE NOVA', 36, 1, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Development · Taller 2 · responsable: Gerardo Trujano
  (99, 'GABINETE NOVA', 36, 1, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Development · Taller 3 · responsable: Gerardo Trujano
  (100, 'Misión Rediseñar el Mundo', 36, 1, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Development · Taller 4 · responsable: Gerardo Trujano
  (101, '¿Qué país quieres construir?', 36, 1, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Development · Taller 5 · responsable: Gerardo Trujano
  (102, 'Global Bids for Business Startup', 37, 1, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Management · Taller 1 · responsable: Nicole Goldberg Dalma
  (103, 'Global Bids for Business Startup', 37, 1, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Management · Taller 2 · responsable: Nicole Goldberg Dalma
  (104, 'Global Bids for Business Startup', 37, 1, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Management · Taller 3 · responsable: Nicole Goldberg Dalma
  (105, 'Global Bids for Business Startup', 37, 1, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Global Management · Taller 5 · responsable: Nicole Goldberg Dalma
  (106, '¿Qué hace el historiador? Detectives del Pasado', 38, 1, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Historia · Taller 1 · responsable: Karla Sandoval Téllez
  (107, 'Historia en acción: El trabajo del historiador en la gestión cultural', 38, 1, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Historia · Taller 2 · responsable: Karla Sandoval Téllez
  (108, 'Historia en la pantalla: el historiador detrás de las películas y los videojuegos. Divulgación histórica.', 38, 1, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Historia · Taller 3 · responsable: Karla Sandoval Téllez
  (109, 'Reconstruir el pasado a partir de la evidencia arqueológica', 38, 1, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Historia · Taller 4 · responsable: Karla Sandoval Téllez
  (110, 'Descifrando el arte: el trabajo del historiador en el mundo del arte', 38, 1, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Historia · Taller 5 · responsable: Karla Sandoval Téllez
  (111, 'Conviértete en Ingenier@ Ambiental: Resuelve un caso real', 39, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Ambiental · Taller 1 · responsable: Alejandra Maribel Barragán Martínez
  (112, 'Basura que genera combustible: El poder del hidrógeno', 39, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Ambiental · Taller 2 · responsable: Alejandra Maribel Barragán Martínez
  (113, 'Energía en el mapa: Planeando un parque solar', 39, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Ambiental · Taller 3 · responsable: Alejandra Maribel Barragán Martínez
  (114, 'Ingeniería del viento: Optimizando un aerogenerador con el kit KidWind', 39, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Ambiental · Taller 4 · responsable: Alejandra Maribel Barragán Martínez
  (115, 'Energía del futuro: Del viento al hidrógeno', 39, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Ambiental · Taller 5 · responsable: Alejandra Maribel Barragán Martínez
  (116, 'Laparoscopía: un método no muy invasivo para intervenir quirúrgicamente', 40, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Biomédica · Taller 1 · responsable: José Ramón Alvarez Bada
  (117, '¿Qué hace la electricidad con nuestros cuerpos?', 40, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Biomédica · Taller 2 · responsable: José Ramón Alvarez Bada
  (118, 'Simuladores médicos', 40, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Biomédica · Taller 3 · responsable: José Ramón Alvarez Bada
  (119, 'Osciloscopios: señales chiquitas para divertirse bastante', 40, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Biomédica · Taller 4 · responsable: José Ramón Alvarez Bada
  (120, 'Bioinformática: bases de datos, gusanitos y antepasados distantes', 40, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Biomédica · Taller 5 · responsable: José Ramón Alvarez Bada
  (121, 'Muros antisísmicos', 9, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Civil · Taller 1 · responsable: Joseph Salvador Guevara Flores
  (122, 'Presas y control de inundaciones', 9, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Civil · Taller 2 · responsable: Joseph Guevara Flores
  (123, 'RETO PUENTE', 9, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Civil · Taller 3 · responsable: JOSEPH SALVADOR GUEVARA FLORES
  (124, 'Ciudad resiliente', 9, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Civil · Taller 4 · responsable: Joseph Salvador Guevara Flores
  (125, 'Diseño de Pavimentos', 9, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Civil · Taller 5 · responsable: Joseph Salvador Guevara Flores
  (126, 'Mi primera Base de Datos con SQL', 12, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería en Sistemas y Tecnologías de la Información · Taller 1 · responsable: Miguel Ángel Méndez Méndez
  (127, 'Arduino como controlador de luz de colores', 12, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería en Sistemas y Tecnologías de la Información · Taller 2 · responsable: Miguel Ángel Méndez Méndez
  (128, 'Programación de un fotomosaico en Python', 12, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería en Sistemas y Tecnologías de la Información · Taller 3 · responsable: Miguel Ángel Méndez Méndez
  (129, '¡Conéctate! Arma y prueba tu cable de red', 12, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería en Sistemas y Tecnologías de la Información · Taller 4 · responsable: Miguel Ángel Méndez Méndez
  (130, 'Arduino como controlador de luz de colores', 12, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería en Sistemas y Tecnologías de la Información · Taller 5 · responsable: Miguel Ángel Méndez Méndez
  (131, 'Snack Lab', 10, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Industrial para la Dirección · Taller 1 · responsable: Brenda María Retana Blanco
  (132, 'VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL', 10, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Industrial para la Dirección · Taller 2 · responsable: Brenda María Retana Blanco
  (133, 'Snack Lab', 10, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Industrial para la Dirección · Taller 3 · responsable: Brenda María retana Blanco
  (134, 'VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL', 10, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Industrial para la Dirección · Taller 4 · responsable: Brenda María Retana Blanco
  (135, 'Snack Lab', 10, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Industrial para la Dirección · Taller 5 · responsable: Brenda María Retana Blanco
  (136, 'Taller Práctico de Electrónica Modular', 11, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Mecatrónica · Taller 1 · responsable: Dr. Leon Hamui Balas
  (137, 'Taller Práctico de electrónica Modular con Impresión 3D', 11, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Mecatrónica · Taller 2 · responsable: Leon Hamui
  (138, 'Taller Práctico de Electrónica Modular', 11, 2, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Mecatrónica · Taller 3 · responsable: Dr. Leon Hamui Balas
  (139, 'Taller Práctico de electrónica Modular con Impresión 3D', 11, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Mecatrónica · Taller 4 · responsable: Leon Hamui
  (140, 'Taller Práctico de electrónica Modular con Impresión 3D', 11, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Mecatrónica · Taller 5 · responsable: leon Hamui
  (141, '¡Crea, Formula y Emprende! Diseña tu pasta de dientes', 41, 2, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Química · Taller 1 · responsable: Alma Delia Rojas Rodríguez
  (142, '¡Crea, Formula y Emprende! Diseña tu pasta de dientes', 41, 2, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Química · Taller 2 · responsable: Alma Delia Rojas Rodríguez
  (143, '¡Crea, Formula y Emprende! Diseña tu pasta de dientes', 41, 2, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Química · Taller 4 · responsable: Alma Delia Rojas Rodríguez
  (144, '¡Crea, Formula y Emprende! Diseña tu pasta de dientes', 41, 2, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Ingeniería Química · Taller 5 · responsable: Alma Delia Rojas Rodríguez
  (145, 'OperandoAndo', 13, 4, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 1 · responsable: Mariana Alvarez Sanchez
  (146, 'OperandoAndo', 13, 4, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 2 · responsable: Mariana Alvarez Sanchez
  (147, 'ER: Ralidad o Ficción', 13, 4, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 3 · responsable: Mariana Alvarez Sanchez
  (148, 'OperandoAndo', 13, 4, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 4 · responsable: Mariana Alvarez Sánchez
  (149, 'OperandoAndo', 13, 4, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 5 · responsable: Mariana Alvarez Sanchez
  (150, 'ER:Realidad o Ficción (Opcional)', 13, 4, 6, '2026-10-15', '16:00:00', '17:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano · Taller 6 · responsable: Mariana Alvarez Sanchez
  (151, 'Toma de impresiones', 42, 4, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano Dentista · Taller 1 · responsable: Antonio copin
  (152, 'Modelado y creatividad', 42, 4, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano Dentista · Taller 2 · responsable: Antonio copin
  (153, 'Simulación en odontologia', 42, 4, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano Dentista · Taller 3 · responsable: Antonio copin
  (154, 'Simulación en odontologia', 42, 4, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano Dentista · Taller 4 · responsable: Antonio copin
  (155, 'Toma de impresiones', 42, 4, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Médico Cirujano Dentista · Taller 5 · responsable: Antonio Copin
  (156, 'BrandLab: Análisis Estratégico de Lego con IA', 14, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Mercadotecnia Estratégica · Taller 1 · responsable: Luz Cecilia Revilla Soriano
  (157, 'BrandLab: Análisis Estratégico de Nike con IA', 14, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Mercadotecnia Estratégica · Taller 2 · responsable: Luz Cecilia Revilla Soriano
  (158, 'BrandLab: Análisis Estratégico de Coca Cola con IA', 14, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Mercadotecnia Estratégica · Taller 3 · responsable: Luz Cecilia Revilla Soriano
  (159, 'BrandLab: Análisis Estratégico de L’Oreal con IA', 14, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Mercadotecnia Estratégica · Taller 4 · responsable: Luz Cecilia Revilla Soriano
  (160, 'BrandLab: Análisis Estratégico de Cinépolis con IA (Opcional)', 14, 6, 6, '2026-10-15', '16:00:00', '17:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Mercadotecnia Estratégica · Taller 6 · responsable: Luz Cecilia Revilla Soriano
  (161, 'Clase abierta: Técnicas de producción digital', 43, 8, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Música Contemporánea · Taller 1 · responsable: Itia Domínguez Rosales
  (162, 'Workshop ensamble musical', 43, 8, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Música Contemporánea · Taller 2 · responsable: Itia Domínguez Rosales
  (163, 'Workshop ensamble musical', 43, 8, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Música Contemporánea · Taller 3 · responsable: Itia Domínguez Rosales
  (164, 'Clase muestra de piano', 43, 8, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Música Contemporánea · Taller 4 · responsable: Itia Dominguez Rosales
  (165, 'Workshop ensamble musical', 43, 8, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Música Contemporánea · Taller 5 · responsable: Itia Domínguez Rosales
  (166, 'Construyendo mi negocio internacional', 15, 6, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Negocios Internacionales · Taller 1 · responsable: Natalia Rodríguez Avendaño
  (167, 'Construyendo mi negocio internacional', 15, 6, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Negocios Internacionales · Taller 2 · responsable: Natalia Rodríguez Avendaño
  (168, 'Construyendo mi negocio internacional', 15, 6, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Negocios Internacionales · Taller 3 · responsable: Natalia Rodríguez Avendaño
  (169, 'Construyendo mi negocio internacional', 15, 6, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Negocios Internacionales · Taller 4 · responsable: Natalia Rodríguez Avendaño
  (170, 'Construyendo mi negocio internacional', 15, 6, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Negocios Internacionales · Taller 5 · responsable: Natalia Rodriguez Avendaño
  (171, 'Azúcar invisible: ¿te atreves a adivinar?', 16, 4, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Nutrición · Taller 1 · responsable: Magdalena Urrutia San Vicente
  (172, 'Sal oculta: el reto de las etiquetas', 16, 4, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Nutrición · Taller 2 · responsable: Magdalena Urrutia
  (173, 'Fuel your performance: taller práctico de nutrición deportiva', 16, 4, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Nutrición · Taller 3 · responsable: Magdalena Urrutia
  (174, 'Azúcar invisible: ¿te atreves a adivinar?', 16, 4, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Nutrición · Taller 4 · responsable: Magdalena Urrutia
  (175, 'Sal oculta: el reto de las etiquetas', 16, 4, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Nutrición · Taller 5 · responsable: Magdalena Urrutia
  (176, 'Pedagogía: Una Experiencia Inmersiva', 44, 3, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Pedagogía Organizacional y Educativa · Taller 1 · responsable: Susana Memun Zaga
  (177, 'Escenarios Pedagógicos', 44, 3, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Pedagogía Organizacional y Educativa · Taller 2 · responsable: Susana Memun Zaga
  (178, 'Pedagogía: Una Experiencia Inmersiva', 44, 3, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Pedagogía Organizacional y Educativa · Taller 3 · responsable: Susana Memun Zaga
  (179, 'Escenarios Pedagógicos', 44, 3, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Pedagogía Organizacional y Educativa · Taller 4 · responsable: Susana Memun Zaga
  (180, 'Pedagogía: Una Experiencia Inmersiva', 44, 3, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Pedagogía Organizacional y Educativa · Taller 5 · responsable: Susana Memun Zaga
  (181, 'Neurolab: Descifrando el Sueño', 17, 3, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Psicología · Taller 1 · responsable: Ana Paula Sánchez Alegría
  (182, 'Detrás del Espejo: Psicología en acción', 17, 3, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Psicología · Taller 2 · responsable: Ana Paula Sánchez
  (183, 'Neurolab: Descifrando el sueño', 17, 3, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Psicología · Taller 3 · responsable: Ana Paula Sánchez
  (184, 'Detrás del espejo: Psicología en acción', 17, 3, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Psicología · Taller 4 · responsable: Ana Paula Sánchez Alegría
  (185, 'Neurolab: Descifrando el sueño', 17, 3, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Psicología · Taller 5 · responsable: Ana Paula Sánchez Alegría
  (186, 'Secuestro de Futbolistas en el Mundial', 18, 1, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Relaciones Internacionales · Taller 1 · responsable: YOANNA SHUBICH
  (187, 'Tiktok Geopolítico: Convierte una crisis mundial en un video viral', 18, 1, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Relaciones Internacionales · Taller 2 · responsable: Yoanna Shubich Green
  (188, 'Guerra por los minerales críticos: ¿qué hay detrás de tu celular?', 18, 1, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Relaciones Internacionales · Taller 3 · responsable: Yoanna Shubich Green
  (189, 'Secuestro de Futbolistas en el Mundial', 18, 1, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Relaciones Internacionales · Taller 4 · responsable: YOANNA SHUBICH
  (190, '¿Qué país quieres construir?', 18, 1, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Relaciones Internacionales · Taller 5 · responsable: Ximena Castellanos
  (191, 'Training teatral', 45, 8, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Teatro y Actuación · Taller 1 · responsable: Mtro. Antonio Rojas Villarreal
  (192, 'Teatro musical', 45, 8, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Teatro y Actuación · Taller 2 · responsable: Antonio Rojas Villarreal
  (193, 'Mecanismos de comedia', 45, 8, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Teatro y Actuación · Taller 3 · responsable: Antonio Rojas Villarreal
  (194, 'Teatro, cuerpo e improvisación', 45, 8, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Teatro y Actuación · Taller 4 · responsable: Antonio Rojas Villarreal
  (195, 'El juego teatral', 45, 8, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Teatro y Actuación · Taller 5 · responsable: Antonio Rojas Villarreal
  (196, 'Fisioterapia para el cuidado de los pacientes hospitalizados.', 46, 4, 1, '2026-10-15', '10:00:00', '11:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Terapia Física y Rehabilitación · Taller 1 · responsable: Moisés Magos
  (197, 'Fisioterapia para el cuidado de los pacientes hospitalizados', 46, 4, 2, '2026-10-15', '11:00:00', '12:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Terapia Física y Rehabilitación · Taller 2 · responsable: Moisés Magos
  (198, 'Vocación en movimiento', 46, 4, 3, '2026-10-15', '12:00:00', '13:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Terapia Física y Rehabilitación · Taller 3 · responsable: Moisés Magos
  (199, 'Vocación en movimiento', 46, 4, 4, '2026-10-15', '13:00:00', '14:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22'),  -- Terapia Física y Rehabilitación · Taller 4 · responsable: Moisés Magos
  (200, 'Vocación en movimiento', 46, 4, 5, '2026-10-15', '15:00:00', '16:00:00', 'programado', NULL, 1, 1, '2026-10-14 18:05:39', '2026-10-14 21:43:22')  -- Terapia Física y Rehabilitación · Taller 5 · responsable: Moisés Magos
;

-- --------------------------------------------------------------------------------------------------
-- Asignaciones (438)
-- Comentario: alumno → taller (grupo).
-- --------------------------------------------------------------------------------------------------
INSERT INTO asignaciones (id, actividad_id, alumno_id, origen, asignado_por, asignado_en) VALUES
  (819, 179, 77, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Torres Hernández, Grecia Sofía → Escenarios Pedagógicos (Taller 4)
  (820, 139, 87, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barragán Hernández, Edgar → Taller Práctico de electrónica Modular con Impresión 3D (Taller 4)
  (821, 174, 57, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arango González, Oscar Adrian → Azúcar invisible: ¿te atreves a adivinar? (Taller 4)
  (822, 50, 58, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Elias, Sophia → Héroes, villanos y personajes inolvidables (Taller 4)
  (823, 63, 67, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cardosa García, Santiago → World Hospitality Challenge (Taller 4)
  (824, 199, 37, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bernal, Natalia → Vocación en movimiento (Taller 4)
  (825, 134, 64, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Martínez Nuñez, Irving → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 4)
  (826, 8, 72, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Valencia Victoria, Karla Luciana → world hospitality challenge (Taller 4)
  (827, 169, 26, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- González López, Jorge Darío → Construyendo mi negocio internacional (Taller 4)
  (828, 34, 36, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Hernández, Antonio → ADN a la Vista (Taller 4)
  (829, 114, 104, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solis Hernández, Juan Pablo → Ingeniería del viento: Optimizando un aerogenerador con el kit KidWind (Taller 4)
  (830, 95, 73, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Velázquez Lara, Renée Sofia → El Arte de la Focaccia: De la Masa al Horno (Taller 4)
  (831, 80, 91, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bautista Almaraz, Astrid Dessireé → Design Lab (Taller 4)
  (832, 129, 9, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Ramírez, Ximena → ¡Conéctate! Arma y prueba tu cable de red (Taller 4)
  (833, 100, 84, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cervantes Chanfreau, Constanza Valeria → Misión Rediseñar el Mundo (Taller 4)
  (834, 194, 114, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cano Guanilo, Isabella → Teatro, cuerpo e improvisación (Taller 4)
  (835, 109, 75, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez Ancona, Santiago → Reconstruir el pasado a partir de la evidencia arqueológica (Taller 4)
  (836, 189, 96, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Torres, Lucía → Secuestro de Futbolistas en el Mundial (Taller 4)
  (837, 76, 42, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Victoria → The Cover Edit / Conviértete en Director de Arte por un día (Taller 4)
  (838, 124, 115, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Terrón Casas, Santiago Emiliano → Ciudad resiliente (Taller 4)
  (839, 184, 15, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pellico Osthoff, Astrid → Detrás del espejo: Psicología en acción (Taller 4)
  (840, 24, 33, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Abreu, Eduardo → El color en el espacio interior (Taller 4)
  (841, 38, 43, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Naranjo, Renata → Dados, canicas y magia (Taller 4)
  (842, 18, 107, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez García, Camila → Misión Verde: Diseña la ciudad del futuro. (Taller 4)
  (843, 44, 93, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- De Jesús Ocegeda, Sebastián → Branding 101: crea una marca que todos recuerden (Taller 4)
  (844, 58, 81, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Ramírez, Ana Sofía → Money Lab (Taller 4)
  (845, 29, 61, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Almanza Repetto, Diego → Acuarela (Taller 4)
  (846, 47, 18, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sifuentes Botello, Camila Anahí → Diseñando una política pública (Taller 4)
  (847, 148, 109, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Salinas Villaseñor, Camila del Carmen → OperandoAndo (Taller 4)
  (848, 159, 23, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Guereschi, Pedro → BrandLab: Análisis Estratégico de L’Oreal con IA (Taller 4)
  (849, 13, 52, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gutiérrez Cordova, Karla → Business workshop (Taller 4)
  (850, 164, 39, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Díaz, Joel → Clase muestra de piano (Taller 4)
  (851, 67, 88, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Morales Ortiz, Carolina → Organización de eventos deportivos (Taller 4)
  (852, 85, 51, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Santaularia, Manuel → Diseño de personajes y ambientes con RA e inteligencia Artificial (Taller 4)
  (853, 143, 22, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dávila Domínguez, Ana Cristina → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 4)
  (854, 154, 102, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bravo López, Rodrigo → Simulación en odontologia (Taller 4)
  (855, 55, 78, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto Luckie, Juan Pablo → World Hospitality Challenge (Taller 4)
  (856, 119, 100, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dorta, Nicolle Alejandra → Osciloscopios: señales chiquitas para divertirse bastante (Taller 4)
  (857, 157, 119, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Navarro Isunza, Javier → BrandLab: Análisis Estratégico de Nike con IA (Taller 2)
  (858, 117, 116, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Monreal Astorga, Leonardo → ¿Qué hace la electricidad con nuestros cuerpos? (Taller 2)
  (859, 22, 38, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castany Bañuelos, Andrea Romero → Maquetas para interiorismo (Taller 2)
  (860, 132, 17, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Pérez, Sofía → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 2)
  (861, 182, 31, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vazquez Sanchez, Samantha Rachel → Detrás del Espejo: Psicología en acción (Taller 2)
  (862, 37, 45, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo, Gustavo → Modelando el futuro con patrones y datos (Taller 2)
  (863, 103, 105, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Fernández Colin, Jacqueline → Global Bids for Business Startup (Taller 2)
  (864, 93, 164, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez, Samantha → Aguachile Lab: Frescura, Fuego y Sabor (Taller 2)
  (865, 2, 62, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Becerril Villalobos, Barbara → Detectives de funciones: resolviendo un misterio matemático (Taller 2)
  (866, 83, 111, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bertín Lobato, Karen Nicole → Diseño de Personajes (Taller 2)
  (867, 142, 113, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cuellar Sanchez, Alonso → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 2)
  (868, 192, 98, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Sanchez, Carlo Bruno → Teatro musical (Taller 2)
  (869, 6, 117, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernández Barrón, Samara → Escape Room: en busca de Harry Styles (Taller 2)
  (870, 70, 1, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Jelwan, Mariano → Coolhunting / Cazando tendencias (Taller 2)
  (871, 66, 20, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Corral Piana, Mariana → Sports Marketing- Controla el juego (Taller 2)
  (872, 137, 76, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Belaunzarán Leyva, Patricio → Taller Práctico de electrónica Modular con Impresión 3D (Taller 2)
  (873, 122, 29, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Martínez, Renée → Presas y control de inundaciones (Taller 2)
  (874, 32, 79, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Suastegui Rosette, Nicole → Conociendo a los Hongos (Taller 2)
  (875, 127, 120, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rosemberg Vincze, Igal → Arduino como controlador de luz de colores (Taller 2)
  (876, 40, 83, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Estela Rincon, Monica Jazmin → Del Play al Podcast, Vive la experiencia de producir en Radio Anáhuac. (Taller 2)
  (877, 61, 95, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Orozco Riaño, Bernardo → Tendencias en hotelería y servicios de hospedaje (Taller 2)
  (878, 112, 34, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera González, Elena → Basura que genera combustible: El poder del hidrógeno (Taller 2)
  (879, 167, 94, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Beirute Buerba, Diego → Construyendo mi negocio internacional (Taller 2)
  (880, 162, 63, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Camargo Medina, Karla Daniela → Workshop ensamble musical (Taller 2)
  (881, 146, 106, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espinosa Reyes, Regina → OperandoAndo (Taller 2)
  (882, 91, 47, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Diaz Aguero, Sebastian → El juego de las finanzas (Taller 2)
  (883, 177, 12, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Castillejos, Erika → Escenarios Pedagógicos (Taller 2)
  (884, 197, 101, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Gómez, Renata → Fisioterapia para el cuidado de los pacientes hospitalizados (Taller 2)
  (885, 187, 54, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castañeda Menéndez, Mariana → Tiktok Geopolítico: Convierte una crisis mundial en un video viral (Taller 2)
  (886, 16, 24, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Flores Higuera, Roselyn → Maquetas volumétricas en arquitectura (Taller 2)
  (887, 39, 44, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Valeria → Cómo hacer un Product Shot (Taller 2)
  (888, 53, 48, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez García, Alitzel → Lego Serious Play para la creación de un concepto restaurantero (Taller 2)
  (889, 152, 27, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Quintero Rodríguez, Estefanía → Modelado y creatividad (Taller 2)
  (890, 107, 6, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Bellan, Ivanna → Historia en acción: El trabajo del historiador en la gestión cultural (Taller 2)
  (892, 11, 65, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Esparza Alanís, Alan Eduardo → WISTAR EN LA NIEVE (Taller 2)
  (893, 172, 99, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arias, Jimena → Sal oculta: el reto de las etiquetas (Taller 2)
  (894, 98, 4, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Andrade Valle, Jose Carlos → GABINETE NOVA (Taller 2)
  (895, 27, 32, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Arellano, Valeria → Plantas tintoreas (Taller 2)
  (896, 74, 118, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Ibarra, Danna Itzel → Make it Iconic / Convierte un objeto en algo extraordinario (Taller 2)
  (897, 160, 103, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez Damián, Jorge Daniel → BrandLab: Análisis Estratégico de Cinépolis con IA (Opcional) (Taller 6)
  (898, 20, 40, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Delgado Guerrero, Regina → Estructuras arquitectónicas (Opcional) (Taller 6)
  (899, 150, 41, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Chávez Espinosa, Diego → ER:Realidad o Ficción (Opcional) (Taller 6)
  (900, 48, 80, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Correa Orozco, Carlos Alain → Simulacion de audiencia oral (Opcional) (Taller 6)
  (901, 175, 71, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ibarra Camargo, Bruno Santino → Sal oculta: el reto de las etiquetas (Taller 5)
  (902, 180, 11, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- García Sánchez, Laura Yazmín → Pedagogía: Una Experiencia Inmersiva (Taller 5)
  (903, 105, 13, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Páez, Erick Leonardo → Global Bids for Business Startup (Taller 5)
  (904, 59, 16, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Viglione Brito, Victoria → Money Lab (Taller 5)
  (905, 51, 30, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera Bazán, Ximena → Branding 101: crea una marca que todos recuerden (Taller 5)
  (906, 130, 74, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santana Acevedo, Natalia → Arduino como controlador de luz de colores (Taller 5)
  (907, 140, 35, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Oliva Contreras, Derek Alexander → Taller Práctico de electrónica Modular con Impresión 3D (Taller 5)
  (908, 120, 25, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- García Lascurain García, Diego → Bioinformática: bases de datos, gusanitos y antepasados distantes (Taller 5)
  (910, 185, 14, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez Téllez Rojo, Inés → Neurolab: Descifrando el sueño (Taller 5)
  (911, 35, 49, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Meza Benitez, Leonardo → Protein Hunters (Taller 5)
  (912, 115, 10, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mattig Martínez, María José → Energía del futuro: Del viento al hidrógeno (Taller 5)
  (913, 4, 21, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Reyna, Ximena → Wall Street: conviértete en inversionista por 45 minutos (Taller 5)
  (914, 170, 19, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cedillo García, Azul Alessandra → Construyendo mi negocio internacional (Taller 5)
  (915, 195, 2, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Macías Leal, Marco Adrián → El juego teatral (Taller 5)
  (916, 125, 82, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espindola Castillo, Aranza → Diseño de Pavimentos (Taller 5)
  (917, 149, 92, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Grimaldo Paulin, Vanessa → OperandoAndo (Taller 5)
  (918, 77, 67, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cardosa García, Santiago → Pop your Brand / Diseña el próximo refresco (Taller 5)
  (919, 64, 64, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Martínez Nuñez, Irving → World Hospitality Challenge (Taller 5)
  (920, 101, 26, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- González López, Jorge Darío → ¿Qué país quieres construir? (Taller 5)
  (921, 56, 73, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Velázquez Lara, Renée Sofia → World Hospitality Challenge (Taller 5)
  (922, 72, 9, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Ramírez, Ximena → Photoshoot de Moda (Taller 5)
  (923, 30, 84, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cervantes Chanfreau, Constanza Valeria → Acuarela (Taller 5)
  (924, 81, 96, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Torres, Lucía → Art Toy (Taller 5)
  (925, 190, 33, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Abreu, Eduardo → ¿Qué país quieres construir? (Taller 5)
  (926, 68, 18, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sifuentes Botello, Camila Anahí → Organización de eventos deportivos (Taller 5)
  (927, 96, 109, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Salinas Villaseñor, Camila del Carmen → Aguachile Lab: Frescura, Fuego y Sabor (Taller 5)
  (928, 9, 23, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Guereschi, Pedro → World Hospitality Challenge (Taller 5)
  (929, 144, 51, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Santaularia, Manuel → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 5)
  (930, 200, 22, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dávila Domínguez, Ana Cristina → Vocación en movimiento (Taller 5)
  (931, 165, 103, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez Damián, Jorge Daniel → Workshop ensamble musical (Taller 5)
  (932, 19, 40, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Delgado Guerrero, Regina → Arquitecto por una hora (Taller 5)
  (933, 14, 41, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Chávez Espinosa, Diego → Business workshop (Taller 5)
  (934, 135, 80, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Correa Orozco, Carlos Alain → Snack Lab (Taller 5)
  (935, 110, 29, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Martínez, Renée → Descifrando el arte: el trabajo del historiador en el mundo del arte (Taller 5)
  (936, 25, 6, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Bellan, Ivanna → Maquetas para interiorismo (Taller 5)
  (937, 65, 70, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Jimenez, María Fernanda → Sports Marketing - controla el juego! (Taller 1)
  (938, 196, 89, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gama Iracheta, Marcela → Fisioterapia para el cuidado de los pacientes hospitalizados. (Taller 1)
  (939, 166, 112, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Antunez, Estefania → Construyendo mi negocio internacional (Taller 1)
  (940, 151, 108, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez y Mora, María Fernanda → Toma de impresiones (Taller 1)
  (941, 156, 90, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Dolores, Mario Eduardo → BrandLab: Análisis Estratégico de Lego con IA (Taller 1)
  (942, 87, 46, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Feregrino, Luis Eduardo → LaunchLab AI :From Idea to Impact (Taller 1)
  (943, 1, 50, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez Amezola, Myranda Mariel → Wall Street: conviértete en inversionista por 45 minutos (Taller 1)
  (944, 5, 97, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Enllanche, Regina Estefanía → Mario Kart: Carrera por el mundo (Taller 1)
  (945, 161, 53, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Malvaez Méndez, Regina → Clase abierta: Técnicas de producción digital (Taller 1)
  (946, 171, 163, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodrigez Hernandez, Antonio → Azúcar invisible: ¿te atreves a adivinar? (Taller 1)
  (947, 52, 56, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santos Pérez, Gael → Preparación de mocktails (Taller 1)
  (948, 92, 68, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto, Regina → El Arte de la Focaccia: de la Masa al Horno (Taller 1)
  (949, 90, 69, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Molina, Nadia → EL JUEGO DE LAS FINANZAS (Taller 1)
  (950, 136, 59, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Yáñez Hernández, Camila Sofía → Taller Práctico de Electrónica Modular (Taller 1)
  (951, 31, 8, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barrios Dorantes, Saúl Emiliano → Gram Challenge (Taller 1)
  (952, 82, 5, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aguilar Ramírez, Santiago → Técnicas de Animación en medio profesional (Taller 1)
  (953, 10, 85, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solís Mendizábal, Erika Vannya → IA Challenge: Crea el futuro hoy. (Taller 1)
  (954, 43, 55, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Rivera, Gabriel → Investigando la comunicación con Realidad extendida (Taller 1)
  (955, 73, 28, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Montaño Guadarrama, Isabel → Make your Mark / Diseña una firma que hable por ti (Taller 1)
  (956, 36, 60, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Valencia, Ricardo → Pronósticos futboleros para actuarios y científicos de datos (Taller 1)
  (957, 69, 110, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Corominas, Kamila Valentina → Moda Inteligente: Explorando el Diseño de Moda con Inteligencia Artificial (Taller 1)
  (958, 116, 66, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Martínez, Isabella → Laparoscopía: un método no muy invasivo para intervenir quirúrgicamente (Taller 1)
  (959, 126, 77, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Torres Hernández, Grecia Sofía → Mi primera Base de Datos con SQL (Taller 1)
  (960, 45, 87, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barragán Hernández, Edgar → Simulación de juicio oral (Taller 1)
  (961, 186, 57, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arango González, Oscar Adrian → Secuestro de Futbolistas en el Mundial (Taller 1)
  (962, 131, 58, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Elias, Sophia → Snack Lab (Taller 1)
  (963, 191, 37, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bernal, Natalia → Training teatral (Taller 1)
  (964, 15, 72, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Valencia Victoria, Karla Luciana → Dibujo Arquitectónico (Taller 1)
  (965, 102, 36, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Hernández, Antonio → Global Bids for Business Startup (Taller 1)
  (966, 49, 104, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solis Hernández, Juan Pablo → El Pitch del Entretenimiento (Taller 1)
  (967, 26, 91, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bautista Almaraz, Astrid Dessireé → Clase muestra: Apreciación artística del cine (Taller 1)
  (968, 21, 114, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cano Guanilo, Isabella → El color en en el espacio interior (Taller 1)
  (969, 145, 75, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez Ancona, Santiago → OperandoAndo (Taller 1)
  (970, 181, 42, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Victoria → Neurolab: Descifrando el Sueño (Taller 1)
  (971, 121, 115, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Terrón Casas, Santiago Emiliano → Muros antisísmicos (Taller 1)
  (972, 176, 15, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pellico Osthoff, Astrid → Pedagogía: Una Experiencia Inmersiva (Taller 1)
  (973, 78, 43, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Naranjo, Renata → Design LAB (Taller 1)
  (974, 97, 107, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez García, Camila → Gabinete Nova (Taller 1)
  (975, 141, 93, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- De Jesús Ocegeda, Sebastián → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 1)
  (976, 60, 81, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Ramírez, Ana Sofía → Lego Serious Play para creación de Restaurante (Taller 1)
  (977, 106, 61, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Almanza Repetto, Diego → ¿Qué hace el historiador? Detectives del Pasado (Taller 1)
  (978, 111, 52, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gutiérrez Cordova, Karla → Conviértete en Ingenier@ Ambiental: Resuelve un caso real (Taller 1)
  (979, 41, 39, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Díaz, Joel → Cómo hacer un Product Shot (Taller 3)
  (980, 23, 88, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Morales Ortiz, Carolina → Diseñando un espacio con IA (Taller 3)
  (981, 54, 102, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bravo López, Rodrigo → Lego Serious Play para la creación de concepto restaurantero. (Taller 3)
  (982, 71, 78, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto Luckie, Juan Pablo → Arquitectura vestible (Taller 3)
  (983, 12, 100, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dorta, Nicolle Alejandra → Laboratorio de Ideas de Negocio (Taller 3)
  (984, 84, 119, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Navarro Isunza, Javier → Anima a un personaje en 3D (Taller 3)
  (985, 198, 116, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Monreal Astorga, Leonardo → Vocación en movimiento (Taller 3)
  (986, 94, 38, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castany Bañuelos, Andrea Romero → Choux Magic: El Secreto de la Pastelería Francesa (Taller 3)
  (987, 28, 17, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Pérez, Sofía → Plantas tintoreas (Taller 3)
  (988, 104, 31, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vazquez Sanchez, Samantha Rachel → Global Bids for Business Startup (Taller 3)
  (989, 133, 45, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo, Gustavo → Snack Lab (Taller 3)
  (990, 188, 105, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Fernández Colin, Jacqueline → Guerra por los minerales críticos: ¿qué hay detrás de tu celular? (Taller 3)
  (991, 113, 164, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez, Samantha → Energía en el mapa: Planeando un parque solar (Taller 3)
  (992, 128, 62, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Becerril Villalobos, Barbara → Programación de un fotomosaico en Python (Taller 3)
  (993, 7, 111, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bertín Lobato, Karen Nicole → Influencers de destinos: el reto del marketing turístico (Taller 3)
  (994, 193, 113, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cuellar Sanchez, Alonso → Mecanismos de comedia (Taller 3)
  (995, 168, 98, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Sanchez, Carlo Bruno → Construyendo mi negocio internacional (Taller 3)
  (996, 183, 117, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernández Barrón, Samara → Neurolab: Descifrando el sueño (Taller 3)
  (997, 33, 1, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Jelwan, Mariano → ADN a la Vista (Taller 3)
  (998, 118, 20, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Corral Piana, Mariana → Simuladores médicos (Taller 3)
  (999, 163, 76, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Belaunzarán Leyva, Patricio → Workshop ensamble musical (Taller 3)
  (1000, 62, 79, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Suastegui Rosette, Nicole → Servicios de lujo y vanguardia en hospitalidad (Taller 3)
  (1001, 75, 120, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rosemberg Vincze, Igal → Build it. Bind it / Crea tu propio sketchbook (Taller 3)
  (1002, 89, 83, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Estela Rincon, Monica Jazmin → LaunchLab AI: From Idea to Impact (Taller 3)
  (1003, 173, 95, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Orozco Riaño, Bernardo → Fuel your performance: taller práctico de nutrición deportiva (Taller 3)
  (1004, 17, 34, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera González, Elena → Estructuras Arquitectónicas (Taller 3)
  (1005, 42, 94, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Beirute Buerba, Diego → Tu Voz, Tu Marca (Taller 3)
  (1006, 79, 63, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Camargo Medina, Karla Daniela → ART TOY (Taller 3)
  (1007, 99, 106, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espinosa Reyes, Regina → GABINETE NOVA (Taller 3)
  (1008, 158, 47, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Diaz Aguero, Sebastian → BrandLab: Análisis Estratégico de Coca Cola con IA (Taller 3)
  (1009, 153, 12, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Castillejos, Erika → Simulación en odontologia (Taller 3)
  (1010, 178, 101, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Gómez, Renata → Pedagogía: Una Experiencia Inmersiva (Taller 3)
  (1011, 46, 54, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castañeda Menéndez, Mariana → El Arte de la Justicia: ¿Cómo piensa un abogado? (Taller 3)
  (1012, 108, 24, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Flores Higuera, Roselyn → Historia en la pantalla: el historiador detrás de las películas y los videojuegos. Divulgación histórica. (Taller 3)
  (1013, 138, 44, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Valeria → Taller Práctico de Electrónica Modular (Taller 3)
  (1014, 123, 48, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez García, Alitzel → RETO PUENTE (Taller 3)
  (1015, 57, 27, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Quintero Rodríguez, Estefanía → Money Lab (Taller 3)
  (1017, 147, 65, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Esparza Alanís, Alan Eduardo → ER: Ralidad o Ficción (Taller 3)
  (1018, 179, 71, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ibarra Camargo, Bruno Santino → Escenarios Pedagógicos (Taller 4)
  (1019, 139, 11, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- García Sánchez, Laura Yazmín → Taller Práctico de electrónica Modular con Impresión 3D (Taller 4)
  (1020, 174, 13, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Páez, Erick Leonardo → Azúcar invisible: ¿te atreves a adivinar? (Taller 4)
  (1021, 50, 16, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Viglione Brito, Victoria → Héroes, villanos y personajes inolvidables (Taller 4)
  (1022, 63, 30, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera Bazán, Ximena → World Hospitality Challenge (Taller 4)
  (1023, 199, 74, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santana Acevedo, Natalia → Vocación en movimiento (Taller 4)
  (1024, 134, 35, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Oliva Contreras, Derek Alexander → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 4)
  (1025, 8, 25, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- García Lascurain García, Diego → world hospitality challenge (Taller 4)
  (1027, 34, 14, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez Téllez Rojo, Inés → ADN a la Vista (Taller 4)
  (1028, 114, 99, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arias, Jimena → Ingeniería del viento: Optimizando un aerogenerador con el kit KidWind (Taller 4)
  (1029, 95, 4, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Andrade Valle, Jose Carlos → El Arte de la Focaccia: De la Masa al Horno (Taller 4)
  (1030, 80, 32, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Arellano, Valeria → Design Lab (Taller 4)
  (1031, 129, 49, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Meza Benitez, Leonardo → ¡Conéctate! Arma y prueba tu cable de red (Taller 4)
  (1032, 100, 118, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Ibarra, Danna Itzel → Misión Rediseñar el Mundo (Taller 4)
  (1033, 194, 70, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Jimenez, María Fernanda → Teatro, cuerpo e improvisación (Taller 4)
  (1034, 109, 10, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mattig Martínez, María José → Reconstruir el pasado a partir de la evidencia arqueológica (Taller 4)
  (1035, 189, 89, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gama Iracheta, Marcela → Secuestro de Futbolistas en el Mundial (Taller 4)
  (1036, 76, 112, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Antunez, Estefania → The Cover Edit / Conviértete en Director de Arte por un día (Taller 4)
  (1037, 124, 108, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez y Mora, María Fernanda → Ciudad resiliente (Taller 4)
  (1038, 184, 90, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Dolores, Mario Eduardo → Detrás del espejo: Psicología en acción (Taller 4)
  (1039, 24, 46, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Feregrino, Luis Eduardo → El color en el espacio interior (Taller 4)
  (1040, 38, 21, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Reyna, Ximena → Dados, canicas y magia (Taller 4)
  (1041, 18, 50, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez Amezola, Myranda Mariel → Misión Verde: Diseña la ciudad del futuro. (Taller 4)
  (1042, 44, 97, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Enllanche, Regina Estefanía → Branding 101: crea una marca que todos recuerden (Taller 4)
  (1043, 58, 19, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cedillo García, Azul Alessandra → Money Lab (Taller 4)
  (1044, 29, 53, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Malvaez Méndez, Regina → Acuarela (Taller 4)
  (1045, 47, 163, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodrigez Hernandez, Antonio → Diseñando una política pública (Taller 4)
  (1046, 148, 56, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santos Pérez, Gael → OperandoAndo (Taller 4)
  (1047, 159, 68, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto, Regina → BrandLab: Análisis Estratégico de L’Oreal con IA (Taller 4)
  (1048, 13, 2, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Macías Leal, Marco Adrián → Business workshop (Taller 4)
  (1049, 164, 69, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Molina, Nadia → Clase muestra de piano (Taller 4)
  (1050, 67, 59, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Yáñez Hernández, Camila Sofía → Organización de eventos deportivos (Taller 4)
  (1051, 85, 82, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espindola Castillo, Aranza → Diseño de personajes y ambientes con RA e inteligencia Artificial (Taller 4)
  (1052, 143, 8, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barrios Dorantes, Saúl Emiliano → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 4)
  (1053, 154, 5, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aguilar Ramírez, Santiago → Simulación en odontologia (Taller 4)
  (1054, 55, 85, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solís Mendizábal, Erika Vannya → World Hospitality Challenge (Taller 4)
  (1055, 119, 55, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Rivera, Gabriel → Osciloscopios: señales chiquitas para divertirse bastante (Taller 4)
  (1056, 157, 28, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Montaño Guadarrama, Isabel → BrandLab: Análisis Estratégico de Nike con IA (Taller 2)
  (1057, 117, 60, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Valencia, Ricardo → ¿Qué hace la electricidad con nuestros cuerpos? (Taller 2)
  (1058, 22, 110, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Corominas, Kamila Valentina → Maquetas para interiorismo (Taller 2)
  (1059, 132, 66, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Martínez, Isabella → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 2)
  (1060, 182, 92, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Grimaldo Paulin, Vanessa → Detrás del Espejo: Psicología en acción (Taller 2)
  (1061, 37, 77, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Torres Hernández, Grecia Sofía → Modelando el futuro con patrones y datos (Taller 2)
  (1062, 103, 87, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barragán Hernández, Edgar → Global Bids for Business Startup (Taller 2)
  (1063, 93, 57, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arango González, Oscar Adrian → Aguachile Lab: Frescura, Fuego y Sabor (Taller 2)
  (1064, 2, 58, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Elias, Sophia → Detectives de funciones: resolviendo un misterio matemático (Taller 2)
  (1065, 83, 37, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bernal, Natalia → Diseño de Personajes (Taller 2)
  (1066, 142, 72, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Valencia Victoria, Karla Luciana → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 2)
  (1067, 192, 36, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Hernández, Antonio → Teatro musical (Taller 2)
  (1068, 6, 104, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solis Hernández, Juan Pablo → Escape Room: en busca de Harry Styles (Taller 2)
  (1069, 70, 73, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Velázquez Lara, Renée Sofia → Coolhunting / Cazando tendencias (Taller 2)
  (1070, 66, 91, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bautista Almaraz, Astrid Dessireé → Sports Marketing- Controla el juego (Taller 2)
  (1071, 137, 114, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cano Guanilo, Isabella → Taller Práctico de electrónica Modular con Impresión 3D (Taller 2)
  (1072, 122, 75, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez Ancona, Santiago → Presas y control de inundaciones (Taller 2)
  (1073, 32, 96, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Torres, Lucía → Conociendo a los Hongos (Taller 2)
  (1074, 127, 42, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Victoria → Arduino como controlador de luz de colores (Taller 2)
  (1075, 40, 115, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Terrón Casas, Santiago Emiliano → Del Play al Podcast, Vive la experiencia de producir en Radio Anáhuac. (Taller 2)
  (1076, 61, 15, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pellico Osthoff, Astrid → Tendencias en hotelería y servicios de hospedaje (Taller 2)
  (1077, 112, 43, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Naranjo, Renata → Basura que genera combustible: El poder del hidrógeno (Taller 2)
  (1078, 167, 107, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez García, Camila → Construyendo mi negocio internacional (Taller 2)
  (1079, 162, 93, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- De Jesús Ocegeda, Sebastián → Workshop ensamble musical (Taller 2)
  (1080, 146, 81, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Ramírez, Ana Sofía → OperandoAndo (Taller 2)
  (1081, 91, 61, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Almanza Repetto, Diego → El juego de las finanzas (Taller 2)
  (1082, 177, 18, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sifuentes Botello, Camila Anahí → Escenarios Pedagógicos (Taller 2)
  (1083, 197, 109, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Salinas Villaseñor, Camila del Carmen → Fisioterapia para el cuidado de los pacientes hospitalizados (Taller 2)
  (1084, 187, 52, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gutiérrez Cordova, Karla → Tiktok Geopolítico: Convierte una crisis mundial en un video viral (Taller 2)
  (1085, 16, 39, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Díaz, Joel → Maquetas volumétricas en arquitectura (Taller 2)
  (1086, 39, 88, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Morales Ortiz, Carolina → Cómo hacer un Product Shot (Taller 2)
  (1087, 53, 102, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bravo López, Rodrigo → Lego Serious Play para la creación de un concepto restaurantero (Taller 2)
  (1088, 152, 78, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto Luckie, Juan Pablo → Modelado y creatividad (Taller 2)
  (1089, 107, 100, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dorta, Nicolle Alejandra → Historia en acción: El trabajo del historiador en la gestión cultural (Taller 2)
  (1090, 88, 70, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Jimenez, María Fernanda → LaunchLab AI : From Idea to Impact (Taller 2)
  (1091, 11, 89, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gama Iracheta, Marcela → WISTAR EN LA NIEVE (Taller 2)
  (1092, 172, 112, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Antunez, Estefania → Sal oculta: el reto de las etiquetas (Taller 2)
  (1093, 98, 108, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez y Mora, María Fernanda → GABINETE NOVA (Taller 2)
  (1094, 27, 90, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Dolores, Mario Eduardo → Plantas tintoreas (Taller 2)
  (1095, 74, 46, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Feregrino, Luis Eduardo → Make it Iconic / Convierte un objeto en algo extraordinario (Taller 2)
  (1096, 160, 67, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cardosa García, Santiago → BrandLab: Análisis Estratégico de Cinépolis con IA (Opcional) (Taller 6)
  (1097, 20, 64, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Martínez Nuñez, Irving → Estructuras arquitectónicas (Opcional) (Taller 6)
  (1098, 150, 26, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- González López, Jorge Darío → ER:Realidad o Ficción (Opcional) (Taller 6)
  (1099, 48, 9, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Ramírez, Ximena → Simulacion de audiencia oral (Opcional) (Taller 6)
  (1100, 65, 119, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Navarro Isunza, Javier → Sports Marketing - controla el juego! (Taller 1)
  (1101, 196, 116, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Monreal Astorga, Leonardo → Fisioterapia para el cuidado de los pacientes hospitalizados. (Taller 1)
  (1102, 166, 38, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castany Bañuelos, Andrea Romero → Construyendo mi negocio internacional (Taller 1)
  (1103, 151, 17, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Pérez, Sofía → Toma de impresiones (Taller 1)
  (1104, 156, 31, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vazquez Sanchez, Samantha Rachel → BrandLab: Análisis Estratégico de Lego con IA (Taller 1)
  (1105, 87, 45, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo, Gustavo → LaunchLab AI :From Idea to Impact (Taller 1)
  (1106, 1, 105, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Fernández Colin, Jacqueline → Wall Street: conviértete en inversionista por 45 minutos (Taller 1)
  (1107, 5, 164, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez, Samantha → Mario Kart: Carrera por el mundo (Taller 1)
  (1108, 161, 62, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Becerril Villalobos, Barbara → Clase abierta: Técnicas de producción digital (Taller 1)
  (1109, 171, 111, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bertín Lobato, Karen Nicole → Azúcar invisible: ¿te atreves a adivinar? (Taller 1)
  (1110, 52, 113, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cuellar Sanchez, Alonso → Preparación de mocktails (Taller 1)
  (1111, 92, 98, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Sanchez, Carlo Bruno → El Arte de la Focaccia: de la Masa al Horno (Taller 1)
  (1112, 90, 117, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernández Barrón, Samara → EL JUEGO DE LAS FINANZAS (Taller 1)
  (1113, 136, 1, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Jelwan, Mariano → Taller Práctico de Electrónica Modular (Taller 1)
  (1114, 31, 20, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Corral Piana, Mariana → Gram Challenge (Taller 1)
  (1115, 82, 76, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Belaunzarán Leyva, Patricio → Técnicas de Animación en medio profesional (Taller 1)
  (1116, 10, 29, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Martínez, Renée → IA Challenge: Crea el futuro hoy. (Taller 1)
  (1117, 43, 79, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Suastegui Rosette, Nicole → Investigando la comunicación con Realidad extendida (Taller 1)
  (1118, 73, 120, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rosemberg Vincze, Igal → Make your Mark / Diseña una firma que hable por ti (Taller 1)
  (1119, 36, 83, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Estela Rincon, Monica Jazmin → Pronósticos futboleros para actuarios y científicos de datos (Taller 1)
  (1120, 69, 95, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Orozco Riaño, Bernardo → Moda Inteligente: Explorando el Diseño de Moda con Inteligencia Artificial (Taller 1)
  (1121, 116, 34, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera González, Elena → Laparoscopía: un método no muy invasivo para intervenir quirúrgicamente (Taller 1)
  (1122, 126, 94, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Beirute Buerba, Diego → Mi primera Base de Datos con SQL (Taller 1)
  (1123, 45, 63, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Camargo Medina, Karla Daniela → Simulación de juicio oral (Taller 1)
  (1124, 186, 106, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espinosa Reyes, Regina → Secuestro de Futbolistas en el Mundial (Taller 1)
  (1125, 131, 47, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Diaz Aguero, Sebastian → Snack Lab (Taller 1)
  (1126, 191, 12, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Castillejos, Erika → Training teatral (Taller 1)
  (1127, 15, 101, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Gómez, Renata → Dibujo Arquitectónico (Taller 1)
  (1128, 102, 54, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castañeda Menéndez, Mariana → Global Bids for Business Startup (Taller 1)
  (1129, 49, 24, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Flores Higuera, Roselyn → El Pitch del Entretenimiento (Taller 1)
  (1130, 26, 44, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Valeria → Clase muestra: Apreciación artística del cine (Taller 1)
  (1131, 21, 48, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez García, Alitzel → El color en en el espacio interior (Taller 1)
  (1132, 145, 27, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Quintero Rodríguez, Estefanía → OperandoAndo (Taller 1)
  (1133, 181, 6, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Bellan, Ivanna → Neurolab: Descifrando el Sueño (Taller 1)
  (1135, 176, 65, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Esparza Alanís, Alan Eduardo → Pedagogía: Una Experiencia Inmersiva (Taller 1)
  (1136, 78, 99, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arias, Jimena → Design LAB (Taller 1)
  (1137, 97, 4, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Andrade Valle, Jose Carlos → Gabinete Nova (Taller 1)
  (1138, 141, 32, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Arellano, Valeria → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 1)
  (1139, 60, 118, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Ibarra, Danna Itzel → Lego Serious Play para creación de Restaurante (Taller 1)
  (1140, 106, 92, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Grimaldo Paulin, Vanessa → ¿Qué hace el historiador? Detectives del Pasado (Taller 1)
  (1141, 111, 73, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Velázquez Lara, Renée Sofia → Conviértete en Ingenier@ Ambiental: Resuelve un caso real (Taller 1)
  (1142, 41, 50, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez Amezola, Myranda Mariel → Cómo hacer un Product Shot (Taller 3)
  (1143, 23, 97, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Enllanche, Regina Estefanía → Diseñando un espacio con IA (Taller 3)
  (1144, 54, 53, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Malvaez Méndez, Regina → Lego Serious Play para la creación de concepto restaurantero. (Taller 3)
  (1145, 71, 163, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodrigez Hernandez, Antonio → Arquitectura vestible (Taller 3)
  (1146, 12, 56, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santos Pérez, Gael → Laboratorio de Ideas de Negocio (Taller 3)
  (1147, 84, 68, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto, Regina → Anima a un personaje en 3D (Taller 3)
  (1148, 198, 69, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Molina, Nadia → Vocación en movimiento (Taller 3)
  (1149, 94, 59, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Yáñez Hernández, Camila Sofía → Choux Magic: El Secreto de la Pastelería Francesa (Taller 3)
  (1150, 28, 8, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barrios Dorantes, Saúl Emiliano → Plantas tintoreas (Taller 3)
  (1151, 104, 5, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aguilar Ramírez, Santiago → Global Bids for Business Startup (Taller 3)
  (1152, 133, 85, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solís Mendizábal, Erika Vannya → Snack Lab (Taller 3)
  (1153, 188, 55, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Rivera, Gabriel → Guerra por los minerales críticos: ¿qué hay detrás de tu celular? (Taller 3)
  (1154, 113, 28, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Montaño Guadarrama, Isabel → Energía en el mapa: Planeando un parque solar (Taller 3)
  (1155, 128, 60, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Valencia, Ricardo → Programación de un fotomosaico en Python (Taller 3)
  (1156, 7, 110, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Corominas, Kamila Valentina → Influencers de destinos: el reto del marketing turístico (Taller 3)
  (1157, 193, 66, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Martínez, Isabella → Mecanismos de comedia (Taller 3)
  (1158, 168, 77, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Torres Hernández, Grecia Sofía → Construyendo mi negocio internacional (Taller 3)
  (1159, 183, 87, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barragán Hernández, Edgar → Neurolab: Descifrando el sueño (Taller 3)
  (1160, 33, 57, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arango González, Oscar Adrian → ADN a la Vista (Taller 3)
  (1161, 118, 58, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Elias, Sophia → Simuladores médicos (Taller 3)
  (1162, 163, 37, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bernal, Natalia → Workshop ensamble musical (Taller 3)
  (1163, 62, 72, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Valencia Victoria, Karla Luciana → Servicios de lujo y vanguardia en hospitalidad (Taller 3)
  (1164, 75, 36, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Hernández, Antonio → Build it. Bind it / Crea tu propio sketchbook (Taller 3)
  (1165, 89, 104, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solis Hernández, Juan Pablo → LaunchLab AI: From Idea to Impact (Taller 3)
  (1166, 173, 91, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bautista Almaraz, Astrid Dessireé → Fuel your performance: taller práctico de nutrición deportiva (Taller 3)
  (1167, 17, 114, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cano Guanilo, Isabella → Estructuras Arquitectónicas (Taller 3)
  (1168, 42, 75, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez Ancona, Santiago → Tu Voz, Tu Marca (Taller 3)
  (1169, 79, 96, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Torres, Lucía → ART TOY (Taller 3)
  (1170, 99, 42, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Victoria → GABINETE NOVA (Taller 3)
  (1171, 158, 115, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Terrón Casas, Santiago Emiliano → BrandLab: Análisis Estratégico de Coca Cola con IA (Taller 3)
  (1172, 153, 15, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pellico Osthoff, Astrid → Simulación en odontologia (Taller 3)
  (1173, 178, 43, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Naranjo, Renata → Pedagogía: Una Experiencia Inmersiva (Taller 3)
  (1174, 46, 107, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Pérez García, Camila → El Arte de la Justicia: ¿Cómo piensa un abogado? (Taller 3)
  (1175, 108, 93, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- De Jesús Ocegeda, Sebastián → Historia en la pantalla: el historiador detrás de las películas y los videojuegos. Divulgación histórica. (Taller 3)
  (1176, 138, 81, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Ramírez, Ana Sofía → Taller Práctico de Electrónica Modular (Taller 3)
  (1177, 123, 61, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Almanza Repetto, Diego → RETO PUENTE (Taller 3)
  (1178, 57, 18, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sifuentes Botello, Camila Anahí → Money Lab (Taller 3)
  (1179, 3, 109, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Salinas Villaseñor, Camila del Carmen → Programando en R: así trabaja un actuario con datos reales (Taller 3)
  (1180, 147, 52, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gutiérrez Cordova, Karla → ER: Ralidad o Ficción (Taller 3)
  (1181, 179, 103, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez Damián, Jorge Daniel → Escenarios Pedagógicos (Taller 4)
  (1182, 139, 40, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Delgado Guerrero, Regina → Taller Práctico de electrónica Modular con Impresión 3D (Taller 4)
  (1183, 174, 41, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Chávez Espinosa, Diego → Azúcar invisible: ¿te atreves a adivinar? (Taller 4)
  (1184, 50, 80, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Correa Orozco, Carlos Alain → Héroes, villanos y personajes inolvidables (Taller 4)
  (1185, 63, 119, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Navarro Isunza, Javier → World Hospitality Challenge (Taller 4)
  (1186, 199, 116, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Monreal Astorga, Leonardo → Vocación en movimiento (Taller 4)
  (1187, 134, 38, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castany Bañuelos, Andrea Romero → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 4)
  (1188, 8, 17, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodríguez Pérez, Sofía → world hospitality challenge (Taller 4)
  (1189, 169, 31, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vazquez Sanchez, Samantha Rachel → Construyendo mi negocio internacional (Taller 4)
  (1190, 34, 45, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo, Gustavo → ADN a la Vista (Taller 4)
  (1191, 114, 105, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Fernández Colin, Jacqueline → Ingeniería del viento: Optimizando un aerogenerador con el kit KidWind (Taller 4)
  (1192, 95, 164, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez, Samantha → El Arte de la Focaccia: De la Masa al Horno (Taller 4)
  (1193, 80, 62, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Becerril Villalobos, Barbara → Design Lab (Taller 4)
  (1194, 129, 111, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bertín Lobato, Karen Nicole → ¡Conéctate! Arma y prueba tu cable de red (Taller 4)
  (1195, 100, 113, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cuellar Sanchez, Alonso → Misión Rediseñar el Mundo (Taller 4)
  (1196, 194, 98, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Sanchez, Carlo Bruno → Teatro, cuerpo e improvisación (Taller 4)
  (1197, 109, 117, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernández Barrón, Samara → Reconstruir el pasado a partir de la evidencia arqueológica (Taller 4)
  (1198, 189, 1, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Jelwan, Mariano → Secuestro de Futbolistas en el Mundial (Taller 4)
  (1199, 76, 20, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Corral Piana, Mariana → The Cover Edit / Conviértete en Director de Arte por un día (Taller 4)
  (1200, 124, 76, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Belaunzarán Leyva, Patricio → Ciudad resiliente (Taller 4)
  (1201, 184, 29, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Martínez, Renée → Detrás del espejo: Psicología en acción (Taller 4)
  (1202, 24, 79, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Suastegui Rosette, Nicole → El color en el espacio interior (Taller 4)
  (1203, 38, 120, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rosemberg Vincze, Igal → Dados, canicas y magia (Taller 4)
  (1204, 18, 83, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Estela Rincon, Monica Jazmin → Misión Verde: Diseña la ciudad del futuro. (Taller 4)
  (1205, 44, 95, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Orozco Riaño, Bernardo → Branding 101: crea una marca que todos recuerden (Taller 4)
  (1206, 58, 34, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rivera González, Elena → Money Lab (Taller 4)
  (1207, 29, 94, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Beirute Buerba, Diego → Acuarela (Taller 4)
  (1208, 47, 63, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Camargo Medina, Karla Daniela → Diseñando una política pública (Taller 4)
  (1209, 148, 106, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Espinosa Reyes, Regina → OperandoAndo (Taller 4)
  (1210, 159, 47, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Diaz Aguero, Sebastian → BrandLab: Análisis Estratégico de L’Oreal con IA (Taller 4)
  (1211, 13, 12, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Castillejos, Erika → Business workshop (Taller 4)
  (1212, 164, 101, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Gómez, Renata → Clase muestra de piano (Taller 4)
  (1213, 67, 54, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castañeda Menéndez, Mariana → Organización de eventos deportivos (Taller 4)
  (1214, 85, 24, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Flores Higuera, Roselyn → Diseño de personajes y ambientes con RA e inteligencia Artificial (Taller 4)
  (1215, 143, 44, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garza Chavez, Valeria → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 4)
  (1216, 154, 48, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Álvarez García, Alitzel → Simulación en odontologia (Taller 4)
  (1217, 55, 27, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Quintero Rodríguez, Estefanía → World Hospitality Challenge (Taller 4)
  (1218, 119, 6, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Bellan, Ivanna → Osciloscopios: señales chiquitas para divertirse bastante (Taller 4)
  (1219, 157, 50, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Hernandez Amezola, Myranda Mariel → BrandLab: Análisis Estratégico de Nike con IA (Taller 2)
  (1220, 117, 97, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Enllanche, Regina Estefanía → ¿Qué hace la electricidad con nuestros cuerpos? (Taller 2)
  (1221, 22, 53, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Malvaez Méndez, Regina → Maquetas para interiorismo (Taller 2)
  (1222, 132, 163, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Rodrigez Hernandez, Antonio → VITA-CELL LABS: RETO DE INGENIERÍA INDUSTRIAL (Taller 2)
  (1223, 182, 56, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Santos Pérez, Gael → Detrás del Espejo: Psicología en acción (Taller 2)
  (1224, 37, 68, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto, Regina → Modelando el futuro con patrones y datos (Taller 2)
  (1225, 103, 69, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Molina, Nadia → Global Bids for Business Startup (Taller 2)
  (1226, 93, 59, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Yáñez Hernández, Camila Sofía → Aguachile Lab: Frescura, Fuego y Sabor (Taller 2)
  (1227, 2, 8, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Barrios Dorantes, Saúl Emiliano → Detectives de funciones: resolviendo un misterio matemático (Taller 2)
  (1228, 83, 5, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aguilar Ramírez, Santiago → Diseño de Personajes (Taller 2)
  (1229, 142, 85, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Solís Mendizábal, Erika Vannya → ¡Crea, Formula y Emprende! Diseña tu pasta de dientes (Taller 2)
  (1230, 192, 55, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Ruiz Rivera, Gabriel → Teatro musical (Taller 2)
  (1231, 160, 84, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cervantes Chanfreau, Constanza Valeria → BrandLab: Análisis Estratégico de Cinépolis con IA (Opcional) (Taller 6)
  (1232, 20, 33, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Abreu, Eduardo → Estructuras arquitectónicas (Opcional) (Taller 6)
  (1233, 150, 23, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Guereschi, Pedro → ER:Realidad o Ficción (Opcional) (Taller 6)
  (1234, 48, 51, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Garcia Santaularia, Manuel → Simulacion de audiencia oral (Opcional) (Taller 6)
  (1235, 65, 39, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Díaz, Joel → Sports Marketing - controla el juego! (Taller 1)
  (1236, 196, 88, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Morales Ortiz, Carolina → Fisioterapia para el cuidado de los pacientes hospitalizados. (Taller 1)
  (1237, 166, 102, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Bravo López, Rodrigo → Construyendo mi negocio internacional (Taller 1)
  (1238, 151, 78, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Aburto Luckie, Juan Pablo → Toma de impresiones (Taller 1)
  (1239, 156, 100, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Dorta, Nicolle Alejandra → BrandLab: Análisis Estratégico de Lego con IA (Taller 1)
  (1240, 87, 96, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Galván Torres, Lucía → LaunchLab AI :From Idea to Impact (Taller 1)
  (1241, 1, 18, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sifuentes Botello, Camila Anahí → Wall Street: conviértete en inversionista por 45 minutos (Taller 1)
  (1242, 5, 109, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Salinas Villaseñor, Camila del Carmen → Mario Kart: Carrera por el mundo (Taller 1)
  (1243, 41, 99, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Arias, Jimena → Cómo hacer un Product Shot (Taller 3)
  (1244, 23, 4, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Andrade Valle, Jose Carlos → Diseñando un espacio con IA (Taller 3)
  (1245, 54, 32, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Cortés Arellano, Valeria → Lego Serious Play para la creación de concepto restaurantero. (Taller 3)
  (1246, 71, 118, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Ibarra, Danna Itzel → Arquitectura vestible (Taller 3)
  (1247, 12, 70, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Vargas Jimenez, María Fernanda → Laboratorio de Ideas de Negocio (Taller 3)
  (1248, 84, 89, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gama Iracheta, Marcela → Anima a un personaje en 3D (Taller 3)
  (1249, 198, 112, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Antunez, Estefania → Vocación en movimiento (Taller 3)
  (1250, 94, 108, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Gómez y Mora, María Fernanda → Choux Magic: El Secreto de la Pastelería Francesa (Taller 3)
  (1251, 28, 90, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mendoza Dolores, Mario Eduardo → Plantas tintoreas (Taller 3)
  (1252, 104, 46, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Castillo Feregrino, Luis Eduardo → Global Bids for Business Startup (Taller 3)
  (1253, 133, 92, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Grimaldo Paulin, Vanessa → Snack Lab (Taller 3)
  (1254, 188, 73, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Velázquez Lara, Renée Sofia → Guerra por los minerales críticos: ¿qué hay detrás de tu celular? (Taller 3)
  (1255, 113, 29, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Mena Martínez, Renée → Energía en el mapa: Planeando un parque solar (Taller 3)
  (1256, 128, 6, 'aleatoria', 1, '2026-10-14 15:11:43'),  -- Sánchez Bellan, Ivanna → Programación de un fotomosaico en Python (Taller 3)
  (1257, 104, 7, 'manual', 1, '2026-10-14 15:28:24'),  -- Arriaga Guzman, Emilio → Global Bids for Business Startup (Taller 3)
  (1258, 103, 7, 'manual', 1, '2026-10-14 15:28:53'),  -- Arriaga Guzman, Emilio → Global Bids for Business Startup (Taller 2)
  (1259, 86, 162, 'aleatoria', 1, '2026-10-14 15:29:07'),  -- Escobedo, Erik → Creación de videojuegos y entornos virutales (Taller 5)
  (1260, 121, 7, 'aleatoria', 1, '2026-10-14 15:29:07'),  -- Arriaga Guzman, Emilio → Muros antisísmicos (Taller 1)
  (1261, 169, 162, 'aleatoria', 1, '2026-10-14 15:29:07')  -- Escobedo, Erik → Construyendo mi negocio internacional (Taller 4)
;

-- --------------------------------------------------------------------------------------------------
-- Bitácora de cambios de estado de los talleres
-- --------------------------------------------------------------------------------------------------
-- (sin registros)
