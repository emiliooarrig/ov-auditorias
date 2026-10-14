# Auditores de Talleres

Control interno de las auditorías de talleres universitarios (Universidad Anáhuac): qué alumnos auditan
cada taller. Solo los administradores tienen cuenta; los alumnos auditores no entran al sistema.
El diseño está en `sdd.md` (copia local, fuera de git).

PHP 8.2+ · MVC propio · Composer (PSR-4) · MySQL 8.0.16+ / MariaDB 10.6+.

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env        # en local: APP_DEBUG=true, SESSION_SECURE=false, credenciales de BD
php bin/instalar-bd.php     # crea la base, el esquema y los catálogos de carreras y edificios
php bin/crear-admin.php     # primer administrador (pide la contraseña; solo guarda el hash)
composer serve              # http://localhost:8000
```

- **Ingreso:** solo administradores, con su correo `@anahuac.mx` y su contraseña. El primero se crea con
  `bin/crear-admin.php`; los demás, desde la pantalla Administradores.
- **Alumnos:** el administrador los da de alta con nombre, apellidos y horario (Matutino 07:00–14:00,
  Vespertino 14:00–19:00 o Todo el día 07:00–19:00), uno por uno o pegando una lista (un alumno por
  renglón, con coma o en columnas de Excel; todos los de la lista reciben el horario elegido). No tienen
  cuenta ni correo.
- **Asignaciones:** a mano desde el panel del alumno o el detalle del taller, o al azar desde
  Asignaciones (ver abajo). Un alumno solo audita talleres que caben en su horario.
- **Reporte PDF:** el botón "Descargar reporte PDF" del Panel central descarga todos los talleres activos
  con su información completa y sus auditores, en una tabla horizontal (carta apaisada) agrupada por
  fecha y grupo. Lo genera Dompdf; las métricas de fuentes se guardan en `storage/cache/dompdf`.
- `php bin/instalar-bd.php --reiniciar` borra y recrea la base (pide escribir su nombre para confirmar).
- `php bin/migrar-bd.php` pone al día una base ya instalada con los cambios de `database/migraciones`
  (por ejemplo, los grupos de talleres del 2026-09-30, los alumnos sin cuenta del 2026-10-10 o los
  horarios de los alumnos del 2026-10-12).
  Se puede ejecutar varias veces.
- `php bin/crear-admin.php` también cambia la contraseña de un administrador o convierte en
  administrador una cuenta anterior de auditor.
- Las pruebas de integración usan la base `auditores_talleres_test`, que se borra y recrea en cada
  corrida con las credenciales del `.env`; si MySQL no está disponible, esas pruebas se omiten.

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
database/      schema.sql, seeds.sql y migraciones/ (cambios para bases ya instaladas)
bin/           scripts de consola (instalar-bd, migrar-bd, crear-admin)
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

## Migración del 2026-10-12 (horario de los alumnos)

`php bin/migrar-bd.php` crea el catálogo `horarios_alumno`, agrega `alumnos.horario_id` y registra el
horario de los 34 auditores del Día OV 2026 según el reporte de asistencias: los reconoce por su nombre
completo (los que cargó `database/nuevos.sql`) o, si vienen de una cuenta de auditor, por su matrícula.
El turno "13:00 a 19:00 hrs." del reporte se registra como Vespertino (14:00–19:00). No pisa horarios ya
registrados; los demás alumnos quedan sin horario hasta que se registre en su panel ("Corregir datos").

## Migración del 2026-10-10 (alumnos sin cuenta)

`php bin/migrar-bd.php` convierte cada cuenta de auditor en un alumno con el mismo id, así sus
asignaciones se conservan. Esas cuentas quedan desactivadas, no se borran, porque la bitácora de estados
guarda quién marcó cada taller. Las asignaciones existentes quedan como manuales.

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
