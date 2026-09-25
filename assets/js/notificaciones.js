/* ═══════════════════════════════════════════════════════════════════════════
   Notificaciones y confirmaciones compartidas (fuente única de verdad)
   ───────────────────────────────────────────────────────────────────────────
   Se carga en todas las pantallas desde views/components/estilos_base.php.
   Estilos: assets/css/notificaciones.css.

   Aviso (toast) — esquina superior derecha, se cierra solo o con la X:
     Notificar.aviso({ tipo: 'ok'|'error'|'aviso'|'info', texto: '...', titulo?: '...',
                       accion?: { texto: 'Ir a Contratación', href: '...' }, duracion?: 6000 })
     duracion 0 = no se cierra solo. Pasar el mouse por encima lo pausa.
   Desde PHP (al cargar la página, p. ej. después de guardar):
     echo avisoAlCargar('ok', 'Contrato renovado correctamente.');   // views/components/notificaciones.php

   Confirmación (antes de una acción que no se puede deshacer):
     Notificar.confirmar({ titulo, mensaje, confirmar: 'Finalizar contrato', cancelar: 'Cancelar', peligro: true })
       .then(function (ok) { if (ok) formulario.submit(); });
   O sin JavaScript propio, en un <form>:
     <form data-confirmar="¿Estás seguro…?" data-confirmar-titulo="…" data-confirmar-boton="…">
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var ICONOS = {
    ok: '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    error: '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    aviso: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    peligro: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/>'
  };
  var DURACION = 6000;

  function crear(etiqueta, clase, texto) {
    var el = document.createElement(etiqueta);
    if (clase) el.className = clase;
    if (texto !== undefined) el.textContent = texto;   // siempre texto, nunca HTML: seguro con nombres de personas
    return el;
  }

  function icono(nombre) {
    var span = crear('span', 'aviso-icono');
    span.setAttribute('aria-hidden', 'true');
    span.innerHTML = '<svg viewBox="0 0 24 24">' + (ICONOS[nombre] || ICONOS.info) + '</svg>';
    return span;
  }

  function contenedor() {
    var c = document.getElementById('avisos');
    if (!c) {
      c = crear('div', 'avisos');
      c.id = 'avisos';
      document.body.appendChild(c);
    }
    return c;
  }

  function aviso(opciones) {
    var op = typeof opciones === 'string' ? { texto: opciones } : (opciones || {});
    var tipo = ICONOS[op.tipo] ? op.tipo : 'ok';
    var duracion = op.duracion === undefined ? DURACION : Number(op.duracion);

    var el = crear('div', 'aviso aviso-' + tipo);
    el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    el.setAttribute('aria-live', tipo === 'error' ? 'assertive' : 'polite');
    el.appendChild(icono(tipo));

    var cuerpo = crear('div', 'aviso-cuerpo');
    if (op.titulo) cuerpo.appendChild(crear('div', 'aviso-titulo', op.titulo));
    cuerpo.appendChild(crear('div', 'aviso-texto', op.texto || ''));
    if (op.accion && op.accion.href && op.accion.texto) {
      var enlace = crear('a', 'btn btn-primary btn-sm aviso-accion', op.accion.texto);
      enlace.href = op.accion.href;
      cuerpo.appendChild(enlace);
    }
    el.appendChild(cuerpo);

    var x = crear('button', 'aviso-cerrar');
    x.type = 'button';
    x.setAttribute('aria-label', 'Cerrar aviso');
    x.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    el.appendChild(x);

    var temporizador = null;
    function cerrar() {
      if (el.dataset.cerrando) return;
      el.dataset.cerrando = '1';
      clearTimeout(temporizador);
      el.classList.add('aviso-saliendo');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 260);
    }
    function programar(ms) {
      clearTimeout(temporizador);
      if (duracion > 0) temporizador = setTimeout(cerrar, ms);
    }
    x.addEventListener('click', cerrar);
    el.addEventListener('mouseenter', function () { clearTimeout(temporizador); });
    el.addEventListener('mouseleave', function () { programar(2500); });

    contenedor().appendChild(el);
    programar(duracion);
    return { elemento: el, cerrar: cerrar };
  }

  function confirmar(opciones) {
    var op = opciones || {};
    return new Promise(function (resolver) {
      var anterior = document.activeElement;
      var fondo = crear('div', 'confirmacion-fondo');
      var caja = crear('div', 'confirmacion' + (op.peligro ? ' confirmacion-peligro' : ''));
      caja.setAttribute('role', 'alertdialog');
      caja.setAttribute('aria-modal', 'true');
      var idTitulo = 'confirmacion-titulo-' + Date.now();
      var idMensaje = idTitulo + '-mensaje';
      caja.setAttribute('aria-labelledby', idTitulo);
      caja.setAttribute('aria-describedby', idMensaje);

      caja.appendChild(icono(op.peligro ? 'peligro' : 'info'));
      var titulo = crear('h3', 'confirmacion-titulo', op.titulo || '¿Confirmas esta acción?');
      titulo.id = idTitulo;
      var mensaje = crear('p', 'confirmacion-mensaje', op.mensaje || '');
      mensaje.id = idMensaje;
      caja.appendChild(titulo);
      caja.appendChild(mensaje);

      var acciones = crear('div', 'confirmacion-acciones');
      var no = crear('button', 'btn btn-outline', op.cancelar || 'Cancelar');
      var si = crear('button', op.peligro ? 'btn btn-danger' : 'btn btn-primary', op.confirmar || 'Confirmar');
      no.type = si.type = 'button';
      acciones.appendChild(no);
      acciones.appendChild(si);
      caja.appendChild(acciones);
      fondo.appendChild(caja);

      function terminar(valor) {
        document.removeEventListener('keydown', teclado, true);
        if (fondo.parentNode) fondo.parentNode.removeChild(fondo);
        document.body.classList.remove('confirmacion-abierta');
        if (anterior && anterior.focus) anterior.focus();
        resolver(valor);
      }
      function teclado(e) {
        if (e.key === 'Escape') { e.preventDefault(); terminar(false); }
        if (e.key === 'Tab') {                 // el foco no sale del diálogo
          e.preventDefault();
          (document.activeElement === no ? si : no).focus();
        }
      }
      no.addEventListener('click', function () { terminar(false); });
      si.addEventListener('click', function () { terminar(true); });
      fondo.addEventListener('click', function (e) { if (e.target === fondo) terminar(false); });
      document.addEventListener('keydown', teclado, true);

      document.body.appendChild(fondo);
      document.body.classList.add('confirmacion-abierta');
      (op.peligro ? no : si).focus();          // en acciones peligrosas, el foco empieza en Cancelar
    });
  }

  // Formularios con data-confirmar: piden confirmación antes de enviarse.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.dataset || !form.dataset.confirmar || form.dataset.confirmado === '1') return;
    e.preventDefault();
    confirmar({
      titulo: form.dataset.confirmarTitulo,
      mensaje: form.dataset.confirmar,
      confirmar: form.dataset.confirmarBoton,
      peligro: form.dataset.confirmarPeligro !== 'no'
    }).then(function (ok) {
      if (!ok) return;
      form.dataset.confirmado = '1';
      form.submit();
    });
  }, true);

  // Avisos que deja el servidor en la página: <script type="application/json" data-aviso>…</script>
  function avisosPendientes() {
    var nodos = document.querySelectorAll('script[data-aviso]');
    for (var i = 0; i < nodos.length; i++) {
      try { aviso(JSON.parse(nodos[i].textContent)); } catch (err) { /* aviso mal formado: se ignora */ }
      nodos[i].parentNode.removeChild(nodos[i]);
    }
    // Quita ?mensaje=… de la dirección para que recargar la página no repita el aviso.
    if (nodos.length && window.history && window.history.replaceState && window.URLSearchParams) {
      try {
        var url = new URL(window.location.href);
        ['mensaje', 'texto', 'campo', 'detalle', 'nombre', 'nuevo_id'].forEach(function (p) { url.searchParams.delete(p); });
        window.history.replaceState(null, '', url.pathname + (url.search || '') + url.hash);
      } catch (err) { /* p. ej. páginas abiertas como archivo: el aviso ya se mostró igual */ }
    }
  }

  // Se publica antes de mostrar los avisos pendientes: si algo falla ahí, la API sigue disponible.
  window.Notificar = { aviso: aviso, confirmar: confirmar };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', avisosPendientes);
  else avisosPendientes();
})();
