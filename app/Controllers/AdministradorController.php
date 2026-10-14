<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Usuario;
use DomainException;

/**
 * Administradores, los únicos usuarios del sistema: alta, activar o desactivar y cambio de contraseña (RF-10).
 */
final class AdministradorController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('administradores/index', [
            'titulo' => 'Administradores',
            'administradores' => (new Usuario($this->db()))->administradores(),
            'actorId' => $this->adminId(),
            'passwordMin' => Password::MIN_LENGTH,
            'dominios' => $this->dominios(),
        ]);
    }

    /**
     * Alta de un administrador con correo institucional y contraseña. Si el correo era de una cuenta
     * de auditor (anterior al 2026-10-10), esa cuenta se convierte en administrador.
     */
    public function guardar(Request $request): Response
    {
        $nombre = Usuario::normalizarNombre($request->inputString('nombre'));
        $apellidos = Usuario::normalizarNombre($request->inputString('apellidos'));
        $correo = Usuario::normalizarCorreo($request->inputString('correo'));
        $usuarios = new Usuario($this->db());
        $existente = $usuarios->buscarPorCorreo($correo);

        $errores = array_filter([
            'nombre' => Usuario::validarNombre($nombre, 80),
            'apellidos' => Usuario::validarNombre($apellidos, 120),
            'correo' => match (true) {
                !Usuario::correoPermitido($correo, $this->dominios()) => 'Usa un correo institucional (@'
                    . implode(' o @', $this->dominios()) . ').',
                $existente !== null && $existente['rol'] === Usuario::ADMINISTRADOR => 'Ya hay un administrador '
                    . 'con ese correo.',
                default => null,
            },
            'password' => $this->errorPassword($request),
        ]);
        if ($errores !== []) {
            $this->session()->flashInput(compact('nombre', 'apellidos', 'correo'));
            $this->session()->flashErrors($errores);

            return $this->redirect('/administradores');
        }

        $hash = Password::hash($this->campoClave($request, 'password'));
        if ($existente !== null) {
            $usuarios->convertirEnAdministrador($existente['id'], $hash, $nombre, $apellidos);
            $id = $existente['id'];
        } else {
            $id = $usuarios->crear($nombre, $apellidos, $correo, $hash);
        }

        $this->registrarAccion('alta', $id);
        $this->session()->flash(
            'exito',
            $nombre . ' ya puede ingresar con ' . $correo . ' y la contraseña que le asignaste.',
            true
        );

        return $this->redirect('/administradores');
    }

    public function cambiarEstado(Request $request): Response
    {
        $id = $request->intParam('id');
        $activo = $request->inputString('activo') === '1';

        try {
            (new Usuario($this->db()))->cambiarActivo($id, $activo, $this->adminId());
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);

            return $this->redirect('/administradores');
        }

        $this->registrarAccion($activo ? 'activación' : 'desactivación', $id);
        $this->session()->flash('exito', $activo
            ? 'Cuenta activada. Puede volver a ingresar.'
            : 'Cuenta desactivada. Ya no puede ingresar; su historial se conserva.', true);

        return $this->redirect('/administradores');
    }

    /**
     * Cambia la contraseña de cualquier administrador. Para la propia se pide además la actual.
     */
    public function cambiarPassword(Request $request): Response
    {
        $id = $request->intParam('id');
        $usuarios = new Usuario($this->db());
        $usuario = $usuarios->buscarPorId($id);
        if ($usuario === null || $usuario['rol'] !== Usuario::ADMINISTRADOR) {
            $this->notFound();
        }

        $error = $id === $this->adminId()
            && !Password::verify($this->campoClave($request, 'password_actual'), $usuario['password_hash'])
            ? 'Tu contraseña actual no es correcta.'
            : $this->errorPassword($request);
        if ($error !== null) {
            $this->session()->flash('error', $error, true);

            return $this->redirect('/administradores');
        }

        $usuarios->actualizarPassword($id, Password::hash($this->campoClave($request, 'password')));
        $this->registrarAccion('cambio de contraseña', $id);
        $this->session()->flash('exito', $id === $this->adminId()
            ? 'Tu contraseña cambió. Úsala la próxima vez que ingreses.'
            : 'Contraseña cambiada. Compártela con ' . $usuario['nombre'] . ' por un medio seguro.', true);

        return $this->redirect('/administradores');
    }

    private function errorPassword(Request $request): ?string
    {
        return Password::validate(
            $this->campoClave($request, 'password'),
            $this->campoClave($request, 'password_confirmacion')
        );
    }

    /**
     * Contraseña tal como se escribió: sin recortar espacios, que también cuentan.
     */
    private function campoClave(Request $request, string $campo): string
    {
        $valor = $request->input($campo);

        return is_string($valor) ? $valor : '';
    }

    /**
     * @return list<string>
     */
    private function dominios(): array
    {
        /** @var list<string> */
        return (array) $this->app->config('auth.allowed_domains', []);
    }

    private function registrarAccion(string $accion, int $usuarioId): void
    {
        $this->app->logger()->info('Gestión de administradores: ' . $accion, [
            'usuario_id' => $usuarioId,
            'por' => $this->adminId(),
        ]);
    }
}
