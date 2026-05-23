# VoAnh — Assistant IA (Clone Claude.ai avec Mistral)

## Structure des fichiers (TOUT à la racine)

```
votre-domaine.com/
├── index.php          ← Interface principale (style Claude.ai)
├── chat.php           ← API : envoi des messages
├── conversations.php  ← API : liste/détail/suppression conversations
├── login.php          ← Page connexion
├── register.php       ← Page inscription
├── logout.php         ← Déconnexion
├── settings.php       ← Paramètres utilisateur + clé API
├── config.php         ← Configuration globale
├── database.php       ← Classe SQLite
├── auth.php           ← Authentification
├── mistral.php        ← Client API Mistral
├── .htaccess          ← Sécurité Apache
└── data/
    ├── .htaccess      ← Bloque l'accès direct
    └── voanh.sqlite   ← Créé automatiquement au premier accès
```

## Déploiement sur Hostinger

1. Uploadez **tous les fichiers** via FTP dans `public_html/`
2. Vérifiez que le dossier `data/` a les permissions **0755**
3. Ouvrez `https://votre-domaine.com/` — la BDD SQLite est créée automatiquement
4. Créez votre compte via "S'inscrire"

## Configuration

Éditez `config.php` pour mettre vos vraies clés API Mistral :
```php
define('DEFAULT_MISTRAL_API_KEYS', [
    'votre_cle_1',
    'votre_cle_2',
    'votre_cle_3'
]);
```

## Fonctionnalités

- ✅ Interface style Claude.ai (sidebar + chat + historique)
- ✅ 20 modèles Mistral (flagship, code, vision, audio, edge…)
- ✅ Rotation automatique des clés API (rate limit → clé suivante)
- ✅ Historique des conversations par utilisateur
- ✅ Authentification sécurisée (bcrypt, sessions DB)
- ✅ Possibilité d'ajouter sa propre clé API Mistral dans Settings
- ✅ Rendu Markdown (code, titres, listes, tableaux, citations)
- ✅ Compatible Hostinger mutualisé (PHP 8.3, cURL only, SQLite, permissions 0755)
- ✅ Responsive mobile

## Compatibilité Hostinger

- ✅ Pas de `exec/shell_exec/system` — cURL exclusivement
- ✅ Pas de `putenv` — config PHP pur
- ✅ Chemins via `dirname(__FILE__)` — jamais de chemins absolus codés en dur
- ✅ Permissions 0755 pour les dossiers, 0644 pour les fichiers
- ✅ Pas de `file_get_contents` pour les URLs — cURL avec USERAGENT et TIMEOUT
- ✅ SQLite (pas besoin de MySQL séparé)
