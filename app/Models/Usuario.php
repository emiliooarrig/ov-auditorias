<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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
