<?php
// Fuentes, escala tipográfica y layout base comunes a todas las pantallas internas
// (views/<modulo>/*.php). Se incluye en el <head>, antes del <style> propio de la pantalla.
//   assets/css/tipografia.css  escala de textos (--tx-*)
//   assets/css/layout.css      sidebar, barra superior, menú de perfil, contenido, pie
//   assets/css/botones.css     botones de acción: .btn + .btn-primary/.btn-outline/.btn-outline-info/
//                              .btn-outline-danger/.btn-danger (+ .btn-sm)
//   assets/css/componentes.css tarjetas de estadística, filtros, tabla, avatares, acciones
//   assets/js/notificaciones.js + assets/css/notificaciones.css  avisos (toast) y confirmaciones
//       (desde PHP: avisoAlCargar(), en views/components/notificaciones.php)
//   assets/js/formularios.js + assets/css/formularios.css  montos con puntos de miles (data-dinero),
//       errores en línea y validación propia sin globos del navegador (data-validar)
// Las pantallas no deben redefinir en su <style> las clases de layout.css.
// componentes.css se carga por defecto (toda pantalla nueva debe usarlo). Las pantallas
// antiguas que todavía tienen su propio CSS de tarjetas/filtros/tabla con los mismos nombres
// de clase lo desactivan con $componentesPendientes = true; antes del require, hasta migrarlas.
// Las fuentes cargan solo los pesos que usa la escala: pedir más pesos (600/700 reales
// de DM Sans) hacía que unas pantallas se vieran más en negrita que otras.
require_once __DIR__ . '/notificaciones.php';
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/tipografia.css">
<link rel="stylesheet" href="../../assets/css/layout.css">
<link rel="stylesheet" href="../../assets/css/botones.css">
<?php if (empty($componentesPendientes)): ?>
<link rel="stylesheet" href="../../assets/css/componentes.css">
<?php endif; ?>
<link rel="stylesheet" href="../../assets/css/notificaciones.css">
<link rel="stylesheet" href="../../assets/css/formularios.css">
<!-- Sin defer: el JavaScript de cada pantalla usa Notificar/Formularios mientras carga. -->
<script src="../../assets/js/notificaciones.js"></script>
<script src="../../assets/js/formularios.js"></script>
