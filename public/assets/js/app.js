/*
 * Mejoras progresivas. Sin este archivo los talleres se filtran enviando el formulario;
 * aquí se agregan el filtrado en tiempo real y el filtro de usuarios en el navegador.
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

  formulario.classList.add('filtros--vivo');

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
    campos.forEach((c) => c.classList.toggle('filtro-activo', c.value !== ''));
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
        const resumen = resultados.querySelector('[data-resumen]') ?? resultados.querySelector('.vacio strong');
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
    } else if (enlace.closest('.paginacion')) {
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
  const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
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
    Object.values(campos).forEach((c) => c.classList.toggle('filtro-activo', c.value !== ''));
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
  const etiqueta = boton.querySelector('.solo-lector');
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

document.addEventListener('DOMContentLoaded', () => {
  iniciarConteoSeleccion(document);
  document.querySelectorAll('[data-ver-clave]').forEach(iniciarVerClave);
  document.querySelectorAll('form[data-filtro-vivo]').forEach(iniciarFiltroVivo);
  document.querySelectorAll('form[data-filtro-local]').forEach(iniciarFiltroLocal);
});
