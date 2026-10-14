# Auditores de Talleres

Control interno de las auditorías de talleres universitarios (Universidad Anáhuac): qué alumnos auditan
cada taller. Solo los administradores tienen cuenta; los alumnos auditores no entran al sistema.
El diseño está en `sdd.md` (copia local, fuera de git).

PHP 8.2+ · MVC propio · Composer (PSR-4) · MySQL 8.0.16+ / MariaDB 10.6+.

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env        # en local: APP_DEBUG=true, SESSION_SECURE=false, credenciales de BD
php bin/instalar-bd.php     # crea la base con database/schema.sql y database/datos.sql
php bin/crear-admin.php     # contraseña del administrador (datos.sql lo trae sin ella; solo guarda el hash)
composer serve              # http://localhost:8000
```

- **Ingreso:** solo administradores, con su correo `@anahuac.mx` y su contraseña. `datos.sql` trae al
  administrador sin contraseña: se la asigna `php bin/crear-admin.php --correo=earriaga@anahuac.mx`.
  Los demás se crean desde la pantalla Administradores.
- **Alumnos:** el administrador los da de alta con nombre, apellidos y horario (Matutino 07:00–14:00,
  Vespertino 13:00–19:00 o Todo el día 07:00–19:00), uno por uno o pegando una lista (un alumno por
  renglón, con coma o en columnas de Excel; todos los de la lista reciben el horario elegido). No tienen
  cuenta ni correo.
- **Asignaciones:** a mano desde el panel del alumno o el detalle del taller, o al azar desde
  Asignaciones (ver abajo). Un alumno solo audita talleres que caben en su horario.
- **Reportes PDF** (Panel central), pensados para repartir: sin métricas ni quién o cuándo se generaron.
  - "Reporte de talleres PDF": todos los talleres activos con su información y sus auditores, en una
    tabla horizontal (carta apaisada) agrupada por fecha y grupo.
  - "Directorio de alumnos PDF": para imprimir. Cada alumno activo en un renglón, por apellido y con
    separadores por letra, con la hora, el taller, la carrera del taller y el edificio de cada uno de sus
    talleres (carta vertical). Así cada alumno se encuentra sin buscar en la tabla de talleres.
  - Los genera Dompdf; las métricas de fuentes se guardan en `storage/cache/dompdf`.
- `php bin/instalar-bd.php --reiniciar` borra y recrea la base (pide escribir su nombre para confirmar).
- `php bin/crear-admin.php` también cambia la contraseña de un administrador o convierte en
  administrador una cuenta anterior de auditor.
- Las pruebas de integración usan la base `auditores_talleres_test`, que se borra y recrea en cada
  corrida con `schema.sql` y `datos.sql` y las credenciales del `.env`; antes de cada prueba se vacían
  usuarios, alumnos, talleres y asignaciones y quedan solo los catálogos. Si MySQL no está disponible,
  esas pruebas se omiten.

En producción, la raíz web del servidor debe apuntar a `public/`; nada fuera de esa carpeta
debe ser accesible por HTTP.

## Comandos

| Comando | Qué hace |
| --- | --- |
| `composer serve` | Servidor de desarrollo en `localhost:8000` |
| `composer test` | Pruebas con PHPUnit |
| `composer lint` | Estilo PSR-12 con PHP_CodeSniffer |
| `composer analyse` | Análisis estático con PHPStan |

## Estructura

```text
public/        front controller (index.php), .htaccess y assets
app/Core/      App, Router, Request, Response, View, Database, Session, Csrf, Logger
app/Controllers/, app/Models/, app/Middleware/, app/Views/
app/Config/    app.php (configuración desde .env) y routes.php
database/      schema.sql (DDL) y datos.sql (DML con todos los datos; ver "Base de datos")
bin/           scripts de consola (instalar-bd, crear-admin)
storage/logs/  bitácora de errores de la aplicación
storage/cache/ caché de fuentes del reporte PDF (se crea sola; no se versiona)
tests/         PHPUnit
```

## Asignación al azar

Desde Asignaciones, el botón "Asignar al azar" completa lo que falta sin tocar lo ya asignado. El campo
"Auditores por taller" (de 1 a 10; propone 2) fija cuántos auditores debe tener cada taller. Participan
los alumnos activos y los talleres programados:

1. Cada taller recibe auditores hasta tener los pedidos, por vueltas: en la primera cada taller sin
   auditor recibe uno, en la segunda cada taller con uno recibe el segundo, y así. Si no alcanzan los
   alumnos, ningún taller recibe otro auditor mientras uno de su misma hora siga con menos.
2. Cada lugar va al alumno libre a esa hora con menos talleres, así que la carga se reparte parejo: los
   talleres que audita cada alumno salen de los auditores pedidos y de cuántos alumnos caben a cada hora.
3. Cada alumno sin taller recibe el taller libre para él con menos auditores, aunque pase de los pedidos.
4. Un alumno nunca queda en dos talleres de la misma fecha y grupo (la misma hora).
5. Un alumno solo recibe talleres que empiezan y terminan dentro de su horario; los alumnos sin horario
   registrado no participan (la pantalla los lista). Un taller solo se queda con menos auditores de los
   pedidos si todos los alumnos cuyo horario lo cubre tienen ya otro taller a esa hora; el resultado lo
   avisa y dice entre cuántos talleres audita ahora cada alumno.

La pantalla muestra el reparto actual: cuántos talleres tienen 0, 1, 2… auditores y cuántos alumnos
auditan 0, 1, 2… talleres.

Para repartir desde cero, "Quitar todas las asignaciones" borra, previa confirmación, las asignaciones de
todos los talleres programados, hechas a mano o al azar. Las de talleres realizados o no realizados se
conservan como registro de quién auditó.

Los empates se rompen al azar. Cada asignación guarda si fue manual o aleatoria, quién la hizo y cuándo.
El algoritmo está en `app/Models/Sorteo.php` y no toca la base, así que se prueba por separado.

Si hay asignaciones programadas fuera del horario del alumno (hechas antes de registrar su horario, o
porque el taller cambió de grupo), Asignaciones las lista con un botón para quitarlas de una vez; después
"Asignar al azar" vuelve a cubrir esos talleres con alumnos en horario.

## Base de datos

La carpeta `database/` tiene solo dos archivos, generados el 2026-10-14 a partir de la base en uso:

- `schema.sql` (DDL): la estructura completa. Unifica el esquema original y las migraciones que se
  aplicaron hasta esa fecha (edificios con nombre, grupos de talleres, alumnos sin cuenta, horarios de
  los alumnos y Vespertino de 13:00 a 19:00).
- `datos.sql` (DML): todos los datos: roles, administrador (sin contraseña), horarios, carreras,
  edificios, grupos, los 120 alumnos, los 200 talleres del Día OV 2026 y sus asignaciones. No incluye la
  bitácora de ingresos (`accesos`). Reemplaza a `seeds.sql`, `nuevos.sql` y `nuevos2.sql`.

Una base instalada con estos dos archivos es idéntica, fila por fila, a la base de la que salieron.

**Auditoría de alumnos.** El encabezado de `datos.sql` concilia a los alumnos con sus dos fuentes: el
reporte de asistencias (34) y el Excel de citas (119). Son 120 distintos (33 en ambas, 1 solo en el
reporte y 86 solo en el Excel); ninguno falta en la base y no hay alumnos sin fuente. Cada alumno lleva un
comentario con su horario, su matrícula (la base no la guarda) y en qué fuente aparece; las marcas
REVISAR señalan matrículas o turnos que el Excel no dejaba claros. Cada taller lleva su carrera, grupo y
responsable, y cada asignación el alumno y el taller.

## Seguridad (SDD, sección 6)

- Con `APP_ENV=production` la depuración se apaga y la cookie de sesión es `Secure` aunque el `.env`
  diga otra cosa. Los errores se registran en `storage/logs/` (`app-*.log` y `php-errores.log`).
- La raíz web debe ser `public/`. El `.htaccess` de la raíz niega todo acceso por si el servidor
  se configura mal.
- HTTPS es obligatorio: la redirección de HTTP a HTTPS se configura en el servidor web.
  La aplicación envía HSTS.
- Toda pantalla exige sesión de administrador activo, y cada petición que cambia datos exige también
  el token CSRF. Una sesión que ya no es de un administrador activo se cierra en la siguiente petición.
- Bloqueo temporal tras `AUTH_MAX_FAILS` fallos en `AUTH_LOCK_MINUTES` minutos por correo
  (por IP, el cuádruple, porque en el campus muchos equipos comparten IP).
- El ingreso no revela si un correo existe: el mensaje es el mismo para correo desconocido y contraseña
  incorrecta.

## Decisiones sobre huecos del SDD

1. Todo administrador tiene contraseña; para cambiar la propia se pide la actual.
2. Revertir un taller a *programado* limpia `motivo_no_realizado`; el motivo queda en `bitacora_estados`.
3. La consulta del panel agrupa también por `c.nombre, e.numero` (compatibilidad con MariaDB).
4. La paginación (20 por página) usa una consulta `COUNT(DISTINCT a.id)` con los mismos filtros.
5. La cookie `Secure` se controla con `SESSION_SECURE` para desarrollo sin HTTPS.
6. Los scripts de consola viven en `bin/`.
7. Las rutas con id usan `{id:\d+}` para no chocar con `/actividades/nueva`.
8. No se pueden asignar talleres a alumnos dados de baja; darlos de baja les quita los talleres que
   siguen programados.
9. Un alumno no puede tener dos talleres a la misma hora (misma fecha y grupo), ni a mano ni al azar.
10. Solo se asigna o se quita un alumno mientras el taller está programado.
11. Dos alumnos no pueden tener el mismo nombre completo (sin correo, es lo único que los distingue).

## Avance

- [x] Fase 1 — Base del proyecto
- [x] Fase 2 — Base de datos y autenticación
- [x] Fase 3 — Talleres y panel
- [x] Fase 4 — Asignaciones y estados
- [x] Fase 5 — Usuarios y endurecimiento
- [x] Control interno: alumnos sin cuenta, asignación al azar (2026-10-10)
- [x] Horario de los alumnos en las asignaciones y reporte PDF de talleres (2026-10-12)
- [ ] Fase 6 — Pruebas y despliegue
