<?php
/**
 * VoAnh - Paramètres utilisateur
 */
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/auth.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$user    = $auth->getCurrentUser();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_api_key') {
        $key = trim($_POST['api_key'] ?? '');
        $auth->updateApiKey($user['id'], $key);
        $success = 'Clé API mise à jour';
        $user    = $auth->getCurrentUser();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paramètres — VoAnh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0a0a0f;--card:#13131a;--border:#1e1e2e;--text:#e8e6f0;--muted:#6b6880;--accent:#7c6af5;--accent2:#a78bfa;--err:#f87171;--success:#4ade80}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;padding:40px 20px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 40% at 50% -10%,rgba(124,106,245,.12),transparent);pointer-events:none;z-index:0}
.wrap{max-width:640px;margin:0 auto;position:relative;z-index:1}
.back{display:inline-flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-size:14px;margin-bottom:28px;transition:.2s}
.back:hover{color:var(--text)}
h1{font-family:'DM Serif Display',serif;font-size:28px;margin-bottom:32px;background:linear-gradient(135deg,var(--text),var(--muted));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:20px}
.card h2{font-size:14px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:20px;font-weight:500}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:7px}
.field input{width:100%;padding:12px 16px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;transition:.2s}
.field input:focus{outline:none;border-color:var(--accent)}
.field .hint{font-size:12px;color:var(--muted);margin-top:6px}
.btn{padding:11px 20px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:.2s}
.btn:hover{opacity:.9}
.msg-ok{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:var(--success);padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:20px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.info-row:last-child{border-bottom:none}
.info-row .lbl{font-size:13px;color:var(--muted)}
.info-row .val{font-size:13px;color:var(--text);font-weight:500}
</style>
</head>
<body>
<div class="wrap">
  <a href="index.php" class="back">← Retour au chat</a>
  <h1>Paramètres</h1>

  <?php if ($success): ?>
  <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Infos compte -->
  <div class="card">
    <h2>Compte</h2>
    <div class="info-row">
      <span class="lbl">Nom d'utilisateur</span>
      <span class="val"><?= htmlspecialchars($user['username']) ?></span>
    </div>
    <div class="info-row">
      <span class="lbl">Email</span>
      <span class="val"><?= htmlspecialchars($user['email']) ?></span>
    </div>
    <div class="info-row">
      <span class="lbl">Membre depuis</span>
      <span class="val"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
    </div>
    <div class="info-row">
      <span class="lbl">Dernière connexion</span>
      <span class="val"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'N/A' ?></span>
    </div>
  </div>

  <!-- Clé API personnelle -->
  <div class="card">
    <h2>Clé API Mistral personnelle (optionnel)</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update_api_key">
      <div class="field">
        <label>Votre clé API Mistral</label>
        <input type="password" name="api_key" placeholder="Laissez vide pour utiliser les clés partagées" value="<?= htmlspecialchars($user['mistral_api_key'] ?? '') ?>">
        <div class="hint">Si vous avez votre propre clé Mistral, elle sera prioritaire sur les clés partagées du système.</div>
      </div>
      <button type="submit" class="btn">Sauvegarder</button>
    </form>
  </div>

  <!-- Modèles disponibles -->
  <div class="card">
    <h2>Modèles disponibles (<?= array_sum(array_map('count', MISTRAL_MODELS)) ?>)</h2>
    <?php foreach (MISTRAL_MODELS as $cat => $models): ?>
    <div style="margin-bottom:14px">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:8px"><?= ucfirst($cat) ?></div>
      <?php foreach ($models as $m): ?>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04)">
        <span style="font-size:13px;color:var(--text)"><?= htmlspecialchars($m['name']) ?></span>
        <span style="font-size:11px;color:var(--muted);font-family:monospace"><?= htmlspecialchars($m['id']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
