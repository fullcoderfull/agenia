<?php
// =============================================================================
// login.php — Connexion Agenia + présentation + essai libre
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

ini_set('display_errors', '0');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/license.php';
license_enforce();
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/auth_guest.php';
$_vox_users = require __DIR__ . '/config/users.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/debug.php';

// ── Résolution de langue : URL → cookie → UI_LANG → 'fr' ────────────────────
$_lang = $_GET['lang'] ?? ($_COOKIE['vox_lang'] ?? (defined('UI_LANG') ? UI_LANG : 'fr'));
$_lang = in_array($_lang, ['fr', 'en'], true) ? $_lang : 'fr';
if (isset($_GET['lang'])) {
  setcookie('vox_lang', $_lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}
$lang  = $_lang;   // alias : compat avec le reste du fichier
$_isFr = $_lang === 'fr';

$error = '';

// ── Déjà connecté → redirect (HMAC, format identique à check.php) ───────────
if (isset($_COOKIE['vox'])) {
  $decoded = base64_decode($_COOKIE['vox'], true);
  if ($decoded) {
    $parts = explode('|', $decoded);
    if (count($parts) === 3) {
      [$user, $exp, $sig] = $parts;
      if (time() < (int)$exp
          && hash_equals(hash_hmac('sha256', $user . '|' . $exp, HMAC_SECRET), $sig)) {
        header('Location: /index.php'); exit;
      }
    }
  }
}
if (isset($_COOKIE['vox_guest'])) { header('Location: /index.php'); exit; }

// ── Messages d'erreur traduits ──────────────────────────────────────────────
$errorMessages = [
  'fr' => [
    'invalid'        => 'Identifiants incorrects.',
    'rate_limit'     => 'Trop de tentatives. Réessayez dans quelques minutes.',
    'blocked'        => 'Accès temporairement suspendu.',
    'invite_invalid' => 'Lien d\'invitation invalide ou expiré.',
    'ip_blocked'     => 'Accès temporairement suspendu suite à une session expirée.',
    'max_connections_reached' => 'Nombres d\'utilisateurs maximum atteind- creer un compte',
    'db_error'       => 'System d\'information indisponible',
  ],
  'en' => [
    'invalid'        => 'Invalid credentials.',
    'rate_limit'     => 'Too many attempts. Please try again in a few minutes.',
    'blocked'        => 'Access temporarily suspended.',
    'invite_invalid' => 'Invalid or expired invitation link.',
    'ip_blocked'     => 'Access temporarily suspended after session expiry.',
    'max_connections_reached' => 'Max connections reached - subscribe',
    'db_error'       => 'Systeme unavailable'
  ],
];

// ── Erreur depuis guest-init.php ────────────────────────────────────────────
if (isset($_GET['error'])) {
  $errKey = $_GET['error'];
  $error  = $errorMessages[$lang][$errKey] ?? $errorMessages[$lang][errKey];
  if ($errKey === 'blocked' && isset($_GET['wait'])) {
    $wait  = (int)$_GET['wait'];
    $error .= $lang === 'fr' ? " Réessayez dans {$wait} minutes." : " Try again in {$wait} minutes.";
  }
}

// ── POST — traitement login (email OU pseudo) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login ="";
  $subscriber = [];
  try {
      $login = trim($_POST['login'] ?? '');
      $db = db();
      
      $subscriber = db_rows('SELECT id FROM waitlist_subscribers WHERE email = ? LIMIT 1', [$login]);
      $result = db_rows('SELECT COUNT(*) as count FROM agenia_users_sessions WHERE created_at < NOW() + INTERVAL 1 HOUR AND  	subscriber_id = ? LIMIT 1', [$subscriber[0]["id"]]);
      //header('Location: /login.php?error=ip_blocked'.$subscriber.$result['count'] );exit;
      // $stmt->execute();
      // $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $activeSessions = $result['count'];
      $settings = json_decode(file_get_contents(__DIR__ . '/config/settings.json'), true);
      $maxConnections = (int)($settings['user']['maxConnections'] ?? 5); // Définissez la limite de connexions max

      if ($activeSessions >= $maxConnections) {
          debug_log('USER_INIT', 'MAX_CONNECTIONS_REACHED', ['activeSessions' => $activeSessions, 'maxConnections' => $maxConnections]);
          header('Location: /login.php?error=ip_blocked');
          exit;
      }
      } catch (Exception $e) {
          debug_log('USER_INIT', 'CONNECTION_CHECK_FAILED', ['err' => $e->getMessage()]);
          header('Location: /login.php?error=db_error&info='.$login);
          exit;
      }

  $login = trim($_POST['login'] ?? '');
  $pass  = $_POST['password'] ?? '';
  usleep(300000);

  // Résolution identifiant → clé réelle de $_vox_users
  $key = null;
  if (isset($_vox_users[$login])) {
    $key = $login;                          // pseudo direct (mike, demo) OU email-clé (inscrits)
  } else {
    foreach ($_vox_users as $k => $u) {     // sinon recherche par email
      if (!empty($u['email']) && strcasecmp($u['email'], $login) === 0) { $key = $k; break; }
    }
  }

  if ($key !== null && password_verify($pass, $_vox_users[$key]['password'])) {
    $exp   = time() + SESSION_TTL;
    // On signe et stocke la CLÉ ($key), pas la saisie — sinon check.php rejette
    $sig   = hash_hmac('sha256', $key . '|' . $exp, HMAC_SECRET);
    $token = base64_encode($key . '|' . $exp . '|' . $sig);
    setcookie('vox', $token, [
      'expires' => $exp, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
    ]);
   // ── IP réelle ─────────────────────────────────────────────────────────────────
   $ip = get_client_ip();
   
   $result = session_user_create($ip, $subscriber[0]["id"]);

    header('Location: /index.php'); exit;
  }
  $error = $errorMessages[$lang]['invalid'];
}

// ── Lien d'invitation ───────────────────────────────────────────────────────
$inviteParam = isset($_GET['invite']) ? '?invite=' . urlencode($_GET['invite']) : '';

// ── i18n inline ─────────────────────────────────────────────────────────────
$t = [
  'fr' => [
    'title'=>'Agenia — Connexion','tagline'=>'L\'interface LLM locale et cloud pour les professionnels',
    'desc'=>'Connectez vos modèles d\'IA locaux et cloud dans une interface unifiée, sécurisée et personnalisable.',
    'feat1'=>'Modèles locaux & cloud','feat1d'=>'Ollama, OpenAI, Anthropic, Mistral, Groq et plus',
    'feat2'=>'Voix & Vision','feat2d'=>'TTS, STT et webcam intégrés nativement',
    'feat3'=>'Multi-utilisateurs','feat3d'=>'Rôles, quotas et prompts personnalisés',
    'login_title'=>'Connexion','username'=>'Email','password'=>'Mot de passe','submit'=>'→ CONNEXION',
    'or'=>'ou','trial_btn'=>'⚡ Essai libre — 20 min','trial_sub'=>'Sans inscription · Accès complet · 10 messages',
    'genie'=>'🧞 Créer mon compte','footer'=>'Agenia · Interface LLM locale','watch'=>'Voir la démo',
  ],
  'en' => [
    'title'=>'Agenia — Login','tagline'=>'The local & cloud LLM interface for professionals',
    'desc'=>'Connect your local and cloud AI models in a unified, secure and customisable interface.',
    'feat1'=>'Local & cloud models','feat1d'=>'Ollama, OpenAI, Anthropic, Mistral, Groq and more',
    'feat2'=>'Voice & Vision','feat2d'=>'TTS, STT and webcam natively integrated',
    'feat3'=>'Multi-user','feat3d'=>'Roles, quotas and custom prompts',
    'login_title'=>'Sign in','username'=>'Email','password'=>'Password','submit'=>'→ LOGIN',
    'or'=>'or','trial_btn'=>'⚡ Free trial — 20 min','trial_sub'=>'No sign-up · Full access · 10 messages',
    'genie'=>'🧞 Create my account','footer'=>'Agenia · Local LLM Interface','watch'=>'Watch demo',
  ],
][$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($t['title']) ?></title>
<script>
(function(){
  var s = localStorage.getItem('vox-theme');
  if (!s) s = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', s);
})();
</script>
<style>
  :root{
    --bg:#0a0a0f;--surface:#12121a;--surface2:#1a1a26;--border:#2a2a3e;
    --accent:#7c3aed;--accent2:#06b6d4;--text:#e8e8f0;--text-muted:#6b6b88;
    --red:#ef4444;--green:#10b981;--mono:'Courier New',Courier,monospace;
    --sans:system-ui,-apple-system,sans-serif;--radius:8px;
  }
  [data-theme="light"]{
    --bg:#f5f5fa;--surface:#ffffff;--surface2:#ececf3;--border:#d8d8e4;
    --accent2:#0891b2;--text:#1a1a26;--text-muted:#6b6b88;--red:#dc2626;--green:#059669;
  }
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{min-height:100vh;background:var(--bg);color:var(--text);font-family:var(--sans);}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
    background-image:linear-gradient(rgba(124,58,237,.03) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(124,58,237,.03) 1px,transparent 1px);
    background-size:40px 40px;}

  .page{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;}

  .hero{width:100%;max-width:1100px;padding:60px 24px 40px;display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;}
  @media(max-width:768px){.hero{grid-template-columns:1fr;padding:32px 16px 24px;gap:32px;}}
  .hero-text .logo{font-family:var(--mono);font-size:28px;font-weight:700;color:var(--accent2);letter-spacing:.1em;margin-bottom:16px;}
  .hero-text .logo span{color:var(--accent);}
  .hero-text h1{font-size:clamp(20px,3vw,28px);font-weight:700;line-height:1.3;margin-bottom:12px;color:var(--text);}
  .hero-text p{font-size:15px;color:var(--text-muted);line-height:1.6;margin-bottom:24px;}
  .features{display:flex;flex-direction:column;gap:12px;}
  .feat{display:flex;gap:12px;align-items:flex-start;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);}
  .feat-icon{width:36px;height:36px;border-radius:6px;background:rgba(124,58,237,.15);border:1px solid rgba(124,58,237,.3);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
  .feat-text strong{display:block;font-size:13px;color:var(--text);margin-bottom:2px;}
  .feat-text span{font-size:11px;color:var(--text-muted);font-family:var(--mono);}

  .video-wrap{position:relative;border-radius:12px;overflow:hidden;border:1px solid var(--border);background:#000;aspect-ratio:16/9;cursor:pointer;}
  .video-wrap video{width:100%;height:100%;object-fit:cover;display:block;}
  .video-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.4);transition:opacity .2s;}
  .video-overlay:hover{background:rgba(0,0,0,.2);}
  .play-btn{width:64px;height:64px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;transition:transform .2s;}
  .play-btn:hover{transform:scale(1.1);}
  .play-btn svg{width:24px;height:24px;fill:#fff;margin-left:4px;}
  .video-label{position:absolute;bottom:12px;left:12px;font-family:var(--mono);font-size:10px;color:rgba(255,255,255,.7);letter-spacing:.1em;}

  .article{width:100%;max-width:1100px;padding:0 24px 48px;}
  @media(max-width:768px){.article{padding:0 16px 32px;}}
  .article-inner{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:32px 40px;}
  @media(max-width:768px){.article-inner{padding:20px;}}
  .article-inner h2{font-size:18px;font-weight:700;color:var(--accent2);margin-bottom:16px;font-family:var(--mono);letter-spacing:.05em;}
  .article-inner p{font-size:14px;color:var(--text-muted);line-height:1.8;margin-bottom:14px;}
  .article-inner ul{padding-left:20px;margin-bottom:14px;}
  .article-inner li{font-size:14px;color:var(--text-muted);line-height:1.8;margin-bottom:6px;}
  .article-inner li::marker{color:var(--accent);}

  .login-section{width:100%;max-width:1100px;padding:0 24px 64px;display:flex;justify-content:center;}
  @media(max-width:768px){.login-section{padding:0 16px 48px;}}
  .card{width:100%;max-width:420px;padding:40px 32px;background:var(--surface);border:1px solid var(--border);border-radius:12px;}
  .card-title{font-family:var(--mono);font-size:13px;color:var(--accent2);letter-spacing:.12em;text-transform:uppercase;margin-bottom:24px;text-align:center;}
  label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;font-family:var(--mono);letter-spacing:.05em;}
  input[type="text"],input[type="password"],input[type="email"]{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 14px;outline:none;margin-bottom:16px;transition:border-color .2s;}
  input:focus{border-color:var(--accent);}
  .btn-login{width:100%;padding:12px;background:var(--accent);border:none;border-radius:var(--radius);color:#fff;font-family:var(--mono);font-size:13px;letter-spacing:.08em;cursor:pointer;transition:background .2s;}
  .btn-login:hover{background:#6d28d9;}

  .separator{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text-muted);font-family:var(--mono);font-size:11px;}
  .separator::before,.separator::after{content:'';flex:1;height:1px;background:var(--border);}

  .btn-trial{display:block;width:100%;padding:14px;background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(6,182,212,.15));border:1px solid var(--accent2);border-radius:var(--radius);color:var(--accent2);font-family:var(--mono);font-size:13px;letter-spacing:.08em;cursor:pointer;transition:all .2s;text-decoration:none;text-align:center;}
  .btn-trial:hover{background:linear-gradient(135deg,rgba(124,58,237,.3),rgba(6,182,212,.3));border-color:var(--accent);color:#fff;}
  .btn-trial-sub{text-align:center;font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-top:8px;letter-spacing:.05em;}

  .btn-genie{display:block;width:100%;padding:14px;margin-top:12px;background:linear-gradient(135deg,var(--green),var(--accent2));border:none;border-radius:var(--radius);color:#fff;font-family:var(--mono);font-size:13px;font-weight:700;letter-spacing:.08em;text-align:center;text-decoration:none;cursor:pointer;position:relative;overflow:hidden;box-shadow:0 4px 14px rgba(16,185,129,.35);transition:transform .15s,box-shadow .2s;}
  .btn-genie:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(16,185,129,.55);}
  .btn-genie:active{transform:translateY(0);}
  .btn-genie::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(120deg,transparent,rgba(255,255,255,.55),transparent);transform:skewX(-20deg);animation:genie-shine 3.2s ease-in-out infinite;}
  @keyframes genie-shine{0%,100%{left:-60%}50%{left:130%}}
  @media (prefers-reduced-motion:reduce){.btn-genie::after{animation:none}}

  .error{color:var(--red);font-family:var(--mono);font-size:11px;margin-bottom:12px;padding:8px;border:1px solid var(--red);border-radius:var(--radius);background:rgba(239,68,68,.08);}
  .footer-note{text-align:center;font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-top:24px;letter-spacing:.05em;}

  .lang-toggle{position:fixed;top:16px;right:16px;z-index:100;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:5px 12px;font-family:var(--mono);font-size:11px;color:var(--text-muted);cursor:pointer;text-decoration:none;transition:all .2s;}
  .lang-toggle:hover{border-color:var(--accent2);color:var(--accent2);}
  .theme-toggle{position:fixed;top:16px;right:64px;z-index:100;width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-muted);cursor:pointer;padding:0;transition:all .2s;}
  .theme-toggle:hover{border-color:var(--accent2);color:var(--accent2);}
</style>
</head>
<body>

<button class="theme-toggle" id="theme-btn" type="button" onclick="toggleTheme()" aria-label="Thème clair / sombre">
  <svg viewBox="0 0 24 24" id="theme-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
  </svg>
</button>

<a class="lang-toggle" href="?lang=<?= $lang === 'fr' ? 'en' : 'fr' ?><?= $inviteParam ? '&invite=' . urlencode($_GET['invite'] ?? '') : '' ?>">
  <?= $lang === 'fr' ? 'EN' : 'FR' ?>
</a>

<div class="page">

  <section class="hero">
    <div class="hero-text">
      <div class="logo">Agen<span>IA</span></div>
      <h1><?= htmlspecialchars($t['tagline']) ?></h1>
      <p><?= htmlspecialchars($t['desc']) ?></p>
      <div class="features">
        <div class="feat"><div class="feat-icon">⚡</div><div class="feat-text"><strong><?= htmlspecialchars($t['feat1']) ?></strong><span><?= htmlspecialchars($t['feat1d']) ?></span></div></div>
        <div class="feat"><div class="feat-icon">🎙</div><div class="feat-text"><strong><?= htmlspecialchars($t['feat2']) ?></strong><span><?= htmlspecialchars($t['feat2d']) ?></span></div></div>
        <div class="feat"><div class="feat-icon">👥</div><div class="feat-text"><strong><?= htmlspecialchars($t['feat3']) ?></strong><span><?= htmlspecialchars($t['feat3d']) ?></span></div></div>
      </div>
    </div>

    <div class="video-wrap" id="video-wrap">
      <video id="demo-video" preload="metadata" poster="/assets/video/demo-poster.jpg">
        <source src="/assets/video/demo.mp4" type="video/mp4">
      </video>
      <div class="video-overlay" id="video-overlay" onclick="playVideo()">
        <div class="play-btn"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>
      </div>
      <div class="video-label"><?= htmlspecialchars($t['watch']) ?></div>
    </div>
  </section>

  <section class="article">
    <div class="article-inner">
      <?php if ($lang === 'fr'): ?>
      <h2>// Pourquoi Agenia ?</h2>
      <p>Agenia est une interface unifiée qui vous permet de connecter et d'utiliser vos modèles d'IA — locaux ou cloud — depuis un seul endroit. Conçue pour les consultants, développeurs et équipes techniques qui veulent garder le contrôle de leurs données et de leurs coûts.</p>
      <ul>
        <li><strong>Confidentialité totale</strong> — vos données restent sur votre infrastructure</li>
        <li><strong>Multi-providers</strong> — basculez entre Ollama, OpenAI, Anthropic, Mistral en un clic</li>
        <li><strong>Voix native</strong> — TTS et STT intégrés, aucun plugin nécessaire</li>
        <li><strong>Vision</strong> — analysez des images directement depuis votre webcam</li>
        <li><strong>Dual license</strong> — open source (AGPL) + licence commerciale disponible</li>
      </ul>
      <p>Essayez maintenant sans inscription — 20 minutes, accès complet, aucune carte bancaire requise.</p>
      <?php else: ?>
      <h2>// Why Agenia?</h2>
      <p>Agenia is a unified interface that lets you connect and use your AI models — local or cloud — from one place. Built for consultants, developers and technical teams who want full control over their data and costs.</p>
      <ul>
        <li><strong>Full privacy</strong> — your data stays on your infrastructure</li>
        <li><strong>Multi-provider</strong> — switch between Ollama, OpenAI, Anthropic, Mistral in one click</li>
        <li><strong>Native voice</strong> — TTS and STT built in, no plugins required</li>
        <li><strong>Vision</strong> — analyse images directly from your webcam</li>
        <li><strong>Dual license</strong> — open source (AGPL) + commercial license available</li>
      </ul>
      <p>Try it now with no sign-up — 20 minutes, full access, no credit card required.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="login-section">
    <div class="card">
      <div class="card-title"><?= htmlspecialchars($t['login_title']) ?></div>

      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" autocomplete="off">
        <label><?= htmlspecialchars($t['username']) ?></label>
        <input type="text" name="login" required autofocus autocomplete="username">
        <label><?= htmlspecialchars($t['password']) ?></label>
        <input type="password" name="password" required autocomplete="current-password">
        <button type="submit" class="btn-login"><?= htmlspecialchars($t['submit']) ?></button>
      </form>

      <div class="separator"><?= htmlspecialchars($t['or']) ?></div>

      <a class="btn-trial" href="./guest-init.php<?= $inviteParam ?>"><?= htmlspecialchars($t['trial_btn']) ?></a>
      <div class="btn-trial-sub"><?= htmlspecialchars($t['trial_sub']) ?></div>

      <a class="btn-genie" href="./register.php<?= $inviteParam ?>"><?= htmlspecialchars($t['genie']) ?></a>

      <div class="footer-note"><?= htmlspecialchars($t['footer']) ?></div>
    </div>
  </section>

</div>

<script>
function playVideo(){
  var v=document.getElementById('demo-video'),o=document.getElementById('video-overlay');
  if(v){v.play();if(o)o.style.display='none';}
}
var _v=document.getElementById('demo-video');
if(_v){_v.addEventListener('ended',function(){var o=document.getElementById('video-overlay');if(o)o.style.display='flex';});}

function toggleTheme(){
  var h=document.documentElement;
  var n=h.getAttribute('data-theme')==='dark'?'light':'dark';
  h.setAttribute('data-theme',n);localStorage.setItem('vox-theme',n);paintThemeIcon(n);
}
function paintThemeIcon(t){
  var i=document.getElementById('theme-icon');if(!i)return;
  i.innerHTML = t==='light'
    ? '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'
    : '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
}
paintThemeIcon(document.documentElement.getAttribute('data-theme')||'dark');
</script>
</body>
</html>