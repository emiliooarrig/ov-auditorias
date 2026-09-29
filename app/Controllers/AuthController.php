<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Acceso;
use App\Models\Usuario;
use PDOException;

/**
 * Ingreso por correo institucional, registro de auditores y cierre de sesión (RF-01, RF-02, CU-04).
 *
 * Paso 1: POST /login con el correo.
 *   - Auditor registrado: abre la sesión sin contraseña.
 *   - Administrador: pasa al paso 2 (contraseña); el correo queda guardado en la sesión.
 *   - Correo nuevo del dominio permitido: va a /registro para completar nombre y apellidos.
 * Paso 2: POST /login con la contraseña, solo si hay un administrador pendiente en la sesión.
 */
final class AuthController extends Controller
{
    private const ADMIN_PENDIENTE = 'login_admin_correo';
    private const REGISTRO_CORREO = 'registro_correo';

    public function mostrarLogin(Request $request): Response
    {
        if ($request->query('cambiar') !== null) {
            $this->session()->forget(self::ADMIN_PENDIENTE);

            return $this->redirect('/login');
        }

        $pendiente = $this->session()->get(self::ADMIN_PENDIENTE);

        return $this->view('auth/login', [
            'titulo' => 'Ingresar',
            'correoAdministrador' => is_string($pendiente) ? $pendiente : null,
            'dominios' => $this->dominios(),
        ]);
    }

    public function login(Request $request): Response
    {
        $pendiente = $this->session()->get(self::ADMIN_PENDIENTE);
        if (is_string($pendiente) && $request->input('password') !== null) {
            return $this->loginAdministrador($pendiente, $request);
        }
        $this->session()->forget(self::ADMIN_PENDIENTE);

        $auth = $this->app->auth();
        $correo = Usuario::normalizarCorreo($request->inputString('correo'));

        if (!Usuario::correoPermitido($correo, $this->dominios())) {
            return $this->volverConError('/login', ['correo' => $this->mensajeDominio()], $request);
        }
        if ($auth->bloqueado($correo, $request)) {
            return $this->volverConError('/login', ['correo' => $this->mensajeBloqueo()], $request);
        }

        $usuarios = new Usuario($this->db());
        $usuario = $usuarios->buscarPorCorreo($correo);

        if ($usuario === null) {
            $this->session()->set(self::REGISTRO_CORREO, $correo);

            return $this->redirect('/registro');
        }
        if ((int) $usuario['activo'] !== 1) {
            $auth->registrarEvento(Acceso::LOGIN_FALLIDO, $correo, $usuario['id'], $request);

            return $this->volverConError('/login', [
                'correo' => 'Tu cuenta está desactivada. Contacta al administrador.',
            ], $request);
        }
        if ($usuario['rol'] === Usuario::ADMINISTRADOR) {
            $this->session()->set(self::ADMIN_PENDIENTE, $correo);

            return $this->redirect('/login');
        }

        $auth->iniciarSesion($usuario, $request);

        return $this->redirect($auth->inicio());
    }

    /**
     * Paso 2 del administrador. El correo sale de la sesión, nunca del formulario.
     */
    private function loginAdministrador(string $correo, Request $request): Response
    {
        $auth = $this->app->auth();
        if ($auth->bloqueado($correo, $request)) {
            return $this->volverConError('/login', ['password' => $this->mensajeBloqueo()]);
        }

        $password = $request->input('password');
        $usuarios = new Usuario($this->db());
        $usuario = $usuarios->buscarPorCorreo($correo);

        if (
            !is_string($password)
            || $usuario === null
            || (int) $usuario['activo'] !== 1
            || $usuario['rol'] !== Usuario::ADMINISTRADOR
            || !Password::verify($password, $usuario['password_hash'])
        ) {
            $auth->registrarEvento(Acceso::LOGIN_FALLIDO, $correo, $usuario['id'] ?? null, $request);

            return $this->volverConError('/login', ['password' => 'La contraseña no es correcta.']);
        }

        if (Password::needsRehash((string) $usuario['password_hash'])) {
            $usuarios->actualizarPassword($usuario['id'], Password::hash($password));
        }

        $this->session()->forget(self::ADMIN_PENDIENTE);
        $auth->iniciarSesion($usuario, $request);

        return $this->redirect($auth->inicio());
    }

    public function mostrarRegistro(Request $request): Response
    {
        $correo = $this->session()->get(self::REGISTRO_CORREO);
        if (!is_string($correo)) {
            return $this->redirect('/login');
        }

        return $this->view('auth/registro', ['titulo' => 'Registro', 'correo' => $correo]);
    }

    public function registrar(Request $request): Response
    {
        $correo = $this->session()->get(self::REGISTRO_CORREO);
        if (!is_string($correo) || !Usuario::correoPermitido($correo, $this->dominios())) {
            $this->session()->forget(self::REGISTRO_CORREO);

            return $this->redirect('/login');
        }

        $nombre = Usuario::normalizarNombre($request->inputString('nombre'));
        $apellidos = Usuario::normalizarNombre($request->inputString('apellidos'));
        $errores = array_filter([
            'nombre' => Usuario::validarNombre($nombre, 80),
            'apellidos' => Usuario::validarNombre($apellidos, 120),
        ]);
        if ($errores !== []) {
            return $this->volverConError('/registro', $errores, $request);
        }

        $usuarios = new Usuario($this->db());
        try {
            $id = $usuarios->crear(Usuario::AUDITOR, $nombre, $apellidos, $correo);
        } catch (PDOException $e) {
            // Correo duplicado: alguien lo registró entre el paso 1 y este envío.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $this->session()->forget(self::REGISTRO_CORREO);
            $this->session()->flash('aviso', 'Ese correo ya está registrado. Ingresa con él.');

            return $this->redirect('/login');
        }

        $auth = $this->app->auth();
        $auth->registrarEvento(Acceso::REGISTRO, $correo, $id, $request);
        $this->session()->forget(self::REGISTRO_CORREO);

        $usuario = $usuarios->buscarPorId($id);
        if ($usuario === null) {
            return $this->redirect('/login');
        }
        $auth->iniciarSesion($usuario, $request);
        $this->session()->flash('exito', 'Tu cuenta quedó registrada. Aquí verás los talleres que te asignen.');

        return $this->redirect($auth->inicio());
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->cerrarSesion($request);
        $this->session()->flash('exito', 'Cerraste sesión.');

        return $this->redirect('/login');
    }

    /**
     * @param array<string, string> $errores
     */
    private function volverConError(string $ruta, array $errores, ?Request $request = null): Response
    {
        if ($request !== null) {
            $this->session()->flashInput($request->allInput());
        }
        $this->session()->flashErrors($errores);

        return $this->redirect($ruta);
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
