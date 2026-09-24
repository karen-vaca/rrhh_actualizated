/*
 * Select con buscador (autocompletar), sin librerías.
 *
 * Convierte cada <select data-buscador> en un campo donde se escribe para filtrar las
 * opciones (sin importar tildes ni mayúsculas). El <select> original sigue en el
 * formulario (oculto): es el que se envía y el que valida el servidor, así que no se
 * puede enviar texto libre. Si se escribe algo que no está en la lista, al salir del
 * campo vuelve a mostrarse la opción seleccionada.
 *
 * Si las opciones del <select> cambian (ej: ciudades al cambiar el departamento),
 * llamar a select.refrescarBuscador().
 */
(function () {
  function normalizar(t) {
    return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  }

  let contador = 0;

  function mejorar(select) {
    if (select.dataset.buscadorListo) return;
    select.dataset.buscadorListo = '1';
    const id = 'sb' + (++contador);

    const caja = document.createElement('div');
    caja.className = 'sb';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = (select.className || '').replace(/\bform-select\b|\bedit-select\b/g, '').trim() + ' sb-input ' +
      (select.classList.contains('edit-select') ? 'edit-input' : 'form-input');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id + '-lista');
    input.autocomplete = 'off';
    const etiqueta = select.id && document.querySelector('label[for="' + select.id + '"]');
    if (etiqueta) { input.id = select.id + '-buscar'; etiqueta.setAttribute('for', input.id); }
    else if (select.getAttribute('aria-label')) input.setAttribute('aria-label', select.getAttribute('aria-label'));

    const lista = document.createElement('ul');
    lista.className = 'sb-lista';
    lista.id = id + '-lista';
    lista.setAttribute('role', 'listbox');
    lista.hidden = true;

    select.parentNode.insertBefore(caja, select);
    caja.appendChild(input);
    caja.appendChild(lista);
    caja.appendChild(select);
    select.classList.add('sb-select-oculto');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    let activo = -1;
    let visibles = [];

    function opcionesValidas() {
      return Array.from(select.options).filter(o => o.value !== '' && !o.disabled);
    }
    function textoPlaceholder() {
      const vacia = Array.from(select.options).find(o => o.value === '');
      return vacia ? vacia.textContent.trim() : 'Seleccionar...';
    }
    function mostrarSeleccion() {
      const o = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
      input.value = o && o.value !== '' ? o.textContent.trim() : '';
      input.placeholder = select.disabled ? textoPlaceholder() : 'Escribe para buscar...';
      input.disabled = select.disabled;
      input.classList.toggle('has-error', select.classList.contains('has-error'));
    }
    function cerrar() {
      lista.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activo = -1;
    }
    function marcar(i) {
      activo = i;
      Array.from(lista.children).forEach((li, j) => li.classList.toggle('activa', j === i));
      if (i >= 0 && lista.children[i]) {
        input.setAttribute('aria-activedescendant', lista.children[i].id);
        lista.children[i].scrollIntoView && lista.children[i].scrollIntoView({ block: 'nearest' });
      }
    }
    function abrir(filtro) {
      const f = normalizar(filtro);
      visibles = opcionesValidas().filter(o => !f || normalizar(o.textContent).includes(f)).slice(0, 200);
      lista.innerHTML = '';
      if (!visibles.length) {
        const li = document.createElement('li');
        li.className = 'sb-vacio';
        li.textContent = 'Sin resultados';
        lista.appendChild(li);
      }
      visibles.forEach((o, i) => {
        const li = document.createElement('li');
        li.id = id + '-op' + i;
        li.setAttribute('role', 'option');
        li.textContent = o.textContent.trim();
        li.setAttribute('aria-selected', o.selected ? 'true' : 'false');
        li.addEventListener('mousedown', ev => { ev.preventDefault(); elegir(o); });
        lista.appendChild(li);
      });
      lista.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      marcar(visibles.length ? 0 : -1);
    }
    function elegir(opcion) {
      select.value = opcion.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      mostrarSeleccion();
      cerrar();
    }

    input.addEventListener('focus', () => { input.select(); abrir(''); });
    input.addEventListener('input', () => abrir(input.value));
    input.addEventListener('keydown', ev => {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); if (lista.hidden) abrir(''); else marcar(Math.min(activo + 1, visibles.length - 1)); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcar(Math.max(activo - 1, 0)); }
      else if (ev.key === 'Enter') { if (!lista.hidden && activo >= 0 && visibles[activo]) { ev.preventDefault(); elegir(visibles[activo]); } }
      else if (ev.key === 'Escape') { mostrarSeleccion(); cerrar(); }
    });
    input.addEventListener('blur', () => {
      // Solo se aceptan opciones de la lista: si coincide exactamente con una, se elige;
      // si no, se vuelve a mostrar la selección actual.
      const exacta = opcionesValidas().find(o => normalizar(o.textContent) === normalizar(input.value));
      if (exacta && exacta.value !== select.value) elegir(exacta);
      else { mostrarSeleccion(); cerrar(); }
      select.dispatchEvent(new Event('blur'));
    });
    select.addEventListener('change', mostrarSeleccion);
    // Los mensajes de error se aplican al <select>; el campo visible los refleja.
    new MutationObserver(mostrarSeleccion).observe(select, { attributes: true, attributeFilter: ['class', 'disabled'] });

    select.refrescarBuscador = mostrarSeleccion;
    mostrarSeleccion();
  }

  window.mejorarSelectsBuscador = function (raiz) {
    (raiz || document).querySelectorAll('select[data-buscador]').forEach(mejorar);
  };
  window.mejorarSelectsBuscador();
})();
