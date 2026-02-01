<?php
/**
 * Admin Panel - Login
 */

// Verificar se precisa de setup inicial
$lockFile = __DIR__ . '/../../storage/.installed';
if (!file_exists($lockFile)) {
    header('Location: ../setup.php');
    exit;
}

session_start();
$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'];
$auth = $container['auth'];

// Se já está autenticado, redirecionar para dashboard
if ($auth && $auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    
    if ($auth && $auth->login($pin)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'PIN inválido. Tenta novamente.';
    }
}

$siteName = $config['tenant']['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login · <?= sanitize($siteName); ?></title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h1><?= sanitize($siteName); ?></h1>
        <p>Painel de Administração</p>
      </div>
      
      <?php if ($error): ?>
      <div class="alert alert-error"><?= sanitize($error); ?></div>
      <?php endif; ?>
      
      <form method="POST" class="login-form">
        <div class="form-group">
          <label for="pin">PIN de Acesso</label>
          <input type="password" id="pin" name="pin" placeholder="••••" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
      </form>
      
      <div class="login-footer">
        <a href="/">&larr; Voltar ao site</a>
      </div>
    </div>
  </div>
</body>
</html>
