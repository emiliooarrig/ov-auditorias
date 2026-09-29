# SDD — Sistema de Asignación y Registro de Auditores de Talleres Universitarios

Sep 29, 2026 · @emi

## 1. Introducción

El sistema deja constancia de qué estudiantes auditaron cada taller universitario, permite que un administrador les asigne actividades a mano y registra los talleres que finalmente no se realizaron.

### 1.1 Propósito

Este documento describe el diseño del sistema: requerimientos, arquitectura PHP + MVC + Composer, modelo de datos, módulos, seguridad e interfaz. Sirve como guía para quien lo implemente y como acuerdo de alcance con quien lo solicite.

### 1.2 Alcance

- **Dentro del alcance:** inicio de sesión, registro de usuarios, catálogo de talleres, asignación manual de auditores, vista "Mis talleres" para cada auditor, registro de talleres no realizados, filtros y consulta del historial.
- **Fuera del alcance:** calificar o evaluar la auditoría, generar reportes de calidad, notificaciones automáticas y asignación automática. El sistema solo mantiene el registro.

### 1.3 Definiciones

| Término | Significado en este documento |
| --- | --- |
| Taller / actividad | Evento universitario que se audita. Ambos términos son sinónimos. |
| Auditor | Estudiante registrado a quien se le asignan uno o varios talleres. |
| Administrador | Usuario con permiso para crear talleres, asignar auditores y gestionar usuarios. |
| Asignación | Relación entre un auditor y un taller. Un taller puede tener varias. |
| Taller no realizado | Taller que estaba programado pero no se llevó a cabo; se registra con su motivo. |
| MVC | Patrón Modelo-Vista-Controlador que separa datos, presentación y lógica de flujo. |

### 1.4 Referencias

- PHP 8.2 o superior, con PDO y MySQL/MariaDB (InnoDB, `utf8mb4`).
- Composer y autocarga PSR-4; estilo de código PSR-12.
- OWASP Top 10 como guía de seguridad.

## 2. Requerimientos

El sistema cubre once requerimientos funcionales y dos roles: el administrador controla las asignaciones y el auditor consulta las suyas y puede marcar como no realizado un taller asignado.

### 2.1 Requerimientos funcionales

| ID | Requerimiento | Rol |
| --- | --- | --- |
| RF-01 | Iniciar y cerrar sesión: el auditor solo con su correo institucional y el administrador con correo y contraseña; cada acción queda ligada a un usuario identificado. | Todos |
| RF-02 | Registrarse con correo institucional, nombre y apellidos, sin contraseña; el usuario queda con rol auditor y visible para el administrador. | Visitante |
| RF-03 | Panel central de administración con la lista de todos los talleres, su estado y sus auditores asignados. El auditor no accede a él. | Administrador |
| RF-04 | Asignar manualmente uno o varios talleres a cada usuario registrado. | Administrador |
| RF-05 | Un taller puede tener varios auditores; un auditor puede tener varios talleres. | Administrador |
| RF-06 | Crear, editar y desactivar talleres con nombre, edificio (número), carrera y horario. | Administrador |
| RF-07 | Marcar un taller como *no realizado* con su motivo. El auditor asignado puede marcarlo en sus talleres; el administrador puede además revertirlo a *programado* o pasarlo a *realizado*. | Administrador y auditor asignado |
| RF-08 | Filtrar talleres por nombre, carrera o edificio, solos o combinados. El auditor filtra solo entre sus talleres. | Administrador y auditor |
| RF-09 | Consultar quién auditó cada taller (historial de asignaciones y de cambios de estado). El auditor solo consulta los talleres que tiene asignados. | Administrador; auditor solo sus talleres |
| RF-10 | Gestionar usuarios: ver registrados, activar o desactivar, cambiar rol. | Administrador |
| RF-11 | Ver "Mis talleres": los talleres asignados al usuario en sesión. Es la pantalla de inicio del auditor, que no accede al panel de administración. | Auditor |

### 2.2 Requerimientos no funcionales

| ID | Requerimiento |
| --- | --- |
| RNF-01 | Arquitectura PHP 8.2+ con patrón MVC y dependencias gestionadas con Composer (autocarga PSR-4). |
| RNF-02 | Solo el administrador usa contraseña, guardada con `password_hash()` (Argon2id o bcrypt), nunca en texto plano; el auditor se identifica solo con su correo institucional. |
| RNF-03 | Todas las consultas SQL con sentencias preparadas (PDO). |
| RNF-04 | Interfaz responsiva, con la paleta de colores de la Universidad Anáhuac. |
| RNF-05 | Toda acción que cambie datos exige sesión activa, rol adecuado y token CSRF. |
| RNF-06 | Base de datos relacional normalizada (3FN) con llaves foráneas e índices en los campos de filtro. |

### 2.3 Reglas de negocio

1. Solo el administrador crea asignaciones; un auditor no puede asignarse talleres.
2. La pareja taller–auditor es única: no se puede asignar dos veces el mismo auditor al mismo taller.
3. Un taller marcado como *no realizado* exige un motivo y conserva sus asignaciones como historial. Lo puede registrar el administrador o un auditor asignado a ese taller.
4. Los talleres y los usuarios no se borran: se desactivan para conservar el registro.
5. Todo cambio de estado de un taller queda en bitácora con usuario y fecha.
6. Una asignación solo se puede quitar mientras el taller esté *programado*; después queda fija como registro de quién auditó.
7. El auditor entra directo a "Mis talleres" y solo ve los talleres que tiene asignados; no accede al panel de administración ni a talleres ajenos.
8. Un auditor solo puede marcar como *no realizado* un taller que tiene asignado y que sigue *programado*; revertirlo o marcarlo *realizado* es exclusivo del administrador.
9. El auditor se identifica solo con su correo institucional (dominio permitido) y no tiene contraseña; el administrador siempre ingresa con contraseña.

## 3. Arquitectura

Toda petición entra por un único punto (`public/index.php`), pasa por el enrutador y los middleware, y llega a un controlador que usa modelos y devuelve una vista.

### 3.1 Capas y flujo de una petición

&#91;embedded content: flujo de una petición · 8 componentes\]

Si el middleware rechaza la petición (sin sesión, sin rol o con token CSRF inválido), el controlador no se ejecuta: el sistema redirige a `/login` o responde 403.

### 3.2 Estructura de carpetas

El código de la aplicación vive en `app/` bajo el espacio de nombres `App\`; solo `public/` es accesible desde el servidor web.

```text
auditores-talleres/
├── composer.json
├── .env.example              # credenciales de BD y claves (el .env real no se versiona)
├── public/
│   ├── index.php             # front controller: único punto de entrada
│   ├── .htaccess             # reescritura hacia index.php
│   └── assets/               # css, js, img
├── app/
│   ├── Core/                 # Router, Request, Response, View, Database, Session, Csrf
│   ├── Controllers/          # Auth, Dashboard, Actividad, Asignacion, Usuario
│   ├── Models/               # Usuario, Actividad, Carrera, Edificio, Asignacion, Bitacora
│   ├── Middleware/           # AuthMiddleware, RoleMiddleware, CsrfMiddleware
│   ├── Views/                # layouts/, auth/, dashboard/, actividades/, asignaciones/, usuarios/
│   └── Config/               # routes.php, app.php
├── database/
│   ├── schema.sql            # esquema completo (sección 4)
│   └── seeds.sql             # roles, admin inicial, carreras y edificios
├── storage/logs/
└── tests/
```

### 3.3 Dependencias con Composer

Se usan pocas dependencias a propósito. Las vistas son PHP nativo con una función `e()` que envuelve `htmlspecialchars()` para escapar toda salida.

| Paquete | Uso | Entorno |
| --- | --- | --- |
| `nikic/fast-route` | Enrutamiento por método y ruta | Producción |
| `vlucas/phpdotenv` | Lectura del archivo `.env` | Producción |
| `phpunit/phpunit` | Pruebas unitarias y de integración | Desarrollo |
| `squizlabs/php_codesniffer` | Verificar estilo PSR-12 | Desarrollo |
| `phpstan/phpstan` | Análisis estático | Desarrollo |

```json
{
  "name": "universidad/auditores-talleres",
  "type": "project",
  "require": {
    "php": ">=8.2",
    "nikic/fast-route": "^1.3",
    "vlucas/phpdotenv": "^5.6"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "squizlabs/php_codesniffer": "^3.10",
    "phpstan/phpstan": "^2.0"
  },
  "autoload": {
    "psr-4": { "App\\": "app/" }
  },
  "scripts": {
    "test": "phpunit",
    "lint": "phpcs --standard=PSR12 app"
  }
}
```

Las versiones son restricciones iniciales; se confirman al ejecutar `composer install` y quedan fijadas en `composer.lock`.

## 4. Modelo de datos

El esquema tiene ocho tablas: seis de negocio y dos de registro (bitácora de estados y accesos). La tabla `asignaciones` resuelve la relación muchos a muchos entre talleres y auditores.

### 4.1 Diagrama entidad-relación

&#91;embedded content: modelo entidad-relación · 8 tablas\]

Un taller tiene muchos auditores y un auditor muchos talleres, por eso `asignaciones` los une; la pareja taller–auditor es única.

### 4.2 Diccionario de tablas

| Tabla | Propósito | Relaciones |
| --- | --- | --- |
| `roles` | Catálogo de roles: administrador y auditor. | 1:N con `usuarios` |
| `usuarios` | Personas que inician sesión y se registran. | N:1 con `roles`; 1:N con `asignaciones` |
| `carreras` | Catálogo de carreras que dan talleres. | 1:N con `actividades` |
| `edificios` | Catálogo de edificios por número. | 1:N con `actividades` |
| `actividades` | Talleres con horario, estado y motivo si no se realizaron. | N:1 con `carreras` y `edificios`; 1:N con `asignaciones` |
| `asignaciones` | Tabla puente: quién audita qué taller, quién lo asignó y cuándo. | N:1 con `actividades` y `usuarios` |
| `bitacora_estados` | Historial de cambios de estado de cada taller. | N:1 con `actividades` y `usuarios` |
| `accesos` | Registros, inicios de sesión, cierres y fallos. | N:1 con `usuarios` (opcional) |

### 4.3 Script SQL

Compatible con MySQL 8.0.16 o superior y MariaDB 10.6 o superior, que aplican las restricciones `CHECK`. Todas las llaves foráneas usan `RESTRICT` para impedir borrar un taller o un usuario que tenga registros ligados.

```sql
CREATE DATABASE IF NOT EXISTS auditores_talleres
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auditores_talleres;

CREATE TABLE roles (
  id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE carreras (
  id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carreras_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE edificios (
  id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero SMALLINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edificios_numero (numero)
) ENGINE=InnoDB;

CREATE TABLE actividades (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre              VARCHAR(150) NOT NULL,
  carrera_id          SMALLINT UNSIGNED NOT NULL,
  edificio_id         SMALLINT UNSIGNED NOT NULL,
  fecha               DATE NOT NULL,
  hora_inicio         TIME NOT NULL,
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
  KEY idx_actividades_fecha (fecha, hora_inicio),
  CONSTRAINT fk_actividades_carrera  FOREIGN KEY (carrera_id)  REFERENCES carreras (id),
  CONSTRAINT fk_actividades_edificio FOREIGN KEY (edificio_id) REFERENCES edificios (id),
  CONSTRAINT fk_actividades_creador  FOREIGN KEY (creado_por)  REFERENCES usuarios (id),
  CONSTRAINT ck_actividades_horario CHECK (hora_fin > hora_inicio),
  CONSTRAINT ck_actividades_motivo  CHECK (estado <> 'no_realizado' OR motivo_no_realizado IS NOT NULL)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
  CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB;

INSERT INTO roles (nombre) VALUES ('administrador'), ('auditor');
```

El primer administrador no se siembra con contraseña fija: se crea con un script de consola (`bin/crear-admin.php`) que pide la contraseña y guarda solo su hash. Los auditores quedan con password\_hash en NULL; la aplicación exige contraseña únicamente al rol administrador.

### 4.4 Consulta base del panel con filtros

El modelo `Actividad` agrega cada condición del `WHERE` solo si el filtro llegó en la petición, y pasa los valores como parámetros vinculados. Para el auditor agrega además una condición EXISTS con el id del usuario en sesión: así ve a todos los auditores de sus talleres, pero ningún otro taller.

```sql
SELECT a.id, a.nombre, c.nombre AS carrera, e.numero AS edificio,
       a.fecha, a.hora_inicio, a.hora_fin, a.estado,
       GROUP_CONCAT(CONCAT(u.nombre, ' ', u.apellidos)
                    ORDER BY u.apellidos SEPARATOR ', ') AS auditores
FROM actividades a
JOIN carreras  c ON c.id = a.carrera_id
JOIN edificios e ON e.id = a.edificio_id
LEFT JOIN asignaciones s ON s.actividad_id = a.id
LEFT JOIN usuarios     u ON u.id = s.usuario_id
WHERE a.activo = 1
  -- condiciones opcionales, según los filtros recibidos:
  AND a.nombre LIKE :nombre          -- valor: '%texto%'
  AND a.carrera_id = :carrera_id
  AND a.edificio_id = :edificio_id
  -- solo para el rol auditor (el id sale de la sesión, nunca del cliente):
  AND EXISTS (SELECT 1 FROM asignaciones m
              WHERE m.actividad_id = a.id AND m.usuario_id = :usuario_sesion)
GROUP BY a.id
ORDER BY a.fecha, a.hora_inicio;
```

## 5. Diseño de módulos

Cinco controladores cubren todos los requerimientos: el administrador accede a todos y el auditor solo a "Mis talleres" y al detalle de los talleres que tiene asignados, donde también puede marcarlos como no realizados.

### 5.1 Rutas y controladores

| Método | Ruta | Controlador::acción | Acceso | RF |
| --- | --- | --- | --- | --- |
| GET | `/login` | `AuthController::mostrarLogin` | Público | RF-01 |
| POST | `/login` | `AuthController::login` | Público | RF-01 |
| GET | `/registro` | `AuthController::mostrarRegistro` | Público | RF-02 |
| POST | `/registro` | `AuthController::registrar` | Público | RF-02 |
| POST | `/logout` | `AuthController::logout` | Autenticado | RF-01 |
| GET | `/` | `DashboardController::index` (panel con filtros; si el usuario es auditor, redirige a /mis-talleres) | Administrador | RF-03, RF-08 |
| GET | `/mis-talleres` | `DashboardController::misTalleres` | Auditor | RF-11 |
| GET | `/actividades/{id}` | `ActividadController::ver` (auditores e historial) | Administrador; auditor solo si el taller es suyo | RF-09 |
| GET | `/actividades/nueva` | `ActividadController::crear` | Administrador | RF-06 |
| POST | `/actividades` | `ActividadController::guardar` | Administrador | RF-06 |
| GET | `/actividades/{id}/editar` | `ActividadController::editar` | Administrador | RF-06 |
| POST | `/actividades/{id}` | `ActividadController::actualizar` | Administrador | RF-06 |
| POST | `/actividades/{id}/estado` | `ActividadController::cambiarEstado` | Administrador; auditor asignado solo para no realizado | RF-07 |
| POST | `/actividades/{id}/desactivar` | `ActividadController::desactivar` | Administrador | RF-06 |
| GET | `/asignaciones` | `AsignacionController::index` | Administrador | RF-04 |
| POST | `/asignaciones` | `AsignacionController::asignar` (uno o varios talleres) | Administrador | RF-04, RF-05 |
| POST | `/asignaciones/{id}/quitar` | `AsignacionController::quitar` (solo si el taller sigue programado) | Administrador | RF-04 |
| GET | `/usuarios` | `UsuarioController::index` | Administrador | RF-10 |
| POST | `/usuarios/{id}/rol` | `UsuarioController::cambiarRol` | Administrador | RF-10 |
| POST | `/usuarios/{id}/estado` | `UsuarioController::cambiarEstado` | Administrador | RF-10 |

**Ingreso por correo.** `POST /login` recibe el correo institucional. Si es de un auditor registrado, abre la sesión y redirige a `/mis-talleres`; si es de un administrador, pide la contraseña en un segundo paso; si es nuevo y del dominio permitido, lleva a `/registro` para completar nombre y apellidos.

### 5.2 Modelos

| Modelo | Responsabilidad principal |
| --- | --- |
| `Usuario` | Buscar por correo, crear, listar, cambiar rol, activar o desactivar. |
| `Actividad` | Filtrar por nombre, carrera y edificio; crear; actualizar; cambiar estado junto con su bitácora en una sola transacción. |
| `Asignacion` | Asignar varios talleres a un usuario en una transacción, quitar, listar auditores de un taller y talleres de un usuario. |
| `Carrera`, `Edificio` | Listados para los filtros y los formularios. |
| `Bitacora` | Registrar cambios de estado y consultar el historial de un taller. |
| `Acceso` | Registrar eventos de registro, inicio y cierre de sesión, y fallos. |

### 5.3 Casos de uso principales

**CU-01 · Asignar talleres a un usuario (administrador)**

1. El administrador abre `/asignaciones` y elige un usuario registrado.
2. El sistema lista los talleres activos, con los mismos filtros del panel.
3. El administrador marca uno o varios talleres y confirma.
4. El sistema inserta las asignaciones en una transacción, omite las que ya existían y guarda quién las hizo.
5. Se muestra un resumen de las asignaciones creadas.

**CU-02 · Registrar un taller no realizado (administrador o auditor asignado)**

1. El administrador o el auditor asignado abre el detalle del taller y elige el estado *No realizado*.
2. El sistema exige el motivo; sin él, rechaza el cambio. Si quien lo registra es un auditor, verifica además que el taller esté asignado a él y siga *programado*.
3. En una transacción se actualiza `actividades` y se agrega la fila en `bitacora_estados`, con el usuario que hizo el cambio.
4. El taller aparece como no realizado, con su motivo, en el panel central y en "Mis talleres" de sus auditores; las asignaciones quedan en el historial.

**CU-03 · Filtrar talleres (administrador y auditor)**

1. El usuario escribe un nombre o elige una carrera o un edificio. El administrador envía el formulario a `/` y el auditor a `/mis-talleres`, siempre con `GET` y los parámetros `nombre`, `carrera` y `edificio`.
2. El modelo combina con `AND` solo los filtros recibidos, usando la consulta de la sección 4.4. Si el usuario es auditor, agrega además la condición de que el taller esté asignado a él.
3. Los resultados se paginan (20 por página) y conservan los filtros al cambiar de página.

**CU-04 · Registro e inicio de sesión (visitante)**

1. El usuario escribe su correo institucional en la pantalla de ingreso.
2. Si el correo pertenece a un auditor registrado, el sistema abre la sesión sin contraseña; si pertenece a un administrador, pide además su contraseña.
3. Si el correo no está registrado y es del dominio permitido, el sistema pide nombre y apellidos, crea la cuenta con rol auditor y abre la sesión.
4. Se regenera el identificador de sesión, se actualiza `ultimo_acceso` y el evento queda en `accesos`.
5. El auditor llega directo a "Mis talleres", vacío hasta que el administrador le asigne talleres; el administrador llega al panel central.

## 6. Seguridad

Cuatro controles se aplican a cada petición: sesión válida, rol permitido, token CSRF en los envíos y consultas preparadas contra la base de datos. El resto refuerza esos cuatro.

| Riesgo (OWASP) | Control de diseño |
| --- | --- |
| Contraseñas del administrador expuestas | `password_hash()` con Argon2id (o bcrypt si el servidor no lo soporta), `password_verify()` y `password_needs_rehash()` al iniciar sesión. Longitud mínima de 10 caracteres. Los auditores no tienen contraseña. |
| Secuestro o fijación de sesión | `session_regenerate_id(true)` tras el login; cookie con `HttpOnly`, `Secure` y `SameSite=Lax`; `session.use_strict_mode=1`; cierre por inactividad. |
| Fuerza bruta en el login | Conteo de fallos por correo e IP en la tabla `accesos`; bloqueo temporal al superar el límite configurado en `.env`. |
| Inyección SQL | Solo sentencias preparadas con PDO (`ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION`); los nombres de columna para ordenar salen de una lista blanca. |
| XSS | Toda salida pasa por `e()`; cabecera `Content-Security-Policy` con `default-src 'self'`. |
| CSRF | Token por sesión en un campo oculto de cada formulario; `CsrfMiddleware` lo compara con `hash_equals()` en todo `POST`. |
| Acceso indebido (control de acceso roto) | `AuthMiddleware` exige sesión y `RoleMiddleware` valida el rol por ruta. "Mis talleres" usa siempre el usuario de la sesión, nunca un id enviado por el cliente. El panel central y las rutas de gestión están vedados al auditor, y el detalle de un taller verifica que esté asignado al usuario antes de mostrarlo (responde 404 si no lo está). |
| Suplantación de un auditor (ingreso solo con correo) | Riesgo aceptado por ser una aplicación interna. Se limita así: el auditor solo ve y puede marcar como no realizados sus propios talleres, el administrador siempre exige contraseña, el correo debe ser del dominio permitido, cada cambio de estado queda en la bitácora con usuario y fecha, y cada ingreso queda en accesos con su IP. Se recomienda publicar la aplicación solo en la red interna de la universidad. |
| Registro abierto a cualquiera | El registro solo acepta correos del dominio institucional, configurable en `.env`; el administrador puede desactivar cualquier cuenta. |
| Fuga de configuración | `.env` fuera de `public/` y sin versionar; `display_errors` apagado en producción, errores en `storage/logs/`. |
| Transporte y navegador | HTTPS obligatorio con HSTS; cabeceras `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` y `Referrer-Policy`. |

## 7. Diseño de interfaz

La interfaz usa los dos colores institucionales de la Universidad Anáhuac, naranja `#FF8300` y café `#6B3F23`, según su [Manual de Imagen Institucional 2017](https://www.anahuac.mx/mexico/files/ManualdeImagenInstitucional-c.pdf). El café lleva el texto y los botones; el naranja queda para acentos, porque el texto blanco sobre naranja tiene un contraste de solo 2.5:1, por debajo del mínimo de accesibilidad (4.5:1).

### 7.1 Paleta

| Token | Color | Origen | Uso |
| --- | --- | --- | --- |
| `--anahuac-naranja` | `#FF8300` | Manual oficial | Franja del encabezado, menú activo, anillo de foco, acentos. Nunca como fondo de texto blanco. |
| `--anahuac-cafe` | `#6B3F23` | Manual oficial | Encabezado, títulos y botones primarios con texto blanco (contraste ≈ 8.9:1). |
| `--anahuac-cafe-oscuro` | `#4F2E19` | Derivado | Botón primario al pasar el cursor y al presionar. |
| `--anahuac-naranja-suave` | `#FFF3E6` | Derivado (naranja al 10%) | Fila al pasar el cursor, filtros activos, celdas destacadas. |
| `--gris-fondo` | `#F7F4F1` | Derivado | Fondo de página. |
| `--gris-borde` | `#D9D2CB` | Derivado | Bordes de tabla y campos. |
| `--gris-texto` | `#5C524B` | Derivado | Texto secundario (contraste ≈ 7.6:1 sobre blanco). |
| `--estado-realizado` | `#2E7D32` | Semántico | Insignia "Realizado". |
| `--estado-no-realizado` | `#B3261E` | Semántico | Insignia "No realizado". |

Los colores marcados como derivados y semánticos son decisiones de diseño de este documento; el manual solo define naranja y café. Las insignias de estado llevan texto e ícono además del color, para que no dependan solo de él.

### 7.2 Tipografía y logotipo

- **Texto de la aplicación:** Helvetica Neue, familia secundaria del manual, con respaldo `Helvetica, Arial, sans-serif`. La tipografía Óptima del manual se reserva para el logotipo y no se usa en otros textos.
- **Logotipo:** se usa el archivo oficial que entrega la Dirección de Comunicación Institucional, sin cambiar color, proporción ni posición, y con la leyenda de marca registrada (MR).

```css
:root {
  --anahuac-naranja: #FF8300;
  --anahuac-cafe: #6B3F23;
  --anahuac-cafe-oscuro: #4F2E19;
  --anahuac-naranja-suave: #FFF3E6;
  --gris-fondo: #F7F4F1;
  --gris-borde: #D9D2CB;
  --gris-texto: #5C524B;
  --estado-realizado: #2E7D32;
  --estado-no-realizado: #B3261E;
}
body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #2B211A; background: var(--gris-fondo); }
.encabezado { background: var(--anahuac-cafe); border-bottom: 4px solid var(--anahuac-naranja); }
.btn-primario { background: var(--anahuac-cafe); color: #fff; }
.btn-primario:hover { background: var(--anahuac-cafe-oscuro); }
tr:hover td { background: var(--anahuac-naranja-suave); }
:focus-visible { outline: 3px solid var(--anahuac-naranja); outline-offset: 2px; }
```

### 7.3 Pantallas

| Pantalla | Contenido | Acceso |
| --- | --- | --- |
| Inicio de sesión y registro | Campo de correo institucional; solo el administrador ve el campo de contraseña. Si el correo es nuevo, pide nombre y apellidos y lo registra como auditor. | Público |
| Panel central | Barra de filtros (nombre, carrera, edificio) con botones *Filtrar* y *Limpiar*; tabla con nombre, carrera, edificio, horario, estado y auditores. Los filtros se conservan tras enviar. | Administrador |
| Mis talleres | Los talleres asignados al usuario, con el mismo formato de tabla y sin acciones de edición, salvo marcar un taller como no realizado desde su detalle. Es la pantalla de inicio del auditor e incluye los filtros por nombre, carrera y edificio, limitados a sus talleres. | Auditor |
| Detalle del taller | Datos del taller, auditores asignados, historial de estados y el formulario de estado. El administrador puede marcarlo *Realizado* o *No realizado* con motivo (o devolverlo a programado); el auditor asignado solo puede marcarlo no realizado. | Administrador; auditor solo de sus talleres |
| Formulario de taller | Nombre, carrera, edificio (lista por número), fecha, hora de inicio y hora de fin. | Administrador |
| Asignaciones | Selector de usuario y lista de talleres con casillas para asignar uno o varios a la vez. | Administrador |
| Usuarios | Lista de registrados con rol y estado; acciones para cambiar rol y activar o desactivar. | Administrador |

La interfaz es responsiva: en pantallas angostas la tabla del panel se convierte en tarjetas apiladas, una por taller.

## 8. Plan de implementación y pruebas

La construcción avanza en seis fases, de la base técnica al despliegue; cada fase termina con algo que se puede probar por sí solo.

### 8.1 Fases

1. **Base del proyecto:** `composer.json`, estructura de carpetas, `Router`, `Request`, `Response`, `View`, conexión PDO y lectura de `.env`.
2. **Base de datos y autenticación:** `schema.sql`, semillas de roles, script `crear-admin.php`, registro, inicio y cierre de sesión, middleware de sesión y de rol.
3. **Talleres y panel:** catálogos de carreras y edificios, alta y edición de talleres, panel central con filtros por nombre, carrera y edificio.
4. **Asignaciones y estados:** asignar y quitar auditores, "Mis talleres", cambio de estado con motivo y bitácora.
5. **Usuarios y endurecimiento:** gestión de usuarios, límite de intentos de acceso, cabeceras de seguridad y revisión contra la sección 6.
6. **Pruebas y despliegue:** pruebas automáticas y manuales, HTTPS, respaldo periódico de la base de datos y entrega al administrador.

### 8.2 Estrategia de pruebas

| Nivel | Qué se verifica | Herramienta |
| --- | --- | --- |
| Unitarias | `Actividad::filtrar` con cada combinación de filtros; `Asignacion::asignar` sin duplicados; cambio a *no realizado* rechazado sin motivo; generación y validación del token CSRF. | PHPUnit con base de datos de prueba |
| Integración | Ingreso de un auditor solo con correo y redirección a Mis talleres; un administrador sin contraseña es rechazado; un auditor recibe 403 en `/asignaciones`; un `POST` sin token CSRF es rechazado; la asignación y la bitácora se guardan en una sola transacción. | PHPUnit |
| Aceptación | Los casos de uso CU-01 a CU-04 ejecutados por rol (administrador y auditor). | Lista de verificación manual |
| Seguridad | Texto con comillas o código en el nombre de un taller y en los filtros (XSS e inyección SQL); reutilización de sesión tras cerrar sesión; bloqueo tras intentos fallidos; un auditor no puede ver ni modificar talleres de otro aunque conozca su id. | Revisión manual con la lista OWASP |
| Calidad de código | Estilo PSR-12 y análisis estático sin errores. | PHP\_CodeSniffer y PHPStan |

### 8.3 Criterios de aceptación

- El panel devuelve solo los talleres que cumplen los filtros recibidos, solos o combinados.
- Un taller con varios auditores muestra a todos en el panel y en su detalle.
- Un taller *no realizado* siempre muestra su motivo y quién lo registró.
- El mismo auditor no puede quedar asignado dos veces al mismo taller.
- Ninguna acción de administrador funciona con una cuenta de auditor, ni con la URL escrita a mano.
- Un auditor entra directo a "Mis talleres", ve solo los talleres que tiene asignados y no puede abrir el panel central ni el detalle de un taller ajeno.
- Un auditor asignado puede marcar su taller como *no realizado* con motivo; uno no asignado recibe 404.
- Un auditor entra solo con su correo; un administrador no entra sin contraseña.

## 9. Supuestos de diseño

El diseño adopta seis supuestos donde los requerimientos dejaban margen; cada uno indica qué cambiaría si resulta distinto.

| Tema | Supuesto adoptado | Si cambia |
| --- | --- | --- |
| Horario del taller | Evento único: fecha, hora de inicio y hora de fin. | Si los talleres se repiten cada semana, se agrega una tabla `horarios` con día de la semana. |
| Carrera del taller | Cada taller lo da una sola carrera. | Si lo comparten varias, se reemplaza `carrera_id` por una tabla puente `actividad_carreras`. |
| Quién marca *no realizado* | El administrador y el auditor asignado al taller. Solo el administrador lo revierte o lo marca como realizado. | Si solo debe hacerlo el administrador, se quita esa acción al rol auditor en la ruta y en la política de acceso. |
| Confirmación de asistencia | Estar asignado equivale a haber auditado; no hay confirmación aparte. | Si se necesita confirmar quién asistió, se agrega `confirmada_en` a `asignaciones`. |
| Ingreso y registro de usuarios | Los auditores ingresan solo con su correo institucional, sin contraseña, y un correo nuevo del dominio permitido se registra solo como auditor. El administrador conserva contraseña y puede desactivar cuentas. | Si cada registro debe aprobarse, se agrega el estado *pendiente* a `usuarios`. |
| Alcance de notificaciones | El sistema no envía correos ni avisos. | Se agregaría un servicio de correo y una tabla de notificaciones. |

Los seis supuestos están confirmados con quien solicita el sistema; si alguno cambia, la última columna indica el ajuste, y los que tocan el esquema de la sección 4 son el horario, la carrera y la confirmación de asistencia.
