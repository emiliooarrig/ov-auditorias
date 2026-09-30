/*
 * Mejoras progresivas. Sin este archivo los talleres se filtran enviando el formulario;
 * aquí se agregan el filtrado en tiempo real, el filtro de usuarios en el navegador
 * y las alertas de SweetAlert2.
 */

// Asignaciones: cuenta los talleres marcados en la barra de acción.
const iniciarConteoSeleccion = (raiz) => {
  raiz.querySelectorAll('[data-conteo-seleccion]').forEach((formulario) => {
    const salida = formulario.querySelector('[data-conteo]');
    const casillas = formulario.querySelectorAll('input[type="checkbox"][name="actividades[]"]');
    if (!salida || casillas.length === 0) {
      return;
    }
    const actualizar = () => {
      const n = [...casillas].filter((c) => c.checked).length;
      salida.textContent = n === 0
        ? 'Ningún taller marcado'
        : n === 1 ? '1 taller marcado' : `${n} talleres marcados`;
    };
    casillas.forEach((c) => c.addEventListener('change', actualizar));
    actualizar();
  });
};

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
      iniciarConteoSeleccion(resultados);
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
 * Usuarios: el servidor entrega la lista completa y aquí se filtra por texto, rol y estado.
 */
const iniciarFiltroLocal = (formulario) => {
  const tabla = document.querySelector(formulario.dataset.filtroLocal);
  if (!tabla) {
    return;
  }
  const filas = [...tabla.querySelectorAll('tbody > tr')];
  const conteo = document.querySelector('[data-conteo-usuarios]');
  const sinCoincidencias = document.querySelector('[data-sin-coincidencias]');
  const limpiar = formulario.querySelector('[data-limpiar]');
  const campos = {
    texto: formulario.elements.texto,
    rol: formulario.elements.rol,
    estado: formulario.elements.estado,
  };
  // Sin acentos ni mayúsculas: "gonzalez" encuentra "González".
  const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  const indice = new Map(filas.map((fila) => [fila, normalizar(fila.dataset.buscar ?? '')]));

  const aplicar = () => {
    const texto = normalizar(campos.texto.value);
    const { value: rol } = campos.rol;
    const { value: estado } = campos.estado;
    let visibles = 0;

    filas.forEach((fila) => {
      const coincide = (texto === '' || indice.get(fila).includes(texto))
        && (rol === '' || fila.dataset.rol === rol)
        && (estado === '' || fila.dataset.estado === estado);
      fila.hidden = !coincide;
      visibles += coincide ? 1 : 0;
    });

    const hayFiltros = Object.values(campos).some((c) => c.value !== '');
    Object.values(campos).forEach((c) => c.classList.toggle('is-active', c.value !== ''));
    if (conteo) {
      const unidad = visibles === 1 ? 'usuario' : 'usuarios';
      conteo.textContent = hayFiltros
        ? `${visibles} de ${filas.length} ${unidad}`
        : `${filas.length} ${filas.length === 1 ? 'usuario' : 'usuarios'}`;
    }
    tabla.hidden = visibles === 0;
    if (sinCoincidencias) {
      sinCoincidencias.hidden = visibles !== 0;
    }
    if (limpiar) {
      limpiar.hidden = !hayFiltros;
    }

    // Los formularios de cada fila reenvían los filtros para volver a la misma vista.
    document.querySelectorAll('[data-regreso]').forEach((oculto) => {
      oculto.value = campos[oculto.dataset.regreso]?.value ?? '';
    });
    const url = new URL(window.location.href);
    Object.entries(campos).forEach(([clave, campo]) => {
      if (campo.value === '') {
        url.searchParams.delete(clave);
      } else {
        url.searchParams.set(clave, campo.value);
      }
    });
    window.history.replaceState(null, '', url);
  };

  const quitarFiltros = () => {
    Object.values(campos).forEach((c) => { c.value = ''; });
    aplicar();
    campos.texto.focus();
  };

  formulario.hidden = false;
  formulario.addEventListener('input', aplicar);
  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    aplicar();
  });
  document.querySelectorAll('[data-limpiar]').forEach((boton) => boton.addEventListener('click', quitarFiltros));
  aplicar();
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
const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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

// Confirmación antes de enviar un formulario con data-confirmar (desactivar, cambiar rol, cerrar sesión).
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
  iniciarConteoSeleccion(document);
  document.querySelectorAll('[data-ver-clave]').forEach(iniciarVerClave);
  document.querySelectorAll('form[data-filtro-vivo]').forEach(iniciarFiltroVivo);
  document.querySelectorAll('form[data-filtro-local]').forEach(iniciarFiltroLocal);
});
