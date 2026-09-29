# Auditores de Talleres

Sistema de asignación y registro de auditores de talleres universitarios (Universidad Anáhuac).
El diseño completo está en `SDD — Sistema de Asignación y Registro de Auditores de Talleres Universitarios.md`.

PHP 8.2+ · MVC propio · Composer (PSR-4) · MySQL 8.0.16+ / MariaDB 10.6+.

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env        # en local: APP_DEBUG=true, SESSION_SECURE=false, credenciales de BD
composer serve              # http://localhost:8000
```

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
database/      schema.sql y seeds.sql
bin/           scripts de consola (crear-admin.php)
storage/logs/  bitácora de errores de la aplicación
tests/         PHPUnit
```

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
- [ ] Fase 2 — Base de datos y autenticación
- [ ] Fase 3 — Talleres y panel
- [ ] Fase 4 — Asignaciones y estados
- [ ] Fase 5 — Usuarios y endurecimiento
- [ ] Fase 6 — Pruebas y despliegue
