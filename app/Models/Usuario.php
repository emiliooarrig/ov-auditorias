<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

/**
 * Usuarios del sistema (auditores y administradores).
 *
 * @phpstan-type UsuarioFila array{
 *     id: int, rol_id: int, rol: string, matricula: ?string, nombre: string, apellidos: string,
 *     correo: string, password_hash: ?string, activo: int, ultimo_acceso: ?string,
 *     creado_en: string, actualizado_en: string
 * }
 */
final class Usuario
{
    public const ADMINISTRADOR = 'administrador';
    public const AUDITOR = 'auditor';

    private const SELECT = 'SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return UsuarioFila|null
     */
    public function buscarPorCorreo(string $correo): ?array
    {
        /** @var UsuarioFila|null */
        return $this->db->fetchOne(self::SELECT . ' WHERE u.correo = :correo', ['correo' => $correo]);
    }

    /**
     * @return UsuarioFila|null
     */
    public function buscarPorId(int $id): ?array
    {
        /** @var UsuarioFila|null */
        return $this->db->fetchOne(self::SELECT . ' WHERE u.id = :id', ['id' => $id]);
    }

    /**
     * Crea un usuario y devuelve su id. Los auditores se crean sin contraseña.
     */
    public function crear(string $rol, string $nombre, string $apellidos, string $correo, ?string $hash = null): int
    {
        $this->db->execute(
            'INSERT INTO usuarios (rol_id, nombre, apellidos, correo, password_hash)
             VALUES ((SELECT id FROM roles WHERE nombre = :rol), :nombre, :apellidos, :correo, :hash)',
            ['rol' => $rol, 'nombre' => $nombre, 'apellidos' => $apellidos, 'correo' => $correo, 'hash' => $hash]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Auditores activos, a quienes se pueden asignar talleres.
     *
     * @return list<array{id: int, nombre: string, apellidos: string, correo: string}>
     */
    public function auditoresActivos(): array
    {
        /** @var list<array{id: int, nombre: string, apellidos: string, correo: string}> */
        return $this->db->fetchAll(
            'SELECT u.id, u.nombre, u.apellidos, u.correo
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre = :rol AND u.activo = 1
             ORDER BY u.apellidos, u.nombre',
            ['rol' => self::AUDITOR]
        );
    }

    /**
     * Todos los usuarios para la gestión (RF-10), con cuántos talleres activos tiene asignados cada uno.
     * La búsqueda y los filtros de rol y estado se hacen en el navegador.
     *
     * @return list<array{id: int, nombre: string, apellidos: string, correo: string, rol: string, activo: int,
     *     ultimo_acceso: ?string, creado_en: string, talleres: int}>
     */
    public function todos(): array
    {
        /** @var list<array{id: int, nombre: string, apellidos: string, correo: string, rol: string, activo: int, ultimo_acceso: ?string, creado_en: string, talleres: int}> $filas */
        $filas = $this->db->fetchAll(
            "SELECT u.id, u.nombre, u.apellidos, u.correo, r.nombre AS rol, u.activo, u.ultimo_acceso, u.creado_en,
                    (SELECT COUNT(*) FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                     WHERE s.usuario_id = u.id AND a.activo = 1) AS talleres
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             ORDER BY u.apellidos, u.nombre, u.id"
        );

        return $filas;
    }

    /**
     * Cambia el rol de un usuario (RF-10). Al promover a administrador exige el hash de su nueva
     * contraseña; al pasar a auditor se borra la contraseña, que el auditor no usa.
     */
    public function cambiarRol(int $id, string $rol, ?string $hash, int $actorId): void
    {
        if (!in_array($rol, [self::ADMINISTRADOR, self::AUDITOR], true)) {
            throw new DomainException('Rol no válido.');
        }
        if ($id === $actorId) {
            throw new DomainException('No puedes cambiar tu propio rol.');
        }
        if ($rol === self::ADMINISTRADOR && ($hash === null || $hash === '')) {
            throw new DomainException('Para hacerlo administrador asígnale una contraseña.');
        }

        $this->db->transaction(function (Database $db) use ($id, $rol, $hash): void {
            $actual = $this->bloquear($db, $id);
            if ($actual['rol'] === $rol) {
                throw new DomainException('El usuario ya tiene ese rol.');
            }
            if ($actual['rol'] === self::ADMINISTRADOR && (int) $actual['activo'] === 1) {
                $this->exigirOtroAdministradorActivo($db, $id);
            }
            $db->execute(
                'UPDATE usuarios SET rol_id = (SELECT id FROM roles WHERE nombre = :rol), password_hash = :hash
                 WHERE id = :id',
                ['rol' => $rol, 'hash' => $rol === self::ADMINISTRADOR ? $hash : null, 'id' => $id]
            );
        });
    }

    /**
     * Activa o desactiva una cuenta (regla 4: los usuarios no se borran). Una cuenta desactivada
     * pierde su sesión en la siguiente petición y no puede volver a ingresar.
     */
    public function cambiarActivo(int $id, bool $activo, int $actorId): void
    {
        if (!$activo && $id === $actorId) {
            throw new DomainException('No puedes desactivar tu propia cuenta.');
        }

        $this->db->transaction(function (Database $db) use ($id, $activo): void {
            $actual = $this->bloquear($db, $id);
            if ((int) $actual['activo'] === (int) $activo) {
                throw new DomainException($activo ? 'La cuenta ya está activa.' : 'La cuenta ya está desactivada.');
            }
            if (!$activo && $actual['rol'] === self::ADMINISTRADOR) {
                $this->exigirOtroAdministradorActivo($db, $id);
            }
            $db->execute('UPDATE usuarios SET activo = :activo WHERE id = :id', ['activo' => $activo, 'id' => $id]);
        });
    }

    /**
     * @return array{rol: string, activo: int}
     */
    private function bloquear(Database $db, int $id): array
    {
        $fila = $db->fetchOne(
            'SELECT r.nombre AS rol, u.activo FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id FOR UPDATE',
            ['id' => $id]
        );
        if ($fila === null) {
            throw new DomainException('El usuario no existe.');
        }

        /** @var array{rol: string, activo: int} $fila */
        return $fila;
    }

    /**
     * Impide dejar el sistema sin ningún administrador activo.
     */
    private function exigirOtroAdministradorActivo(Database $db, int $exceptoId): void
    {
        $otros = (int) $db->fetchValue(
            'SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre = :rol AND u.activo = 1 AND u.id <> :id FOR UPDATE',
            ['rol' => self::ADMINISTRADOR, 'id' => $exceptoId]
        );
        if ($otros === 0) {
            throw new DomainException('Debe quedar al menos un administrador activo.');
        }
    }

    public function registrarAcceso(int $id): void
    {
        $this->db->execute('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function actualizarPassword(int $id, string $hash): void
    {
        $this->db->execute('UPDATE usuarios SET password_hash = :hash WHERE id = :id', ['hash' => $hash, 'id' => $id]);
    }

    /**
     * Promueve a administrador asignándole contraseña (un administrador sin contraseña no podría entrar).
     */
    public function convertirEnAdministrador(int $id, string $hash): void
    {
        $this->db->execute(
            'UPDATE usuarios
             SET rol_id = (SELECT id FROM roles WHERE nombre = :rol), password_hash = :hash, activo = 1
             WHERE id = :id',
            ['rol' => self::ADMINISTRADOR, 'hash' => $hash, 'id' => $id]
        );
    }

    public static function normalizarCorreo(string $correo): string
    {
        return mb_strtolower(trim($correo));
    }

    /**
     * El correo debe ser válido y su dominio exactamente uno de los permitidos (sin subdominios).
     *
     * @param list<string> $dominios
     */
    public static function correoPermitido(string $correo, array $dominios): bool
    {
        if (strlen($correo) > 150 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $dominio = substr($correo, strrpos($correo, '@') + 1);

        return in_array($dominio, $dominios, true);
    }

    /**
     * Quita espacios repetidos de un nombre o apellido.
     */
    public static function normalizarNombre(string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    }

    /**
     * Mensaje de error si un nombre o apellido no es válido; null si lo es.
     */
    public static function validarNombre(string $valor, int $maximo): ?string
    {
        if ($valor === '') {
            return 'Este campo es obligatorio.';
        }
        if (mb_strlen($valor) > $maximo) {
            return "No puede pasar de {$maximo} caracteres.";
        }
        if (preg_match("/^\\p{L}[\\p{L}\\p{M}'’. \\-]*$/u", $valor) !== 1) {
            return 'Solo se permiten letras, espacios, apóstrofos, puntos y guiones.';
        }

        return null;
    }
}
