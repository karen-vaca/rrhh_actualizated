/* ═══════════════════════════════════════════════════════════════════════════
   Formularios compartidos (fuente única de verdad)
   ───────────────────────────────────────────────────────────────────────────
   Se carga en todas las pantallas desde views/components/estilos_base.php.
   Estilos: assets/css/formularios.css.

   Montos en pesos:   <input type="text" data-dinero ...>
     El usuario escribe solo dígitos ("1750905") y el campo muestra "1.750.905" mientras
     escribe. Letras y símbolos no se pueden escribir; pegar un texto con letras se rechaza
     con un aviso (nunca se "limpia" en silencio). El servidor acepta el valor con puntos.

   Errores en línea:  Formularios.marcarError(campo, 'mensaje')  /  Formularios.marcarError(campo, '')

   Validación propia (sin los globos del navegador):
     <form data-validar novalidate> — revisa los campos required al enviar y marca el error
     debajo de cada uno. Reglas adicionales: form.reglasValidacion = [function () {
       return { campo: elemento, mensaje: '...' } | null; }]
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  function soloDigitos(texto) { return String(texto || '').replace(/\D/g, ''); }

  function formatearMiles(valor) {
    var d = soloDigitos(valor).replace(/^0+(?=\d)/, '');
    return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function cajaDe(campo) {
    return campo.closest('.form-group, .edit-field, .filter-group, .form-field') || campo.parentNode;
  }

  function marcarError(campo, mensaje) {
    if (!campo) return;
    var caja = cajaDe(campo);
    var previo = caja.querySelector('.field-error');
    if (previo) previo.parentNode.removeChild(previo);
    campo.classList.toggle('has-error', !!mensaje);
    if (mensaje) {
      campo.setAttribute('aria-invalid', 'true');
      var div = document.createElement('div');
      div.className = 'field-error';
      div.setAttribute('role', 'alert');
      div.textContent = mensaje;
      caja.appendChild(div);
    } else {
      campo.removeAttribute('aria-invalid');
    }
  }

  function enfocar(campo) {
    if (!campo) return;
    if (campo.scrollIntoView) campo.scrollIntoView({ block: 'center', behavior: 'smooth' });
    try { campo.focus({ preventScroll: true }); } catch (e) { campo.focus(); }
  }

  // Deja el cursor después del mismo número de dígitos que tenía antes de reformatear.
  function reformatear(input) {
    var pos = input.selectionStart == null ? input.value.length : input.selectionStart;
    var digitosAntes = soloDigitos(input.value.slice(0, pos)).length;
    var nuevo = formatearMiles(input.value);
    if (nuevo === input.value) return;
    input.value = nuevo;
    var i = 0, vistos = 0;
    while (i < nuevo.length && vistos < digitosAntes) {
      if (/\d/.test(nuevo[i])) vistos++;
      i++;
    }
    try { input.setSelectionRange(i, i); } catch (e) { /* algunos tipos de campo no lo permiten */ }
  }

  function mascaraDinero(input) {
    if (!input || input.dataset.mascaraDinero === 'lista') return;
    input.dataset.mascaraDinero = 'lista';
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'off');

    input.addEventListener('keydown', function (e) {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.key && e.key.length === 1 && !/\d/.test(e.key)) e.preventDefault();
    });
    input.addEventListener('beforeinput', function (e) {   // teclados de celular
      if (e.inputType === 'insertText' && e.data && /\D/.test(e.data)) e.preventDefault();
    });
    input.addEventListener('paste', function (e) {
      var texto = (e.clipboardData || window.clipboardData).getData('text');
      if (!/^\s*\$?\s*[\d.\s]*$/.test(texto)) {
        e.preventDefault();
        marcarError(input, 'Solo se pueden pegar valores numéricos, por ejemplo 1.750.905.');
      }
    });
    input.addEventListener('input', function () { reformatear(input); });
    reformatear(input);
  }

  function visible(campo) {
    return !!(campo.offsetWidth || campo.offsetHeight || campo.getClientRects().length);
  }

  function validar(form) {
    var primero = null;
    var campos = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < campos.length; i++) {
      var c = campos[i];
      if (c.type === 'hidden' || c.disabled || c.readOnly || !visible(c)) continue;
      var mensaje = '';
      if (c.type === 'date' && c.validity && c.validity.badInput) {
        mensaje = 'La fecha no es válida (ese día no existe o está incompleta).';
      } else if (c.required && !String(c.value || '').trim()) {
        mensaje = c.dataset.mensajeRequerido || 'Este campo es obligatorio.';
      }
      marcarError(c, mensaje);
      if (mensaje && !primero) primero = c;
    }
    (form.reglasValidacion || []).forEach(function (regla) {
      var r = regla();
      if (r && r.campo) {
        marcarError(r.campo, r.mensaje);
        if (!primero) primero = r.campo;
      }
    });
    if (primero) enfocar(primero);
    return !primero;
  }

  function iniciar(raiz) {
    (raiz || document).querySelectorAll('input[data-dinero]').forEach(mascaraDinero);
    (raiz || document).querySelectorAll('form[data-validar]').forEach(function (form) { form.noValidate = true; });
  }

  // Formularios con data-validar: validación propia al enviar.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.hasAttribute && form.hasAttribute('data-validar') && !validar(form)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  // Al corregir un campo marcado con error, el aviso desaparece (solo en formularios con
  // data-validar: otras pantallas tienen su propia validación en vivo y no se tocan).
  ['input', 'change'].forEach(function (tipo) {
    document.addEventListener(tipo, function (e) {
      var c = e.target;
      if (c && c.form && c.form.hasAttribute('data-validar') && c.classList.contains('has-error')
          && c.type !== 'date' && String(c.value || '').trim()) {
        marcarError(c, '');
      }
    });
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { iniciar(); });
  else iniciar();

  window.Formularios = {
    mascaraDinero: mascaraDinero,
    formatearMiles: formatearMiles,
    soloDigitos: soloDigitos,
    marcarError: marcarError,
    enfocar: enfocar,
    validar: validar,
    iniciar: iniciar
  };
})();
