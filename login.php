<?php
/**
 * VoAnh - Connexion
 */
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/auth.php';

$auth  = new Auth();
$error = '';

if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — VoAnh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0a0a0f;--card:#13131a;--border:#1e1e2e;--text:#e8e6f0;--muted:#6b6880;--accent:#7c6af5;--accent2:#a78bfa;--err:#f87171;--success:#4ade80}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(124,106,245,.18),transparent);pointer-events:none}
.card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:48px 40px;width:100%;max-width:420px;position:relative}
.card::before{content:'';position:absolute;inset:-1px;border-radius:20px;background:linear-gradient(135deg,rgba(124,106,245,.3),transparent 60%);z-index:-1;pointer-events:none}
.brand{text-align:center;margin-bottom:36px}
.brand-name{font-family:'DM Serif Display',serif;font-size:32px;background:linear-gradient(135deg,var(--accent2),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-0.5px}
.brand-sub{font-size:13px;color:var(--muted);margin-top:4px;letter-spacing:.5px;text-transform:uppercase}
.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.3);color:var(--err);padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:24px}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:8px;font-weight:500}
.field input{width:100%;padding:13px 16px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:15px;font-family:'DM Sans',sans-serif;transition:.2s}
.field input:focus{outline:none;border-color:var(--accent);background:rgba(124,106,245,.06)}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;margin-top:8px;transition:.2s;letter-spacing:.3px}
.btn:hover{opacity:.9;transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
.links{text-align:center;margin-top:24px;font-size:13.5px;color:var(--muted)}
.links a{color:var(--accent2);text-decoration:none;font-weight:500}
.links a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-name">VoAnh</div>
    <div class="brand-sub">Assistant IA Avancé</div>
  </div>

  <?php if ($error): ?>
  <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="field">
      <label for="username">Identifiant</label>
      <input type="text" id="username" name="username" placeholder="Nom d'utilisateur ou email" autofocus autocomplete="username">
    </div>
    <div class="field">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Se connecter</button>
  </form>

  <div class="links">
    Pas encore de compte ? <a href="register.php">S'inscrire gratuitement</a>
  </div>
</div>
</body>
</html>
