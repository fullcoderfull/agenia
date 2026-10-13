<?php
// =============================================================================
// register.php — Inscription Agenia (friction minimale, base waitlist_subscribers)
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/license.php';
license_enforce();
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';


$_vox_users = (array) require __DIR__ . '/config/users.php';

// ── Résolution de langue : URL → cookie → UI_LANG → 'fr' ────────────────────
$_lang = $_GET['lang'] ?? ($_COOKIE['vox_lang'] ?? (defined('UI_LANG') ? UI_LANG : 'fr'));
$_lang = in_array($_lang, ['fr', 'en'], true) ? $_lang : 'fr';
if (isset($_GET['lang'])) {
  setcookie('vox_lang', $_lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}
$lang  = $_lang;
$_isFr = $_lang === 'fr';

const REGISTER_USER_TYPE_ID = 3;   // "standard" — VÉRIFIE cet id dans ta table user_type
const REGISTER_MIN_PASS     = 8;

// ── Déjà connecté → redirect ────────────────────────────────────────────────
if (isset($_COOKIE['vox'])) {
  $d = base64_decode($_COOKIE['vox'], true);
  if ($d && count($p = explode('|', $d)) === 3) {
    [$u, $exp, $sig] = $p;
    if (time() < (int)$exp
        && hash_equals(hash_hmac('sha256', $u . '|' . $exp, HMAC_SECRET), $sig)) {
      header('Location: /index.php'); exit;
    }
  }
}

// ── CSRF (double-submit) ────────────────────────────────────────────────────
$inviteRaw   = isset($_GET['invite']) ? (string)$_GET['invite'] : ($_POST['invite'] ?? '');
$inviteParam = $inviteRaw !== '' ? '?invite=' . urlencode($inviteRaw) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $csrf = bin2hex(random_bytes(16));
  setcookie('vox_csrf', $csrf, ['expires' => time() + 1200, 'path' => '/', 'secure' => true, 'httponly' => false, 'samesite' => 'Strict']);
} else {
  $csrf = $_COOKIE['vox_csrf'] ?? '';
}

// ── i18n ────────────────────────────────────────────────────────────────────
$t = [
  'fr' => [
    'title'=>'Agenia — Inscription','heading'=>'Créer un compte',
    'email'=>'Email','password'=>'Mot de passe','show'=>'Afficher','hide'=>'Masquer',
    'submit'=>'🧞 Créer mon compte','loading'=>'Création…',
    'have'=>'Déjà un compte ?','login'=>'Se connecter',
    'footer'=>'Agenia · Interface LLM locale','hint'=>REGISTER_MIN_PASS.' caractères minimum',
  ],
  'en' => [
    'title'=>'Agenia — Sign up','heading'=>'Create an account',
    'email'=>'Email','password'=>'Password','show'=>'Show','hide'=>'Hide',
    'submit'=>'🧞 Create my account','loading'=>'Creating…',
    'have'=>'Already have an account?','login'=>'Sign in',
    'footer'=>'Agenia · Local LLM Interface','hint'=>'At least '.REGISTER_MIN_PASS.' characters',
  ],
][$lang];

$err = [
  'fr' => ['csrf'=>'Session expirée, réessayez.','email'=>'Adresse email invalide.',
    'taken'=>'Cet email est déjà utilisé.','pass'=>'Mot de passe trop court (min '.REGISTER_MIN_PASS.').',
    'save'=>'Erreur d\'enregistrement. Réessayez.'],
  'en' => ['csrf'=>'Session expired, retry.','email'=>'Invalid email address.',
    'taken'=>'This email is already in use.','pass'=>'Password too short (min '.REGISTER_MIN_PASS.').',
    'save'=>'Could not save. Please retry.'],
][$lang];

$error = '';

// ── POST — traitement inscription ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  usleep(300000);
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $tok   = $_POST['csrf'] ?? '';

  if ($csrf === '' || !hash_equals($csrf, $tok)) {
    $error = $err['csrf'];
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = $err['email'];
  } elseif (isset($_vox_users[$email])) {              // anti-doublon : figés + base
    $error = $err['taken'];
  } elseif (strlen($pass) < REGISTER_MIN_PASS) {
    $error = $err['pass'];
  } else {
    try {
      // firstname/lastname en '' : récupérés plus tard dans le profil (friction min)
      $stmt = db()->prepare(
        'INSERT INTO waitlist_subscribers (firstname, lastname, email, password, language, user_type_id)
         VALUES (:fn, :ln, :em, :pw, :lg, :ut)'
      );
      $stmt->execute([
        ':fn' => '', ':ln' => '', ':em' => $email,
        ':pw' => password_hash($pass, PASSWORD_DEFAULT),
        ':lg' => $lang, ':ut' => REGISTER_USER_TYPE_ID,
      ]);
    } catch (PDOException $e) {
      $error = ($e->getCode() === '23000') ? $err['taken'] : $err['save'];
      if ($e->getCode() !== '23000') error_log('register insert: ' . $e->getMessage());
    }

    if ($error === '') {
      // Auto-login : cookie vox au format EXACT de check.php (clé = email)
      $exp = time() + SESSION_TTL;
      $sig = hash_hmac('sha256', $email . '|' . $exp, HMAC_SECRET);
      setcookie('vox', base64_encode($email . '|' . $exp . '|' . $sig), [
        'expires' => $exp, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
      ]);
      setcookie('vox_csrf', '', ['expires' => time() - 1200, 'path' => '/']);
      header('Location: /index.php'); exit;
    }
  }
}

// Email pré-rempli (depuis le lien de login, ou re-soumission)
$prefillEmail = $_POST['email'] ?? ($_GET['email'] ?? '');
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
  body{min-height:100vh;background:var(--bg);color:var(--text);font-family:var(--sans);
       display:flex;align-items:center;justify-content:center;padding:24px;}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
    background-image:linear-gradient(rgba(124,58,237,.03) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(124,58,237,.03) 1px,transparent 1px);
    background-size:40px 40px;}
  .card{position:relative;z-index:1;width:100%;max-width:420px;padding:40px 32px;
        background:var(--surface);border:1px solid var(--border);border-radius:12px;}
  .logo{font-family:var(--mono);font-size:24px;font-weight:700;color:var(--accent2);
        letter-spacing:.1em;text-align:center;margin-bottom:8px;}
  .logo span{color:var(--accent);}
  .card-title{font-family:var(--mono);font-size:13px;color:var(--text-muted);
        letter-spacing:.12em;text-transform:uppercase;margin-bottom:24px;text-align:center;}
  label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;
        font-family:var(--mono);letter-spacing:.05em;}
  input{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);
        color:var(--text);font-size:14px;padding:10px 14px;outline:none;margin-bottom:16px;
        transition:border-color .2s;}
  input:focus{border-color:var(--accent);}
  .pass-wrap{position:relative;}
  .pass-wrap input{margin-bottom:6px;padding-right:64px;}
  .pass-toggle{position:absolute;top:10px;right:10px;background:none;border:none;
        color:var(--accent2);font-family:var(--mono);font-size:10px;cursor:pointer;
        letter-spacing:.05em;padding:2px 4px;}
  .hint{font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-bottom:16px;}
  .btn-genie{display:block;width:100%;padding:14px;margin-top:4px;
        background:linear-gradient(135deg,var(--green),var(--accent2));border:none;
        border-radius:var(--radius);color:#fff;font-family:var(--mono);font-size:13px;
        font-weight:700;letter-spacing:.08em;text-align:center;cursor:pointer;
        position:relative;overflow:hidden;box-shadow:0 4px 14px rgba(16,185,129,.35);
        transition:transform .15s,box-shadow .2s;}
  .btn-genie:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(16,185,129,.55);}
  .btn-genie:active{transform:translateY(0);}
  .btn-genie:disabled{opacity:.6;cursor:wait;transform:none;}
  .btn-genie::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;
        background:linear-gradient(120deg,transparent,rgba(255,255,255,.55),transparent);
        transform:skewX(-20deg);animation:genie-shine 3.2s ease-in-out infinite;}
  .btn-genie:disabled::after{animation:none;}
  @keyframes genie-shine{0%,100%{left:-60%}50%{left:130%}}
  @media (prefers-reduced-motion:reduce){.btn-genie::after{animation:none}}
  .error{color:var(--red);font-family:var(--mono);font-size:11px;margin-bottom:14px;padding:8px;
         border:1px solid var(--red);border-radius:var(--radius);background:rgba(239,68,68,.08);}
  .alt{text-align:center;font-family:var(--mono);font-size:11px;color:var(--text-muted);margin-top:20px;}
  .alt a{color:var(--accent2);text-decoration:none;}
  .alt a:hover{text-decoration:underline;}
  .footer-note{text-align:center;font-family:var(--mono);font-size:10px;color:var(--text-muted);
               margin-top:20px;letter-spacing:.05em;}
  .lang-toggle{position:fixed;top:16px;right:16px;z-index:100;background:var(--surface2);
       border:1px solid var(--border);border-radius:var(--radius);padding:5px 12px;
       font-family:var(--mono);font-size:11px;color:var(--text-muted);cursor:pointer;
       text-decoration:none;transition:all .2s;}
  .lang-toggle:hover{border-color:var(--accent2);color:var(--accent2);}
  .theme-toggle{position:fixed;top:16px;right:64px;z-index:100;width:34px;height:34px;
       display:flex;align-items:center;justify-content:center;background:var(--surface2);
       border:1px solid var(--border);border-radius:var(--radius);color:var(--text-muted);
       cursor:pointer;padding:0;transition:all .2s;}
  .theme-toggle:hover{border-color:var(--accent2);color:var(--accent2);}
</style>
</head>
<body>

<button class="theme-toggle" id="theme-btn" type="button" onclick="toggleTheme()" aria-label="Thème clair / sombre">
  <svg viewBox="0 0 24 24" id="theme-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
  </svg>
</button>

<a class="lang-toggle" href="?lang=<?= $lang === 'fr' ? 'en' : 'fr' ?><?= $inviteRaw !== '' ? '&invite=' . urlencode($inviteRaw) : '' ?>">
  <?= $lang === 'fr' ? 'EN' : 'FR' ?>
</a>

<div class="card">
  <div class="logo">Agen<span>IA</span></div>
  <div class="card-title"><?= htmlspecialchars($t['heading']) ?></div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="off" id="reg-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="invite" value="<?= htmlspecialchars($inviteRaw) ?>">

    <label><?= htmlspecialchars($t['email']) ?></label>
    <input type="email" name="email" required autofocus
           value="<?= htmlspecialchars($prefillEmail) ?>" autocomplete="email">

    <label><?= htmlspecialchars($t['password']) ?></label>
    <div class="pass-wrap">
      <input type="password" name="password" id="pass" required autocomplete="new-password">
      <button type="button" class="pass-toggle" id="pass-toggle"
              data-show="<?= htmlspecialchars($t['show']) ?>"
              data-hide="<?= htmlspecialchars($t['hide']) ?>"><?= htmlspecialchars($t['show']) ?></button>
    </div>
    <div class="hint"><?= htmlspecialchars($t['hint']) ?></div>

    <button type="submit" class="btn-genie" id="submit-btn"
            data-loading="<?= htmlspecialchars($t['loading']) ?>"><?= htmlspecialchars($t['submit']) ?></button>
  </form>

  <div class="alt">
    <?= htmlspecialchars($t['have']) ?>
    <a href="./login.php<?= $inviteParam ?>"><?= htmlspecialchars($t['login']) ?></a>
  </div>

  <div class="footer-note"><?= htmlspecialchars($t['footer']) ?></div>
</div>

<script>
// Voir / masquer le mot de passe
(function(){
  var p=document.getElementById('pass'),b=document.getElementById('pass-toggle');
  if(p&&b){b.addEventListener('click',function(){
    var show=p.type==='password';p.type=show?'text':'password';
    b.textContent=show?b.dataset.hide:b.dataset.show;
  });}
})();
// État de chargement au submit (évite le double-clic)
(function(){
  var f=document.getElementById('reg-form'),s=document.getElementById('submit-btn');
  if(f&&s){f.addEventListener('submit',function(){
    if(f.checkValidity()){s.disabled=true;s.textContent=s.dataset.loading;}
  });}
})();
// Thème
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