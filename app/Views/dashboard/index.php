<?php
/**
 * Fase 1: verificación de la base técnica. Se reemplaza por el panel central en la fase 3.
 *
 * @var array{ok: bool, detalle: string} $bd
 * @var string $php
 */
?>
<h1>Panel central</h1>
<p class="texto-secundario">La base del proyecto está lista. El panel con filtros se construye en la fase 3.</p>

<section class="tarjeta">
    <h2>Estado del sistema</h2>
    <table class="tabla">
        <tbody>
            <tr>
                <th scope="row">PHP</th>
                <td><?= e($php) ?></td>
            </tr>
            <tr>
                <th scope="row">Base de datos</th>
                <td>
                    <?php if ($bd['ok']) : ?>
                        <span class="insignia insignia--realizado">✓ Conectada</span> <?= e($bd['detalle']) ?>
                    <?php else : ?>
                        <span class="insignia insignia--no-realizado">✕ Sin conexión</span> <?= e($bd['detalle']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</section>
