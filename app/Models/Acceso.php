<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Bitácora de accesos: registros, inicios y cierres de sesión, y fallos.
 */
final class Acceso
{
    public const REGISTRO = 'registro';
    public const LOGIN_OK = 'login_ok';
    public const LOGIN_FALLIDO = 'login_fallido';
    public const LOGOUT = 'logout';

    public function __construct(private readonly Database $db)
    {
    }

    public function registrar(string $evento, string $correo, ?int $usuarioId, string $ip, ?string $userAgent): void
    {
        $this->db->execute(
            'INSERT INTO accesos (usuario_id, correo, evento, ip, user_agent)
             VALUES (:usuario_id, :correo, :evento, :ip, :user_agent)',
            [
                'usuario_id' => $usuarioId,
                'correo' => mb_substr($correo, 0, 150),
                'evento' => $evento,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]
        );
    }

    /**
     * Fallos de inicio de sesión recientes para un correo y para una IP.
     *
     * @return array{correo: int, ip: int}
     */
    public function fallosRecientes(string $correo, string $ip, int $minutos): array
    {
        $fila = $this->db->fetchOne(
            "SELECT COALESCE(SUM(correo = :correo), 0) AS por_correo, COALESCE(SUM(ip = :ip), 0) AS por_ip
             FROM accesos
             WHERE evento = 'login_fallido'
               AND creado_en >= NOW() - INTERVAL :minutos MINUTE
               AND (correo = :correo2 OR ip = :ip2)",
            ['correo' => $correo, 'ip' => $ip, 'minutos' => $minutos, 'correo2' => $correo, 'ip2' => $ip]
        );

        return ['correo' => (int) ($fila['por_correo'] ?? 0), 'ip' => (int) ($fila['por_ip'] ?? 0)];
    }
}
