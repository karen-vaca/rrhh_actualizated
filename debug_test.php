<?php
require_once __DIR__ . '/config/auth.php';
soloConsola();
// Archivo de diagnóstico. No toca la conexión ni la lógica.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Debug Test</title></head>
<body>
  <div style="position:fixed;top:10px;left:10px;background:#111;color:#fff;padding:10px;border-radius:6px;z-index:9999">DEBUG TEST: RENDER OK</div>
  <h1>Debug test page</h1>
  <p>Si ves este texto, el servidor está devolviendo HTML correctamente.</p>
</body>
</html>