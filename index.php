<?php
/**
 * VoAnh - Interface principale (style Claude.ai)
 * Tout est à la racine — index.php
 */
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/auth.php';
require_once dirname(__FILE__) . '/database.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

// Conversations récentes si connecté
$recentConvs = [];
if ($user) {
    $db          = Database::getInstance();
    $recentConvs = $db->fetchAll(
        "SELECT id, title, model_used, updated_at FROM conversations 
         WHERE user_id = ? AND is_archived = 0 
         ORDER BY updated_at DESC LIMIT 30",
        [$user['id']]
    );
}

// Modèle par défaut sélectionné
$defaultModel = MASTER_AGENT_MODEL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VoAnh — Assistant IA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">

<style>
/* ============================================================
   RESET & ROOT
   ============================================================ */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg: #0a0a0f;
  --sidebar: #0f0f18;
  --card: #13131e;
  --surface: #18182a;
  --border: #1e1e30;
  --border2: #252538;
  --text: #e8e6f5;
  --muted: #5c5a72;
  --muted2: #8886a0;
  --accent: #7c6af5;
  --accent2: #a78bfa;
  --accent3: #c4b5fd;
  --user-bg: #1a1a2e;
  --user-border: #2a2a48;
  --ai-accent: #7c6af5;
  --success: #4ade80;
  --err: #f87171;
  --warn: #fbbf24;
  --sidebar-w: 260px;
  --input-h: 52px;
  --radius: 14px;
  --radius-sm: 8px;
}

html, body { height: 100%; overflow: hidden; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  line-height: 1.65;
  display: flex;
}

/* ============================================================
   SCROLLBAR
   ============================================================ */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }

/* ============================================================
   SIDEBAR
   ============================================================ */
.sidebar {
  width: var(--sidebar-w);
  min-width: var(--sidebar-w);
  height: 100vh;
  background: var(--sidebar);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: transform .3s ease;
  position: relative;
  z-index: 10;
}

.sidebar-top {
  padding: 20px 16px 12px;
  border-bottom: 1px solid var(--border);
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.brand-icon {
  width: 32px; height: 32px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}

.brand-name {
  font-family: 'DM Serif Display', serif;
  font-size: 19px;
  background: linear-gradient(135deg, var(--accent3), var(--accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.new-chat-btn {
  width: 100%;
  padding: 10px 14px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none;
  border-radius: var(--radius-sm);
  color: #fff;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: opacity .2s, transform .15s;
}
.new-chat-btn:hover { opacity: .9; transform: translateY(-1px); }
.new-chat-btn svg { width: 15px; height: 15px; flex-shrink: 0; }

/* History */
.sidebar-search {
  padding: 10px 16px;
}
.sidebar-search input {
  width: 100%;
  padding: 8px 12px;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: 13px;
  font-family: 'DM Sans', sans-serif;
  outline: none;
}
.sidebar-search input::placeholder { color: var(--muted); }
.sidebar-search input:focus { border-color: var(--accent); }

.conv-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px 8px;
}

.conv-section-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: var(--muted);
  padding: 10px 8px 4px;
  font-weight: 500;
}

.conv-item {
  display: flex;
  align-items: center;
  padding: 9px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background .15s;
  gap: 8px;
  position: relative;
  overflow: hidden;
  text-decoration: none;
  color: var(--muted2);
  font-size: 13.5px;
}
.conv-item:hover { background: var(--surface); color: var(--text); }
.conv-item.active { background: rgba(124,106,245,.15); color: var(--text); }
.conv-item .conv-title {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 13.5px;
}
.conv-item .conv-del {
  opacity: 0;
  color: var(--muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
  font-size: 14px;
  transition: opacity .15s, color .15s;
  flex-shrink: 0;
}
.conv-item:hover .conv-del { opacity: 1; }
.conv-item .conv-del:hover { color: var(--err); }

/* Sidebar footer */
.sidebar-footer {
  border-top: 1px solid var(--border);
  padding: 12px 10px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: var(--radius-sm);
  color: var(--muted2);
  text-decoration: none;
  font-size: 13.5px;
  transition: background .15s, color .15s;
}
.nav-link:hover { background: var(--surface); color: var(--text); }
.nav-link svg { width: 16px; height: 16px; flex-shrink: 0; }

.user-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  margin-top: 4px;
}
.user-avatar {
  width: 30px; height: 30px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  flex-shrink: 0;
}
.user-name { font-size: 13px; color: var(--muted2); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.logout-link {
  color: var(--muted);
  font-size: 18px;
  text-decoration: none;
  padding: 4px;
  border-radius: 4px;
  transition: color .15s;
}
.logout-link:hover { color: var(--err); }

/* ============================================================
   MAIN
   ============================================================ */
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  position: relative;
}

/* Ambient bg glow */
.main::before {
  content: '';
  position: absolute;
  top: -200px; left: 50%;
  transform: translateX(-50%);
  width: 700px; height: 500px;
  background: radial-gradient(ellipse, rgba(124,106,245,.08) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

/* ============================================================
   MESSAGES AREA
   ============================================================ */
.messages-wrap {
  flex: 1;
  overflow-y: auto;
  position: relative;
  z-index: 1;
}

.messages-inner {
  max-width: 740px;
  margin: 0 auto;
  padding: 40px 24px 20px;
}

/* Welcome screen */
.welcome {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
  padding: 60px 20px 20px;
  animation: fadeUp .5s ease;
}

.welcome-icon {
  width: 56px; height: 56px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
  margin-bottom: 20px;
  box-shadow: 0 0 40px rgba(124,106,245,.3);
}

.welcome h1 {
  font-family: 'DM Serif Display', serif;
  font-size: 34px;
  background: linear-gradient(135deg, var(--text) 40%, var(--muted2));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 10px;
  line-height: 1.2;
}

.welcome-sub {
  color: var(--muted2);
  font-size: 16px;
  margin-bottom: 44px;
  font-weight: 300;
}

.starters {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  width: 100%;
  max-width: 560px;
}

.starter-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 18px 14px;
  cursor: pointer;
  text-align: left;
  transition: border-color .2s, transform .2s, background .2s;
  position: relative;
  overflow: hidden;
}
.starter-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(124,106,245,.05), transparent);
  opacity: 0;
  transition: opacity .2s;
}
.starter-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.starter-card:hover::before { opacity: 1; }
.starter-emoji { font-size: 22px; display: block; margin-bottom: 10px; }
.starter-title { font-size: 13.5px; font-weight: 500; color: var(--text); margin-bottom: 4px; }
.starter-desc { font-size: 12px; color: var(--muted2); line-height: 1.5; }

/* Messages */
.msg {
  margin-bottom: 28px;
  animation: fadeUp .3s ease;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.msg-user {
  display: flex;
  justify-content: flex-end;
}

.msg-user .bubble {
  background: var(--user-bg);
  border: 1px solid var(--user-border);
  border-radius: var(--radius) var(--radius) 4px var(--radius);
  padding: 14px 18px;
  max-width: 80%;
  font-size: 15px;
  line-height: 1.65;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.msg-ai {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.ai-avatar {
  width: 32px; height: 32px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
  margin-top: 2px;
}

.ai-body { flex: 1; min-width: 0; }

.ai-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.ai-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--accent2);
}

.ai-model {
  font-size: 11px;
  color: var(--muted);
  background: rgba(124,106,245,.1);
  border: 1px solid rgba(124,106,245,.2);
  border-radius: 99px;
  padding: 2px 8px;
  font-family: monospace;
}

.ai-content {
  font-size: 15px;
  line-height: 1.75;
  color: var(--text);
  white-space: pre-wrap;
  word-wrap: break-word;
}

/* Code blocks */
.ai-content pre {
  background: #0d0d1a;
  border: 1px solid var(--border2);
  border-radius: 10px;
  padding: 16px 18px;
  overflow-x: auto;
  margin: 14px 0;
  font-size: 13px;
  line-height: 1.6;
  position: relative;
}
.ai-content code {
  font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
  font-size: 13px;
  color: #c9d1d9;
}
.ai-content p code {
  background: rgba(124,106,245,.12);
  border: 1px solid rgba(124,106,245,.2);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 13px;
  color: var(--accent3);
}
.ai-content p { margin-bottom: 10px; }
.ai-content ul, .ai-content ol { margin: 8px 0 8px 20px; }
.ai-content li { margin-bottom: 4px; }
.ai-content h1,.ai-content h2,.ai-content h3 { margin: 16px 0 8px; font-weight: 600; }
.ai-content h1 { font-size: 20px; }
.ai-content h2 { font-size: 17px; }
.ai-content h3 { font-size: 15px; }
.ai-content blockquote {
  border-left: 3px solid var(--accent);
  padding-left: 16px;
  margin: 12px 0;
  color: var(--muted2);
  font-style: italic;
}
.ai-content strong { color: var(--accent3); font-weight: 500; }
.ai-content table { border-collapse: collapse; width: 100%; margin: 12px 0; font-size: 13.5px; }
.ai-content th { background: var(--surface); padding: 8px 12px; border: 1px solid var(--border2); text-align: left; font-weight: 500; color: var(--accent3); }
.ai-content td { padding: 8px 12px; border: 1px solid var(--border2); }

/* Thinking indicator */
.thinking {
  display: flex;
  gap: 5px;
  align-items: center;
  padding: 6px 0;
}
.thinking span {
  width: 7px; height: 7px;
  background: var(--accent);
  border-radius: 50%;
  animation: blink 1.4s ease infinite;
}
.thinking span:nth-child(2) { animation-delay: .2s; }
.thinking span:nth-child(3) { animation-delay: .4s; }

@keyframes blink {
  0%, 80%, 100% { opacity: .25; transform: scale(.8); }
  40% { opacity: 1; transform: scale(1); }
}

/* ============================================================
   INPUT AREA
   ============================================================ */
.input-wrap {
  position: relative;
  z-index: 2;
  padding: 16px 24px 20px;
  background: linear-gradient(to top, var(--bg) 60%, transparent);
}

.input-inner {
  max-width: 740px;
  margin: 0 auto;
}

/* Model selector */
.model-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.model-label {
  font-size: 11px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .7px;
  flex-shrink: 0;
}

.model-select {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: 12.5px;
  font-family: 'DM Sans', sans-serif;
  padding: 5px 10px;
  cursor: pointer;
  outline: none;
  max-width: 320px;
  transition: border-color .2s;
}
.model-select:focus { border-color: var(--accent); }
.model-select option, .model-select optgroup { background: #13131e; }

/* Input box */
.input-box {
  background: var(--card);
  border: 1px solid var(--border2);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color .2s, box-shadow .2s;
  box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.input-box:focus-within {
  border-color: var(--accent);
  box-shadow: 0 4px 24px rgba(124,106,245,.15);
}

#msg-input {
  width: 100%;
  min-height: 56px;
  max-height: 200px;
  padding: 16px 18px 8px;
  background: transparent;
  border: none;
  color: var(--text);
  font-size: 15px;
  font-family: 'DM Sans', sans-serif;
  line-height: 1.6;
  resize: none;
  outline: none;
  overflow-y: auto;
}
#msg-input::placeholder { color: var(--muted); }

.input-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px 12px;
  gap: 8px;
}

.quick-btns {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.quick-btn {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 99px;
  color: var(--muted2);
  font-size: 12px;
  font-family: 'DM Sans', sans-serif;
  padding: 5px 12px;
  cursor: pointer;
  transition: all .2s;
  white-space: nowrap;
}
.quick-btn:hover {
  background: rgba(124,106,245,.12);
  border-color: var(--accent);
  color: var(--accent2);
}

.send-btn {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none;
  border-radius: var(--radius-sm);
  color: #fff;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: opacity .2s, transform .15s;
}
.send-btn:hover { opacity: .9; transform: scale(1.05); }
.send-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }
.send-btn svg { width: 16px; height: 16px; }

.input-hint {
  text-align: center;
  font-size: 11.5px;
  color: var(--muted);
  margin-top: 10px;
}

/* Upload & Download */
.upload-btn {
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--muted2);
  padding: 6px 10px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all .2s;
  font-family: 'DM Sans', sans-serif;
}
.upload-btn:hover { border-color: var(--accent); color: var(--accent2); background: rgba(124,106,245,.08); }

.file-preview {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: rgba(124,106,245,.1);
  border: 1px solid rgba(124,106,245,.2);
  border-radius: var(--radius-sm);
  font-size: 12.5px;
  color: var(--accent2);
}
.file-preview .remove-file {
  cursor: pointer;
  color: var(--muted);
  font-size: 15px;
  line-height: 1;
  margin-left: auto;
}
.file-preview .remove-file:hover { color: var(--err); }

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  padding: 7px 14px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none;
  border-radius: var(--radius-sm);
  color: #fff;
  font-size: 13px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: opacity .2s;
}
.download-btn:hover { opacity: .85; }

/* ============================================================
   MOBILE TOGGLE
   ============================================================ */
.sidebar-toggle {
  display: none;
  position: fixed;
  top: 14px; left: 14px;
  z-index: 100;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 8px;
  cursor: pointer;
  color: var(--muted2);
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    transform: translateX(-100%);
    z-index: 50;
  }
  .sidebar.open { transform: translateX(0); }
  .main { width: 100%; }
  .sidebar-toggle { display: flex; }
  .starters { grid-template-columns: 1fr; }
  .messages-inner { padding: 60px 14px 20px; }
  .input-wrap { padding: 10px 14px 16px; }
  .model-select { max-width: 220px; }
}
</style>
</head>
<body>

<!-- Sidebar Toggle (mobile) -->
<button class="sidebar-toggle" id="sidebar-toggle" aria-label="Menu" onclick="toggleSidebar()">
  <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
    <path fill-rule="evenodd" d="M3 5h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2z" clip-rule="evenodd"/>
  </svg>
</button>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">

  <div class="sidebar-top">
    <div class="brand">
      <div class="brand-icon">✦</div>
      <span class="brand-name">VoAnh</span>
    </div>
    <button class="new-chat-btn" onclick="newChat()">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
      Nouvelle conversation
    </button>
  </div>

  <?php if ($user): ?>
  <div class="sidebar-search">
    <input type="text" id="conv-search" placeholder="Rechercher…" oninput="filterConvs(this.value)">
  </div>

  <div class="conv-list" id="conv-list">
    <?php if ($recentConvs): ?>
    <div class="conv-section-label">Récents</div>
    <?php foreach ($recentConvs as $c): ?>
    <div class="conv-item" id="conv-<?= (int)$c['id'] ?>" data-id="<?= (int)$c['id'] ?>" onclick="loadConversation(<?= (int)$c['id'] ?>)">
      <span class="conv-title"><?= htmlspecialchars($c['title'] ?: 'Conversation') ?></span>
      <button class="conv-del" title="Supprimer" onclick="event.stopPropagation(); deleteConversation(<?= (int)$c['id'] ?>)">×</button>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="padding:16px 8px;font-size:13px;color:var(--muted);text-align:center">Aucune conversation</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="sidebar-footer">
    <a href="settings.php" class="nav-link">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
      Paramètres
    </a>

    <?php if ($user): ?>
    <div class="user-pill">
      <div class="user-avatar"><?= strtoupper(mb_substr($user['username'], 0, 2)) ?></div>
      <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
      <a href="logout.php" class="logout-link" title="Déconnexion">⇠</a>
    </div>
    <?php else: ?>
    <a href="login.php" class="nav-link" style="background:rgba(124,106,245,.1);border:1px solid rgba(124,106,245,.2);justify-content:center;color:var(--accent2)">
      Se connecter
    </a>
    <a href="register.php" class="nav-link" style="justify-content:center;margin-top:6px">
      Créer un compte
    </a>
    <?php endif; ?>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="main">

  <!-- Messages -->
  <div class="messages-wrap" id="messages-wrap">
    <div class="messages-inner" id="messages-inner">

      <!-- Welcome screen -->
      <div id="welcome" class="welcome">
        <div class="welcome-icon">✦</div>
        <h1>Bonjour<?= $user ? ', ' . htmlspecialchars($user['username']) : '' ?></h1>
        <p class="welcome-sub">Comment puis-je vous aider aujourd'hui ?</p>

        <div class="starters">
          <div class="starter-card" onclick="setPrompt('Explique-moi comment fonctionne une architecture microservices et donne un exemple concret.')">
            <span class="starter-emoji">🏗️</span>
            <div class="starter-title">Expliquer un concept</div>
            <div class="starter-desc">Architecture microservices, patterns de conception…</div>
          </div>
          <div class="starter-card" onclick="setPrompt('Écris un script Python pour analyser un fichier CSV et générer des statistiques descriptives.')">
            <span class="starter-emoji">💻</span>
            <div class="starter-title">Générer du code</div>
            <div class="starter-desc">Python, JavaScript, PHP, SQL…</div>
          </div>
          <div class="starter-card" onclick="setPrompt('Rédige un email professionnel pour demander un report de deadline en restant positif et constructif.')">
            <span class="starter-emoji">✍️</span>
            <div class="starter-title">Rédiger un texte</div>
            <div class="starter-desc">Email, rapport, résumé, article…</div>
          </div>
          <div class="starter-card" onclick="setPrompt('Décompose ce projet en sous-tâches et crée un plan de développement structuré : Créer une application de gestion de bibliothèque.')">
            <span class="starter-emoji">📋</span>
            <div class="starter-title">Planifier un projet</div>
            <div class="starter-desc">Roadmap, sprints, architecture…</div>
          </div>
        </div>
      </div>

      <!-- Messages list -->
      <div id="messages-list"></div>

    </div>
  </div>

  <!-- Input area -->
  <div class="input-wrap">
    <div class="input-inner">

      <!-- Model selector -->
      <div class="model-bar">
        <span class="model-label">Modèle</span>
        <select class="model-select" id="model-select">
          <?php foreach (MISTRAL_MODELS as $cat => $models): ?>
          <optgroup label="<?= ucfirst($cat) ?>">
            <?php foreach ($models as $m): ?>
            <option value="<?= htmlspecialchars($m['id']) ?>" <?= $m['id'] === $defaultModel ? 'selected' : '' ?>>
              <?= htmlspecialchars($m['name']) ?> — <?= htmlspecialchars($m['desc']) ?>
            </option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Textarea + actions -->
      <!-- File preview -->
      <div id="file-preview-bar" style="display:none;margin-bottom:8px"></div>
      <input type="file" id="file-input" accept="image/*,.pdf" style="display:none" onchange="handleFileSelect(this)">

      <div class="input-box">
        <textarea
          id="msg-input"
          placeholder="Posez votre question… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)"
          rows="1"
          oninput="autoResize(this)"
          onkeydown="handleKey(event)"
        ></textarea>
        <div class="input-actions">
          <div class="quick-btns">
            <button class="upload-btn" onclick="document.getElementById('file-input').click()" title="Joindre un fichier">
              📎 Joindre
            </button>
            <button class="quick-btn" onclick="setPrompt('Explique en détail : ')">💡 Expliquer</button>
            <button class="quick-btn" onclick="setPrompt('Génère le code pour : ')">💻 Coder</button>
            <button class="quick-btn" onclick="setPrompt('Analyse ce texte : ')">🔍 Analyser</button>
            <button class="quick-btn" onclick="setPrompt('Planifie et décompose : ')">📋 Planifier</button>
          </div>
          <button class="send-btn" id="send-btn" onclick="sendMessage()" disabled title="Envoyer">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="input-hint">VoAnh peut faire des erreurs. Vérifiez les informations importantes.</div>
    </div>
  </div>
</main>

<script>
// ============================================================
// STATE
// ============================================================
let currentConvId = null;
let isBusy = false;
let isLoggedIn = <?= $user ? 'true' : 'false' ?>;

// ============================================================
// FILE UPLOAD
// ============================================================
let currentFile = null;

function handleFileSelect(input) {
  const file = input.files[0];
  if (!file) return;

  const bar = document.getElementById('file-preview-bar');
  bar.style.display = 'flex';
  bar.innerHTML = `
    <div class="file-preview">
      <span>${file.type.startsWith('image/') ? '🖼️' : '📄'} ${escHtml(file.name)}</span>
      <span class="remove-file" onclick="removeFile()">×</span>
    </div>
  `;

  // Lire le fichier
  const reader = new FileReader();
  reader.onload = function(e) {
    const base64 = e.target.result.split(',')[1];
    currentFile = { name: file.name, mime: file.type, base64: base64 };
  };
  reader.readAsDataURL(file);

  // Activer le bouton envoyer
  document.getElementById('send-btn').disabled = false;
}

function removeFile() {
  currentFile = null;
  document.getElementById('file-input').value = '';
  document.getElementById('file-preview-bar').style.display = 'none';
  const inp = document.getElementById('msg-input');
  document.getElementById('send-btn').disabled = inp.value.trim() === '';
}

// ============================================================
// AUTO RESIZE TEXTAREA
// ============================================================
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 200) + 'px';
  document.getElementById('send-btn').disabled = el.value.trim() === '';
}

// ============================================================
// SIDEBAR
// ============================================================
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

document.addEventListener('click', e => {
  const sb = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebar-toggle');
  if (window.innerWidth <= 768 && sb.classList.contains('open') &&
      !sb.contains(e.target) && !toggle.contains(e.target)) {
    sb.classList.remove('open');
  }
});

// ============================================================
// FILTER CONVERSATIONS
// ============================================================
function filterConvs(q) {
  const items = document.querySelectorAll('.conv-item');
  const lq = q.toLowerCase();
  items.forEach(el => {
    const t = el.querySelector('.conv-title').textContent.toLowerCase();
    el.style.display = (!q || t.includes(lq)) ? '' : 'none';
  });
}

// ============================================================
// SET PROMPT
// ============================================================
function setPrompt(text) {
  const el = document.getElementById('msg-input');
  el.value = text;
  el.focus();
  autoResize(el);
  el.setSelectionRange(text.length, text.length);
}

// ============================================================
// NEW CHAT
// ============================================================
function newChat() {
  currentConvId = null;
  document.getElementById('messages-list').innerHTML = '';
  document.getElementById('welcome').style.display = 'flex';
  document.getElementById('msg-input').value = '';
  document.getElementById('send-btn').disabled = true;
  document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
  if (window.innerWidth <= 768) document.getElementById('sidebar').classList.remove('open');
}

// ============================================================
// LOAD CONVERSATION
// ============================================================
async function loadConversation(id) {
  if (isBusy) return;

  currentConvId = id;
  document.getElementById('welcome').style.display = 'none';
  document.getElementById('messages-list').innerHTML = '';

  document.querySelectorAll('.conv-item').forEach(el => {
    el.classList.toggle('active', el.dataset.id == id);
  });

  if (window.innerWidth <= 768) document.getElementById('sidebar').classList.remove('open');

  try {
    const resp = await fetch(`conversations.php?action=messages&id=${id}`);
    const data = await resp.json();

    if (data.success && data.messages) {
      data.messages.forEach(m => {
        if (m.role === 'user') appendUserMsg(m.content);
        else if (m.role === 'assistant') appendAiMsg(m.content, m.model_used || '');
      });
      scrollBottom();
    }
  } catch(e) {
    console.error('Load conv error:', e);
  }
}

// ============================================================
// DELETE CONVERSATION
// ============================================================
async function deleteConversation(id) {
  if (!confirm('Supprimer cette conversation ?')) return;

  try {
    const resp = await fetch('conversations.php?action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await resp.json();
    if (data.success) {
      const el = document.getElementById(`conv-${id}`);
      if (el) el.remove();
      if (currentConvId === id) newChat();
    }
  } catch(e) {}
}

// ============================================================
// SEND MESSAGE
// ============================================================
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

async function sendMessage() {
  if (isBusy) return;

  const input  = document.getElementById('msg-input');
  const msg    = input.value.trim();
  const model  = document.getElementById('model-select').value;

  if (!msg) return;

  isBusy = true;
  document.getElementById('send-btn').disabled = true;
  input.disabled = true;

  // Hide welcome
  document.getElementById('welcome').style.display = 'none';

  // Append user message (si pas de fichier joint, sinon déjà fait)
  if (!currentFile) appendUserMsg(msg);
  input.value = '';
  input.style.height = 'auto';

  // Thinking indicator
  const thinkId = appendThinking();
  scrollBottom();

  // Afficher le fichier joint dans la bulle utilisateur
  if (currentFile) {
    const fileInfo = currentFile;
    appendUserMsg(msg, fileInfo);
  }

  try {
    const body = {
      message: msg,
      model: model,
      conversation_id: currentConvId,
    };
    if (currentFile) {
      body.file_base64 = currentFile.base64;
      body.file_mime = currentFile.mime;
      body.file_name = currentFile.name;
      removeFile();
    }

    const resp = await fetch('chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    const data = await resp.json();
    removeThinking(thinkId);

    if (data.success) {
      appendAiMsg(data.content, data.model);
      if (data.conversation_id && currentConvId !== data.conversation_id) {
        currentConvId = data.conversation_id;
        addConvToSidebar(data.conversation_id, msg);
      }
    } else {
      appendAiMsg('⚠️ ' + (data.error || 'Erreur inconnue'), model, true);
    }

  } catch(e) {
    removeThinking(thinkId);
    appendAiMsg('⚠️ Erreur de connexion : ' + e.message, model, true);
  }

  isBusy = false;
  input.disabled = false;
  input.focus();
  scrollBottom();
}

// ============================================================
// RENDER HELPERS
// ============================================================
function appendUserMsg(text, file) {
  const list = document.getElementById('messages-list');
  const div  = document.createElement('div');
  div.className = 'msg msg-user';
  let fileHtml = '';
  if (file) {
    if (file.mime.startsWith('image/')) {
      fileHtml = `<img src="data:${file.mime};base64,${file.base64}" style="max-width:300px;max-height:200px;border-radius:8px;margin-bottom:8px;display:block">`;
    } else {
      fileHtml = `<div style="font-size:12px;opacity:.7;margin-bottom:6px">📄 ${escHtml(file.name)}</div>`;
    }
  }
  div.innerHTML = `<div class="bubble">${fileHtml}${text ? escHtml(text) : ''}</div>`;
  list.appendChild(div);
}

function appendAiMsg(text, model, isErr = false) {
  const list = document.getElementById('messages-list');
  const div  = document.createElement('div');
  div.className = 'msg msg-ai';

  const modelShort = model ? model.split('-').slice(0,2).join('-') : 'voanh';

  // Détecter si la réponse contient du HTML ou autre code téléchargeable
  const htmlMatch = text.match(/```html\n([\s\S]*?)```/);
  const cssMatch  = text.match(/```css\n([\s\S]*?)```/);
  const jsMatch   = text.match(/```javascript\n([\s\S]*?)```/);

  let downloadBtn = '';
  if (htmlMatch) {
    const code = htmlMatch[1];
    downloadBtn = `<button class="download-btn" onclick="downloadCode(${JSON.stringify(code)}, 'site.html', 'text/html')">⬇️ Télécharger le fichier HTML</button>`;
  } else if (cssMatch) {
    const code = cssMatch[1];
    downloadBtn = `<button class="download-btn" onclick="downloadCode(${JSON.stringify(code)}, 'style.css', 'text/css')">⬇️ Télécharger le CSS</button>`;
  } else if (jsMatch) {
    const code = jsMatch[1];
    downloadBtn = `<button class="download-btn" onclick="downloadCode(${JSON.stringify(code)}, 'script.js', 'text/javascript')">⬇️ Télécharger le JS</button>`;
  }

  div.innerHTML = `
    <div class="ai-avatar">✦</div>
    <div class="ai-body">
      <div class="ai-meta">
        <span class="ai-name">VoAnh</span>
        ${model ? `<span class="ai-model">${escHtml(modelShort)}</span>` : ''}
      </div>
      <div class="ai-content ${isErr ? 'err-content' : ''}">${renderMarkdown(text)}</div>
      ${downloadBtn}
    </div>
  `;
  list.appendChild(div);
}

function downloadCode(code, filename, mime) {
  const blob = new Blob([code], { type: mime + ';charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function appendThinking() {
  const list = document.getElementById('messages-list');
  const id   = 'think-' + Date.now();
  const div  = document.createElement('div');
  div.id        = id;
  div.className = 'msg msg-ai';
  div.innerHTML = `
    <div class="ai-avatar">✦</div>
    <div class="ai-body">
      <div class="thinking">
        <span></span><span></span><span></span>
      </div>
    </div>
  `;
  list.appendChild(div);
  return id;
}

function removeThinking(id) {
  const el = document.getElementById(id);
  if (el) el.remove();
}

function scrollBottom() {
  const wrap = document.getElementById('messages-wrap');
  wrap.scrollTop = wrap.scrollHeight;
}

function addConvToSidebar(id, title) {
  if (!isLoggedIn) return;
  const list = document.getElementById('conv-list');
  if (!list) return;

  // Remove "no convs" message if present
  const empty = list.querySelector('[data-empty]');
  if (empty) empty.remove();

  // Check label
  let label = list.querySelector('.conv-section-label');
  if (!label) {
    label = document.createElement('div');
    label.className = 'conv-section-label';
    label.textContent = 'Récents';
    list.insertBefore(label, list.firstChild);
  }

  // Check not already there
  if (document.getElementById(`conv-${id}`)) return;

  const shortTitle = title.length > 40 ? title.slice(0, 40) + '…' : title;
  const div = document.createElement('div');
  div.id        = `conv-${id}`;
  div.dataset.id = id;
  div.className  = 'conv-item active';
  div.innerHTML  = `
    <span class="conv-title">${escHtml(shortTitle)}</span>
    <button class="conv-del" title="Supprimer" onclick="event.stopPropagation(); deleteConversation(${id})">×</button>
  `;
  div.onclick = () => loadConversation(id);

  // Remove active from others
  document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));

  list.insertBefore(div, label.nextSibling);
}

// ============================================================
// MARKDOWN RENDERER (simple)
// ============================================================
function renderMarkdown(text) {
  // Escape HTML first
  let html = escHtml(text);

  // Code blocks ```lang\n...\n```
  html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
    return `<pre><code class="lang-${lang}">${code.trim()}</code></pre>`;
  });

  // Inline code `...`
  html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');

  // Headers
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

  // Bold + italic
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

  // Strikethrough
  html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');

  // Blockquote
  html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');

  // Unordered list
  html = html.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
  html = html.replace(/(<li>.*<\/li>\n?)+/g, m => `<ul>${m}</ul>`);

  // Ordered list
  html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

  // Horizontal rule
  html = html.replace(/^---+$/gm, '<hr>');

  // Line breaks → paragraphs (skip if inside block elements)
  html = html.replace(/\n\n+/g, '</p><p>');
  html = html.replace(/\n/g, '<br>');
  html = '<p>' + html + '</p>';

  // Clean up empty paragraphs around block elements
  html = html.replace(/<p>\s*(<(?:pre|ul|ol|blockquote|h[1-6]|hr)[^>]*>)/g, '$1');
  html = html.replace(/(<\/(?:pre|ul|ol|blockquote|h[1-6]|hr)>)\s*<\/p>/g, '$1');
  html = html.replace(/<p><br><\/p>/g, '');
  html = html.replace(/<p>\s*<\/p>/g, '');

  return html;
}

// ============================================================
// UTILS
// ============================================================
function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Init: enable/disable send button
document.addEventListener('DOMContentLoaded', () => {
  const inp = document.getElementById('msg-input');
  if (inp) {
    inp.addEventListener('input', () => {
      document.getElementById('send-btn').disabled = inp.value.trim() === '' || isBusy;
    });
  }
});
</script>
</body>
</html>
