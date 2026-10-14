/*
 * Mejoras progresivas. Sin este archivo todo funciona enviando formularios y siguiendo enlaces;
 * aquí se agregan el filtrado en tiempo real de talleres y alumnos, el panel de alumnos sin
 * recargar la página y las alertas de SweetAlert2.
 */

// Sin acentos ni mayúsculas: "gonzalez" encuentra "González" (igual que texto_busqueda() en PHP).
const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 * Talleres: filtra en tiempo real. Pide al servidor la misma página con los filtros nuevos
 * y reemplaza solo el bloque [data-resultados], sin recargar ni perder el foco.
 */
const iniciarFiltroVivo = (formulario) => {
  const resultados = document.querySelector('[data-resultados]');
  if (!resultados) {
    return;
  }
  const anuncio = formulario.querySelector('[data-anuncio]');
  const limpiar = formulario.querySelector('[data-limpiar]');
  const campos = [...formulario.querySelectorAll('input[type="search"], select')];
  let espera = 0;
  let peticion = null;

  formulario.classList.add('filters--live');

  const urlDeFiltros = () => {
    const url = new URL(formulario.action, window.location.href);
    new FormData(formulario).forEach((valor, clave) => {
      if (valor !== '') {
        url.searchParams.set(clave, valor);
      }
    });
    return url;
  };

  const marcarActivos = () => {
    campos.forEach((c) => c.classList.toggle('is-active', c.value !== ''));
    if (limpiar) {
      limpiar.hidden = campos.every((c) => c.value === '');
    }
  };

  const cargar = async (url) => {
    peticion?.abort();
    peticion = new AbortController();
    resultados.setAttribute('aria-busy', 'true');
    try {
      const respuesta = await fetch(url, { signal: peticion.signal, credentials: 'same-origin' });
      const nuevo = respuesta.ok && !respuesta.redirected
        ? new DOMParser().parseFromString(await respuesta.text(), 'text/html').querySelector('[data-resultados]')
        : null;
      if (!nuevo) {
        // Sesión vencida u otra respuesta inesperada: se deja que el navegador la muestre.
        window.location.assign(url);
        return;
      }
      resultados.replaceChildren(...nuevo.childNodes);
      window.history.replaceState(null, '', url);
      if (anuncio) {
        const resumen = resultados.querySelector('[data-resumen]') ?? resultados.querySelector('.empty strong');
        anuncio.textContent = resumen ? resumen.textContent.replace(/\s+/g, ' ').trim() : '';
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        window.location.assign(url);
      }
    } finally {
      resultados.removeAttribute('aria-busy');
    }
  };

  const filtrar = () => {
    window.clearTimeout(espera);
    marcarActivos();
    cargar(urlDeFiltros());
  };

  const quitarFiltros = () => {
    campos.forEach((c) => { c.value = ''; });
    filtrar();
  };

  // Escribir espera una pausa corta; elegir en una lista filtra de inmediato.
  formulario.addEventListener('input', (evento) => {
    window.clearTimeout(espera);
    if (evento.target.type === 'search') {
      marcarActivos();
      espera = window.setTimeout(filtrar, 250);
    } else {
      filtrar();
    }
  });
  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    filtrar();
  });
  limpiar?.addEventListener('click', (evento) => {
    evento.preventDefault();
    quitarFiltros();
  });

  // Paginación y "Quitar filtros" dentro de los resultados también se cargan sin recargar.
  resultados.addEventListener('click', (evento) => {
    const enlace = evento.target.closest('a');
    if (!enlace || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey) {
      return;
    }
    if (enlace.hasAttribute('data-limpiar')) {
      evento.preventDefault();
      quitarFiltros();
    } else if (enlace.closest('.pagination')) {
      evento.preventDefault();
      cargar(new URL(enlace.href));
      resultados.scrollIntoView({ block: 'start' });
    }
  });
};

/*
 * Alumnos: el servidor entrega la lista completa (y oculta lo que no coincide con los filtros de la URL
 * o cae en otra página); aquí se filtra y pagina en vivo. Cada fila lleva data-buscar con su texto y un
 * data-* por cada lista del formulario (data-estado, data-talleres, data-horario); la lista lleva
 * data-por-pagina y data-pagina. Los filtros y la página viajan en la URL, en los enlaces marcados con
 * data-conserva-filtros y en los campos ocultos data-regreso de los formularios del panel.
 */
let sincronizarFiltros = null;

const icono = (nombre) => `<svg class="icon" aria-hidden="true" focusable="false"><use href="#i-${nombre}"></use></svg>`;

// Mismos enlaces que partials/paginacion.php: primera, última y dos a cada lado de la actual.
const htmlPaginacion = (pagina, paginas, enlace) => {
  if (paginas <= 1) {
    return '';
  }
  const visibles = [...new Set([1, paginas, pagina - 2, pagina - 1, pagina, pagina + 1, pagina + 2])]
    .filter((p) => p >= 1 && p <= paginas)
    .sort((a, b) => a - b);
  const elementos = [];
  if (pagina > 1) {
    elementos.push(`<li><a href="${enlace(pagina - 1)}" rel="prev">${icono('izquierda')} Anterior</a></li>`);
  }
  let anterior = 0;
  visibles.forEach((p) => {
    if (p - anterior > 1) {
      elementos.push('<li><span class="pagination__gap" aria-hidden="true">…</span></li>');
    }
    elementos.push(p === pagina
      ? `<li><span aria-current="page">${p}</span></li>`
      : `<li><a href="${enlace(p)}" aria-label="Página ${p}">${p}</a></li>`);
    anterior = p;
  });
  if (pagina < paginas) {
    elementos.push(`<li><a href="${enlace(pagina + 1)}" rel="next">Siguiente ${icono('derecha')}</a></li>`);
  }
  return `<nav aria-label="Paginación"><ul class="pagination">${elementos.join('')}</ul></nav>`;
};

const iniciarFiltroLocal = (formulario) => {
  const lista = document.querySelector(formulario.dataset.filtroLocal);
  if (!lista) {
    return;
  }
  const filas = [...lista.querySelectorAll('[data-buscar]')];
  const campos = [...formulario.querySelectorAll('input[type="search"], select')];
  const texto = campos.find((c) => c.type === 'search');
  const listas = campos.filter((c) => c.tagName === 'SELECT');
  const conteo = document.querySelector('[data-conteo-filtro]');
  const sinCoincidencias = document.querySelector('[data-sin-coincidencias]');
  const paginacion = document.querySelector('[data-paginacion]');
  const limpiar = formulario.querySelector('[data-limpiar]');
  const indice = new Map(filas.map((fila) => [fila, normalizar(fila.dataset.buscar ?? '')]));
  const unidad = (n) => (n === 1 ? conteo?.dataset.singular : conteo?.dataset.plural) ?? '';
  const porPagina = Number(lista.dataset.porPagina) || filas.length || 1;
  let pagina = Number(lista.dataset.pagina) || 1;

  // La página 1 no se escribe en la URL, igual que en el servidor.
  const valores = () => [...campos.map((c) => [c.name, c.value]), ['pagina', pagina > 1 ? String(pagina) : '']];

  const conFiltros = (direccion, valoresUrl = valores()) => {
    const url = new URL(direccion, window.location.href);
    valoresUrl.forEach(([nombre, valor]) => {
      if (valor === '') {
        url.searchParams.delete(nombre);
      } else {
        url.searchParams.set(nombre, valor);
      }
    });
    return url.href;
  };

  // Enlaces y formularios (también los de un panel recién cargado) vuelven a la lista como está.
  const sincronizar = () => {
    const actuales = new Map(valores());
    document.querySelectorAll('[data-regreso]').forEach((oculto) => {
      oculto.value = actuales.get(oculto.dataset.regreso) ?? '';
    });
    document.querySelectorAll('a[data-conserva-filtros]').forEach((enlace) => {
      enlace.href = conFiltros(enlace.href);
    });
    window.history.replaceState(window.history.state, '', conFiltros(window.location.href));
  };

  const aplicar = () => {
    const buscado = normalizar(texto?.value ?? '');
    const coincidentes = filas.filter((fila) => (buscado === '' || indice.get(fila).includes(buscado))
      && listas.every((c) => c.value === '' || fila.dataset[c.name] === c.value));
    const visibles = coincidentes.length;
    const paginas = Math.max(1, Math.ceil(visibles / porPagina));
    pagina = Math.min(Math.max(1, pagina), paginas);
    const enPagina = new Set(coincidentes.slice((pagina - 1) * porPagina, pagina * porPagina));
    filas.forEach((fila) => { fila.hidden = !enPagina.has(fila); });

    const hayFiltros = campos.some((c) => c.value !== '');
    campos.forEach((c) => c.classList.toggle('is-active', c.value !== ''));
    if (conteo) {
      const total = `${filas.length} ${unidad(filas.length)}`;
      conteo.textContent = hayFiltros ? `${visibles} de ${total}` : total;
    }
    lista.hidden = visibles === 0;
    if (sinCoincidencias) {
      sinCoincidencias.hidden = visibles !== 0;
    }
    if (limpiar) {
      limpiar.hidden = !hayFiltros;
    }
    if (paginacion) {
      const ruta = window.location.href;
      const enlace = (p) => conFiltros(ruta, [...valores().slice(0, -1), ['pagina', p > 1 ? String(p) : '']]);
      paginacion.innerHTML = htmlPaginacion(pagina, paginas, enlace);
    }
    sincronizar();
  };

  // Cambiar un filtro vuelve a la primera página.
  const filtrar = () => {
    pagina = 1;
    aplicar();
  };

  const quitarFiltros = () => {
    campos.forEach((c) => { c.value = ''; });
    filtrar();
    texto?.focus();
  };

  formulario.classList.add('filters--live');
  formulario.addEventListener('input', filtrar);
  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    filtrar();
  });
  [limpiar, sinCoincidencias?.querySelector('[data-limpiar]')].forEach((boton) => {
    boton?.addEventListener('click', (evento) => {
      evento.preventDefault();
      quitarFiltros();
    });
  });
  paginacion?.addEventListener('click', (evento) => {
    const enlace = evento.target.closest('.pagination a');
    if (!enlace || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
      return;
    }
    evento.preventDefault();
    pagina = Number(new URL(enlace.href).searchParams.get('pagina')) || 1;
    aplicar();
    // Los enlaces de la paginación se reemplazaron: el foco pasa al primer alumno de la página.
    lista.querySelector('li:not([hidden]) a')?.focus({ preventScroll: true });
    lista.closest('section')?.scrollIntoView({ block: 'start', behavior: sinMovimiento ? 'auto' : 'smooth' });
  });
  sincronizarFiltros = sincronizar;
};

/*
 * Alumnos: al elegir uno de la lista (o "Agregar alumnos") el panel se carga sin recargar la página,
 * así no se pierde el lugar en la lista. Atrás y Adelante del navegador también cambian el panel.
 * Ante cualquier respuesta inesperada se navega normalmente.
 */
const iniciarPanel = (contenedor) => {
  const panel = contenedor.querySelector('[data-panel]');
  if (!panel) {
    return;
  }
  const angosta = window.matchMedia('(max-width: 1023px)');
  let peticion = null;

  const marcarActual = (url) => {
    contenedor.querySelectorAll('.roster__item').forEach((enlace) => {
      if (new URL(enlace.href).pathname === url.pathname) {
        enlace.setAttribute('aria-current', 'true');
      } else {
        enlace.removeAttribute('aria-current');
      }
    });
  };

  const cargar = async (url, { historial = true } = {}) => {
    peticion?.abort();
    peticion = new AbortController();
    panel.setAttribute('aria-busy', 'true');
    try {
      const respuesta = await fetch(url, { signal: peticion.signal, credentials: 'same-origin' });
      const documento = respuesta.ok && !respuesta.redirected
        ? new DOMParser().parseFromString(await respuesta.text(), 'text/html')
        : null;
      const nuevo = documento?.querySelector('[data-panel]');
      if (!nuevo) {
        window.location.assign(url);
        return;
      }
      panel.replaceChildren(...nuevo.childNodes);
      contenedor.classList.toggle(
        'layout-roster--selected',
        documento.querySelector('[data-roster]')?.classList.contains('layout-roster--selected') ?? false,
      );
      document.title = documento.title;
      if (historial) {
        window.history.pushState({ panel: true }, '', url);
      }
      marcarActual(url);
      sincronizarFiltros?.();
      panel.querySelector('[data-panel-titulo]')?.focus({ preventScroll: true });
      // En pantallas angostas el panel queda arriba de la lista: se lleva a la vista.
      if (angosta.matches || url.hash !== '') {
        panel.scrollIntoView({ block: 'start', behavior: sinMovimiento ? 'auto' : 'smooth' });
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        window.location.assign(url);
      }
    } finally {
      panel.removeAttribute('aria-busy');
    }
  };

  document.addEventListener('click', (evento) => {
    const enlace = evento.target.closest('a[data-panel-enlace]');
    if (!enlace || evento.defaultPrevented || evento.button !== 0
      || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
      return;
    }
    evento.preventDefault();
    cargar(new URL(enlace.href));
  });
  window.addEventListener('popstate', () => cargar(new URL(window.location.href), { historial: false }));
};

/*
 * Contraseña: botón para mostrarla u ocultarla mientras se escribe.
 */
const iniciarVerClave = (boton) => {
  const campo = document.getElementById(boton.dataset.verClave);
  const etiqueta = boton.querySelector('.sr-only');
  if (!campo) {
    return;
  }
  boton.hidden = false;
  boton.addEventListener('click', () => {
    const visible = campo.type === 'password';
    campo.type = visible ? 'text' : 'password';
    boton.setAttribute('aria-pressed', String(visible));
    if (etiqueta) {
      etiqueta.textContent = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
    }
    campo.focus();
  });
  // Se oculta de nuevo al enviar, para que el navegador no la guarde ni la muestre como texto.
  campo.form?.addEventListener('submit', () => {
    campo.type = 'password';
  });
};

/*
 * Alertas con SweetAlert2 (public/assets/vendor/sweetalert2, servido localmente por la CSP).
 * Sin la biblioteca o sin JavaScript todo sigue funcionando: los formularios se envían directo
 * (el del taller conserva su casilla de confirmación) y los mensajes se ven como avisos normales.
 * El texto siempre entra como `text`, nunca como `html`, para no interpretar datos del usuario.
 */
const Swal = window.Sweetalert2 ?? window.Swal ?? null;
const token = (nombre) => getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
const animacion = sinMovimiento ? { showClass: { popup: '' }, hideClass: { popup: '' } } : {};

// fire() reemplaza customClass completo en lugar de mezclarlo; por eso cada llamada parte de esta base.
const clasesDialogo = {
  popup: 'dialog',
  title: 'dialog__title',
  htmlContainer: 'dialog__text',
  actions: 'dialog__actions',
  confirmButton: 'btn btn--primary',
  cancelButton: 'btn btn--secondary',
};

const dialogo = Swal?.mixin({
  ...animacion,
  buttonsStyling: false,
  reverseButtons: true,
  customClass: clasesDialogo,
});

const aviso = Swal?.mixin({
  ...animacion,
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: !sinMovimiento,
  customClass: { popup: 'dialog dialog--toast', title: 'dialog__title' },
  // Pausa mientras el cursor está encima, para dar tiempo de leerlo.
  didOpen: (ventana) => {
    ventana.addEventListener('mouseenter', Swal.stopTimer);
    ventana.addEventListener('mouseleave', Swal.resumeTimer);
  },
});

// Confirmación antes de enviar un formulario con data-confirmar (desactivar, dar de baja, asignar al azar…).
const iniciarConfirmaciones = () => {
  // La casilla de confirmación sin JavaScript se sustituye por el diálogo. Se le quita `required`:
  // oculta y obligatoria impediría enviar el formulario sin mostrar por qué. El servidor la sigue exigiendo.
  document.querySelectorAll('[data-confirmacion-manual]').forEach((etiqueta) => {
    etiqueta.hidden = true;
    etiqueta.querySelectorAll('input').forEach((casilla) => { casilla.required = false; });
  });

  document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;
    if (!(formulario instanceof HTMLFormElement) || !formulario.dataset.confirmar) {
      return;
    }
    if (formulario.dataset.confirmado === '1') {
      delete formulario.dataset.confirmado;
      return;
    }
    evento.preventDefault();
    const boton = evento.submitter;
    const peligro = formulario.dataset.confirmarTipo === 'peligro';
    // Un aviso breve abierto (p. ej. "Cuenta activada.") impide abrir el diálogo: se cierra antes.
    if (Swal.isVisible()) {
      Swal.close();
      await new Promise((listo) => { setTimeout(listo, 0); });
    }

    const { isConfirmed } = await dialogo.fire({
      icon: peligro ? 'warning' : 'question',
      iconColor: token(peligro ? '--estado-no-realizado' : '--anahuac-cafe'),
      title: formulario.dataset.confirmar,
      text: formulario.dataset.confirmarTexto ?? '',
      showCancelButton: true,
      confirmButtonText: formulario.dataset.confirmarBoton ?? 'Confirmar',
      cancelButtonText: 'Cancelar',
      // En acciones de peligro el foco inicial queda en "Cancelar".
      focusCancel: peligro,
      customClass: { ...clasesDialogo, confirmButton: peligro ? 'btn btn--danger' : 'btn btn--primary' },
    });
    if (!isConfirmed) {
      return;
    }
    formulario.querySelectorAll('[data-confirmacion-manual] input[type="checkbox"]').forEach((c) => {
      c.checked = true;
    });
    formulario.dataset.confirmado = '1';
    formulario.requestSubmit(boton ?? undefined);
  });
};

// Mensajes marcados con data-alerta: los de éxito como aviso breve, los de error como ventana.
const mostrarAlertas = async () => {
  const tipos = {
    exito: ['success', '--estado-realizado'],
    error: ['error', '--estado-no-realizado'],
    aviso: ['warning', '--anahuac-cafe'],
  };
  for (const mensaje of document.querySelectorAll('[data-alerta]')) {
    const tipo = Object.keys(tipos).find((t) => mensaje.classList.contains(`alert--${t}`)) ?? 'aviso';
    const [icono, color] = tipos[tipo];
    const texto = mensaje.textContent.replace(/\s+/g, ' ').trim();
    mensaje.remove();
    // eslint-disable-next-line no-await-in-loop -- uno tras otro, nunca encimados
    await (tipo === 'error'
      ? dialogo.fire({ icon: icono, iconColor: token(color), title: texto, confirmButtonText: 'Entendido' })
      : aviso.fire({ icon: icono, iconColor: token(color), title: texto }));
  }
};

/*
 * Menú de hamburguesa en celulares (≤ 767 px). Sin JavaScript el menú queda desplegado.
 * Se cierra con Escape (el foco vuelve al botón), con un clic fuera o al pasar a pantalla ancha.
 */
const iniciarMenu = (cabecera) => {
  const boton = cabecera.querySelector('.menu-toggle');
  const panel = boton ? document.getElementById(boton.getAttribute('aria-controls')) : null;
  if (!boton || !panel) {
    return;
  }
  const etiqueta = boton.querySelector('span');
  const celular = window.matchMedia('(max-width: 767px)');

  const abrir = (abierto) => {
    boton.setAttribute('aria-expanded', String(abierto));
    cabecera.classList.toggle('is-open', abierto);
    if (etiqueta) {
      etiqueta.textContent = abierto ? 'Cerrar' : 'Menú';
    }
  };

  cabecera.classList.add('is-collapsible');
  abrir(false);
  boton.addEventListener('click', () => abrir(boton.getAttribute('aria-expanded') !== 'true'));
  document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && cabecera.classList.contains('is-open')) {
      abrir(false);
      boton.focus();
    }
  });
  document.addEventListener('click', (evento) => {
    if (cabecera.classList.contains('is-open') && !cabecera.contains(evento.target)) {
      abrir(false);
    }
  });
  celular.addEventListener('change', () => abrir(false));
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-menu]').forEach(iniciarMenu);
  if (Swal) {
    iniciarConfirmaciones();
    mostrarAlertas();
  }
  document.querySelectorAll('[data-ver-clave]').forEach(iniciarVerClave);
  document.querySelectorAll('form[data-filtro-vivo]').forEach(iniciarFiltroVivo);
  document.querySelectorAll('form[data-filtro-local]').forEach(iniciarFiltroLocal);
  document.querySelectorAll('[data-roster]').forEach(iniciarPanel);
});
