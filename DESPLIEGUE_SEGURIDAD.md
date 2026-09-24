# Despliegue: protección de archivos

Este sistema es PHP sin framework y **toda la carpeta del proyecto es la raíz web**:
cualquier archivo que exista en ella se puede pedir por URL. Hay dos capas de protección:

| Capa | Dónde vive | ¿Funciona en cualquier servidor? |
|---|---|---|
| Sesión, rol y token CSRF en cada página `.php` | `config/auth.php` (`requerirAcceso()`, `requerirPost()`, `soloConsola()`) | **Sí**: es código PHP |
| Bloqueo de archivos internos y documentos protegidos | `LocalValetDriver.php` (solo **Laravel Herd / Valet**) | **No**: fuera de Herd hay que replicarla |

> ⚠️ Si el proyecto se despliega en Apache, Nginx o cualquier servidor que no sea Herd,
> `LocalValetDriver.php` **se ignora** y sin la configuración de abajo quedarían
> descargables el `.env` (credenciales de la base de datos), todo el repositorio `.git/`,
> los respaldos SQL con datos personales y los contratos de los trabajadores.

## Qué hace `LocalValetDriver.php` (lo que hay que replicar)

**1. Rutas que nunca se sirven (responder 404):**

| Ruta | Por qué |
|---|---|
| Todo lo que empiece por `.` (`/.env`, `/.env.example`, `/.git/`, `/.gitignore`, `/.claude/`) | Credenciales y el repositorio completo |
| `/vendor/`, `/db/`, `/tests/`, `/config/` | Código interno, migraciones, **respaldos SQL con datos personales** (`db/respaldos/`) y scripts de mantenimiento |
| `/composer.json`, `/composer.lock`, `/LocalValetDriver.php`, `/README*` | Información interna del proyecto |
| Archivos sueltos en la raíz con extensión `.png`, `.jpg`, `.jpeg`, `.html`, `.md`, `.sql`, `.log`, `.txt` | Capturas de pantalla y volcados de prueba con datos (`tmp_output.html`, `trabajadores_*.png`...) y esta documentación |
| `/dbcheck.php`, `/debug_test.php` | Diagnósticos |

**2. Documentos que solo se entregan con sesión iniciada** — se envían a
`archivo_protegido.php`, que valida sesión y rol antes de servir el archivo:

| Ruta | Contenido |
|---|---|
| `/expedientes/` | Contratos, perfiles de cargo e inducciones (`.docx`) |
| `/uploads/` | Soportes de novedades (PDF / imágenes) |
| `/novedades/` | Soportes antiguos de novedades |

Todo lo demás (páginas `.php`, `/assets/`) se sirve normalmente.

## Configuración equivalente

### Nginx

```nginx
server {
    root /ruta/al/proyecto;
    index index.php;

    # 1. Nunca se sirven
    location ~ ^/\.                                   { return 404; }
    location ~ ^/(vendor|db|tests|config)(/|$)        { return 404; }
    location ~ ^/(composer\.(json|lock)|LocalValetDriver\.php|README[^/]*)$ { return 404; }
    location ~ ^/[^/]+\.(png|jpe?g|html|md|sql|log|txt)$ { return 404; }
    location ~ ^/(dbcheck|debug_test)\.php$           { return 404; }

    # 2. Documentos: solo a través de archivo_protegido.php (valida sesión)
    location ~ ^/(expedientes|uploads|novedades)/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/archivo_protegido.php;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;   # ajustar al socket de PHP-FPM
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

### Apache (`.htaccess` en la raíz, requiere `mod_rewrite` y `AllowOverride All`)

```apache
RewriteEngine On

# 1. Nunca se sirven
RewriteRule ^\. - [R=404,L]
RewriteRule ^(vendor|db|tests|config)(/|$) - [R=404,L]
RewriteRule ^(composer\.(json|lock)|LocalValetDriver\.php|README[^/]*)$ - [R=404,L,NC]
RewriteRule ^[^/]+\.(png|jpe?g|html|md|sql|log|txt)$ - [R=404,L,NC]
RewriteRule ^(dbcheck|debug_test)\.php$ - [R=404,L,NC]

# 2. Documentos: solo a través de archivo_protegido.php (valida sesión)
RewriteRule ^(expedientes|uploads|novedades)/ archivo_protegido.php [L,NC]
```

`archivo_protegido.php` lee la ruta pedida de `$_SERVER['REQUEST_URI']`, así que funciona
igual con las dos configuraciones.

> Alternativa más robusta para producción: mover `expedientes/`, `uploads/`, `db/`,
> `tests/`, `config/` y `vendor/` **fuera** de la raíz web y servir solo una carpeta
> pública. Requiere ajustar las rutas en el código (`generar_documento.php`,
> `novedades/guardar.php`, `archivo_protegido.php` y los `require` de `config/`).

## Cómo verificar después de desplegar

Sin iniciar sesión, todas estas URLs deben responder **404** (o redirigir al login en el
caso de los documentos). Si alguna descarga contenido, la configuración no quedó aplicada:

```
/.env
/.git/config
/db/respaldos/
/db/run_seed.php
/tests/run.php
/config/conexion.php
/tmp_output.html
/trabajadores_screenshot.png
/dbcheck.php
/expedientes/3/Contrato_Karen_Paola_Vaca_Franco_20260630.docx   (→ login, no descarga)
/uploads/novedades/novedad_20260704_115022_79d3fded.pdf         (→ login, no descarga)
```

Y con sesión de Administrador, las páginas de `views/` y los documentos deben abrir
normalmente.

## Otras notas de seguridad

- **Scripts de mantenimiento** (`db/*.php`, `tests/*.php`, `dbcheck.php`): llaman a
  `soloConsola()` y responden 404 desde el navegador aunque falle la configuración del
  servidor. Se ejecutan con `php db/run_seed.php`, `php tests/run.php`, etc.
- **Roles:** los módulos RRHH / SG-SST exigen el rol Administrador (`ROLES_RRHH` en
  `config/auth.php`). El dashboard y el perfil propio, cualquier usuario con sesión.
- **CSRF:** todo `POST` debe incluir `<?php echo campoCsrf(); ?>` dentro del `<form>` (o la
  cabecera `X-CSRF-Token` en un `fetch`). Las acciones que cambian datos llaman además a
  `requerirPost()` para que un enlace no pueda ejecutarlas.
