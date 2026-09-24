<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$usuario = trim($_POST['usuario'] ?? '');
$contrasena = $_POST['contrasena'] ?? $_POST['password'] ?? '';

if ($usuario === '' || $contrasena === '') {
    $_SESSION['login_error'] = 'Debes ingresar usuario y contraseña.';
    header('Location: index.php');
    exit();
}

try {
    // Nombre real del usuario (desde el trabajador vinculado) y nombre del rol
    // tal como está en la tabla roles.
    $sql = "SELECT 
                u.id_usuario,
                u.usuario,
                u.contrasena,
                u.id_roles,
                u.id_trabajador,
                r.nombre_rol,
                t.nombres,
                t.apellidos
            FROM usuarios u
            LEFT JOIN roles r ON r.id_roles = u.id_roles
            LEFT JOIN trabajadores t ON t.id_trabajador = u.id_trabajador
            WHERE u.usuario = :usuario
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':usuario' => $usuario
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Usuario o contraseña incorrectos.';
        header('Location: index.php');
        exit();
    }

    $hashGuardado = $user['contrasena'];

    $passwordCorrecta = password_verify($contrasena, $hashGuardado);

    // Respaldo por si en algún momento guardaste contraseña sin encriptar
    if (!$passwordCorrecta && hash_equals($hashGuardado, $contrasena)) {
        $passwordCorrecta = true;
    }

    if (!$passwordCorrecta) {
        $_SESSION['login_error'] = 'Usuario o contraseña incorrectos.';
        header('Location: index.php');
        exit();
    }

    // Nuevo id de sesión al autenticarse (evita fijación de sesión).
    session_regenerate_id(true);
    $_SESSION['logueado'] = true;
    $_SESSION['id_usuario'] = $user['id_usuario'];
    $_SESSION['usuario'] = $user['usuario'];
    $_SESSION['id_roles'] = $user['id_roles'];
    $_SESSION['id_trabajador'] = $user['id_trabajador'];

    // Datos para la barra superior: los del usuario que inició sesión.
    // Si el usuario no está vinculado a un trabajador, se muestra su usuario.
    $_SESSION['nombres'] = trim((string)($user['nombres'] ?? '')) ?: $user['usuario'];
    $_SESSION['nombre'] = $_SESSION['nombres'];
    $_SESSION['apellidos'] = trim((string)($user['apellidos'] ?? ''));

    $_SESSION['rol_nombre'] = $user['nombre_rol'] ?: 'Sin rol';

    $_SESSION['rol'] = $_SESSION['rol_nombre'];

    header('Location: views/dashboard/dashboard.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['login_error'] = 'Error en el inicio de sesión: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}