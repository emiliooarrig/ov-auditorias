<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Usuario;
use DomainException;

/**
 * Gestión de usuarios: ver registrados, activar o desactivar, cambiar rol (RF-10). Solo administrador.
 */
final class UsuarioController extends Controller
{
    /**
     * Lista completa de usuarios. Los filtros se aplican en el navegador (app.js); los valores que
     * llegan por GET solo sirven para restaurarlos al volver de una acción.
     */
    public function index(Request $request): Response
    {
        return $this->view('usuarios/index', [
            'titulo' => 'Usuarios',
            'usuarios' => (new Usuario($this->db()))->todos(),
            'query' => [
                'texto' => mb_substr($request->queryString('texto'), 0, 150),
                'rol' => in_array($request->queryString('rol'), [Usuario::ADMINISTRADOR, Usuario::AUDITOR], true)
                    ? $request->queryString('rol')
                    : '',
                'estado' => in_array($request->queryString('estado'), ['activos', 'desactivados'], true)
                    ? $request->queryString('estado')
                    : '',
            ],
            'actorId' => (int) $this->app->auth()->id(),
            'passwordMin' => Password::MIN_LENGTH,
        ]);
    }

    /**
     * Promover exige asignar contraseña (el administrador siempre ingresa con ella);
     * degradar a auditor la borra.
     */
    public function cambiarRol(Request $request): Response
    {
        $id = $request->intParam('id');
        $rol = $request->inputString('rol');
        $hash = null;

        try {
            if ($rol === Usuario::ADMINISTRADOR) {
                $password = $request->input('password');
                $confirmacion = $request->input('password_confirmacion');
                $error = Password::validate(
                    is_string($password) ? $password : '',
                    is_string($confirmacion) ? $confirmacion : ''
                );
                if ($error !== null) {
                    throw new DomainException($error);
                }
                $hash = Password::hash((string) $password);
            }
            (new Usuario($this->db()))->cambiarRol($id, $rol, $hash, (int) $this->app->auth()->id());
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);

            return $this->regresar($request);
        }

        $this->registrarAccion('cambio de rol', $id, ['rol' => $rol]);
        $this->session()->flash('exito', $rol === Usuario::ADMINISTRADOR
            ? 'El usuario ahora es administrador. Ingresará con su correo y la contraseña asignada.'
            : 'El usuario ahora es auditor. Ingresará solo con su correo.', true);

        return $this->regresar($request);
    }

    public function cambiarEstado(Request $request): Response
    {
        $id = $request->intParam('id');
        $activo = $request->inputString('activo') === '1';

        try {
            (new Usuario($this->db()))->cambiarActivo($id, $activo, (int) $this->app->auth()->id());
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);

            return $this->regresar($request);
        }

        $this->registrarAccion($activo ? 'activación' : 'desactivación', $id);
        $this->session()->flash('exito', $activo
            ? 'Cuenta activada.'
            : 'Cuenta desactivada. Su historial y asignaciones se conservan.', true);

        return $this->regresar($request);
    }

    /**
     * Vuelve a la lista con los mismos filtros.
     */
    private function regresar(Request $request): Response
    {
        return $this->redirect('/usuarios', [
            'texto' => mb_substr($request->inputString('texto'), 0, 150),
            'rol' => $request->inputString('filtro_rol'),
            'estado' => $request->inputString('estado'),
        ]);
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function registrarAccion(string $accion, int $usuarioId, array $contexto = []): void
    {
        $this->app->logger()->info('Gestión de usuarios: ' . $accion, $contexto + [
            'usuario_id' => $usuarioId,
            'por' => $this->app->auth()->id(),
        ]);
    }
}
