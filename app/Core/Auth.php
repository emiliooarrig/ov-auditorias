<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Acceso;
use App\Models\Usuario;

/**
 * Usuario en sesión. Se recarga de la base en cada petición, así un cambio de rol
 * o una desactivación aplican de inmediato.
 *
 * @phpstan-import-type UsuarioFila from Usuario
 */
final class Auth
{
    private const USUARIO_ID = 'usuario_id';

    /** @var UsuarioFila|null */
    private ?array $usuario = null;
    private bool $cargado = false;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return UsuarioFila|null
     */
    public function user(): ?array
    {
        if (!$this->cargado) {
            $this->cargado = true;
            $id = $this->app->session()->get(self::USUARIO_ID);
            if (is_int($id)) {
                $usuario = (new Usuario($this->app->db()))->buscarPorId($id);
                if ($usuario !== null && (int) $usuario['activo'] === 1) {
                    $this->usuario = $usuario;
                } else {
                    // Cuenta desactivada o eliminada: la sesión deja de ser válida.
                    $this->app->session()->clear();
                }
            }
        }

        return $this->usuario;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function rol(): ?string
    {
        return $this->user()['rol'] ?? null;
    }

    public function esAdministrador(): bool
    {
        return $this->rol() === Usuario::ADMINISTRADOR;
    }

    /**
     * Pantalla de inicio según el rol: panel central o "Mis talleres".
     */
    public function inicio(): string
    {
        return $this->esAdministrador() ? '/' : '/mis-talleres';
    }

    /**
     * Abre la sesión: nuevo id de sesión, nuevo token CSRF, último acceso y bitácora.
     *
     * @param UsuarioFila $usuario
     */
    public function iniciarSesion(array $usuario, Request $request): void
    {
        $session = $this->app->session();
        $session->regenerate();
        $this->app->csrf()->rotate();
        $session->set(self::USUARIO_ID, $usuario['id']);

        (new Usuario($this->app->db()))->registrarAcceso($usuario['id']);
        $this->registrarEvento(Acceso::LOGIN_OK, $usuario['correo'], $usuario['id'], $request);

        $this->usuario = $usuario;
        $this->cargado = true;
    }

    public function cerrarSesion(Request $request): void
    {
        $usuario = $this->user();
        if ($usuario !== null) {
            $this->registrarEvento(Acceso::LOGOUT, $usuario['correo'], $usuario['id'], $request);
        }
        $this->app->session()->clear();
        $this->usuario = null;
    }

    public function registrarEvento(string $evento, string $correo, ?int $usuarioId, Request $request): void
    {
        (new Acceso($this->app->db()))->registrar($evento, $correo, $usuarioId, $request->ip(), $request->userAgent());
    }

    /**
     * Bloqueo temporal por fuerza bruta: demasiados fallos recientes para el correo o la IP.
     * El límite por IP es más amplio porque en el campus muchos usuarios comparten IP.
     */
    public function bloqueado(string $correo, Request $request): bool
    {
        $maximo = (int) $this->app->config('auth.max_fails', 5);
        $fallos = (new Acceso($this->app->db()))->fallosRecientes(
            $correo,
            $request->ip(),
            (int) $this->app->config('auth.lock_minutes', 15)
        );

        return $fallos['correo'] >= $maximo || $fallos['ip'] >= $maximo * 4;
    }
}
