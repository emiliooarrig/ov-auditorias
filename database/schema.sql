-- Esquema de la base de datos (SDD, sección 4.3).
-- Compatible con MySQL 8.0.16+ y MariaDB 10.6+ (aplican las restricciones CHECK).
--
-- Instalación recomendada:  php bin/instalar-bd.php
-- Instalación manual:
--   mysql -u root -e "CREATE DATABASE auditores_talleres CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u root auditores_talleres < database/schema.sql
--   mysql -u root auditores_talleres < database/seeds.sql
--
-- La creación de la base se separa del esquema para poder instalarlo también en la base de pruebas.
-- Todas las llaves foráneas usan RESTRICT (valor por defecto) para impedir borrar registros ligados.

CREATE TABLE roles (
  id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_id         TINYINT UNSIGNED NOT NULL,
  matricula      VARCHAR(20)  NULL,
  nombre         VARCHAR(80)  NOT NULL,
  apellidos      VARCHAR(120) NOT NULL,
  correo         VARCHAR(150) NOT NULL,
  password_hash  VARCHAR(255) NULL,      -- solo administradores
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME     NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  UNIQUE KEY uq_usuarios_matricula (matricula),
  KEY idx_usuarios_rol (rol_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carreras (
  id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carreras_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edificios (
  id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero SMALLINT UNSIGNED NOT NULL,
  nombre VARCHAR(60) NOT NULL DEFAULT '',  -- área que ocupa el edificio, p. ej. 'Ingeniería'
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edificios_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grupos_taller (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero      TINYINT UNSIGNED NOT NULL,
  nombre      VARCHAR(30) NOT NULL,              -- 'Taller 1' … 'Taller 6'
  hora_inicio TIME NOT NULL,                     -- horario fijo del grupo
  hora_fin    TIME NOT NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grupos_taller_numero (numero),
  CONSTRAINT ck_grupos_taller_horario CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividades (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre              VARCHAR(150) NOT NULL,
  carrera_id          SMALLINT UNSIGNED NOT NULL,
  edificio_id         SMALLINT UNSIGNED NOT NULL,
  grupo_id            TINYINT UNSIGNED NOT NULL,   -- grupo (Taller 1 … 6) que fija el horario
  fecha               DATE NOT NULL,
  hora_inicio         TIME NOT NULL,               -- copia del horario del grupo; la fija el modelo
  hora_fin            TIME NOT NULL,
  estado              ENUM('programado','realizado','no_realizado') NOT NULL DEFAULT 'programado',
  motivo_no_realizado VARCHAR(255) NULL,
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  creado_por          INT UNSIGNED NOT NULL,
  creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_actividades_nombre (nombre),
  KEY idx_actividades_carrera (carrera_id),
  KEY idx_actividades_edificio (edificio_id),
  KEY idx_actividades_grupo (grupo_id),
  KEY idx_actividades_fecha (fecha, hora_inicio),
  CONSTRAINT fk_actividades_carrera  FOREIGN KEY (carrera_id)  REFERENCES carreras (id),
  CONSTRAINT fk_actividades_edificio FOREIGN KEY (edificio_id) REFERENCES edificios (id),
  CONSTRAINT fk_actividades_grupo    FOREIGN KEY (grupo_id)    REFERENCES grupos_taller (id),
  CONSTRAINT fk_actividades_creador  FOREIGN KEY (creado_por)  REFERENCES usuarios (id),
  CONSTRAINT ck_actividades_horario CHECK (hora_fin > hora_inicio),
  CONSTRAINT ck_actividades_motivo  CHECK (estado <> 'no_realizado' OR motivo_no_realizado IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asignaciones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actividad_id INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NOT NULL,
  asignado_por INT UNSIGNED NOT NULL,
  asignado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asignacion (actividad_id, usuario_id),
  KEY idx_asignaciones_usuario (usuario_id),
  CONSTRAINT fk_asignaciones_actividad FOREIGN KEY (actividad_id) REFERENCES actividades (id),
  CONSTRAINT fk_asignaciones_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id),
  CONSTRAINT fk_asignaciones_admin     FOREIGN KEY (asignado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bitacora_estados (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actividad_id    INT UNSIGNED NOT NULL,
  estado_anterior ENUM('programado','realizado','no_realizado') NULL,
  estado_nuevo    ENUM('programado','realizado','no_realizado') NOT NULL,
  motivo          VARCHAR(255) NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bitacora_actividad (actividad_id, creado_en),
  CONSTRAINT fk_bitacora_actividad FOREIGN KEY (actividad_id) REFERENCES actividades (id),
  CONSTRAINT fk_bitacora_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accesos (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  correo     VARCHAR(150) NOT NULL,
  evento     ENUM('registro','login_ok','login_fallido','logout') NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  user_agent VARCHAR(255) NULL,
  creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_accesos_usuario (usuario_id, creado_en),
  KEY idx_accesos_correo  (correo, creado_en),
  KEY idx_accesos_ip      (ip, creado_en),   -- conteo de fallos por IP (bloqueo temporal)
  CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (nombre) VALUES ('administrador'), ('auditor');
