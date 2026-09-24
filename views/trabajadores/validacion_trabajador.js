/*
 * Validación en el navegador de los formularios de trabajador (crear / editar).
 * Replica las reglas de validarTrabajador() en funciones_trabajador.php para dar
 * mensajes inmediatos por campo. El backend vuelve a validar todo (incluidas las
 * reglas que requieren la base de datos: unicidad, dominio del correo, cargo/área).
 *
 * Uso: <form data-validar-trabajador novalidate> ... <script src="validacion_trabajador.js"></script>
 */
(function () {
  var EDAD_MINIMA = 18;
  var EDAD_MAXIMA = 80;
  var SOLO_LETRAS = /^\p{L}+( \p{L}+)*$/u;
  var MAX_HIJOS = 15;
  // Mismo patrón que PATRON_CORREO en funciones_trabajador.php.
  var PATRON_CORREO = /^[a-z0-9_%+'-]+(\.[a-z0-9_%+'-]+)*@([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i;

  // Mismos mensajes que mensajeCorreoInvalido() del backend.
  function mensajeCorreo(v) {
    var ejemplo = ' Ejemplo: nombre@gmail.com';
    if ((v.match(/@/g) || []).length !== 1) return 'El correo debe tener exactamente una @.' + ejemplo;
    if (/\s/.test(v)) return 'El correo no puede tener espacios.' + ejemplo;
    var raros = v.match(/[^a-z0-9@._%+'-]/gi);
    if (raros) {
      var unicos = raros.filter(function (c, i) { return raros.indexOf(c) === i; });
      return 'El correo tiene caracteres no permitidos: ' + unicos.map(function (c) { return '«' + c + '»'; }).join(' ') + '.' + ejemplo;
    }
    if (/(^\.|\.@|\.\.)/.test(v)) return 'El correo tiene un punto mal ubicado (al inicio, repetido o justo antes de la @).' + ejemplo;
    return 'El correo electrónico no es válido. Revisa lo que va después de la @.' + ejemplo;
  }

  function hoy() {
    var d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function parsearFecha(valor) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);
    if (!m) return null;
    var d = new Date(+m[1], +m[2] - 1, +m[3]);
    return d.getMonth() === +m[2] - 1 ? d : null;
  }

  function sumarAnios(fecha, anios) {
    var d = new Date(fecha.getTime());
    d.setFullYear(d.getFullYear() + anios);
    return d;
  }

  function edad(nacimiento) {
    var h = hoy();
    var e = h.getFullYear() - nacimiento.getFullYear();
    if (h < sumarAnios(nacimiento, e)) e--;
    return e;
  }

  function formatoDMY(d) {
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
  }

  function aISO(d) {
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  function limpio(v) {
    return (v || '').replace(/\s+/g, ' ').trim();
  }

  // Cada regla recibe (valor, form) y devuelve un mensaje de error o ''.
  var reglas = {
    nombres: function (v) { return reglaNombre(v, 'Los nombres'); },
    apellidos: function (v) { return reglaNombre(v, 'Los apellidos'); },

    id_tipos_documentos: requerido('Selecciona un tipo de documento válido.'),
    id_generos: requerido('Selecciona un género válido.'),
    id_nacionalidad: requerido('Selecciona una nacionalidad válida.'),
    id_eps: requerido('Selecciona una EPS válida.'),
    id_formacion_educativa: requerido('Selecciona una formación educativa válida.'),
    id_estado_civil: requerido('Selecciona un estado civil válido.'),
    id_grupos_etnicos: requerido('Selecciona un grupo étnico válido.'),
    id_area: requerido('Selecciona un área válida.'),
    id_cargo: requerido('Selecciona un cargo.'),
    tiene_hijos: requerido('Indica si el trabajador tiene hijos.'),

    numero_documento: function (v, form) {
      v = (v || '').replace(/[\s.\-]/g, '');
      if (!v) return 'El número de documento es obligatorio.';
      var tipo = form.elements.id_tipos_documentos;
      var texto = tipo && tipo.selectedIndex >= 0 ? tipo.options[tipo.selectedIndex].text : '';
      if (/pasaporte/i.test(texto)) {
        return /^[A-Za-z0-9]{5,20}$/.test(v) ? '' : 'El pasaporte debe tener entre 5 y 20 letras o números.';
      }
      return /^\d{6,10}$/.test(v) ? '' : 'El número de documento debe tener solo dígitos (entre 6 y 10).';
    },

    fecha_nacimiento: function (v) {
      if (!v) return 'La fecha de nacimiento es obligatoria.';
      var d = parsearFecha(v);
      if (!d) return 'La fecha de nacimiento no es una fecha válida.';
      if (d >= hoy()) return 'La fecha de nacimiento no puede ser hoy ni una fecha futura.';
      if (edad(d) < EDAD_MINIMA) return 'El trabajador debe tener al menos ' + EDAD_MINIMA + ' años.';
      if (edad(d) > EDAD_MAXIMA) return 'La fecha de nacimiento indica más de ' + EDAD_MAXIMA + ' años. Revisa el año.';
      return '';
    },

    fecha_ingreso: function (v, form) {
      if (!v) return 'La fecha de ingreso es obligatoria.';
      var d = parsearFecha(v);
      if (!d) return 'La fecha de ingreso no es una fecha válida.';
      if (d > hoy()) return 'La fecha de ingreso no puede ser una fecha futura.';
      var nac = parsearFecha(form.elements.fecha_nacimiento ? form.elements.fecha_nacimiento.value : '');
      if (nac) {
        var minimo = sumarAnios(nac, EDAD_MINIMA);
        if (d < minimo) {
          return 'La fecha de ingreso debe ser posterior a que el trabajador cumpliera ' + EDAD_MINIMA + ' años (' + formatoDMY(minimo) + ').';
        }
      }
      return '';
    },

    // Lugar de nacimiento (opcional): departamento y ciudad van juntos.
    departamento_nacimiento: function (v, form) {
      return !v && form.elements.ciudad_nacimiento && form.elements.ciudad_nacimiento.value ? 'Selecciona el departamento.' : '';
    },
    ciudad_nacimiento: function (v, form) {
      return !v && form.elements.departamento_nacimiento && form.elements.departamento_nacimiento.value ? 'Selecciona la ciudad o municipio.' : '';
    },

    correo_personal: function (v) {
      v = (v || '').trim();
      if (!v) return 'El correo electrónico es obligatorio.';
      if (v.length > 100) return 'El correo no puede superar 100 caracteres.';
      return PATRON_CORREO.test(v) ? '' : mensajeCorreo(v);
    },

    telefono: function (v) {
      v = (v || '').replace(/[\s\-()]/g, '');
      if (!v) return 'El teléfono es obligatorio.';
      return /^(3\d{9}|60\d{8})$/.test(v)
        ? '' : 'El teléfono debe tener exactamente 10 dígitos (celular que empiece por 3, o fijo que empiece por 60).';
    },

    numero_hijos: function (v, form) {
      if (!form.elements.tiene_hijos || form.elements.tiene_hijos.value !== '1') return '';
      var campo = form.elements.numero_hijos;
      // En type="number", si se escriben letras el navegador deja value = '' y marca badInput.
      if (campo && campo.validity && campo.validity.badInput) return 'El número de hijos debe ser un número entero, sin letras ni símbolos.';
      if (v === '') return 'Indicaste que tiene hijos: escribe cuántos (mínimo 1).';
      if (!/^\d+$/.test(v)) return 'El número de hijos debe ser un número entero, sin letras ni símbolos.';
      if (+v < 1) return 'Indicaste que tiene hijos: el número debe ser al menos 1. Si no tiene, elige "No" en "¿Tiene hijos?".';
      if (+v > MAX_HIJOS) return 'El número de hijos no puede ser mayor a ' + MAX_HIJOS + '.';
      return '';
    },

    talla_pantalon: function (v) { return limpio(v).length > 10 ? 'La talla de pantalón no puede superar 10 caracteres.' : ''; },
    talla_botas: function (v) { return limpio(v).length > 10 ? 'La talla de botas no puede superar 10 caracteres.' : ''; }
  };

  function reglaNombre(v, etiqueta) {
    v = limpio(v);
    if (!v) return etiqueta + ' son obligatorios.';
    if (!SOLO_LETRAS.test(v)) return etiqueta + ' solo pueden contener letras y espacios.';
    if (v.length < 2 || v.length > 100) return etiqueta + ' deben tener entre 2 y 100 caracteres.';
    return '';
  }

  function requerido(mensaje) {
    return function (v) { return v === '' || v == null ? mensaje : ''; };
  }

  function contenedor(campo) {
    return campo.closest('.form-group, .edit-field') || campo.parentNode;
  }

  function mostrarError(campo, mensaje) {
    var caja = contenedor(campo);
    var existente = caja.querySelector('.field-error');
    if (existente) existente.remove();
    campo.classList.toggle('has-error', !!mensaje);
    if (mensaje) {
      var div = document.createElement('div');
      div.className = 'field-error';
      div.textContent = mensaje;
      caja.appendChild(div);
    }
  }

  function validarCampo(form, nombre) {
    var campo = form.elements[nombre];
    if (!campo || !reglas[nombre]) return '';
    var mensaje = reglas[nombre](campo.value, form);
    mostrarError(campo, mensaje);
    return mensaje;
  }

  function actualizarMinimoIngreso(form) {
    var nac = parsearFecha(form.elements.fecha_nacimiento ? form.elements.fecha_nacimiento.value : '');
    if (form.elements.fecha_ingreso) {
      form.elements.fecha_ingreso.min = nac ? aISO(sumarAnios(nac, EDAD_MINIMA)) : '';
    }
  }

  function soloDigitos(campo) {
    campo.addEventListener('keydown', function (ev) {
      if (ev.key.length === 1 && !/\d/.test(ev.key) && !ev.ctrlKey && !ev.metaKey) ev.preventDefault();
    });
    campo.addEventListener('paste', function (ev) {
      var texto = (ev.clipboardData || window.clipboardData).getData('text');
      if (!/^\d+$/.test(texto.trim())) ev.preventDefault();
    });
  }

  function iniciar(form) {
    form.noValidate = true;
    actualizarMinimoIngreso(form);
    if (form.elements.numero_hijos) soloDigitos(form.elements.numero_hijos);

    Object.keys(reglas).forEach(function (nombre) {
      var campo = form.elements[nombre];
      if (!campo) return;
      var evento = campo.tagName === 'SELECT' || campo.type === 'date' ? 'change' : 'blur';
      campo.addEventListener(evento, function () {
        validarCampo(form, nombre);
        if (nombre === 'fecha_nacimiento') {
          actualizarMinimoIngreso(form);
          if (form.elements.fecha_ingreso && form.elements.fecha_ingreso.value) validarCampo(form, 'fecha_ingreso');
        }
        if (nombre === 'id_tipos_documentos' && form.elements.numero_documento.value) validarCampo(form, 'numero_documento');
        if (nombre === 'tiene_hijos' && form.elements.numero_hijos) validarCampo(form, 'numero_hijos');
      });
      // Al corregir, se quita el error sin esperar a salir del campo.
      campo.addEventListener('input', function () {
        if (campo.classList.contains('has-error') && !reglas[nombre](campo.value, form)) mostrarError(campo, '');
      });
    });

    form.addEventListener('submit', function (ev) {
      var primero = null;
      Object.keys(reglas).forEach(function (nombre) {
        if (validarCampo(form, nombre) && !primero) primero = form.elements[nombre];
      });
      var resumen = form.querySelector('.form-errors-summary');
      if (primero) {
        ev.preventDefault();
        var total = form.querySelectorAll('.field-error').length;
        if (!resumen) {
          resumen = document.createElement('div');
          resumen.className = 'form-errors-summary';
          var cuerpo = form.querySelector('.modal-body, .edit-body') || form;
          cuerpo.insertBefore(resumen, cuerpo.firstChild);
        }
        resumen.textContent = 'Revisa ' + (total === 1 ? 'el campo marcado' : 'los ' + total + ' campos marcados') + ' antes de guardar.';
        const visible = (primero.closest && primero.closest('.sb') && primero.closest('.sb').querySelector('.sb-input')) || primero;
        visible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        visible.focus({ preventScroll: true });
      } else if (resumen) {
        resumen.remove();
      }
    });
  }

  document.querySelectorAll('form[data-validar-trabajador]').forEach(iniciar);
})();
