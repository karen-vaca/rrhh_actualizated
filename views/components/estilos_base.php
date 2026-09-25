<?php
// Fuentes, escala tipográfica y layout base comunes a todas las pantallas internas
// (views/<modulo>/*.php). Se incluye en el <head>, antes del <style> propio de la pantalla.
//   assets/css/tipografia.css  escala de textos (--tx-*)
//   assets/css/layout.css      sidebar, barra superior, menú de perfil, contenido, pie
// Las pantallas no deben redefinir en su <style> las clases de layout.css.
// Las fuentes cargan solo los pesos que usa la escala: pedir más pesos (600/700 reales
// de DM Sans) hacía que unas pantallas se vieran más en negrita que otras.
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/tipografia.css">
<link rel="stylesheet" href="../../assets/css/layout.css">
