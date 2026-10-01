# Auditores de Talleres

Sistema de asignación y registro de auditores de talleres universitarios (Universidad Anáhuac).
El diseño completo está en `SDD — Sistema de Asignación y Registro de Auditores de Talleres Universitarios.md`.

PHP 8.2+ · MVC propio · Composer (PSR-4) · MySQL 8.0.16+ / MariaDB 10.6+.

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env        # en local: APP_DEBUG=true, SESSION_SECURE=false, credenciales de BD
php bin/instalar-bd.php     # crea la base, el esquema y los catálogos de carreras y edificios
php bin/crear-admin.php     # primer administrador (pide la contraseña; solo guarda el hash)
composer serve              # http://localhost:8000
```

- **Ingreso:** el auditor entra solo con su correo `@anahuac.mx`; un correo nuevo del dominio se
  registra como auditor. El administrador escribe su correo y luego su contraseña.
- `php bin/instalar-bd.php --reiniciar` borra y recrea la base (pide escribir su nombre para confirmar).
- `php bin/migrar-bd.php` pone al día una base ya instalada con los cambios de `database/migraciones`
  (por ejemplo, los edificios con nombre y los grupos de talleres Taller 1 a 6 del 2026-09-30).
  Se puede ejecutar varias veces.
- `php bin/crear-admin.php` también convierte en administrador a un usuario existente o cambia
  la contraseña de un administrador.
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
bin/           scripts de consola (crear-admin.php)
storage/logs/  bitácora de errores de la aplicación
tests/         PHPUnit
```

## Seguridad (SDD, sección 6)

- Con `APP_ENV=production` la depuración se apaga y la cookie de sesión es `Secure` aunque el `.env`
  diga otra cosa. Los errores se registran en `storage/logs/` (`app-*.log` y `php-errores.log`).
- La raíz web debe ser `public/`. El `.htaccess` de la raíz niega todo acceso por si el servidor
  se configura mal.
- HTTPS es obligatorio: la redirección de HTTP a HTTPS se configura en el servidor web.
  La aplicación envía HSTS.
- Cada petición que cambia datos exige sesión, rol y token CSRF; el auditor solo ve sus talleres
  (404 para los ajenos).
- Bloqueo temporal tras `AUTH_MAX_FAILS` fallos en `AUTH_LOCK_MINUTES` minutos por correo
  (por IP, el cuádruple, porque en el campus muchos usuarios comparten IP).
- Riesgo aceptado: el auditor ingresa solo con su correo. Se recomienda publicar la aplicación
  únicamente en la red interna de la universidad.

## Decisiones sobre huecos del SDD

1. Promover un auditor a administrador exige asignarle contraseña.
2. Revertir un taller a *programado* limpia `motivo_no_realizado`; el motivo queda en `bitacora_estados`.
3. La consulta del panel agrupa también por `c.nombre, e.numero` (compatibilidad con MariaDB).
4. La paginación (20 por página) usa una consulta `COUNT(DISTINCT a.id)` con los mismos filtros.
5. La cookie `Secure` se controla con `SESSION_SECURE` para desarrollo sin HTTPS.
6. Los scripts de consola viven en `bin/`.
7. Las rutas con id usan `{id:\d+}` para no chocar con `/actividades/nueva`.
8. No se pueden asignar talleres a usuarios desactivados.

## Avance

- [x] Fase 1 — Base del proyecto
- [x] Fase 2 — Base de datos y autenticación
- [x] Fase 3 — Talleres y panel
- [x] Fase 4 — Asignaciones y estados
- [x] Fase 5 — Usuarios y endurecimiento
- [ ] Fase 6 — Pruebas y despliegue
