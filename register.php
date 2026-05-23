<?php
/**
 * VoAnh - Inscription
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
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Tous les champs sont requis';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas';
    } else {
        $result = $auth->register($username, $email, $password);
        if ($result['success']) {
            $auth->login($username, $password);
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
<title>Inscription — VoAnh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0a0a0f;--card:#13131a;--border:#1e1e2e;--text:#e8e6f0;--muted:#6b6880;--accent:#7c6af5;--accent2:#a78bfa;--err:#f87171;--success:#4ade80}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(124,106,245,.18),transparent);pointer-events:none}
.card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:48px 40px;width:100%;max-width:440px;position:relative}
.card::before{content:'';position:absolute;inset:-1px;border-radius:20px;background:linear-gradient(135deg,rgba(124,106,245,.3),transparent 60%);z-index:-1;pointer-events:none}
.brand{text-align:center;margin-bottom:36px}
.brand-name{font-family:'DM Serif Display',serif;font-size:32px;background:linear-gradient(135deg,var(--accent2),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.brand-sub{font-size:13px;color:var(--muted);margin-top:4px;letter-spacing:.5px;text-transform:uppercase}
.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.3);color:var(--err);padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:24px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:7px;font-weight:500}
.field input{width:100%;padding:13px 16px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:15px;font-family:'DM Sans',sans-serif;transition:.2s}
.field input:focus{outline:none;border-color:var(--accent);background:rgba(124,106,245,.06)}
.perks{margin:20px 0;padding:16px;background:rgba(124,106,245,.06);border:1px solid rgba(124,106,245,.15);border-radius:10px}
.perks p{font-size:12px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;font-weight:500}
.perks ul{list-style:none}
.perks li{font-size:13px;color:var(--muted);padding:3px 0}
.perks li::before{content:'✦ ';color:var(--accent2)}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;margin-top:8px;transition:.2s}
.btn:hover{opacity:.9;transform:translateY(-1px)}
.links{text-align:center;margin-top:24px;font-size:13.5px;color:var(--muted)}
.links a{color:var(--accent2);text-decoration:none;font-weight:500}
.links a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-name">VoAnh</div>
    <div class="brand-sub">Créer un compte</div>
  </div>

  <?php if ($error): ?>
  <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label>Nom d'utilisateur</label>
      <input type="text" name="username" placeholder="monpseudo" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Email</label>
      <input type="email" name="email" placeholder="vous@exemple.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="Min. <?= PASSWORD_MIN_LENGTH ?> caractères" required>
    </div>
    <div class="field">
      <label>Confirmer</label>
      <input type="password" name="confirm" placeholder="Répétez le mot de passe" required>
    </div>

    <div class="perks">
      <p>Inclus</p>
      <ul>
        <li>20 modèles Mistral AI (Code, Vision, Audio…)</li>
        <li>Historique illimité des conversations</li>
        <li>Rotation automatique des clés API</li>
        <li>Interface style Claude.ai</li>
      </ul>
    </div>

    <button type="submit" class="btn">Créer mon compte</button>
  </form>

  <div class="links">
    Déjà inscrit ? <a href="login.php">Se connecter</a>
  </div>
</div>
</body>
</html>
