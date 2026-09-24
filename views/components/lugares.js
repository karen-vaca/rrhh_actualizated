/*
 * Departamento → Ciudad en cascada (catálogo DIVIPOLA).
 *
 * Uso:
 *   <select name="departamento_x" data-buscador data-cascada-ciudad="idDelSelectDeCiudad">…</select>
 *   <select name="ciudad_x" id="idDelSelectDeCiudad" data-buscador>…</select>
 *   <script>window.CIUDADES_POR_DEPARTAMENTO = {"15": [["15001","Tunja"], …], …};</script>
 *
 * La ciudad solo ofrece los municipios del departamento elegido (no se pueden armar
 * combinaciones imposibles) y queda deshabilitada hasta elegir departamento. El
 * servidor vuelve a validar la pareja (validarLugar() en views/components/lugares.php).
 */
(function () {
  function iniciarCascada(depSelect) {
    const ciudad = document.getElementById(depSelect.dataset.cascadaCiudad);
    if (!ciudad || depSelect.dataset.cascadaLista) return;
    depSelect.dataset.cascadaLista = '1';
    const datos = window.CIUDADES_POR_DEPARTAMENTO || {};

    function cargarCiudades() {
      const seleccionada = ciudad.value;
      const lista = datos[depSelect.value] || [];
      ciudad.innerHTML = '';
      const vacia = document.createElement('option');
      vacia.value = '';
      vacia.textContent = depSelect.value ? 'Seleccione la ciudad o municipio...' : 'Primero elija el departamento';
      ciudad.appendChild(vacia);
      lista.forEach(function (c) {
        const o = document.createElement('option');
        o.value = c[0];
        o.textContent = c[1];
        if (c[0] === seleccionada) o.selected = true;
        ciudad.appendChild(o);
      });
      ciudad.disabled = !depSelect.value;
      if (ciudad.refrescarBuscador) ciudad.refrescarBuscador();
    }

    depSelect.addEventListener('change', cargarCiudades);
    cargarCiudades();
  }

  document.querySelectorAll('select[data-cascada-ciudad]').forEach(iniciarCascada);
})();
