<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Acceso;
use App\Models\Usuario;

/**
 * Ingreso y cierre de sesión de los administradores, los únicos usuarios del sistema (RF-01, CU-04).
 * Los alumnos auditores no ingresan.
 */
final class AuthController extends Controller
{
    public function mostrarLogin(Request $request): Response
    {
        return $this->view('auth/login', [
            'titulo' => 'Ingresar',
            'dominios' => $this->dominios(),
        ]);
    }

    /**
     * Correo institucional y contraseña en un solo paso. Ante un fallo no se dice si el correo existe:
     * el mensaje es el mismo para correo desconocido y contraseña incorrecta.
     */
    public function login(Request $request): Response
    {
        $auth = $this->app->auth();
        $correo = Usuario::normalizarCorreo($request->inputString('correo'));
        $password = $request->input('password');
        $password = is_string($password) ? $password : '';

        if (!Usuario::correoPermitido($correo, $this->dominios())) {
            return $this->volverConError($correo, ['correo' => $this->mensajeDominio()]);
        }
        if ($auth->bloqueado($correo, $request)) {
            return $this->volverConError($correo, ['password' => $this->mensajeBloqueo()]);
        }

        $usuarios = new Usuario($this->db());
        $usuario = $usuarios->buscarPorCorreo($correo);
        $esAdministrador = $usuario !== null && $usuario['rol'] === Usuario::ADMINISTRADOR;

        if (!$esAdministrador || !Password::verify($password, $usuario['password_hash'])) {
            $auth->registrarEvento(Acceso::LOGIN_FALLIDO, $correo, $usuario['id'] ?? null, $request);

            return $this->volverConError($correo, ['password' => 'El correo o la contraseña no son correctos.']);
        }
        // Solo con la contraseña correcta se revela que la cuenta está desactivada.
        if (!Auth::puedeIngresar($usuario)) {
            $auth->registrarEvento(Acceso::LOGIN_FALLIDO, $correo, $usuario['id'], $request);

            return $this->volverConError($correo, [
                'password' => 'Tu cuenta está desactivada. Pide a otro administrador que la reactive.',
            ]);
        }

        if (Password::needsRehash((string) $usuario['password_hash'])) {
            $usuarios->actualizarPassword($usuario['id'], Password::hash($password));
        }

        $auth->iniciarSesion($usuario, $request);
        $this->session()->flash('exito', 'Hola, ' . $usuario['nombre'] . '. Iniciaste sesión.', true);

        return $this->redirect('/');
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->cerrarSesion($request);
        $this->session()->flash('exito', 'Cerraste sesión.', true);

        return $this->redirect('/login');
    }

    /**
     * Regresa al ingreso con el correo escrito; la contraseña nunca se conserva.
     *
     * @param array<string, string> $errores
     */
    private function volverConError(string $correo, array $errores): Response
    {
        $this->session()->flashInput(['correo' => $correo]);
        $this->session()->flashErrors($errores);

        return $this->redirect('/login');
    }

    /**
     * @return list<string>
     */
    private function dominios(): array
    {
        /** @var list<string> */
        return (array) $this->app->config('auth.allowed_domains', []);
    }

    private function mensajeDominio(): string
    {
        return 'Usa tu correo institucional (@' . implode(' o @', $this->dominios()) . ').';
    }

    private function mensajeBloqueo(): string
    {
        return 'Demasiados intentos fallidos. Espera ' . (int) $this->app->config('auth.lock_minutes', 15)
            . ' minutos e inténtalo de nuevo.';
    }
}
