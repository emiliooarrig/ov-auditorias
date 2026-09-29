<?php

/**
 * Paginación que conserva los filtros (CU-03).
 *
 * @var string               $ruta
 * @var array<string, mixed> $query  Filtros actuales (sin la página).
 * @var int                  $pagina
 * @var int                  $paginas
 */

if ($paginas <= 1) {
    return;
}

// Primera, última y dos a cada lado de la actual; los huecos se marcan con "…".
$visibles = array_unique(array_filter(
    [1, $paginas, ...range(max(1, $pagina - 2), min($paginas, $pagina + 2))],
    static fn (int $p): bool => $p >= 1 && $p <= $paginas
));
sort($visibles);
$enlace = static fn (int $p): string => url($ruta, $query + ['pagina' => $p > 1 ? $p : null]);
?>
<nav aria-label="Paginación">
    <ul class="pagination">
        <?php if ($pagina > 1) : ?>
            <li><a href="<?= e($enlace($pagina - 1)) ?>" rel="prev"><?= icono('izquierda') ?> Anterior</a></li>
        <?php endif; ?>
        <?php $anterior = 0; ?>
        <?php foreach ($visibles as $p) : ?>
            <?php if ($p - $anterior > 1) : ?>
                <li><span class="pagination__gap" aria-hidden="true">…</span></li>
            <?php endif; ?>
            <li>
                <?php if ($p === $pagina) : ?>
                    <span aria-current="page"><?= e($p) ?></span>
                <?php else : ?>
                    <a href="<?= e($enlace($p)) ?>" aria-label="Página <?= e($p) ?>"><?= e($p) ?></a>
                <?php endif; ?>
            </li>
            <?php $anterior = $p; ?>
        <?php endforeach; ?>
        <?php if ($pagina < $paginas) : ?>
            <li><a href="<?= e($enlace($pagina + 1)) ?>" rel="next">Siguiente <?= icono('derecha') ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
