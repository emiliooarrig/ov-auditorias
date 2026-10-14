<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

/**
 * Cuentas del sistema. Solo los administradores ingresan; los alumnos auditores no tienen cuenta
 * (ver Alumno). Las cuentas de auditor de antes del 2026-10-10 quedan desactivadas por la migración.
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
     * Crea un administrador y devuelve su id.
     */
    public function crear(string $nombre, string $apellidos, string $correo, string $hash): int
    {
        $this->db->execute(
            'INSERT INTO usuarios (rol_id, nombre, apellidos, correo, password_hash)
             VALUES ((SELECT id FROM roles WHERE nombre = :rol), :nombre, :apellidos, :correo, :hash)',
            [
                'rol' => self::ADMINISTRADOR,
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'correo' => $correo,
                'hash' => $hash,
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Administradores para su gestión, activos primero.
     *
     * @return list<array{id: int, nombre: string, apellidos: string, correo: string, activo: int,
     *     ultimo_acceso: ?string, creado_en: string}>
     */
    public function administradores(): array
    {
        /** @var list<array{id: int, nombre: string, apellidos: string, correo: string, activo: int, ultimo_acceso: ?string, creado_en: string}> */
        return $this->db->fetchAll(
            'SELECT u.id, u.nombre, u.apellidos, u.correo, u.activo, u.ultimo_acceso, u.creado_en
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre = :rol
             ORDER BY u.activo DESC, u.apellidos, u.nombre, u.id',
            ['rol' => self::ADMINISTRADOR]
        );
    }

    /**
     * Activa o desactiva un administrador (regla 4: las cuentas no se borran). Una cuenta desactivada
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
            if (!$activo) {
                $this->exigirOtroAdministradorActivo($db, $id);
            }
            $db->execute('UPDATE usuarios SET activo = :activo WHERE id = :id', ['activo' => $activo, 'id' => $id]);
        });
    }

    /**
     * Bloquea la fila del administrador hasta el fin de la transacción.
     *
     * @return array{activo: int}
     */
    private function bloquear(Database $db, int $id): array
    {
        $fila = $db->fetchOne(
            'SELECT u.activo FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id AND r.nombre = :rol FOR UPDATE',
            ['id' => $id, 'rol' => self::ADMINISTRADOR]
        );
        if ($fila === null) {
            throw new DomainException('El administrador no existe.');
        }

        /** @var array{activo: int} $fila */
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
     * Convierte una cuenta anterior (de auditor) en administrador activo con contraseña y nombre nuevos.
     */
    public function convertirEnAdministrador(
        int $id,
        string $hash,
        ?string $nombre = null,
        ?string $apellidos = null,
    ): void {
        $this->db->execute(
            'UPDATE usuarios
             SET rol_id = (SELECT id FROM roles WHERE nombre = :rol), password_hash = :hash, activo = 1,
                 nombre = COALESCE(:nombre, nombre), apellidos = COALESCE(:apellidos, apellidos)
             WHERE id = :id',
            ['rol' => self::ADMINISTRADOR, 'hash' => $hash, 'nombre' => $nombre, 'apellidos' => $apellidos, 'id' => $id]
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
