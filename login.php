<?php
// =============================================================================
// login.php — Connexion Agenia
// Logique d'authentification inchangée (cookie HMAC « vox », sessions, limites).
// Présentation alignée sur la page d'accueil (/app) : mêmes couleurs, même thème
// (clé localStorage « agenia-theme »), même discours.
// Corrections : message d'erreur inconnu (erreur fatale), identifiant retiré des URL,
// redirection directe vers l'application après connexion.
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

// ── Langue : URL → cookie → UI_LANG → fr ────────────────────────────────────
$_lang = $_GET['lang'] ?? ($_COOKIE['vox_lang'] ?? (defined('UI_LANG') ? UI_LANG : 'fr'));
$_lang = in_array($_lang, ['fr', 'en'], true) ? $_lang : 'fr';
if (isset($_GET['lang'])) {
  setcookie('vox_lang', $_lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}
$lang  = $_lang;
$_isFr = $_lang === 'fr';
$appHome = '/app/' . $lang . '/';
$appChat = '/app/' . $lang . '/chat/';

$error = '';

// ── Déjà connecté → application (HMAC, format identique à check.php) ────────
if (isset($_COOKIE['vox'])) {
  $decoded = base64_decode($_COOKIE['vox'], true);
  if ($decoded) {
    $parts = explode('|', $decoded);
    if (count($parts) === 3) {
      [$user, $exp, $sig] = $parts;
      if (time() < (int)$exp
          && hash_equals(hash_hmac('sha256', $user . '|' . $exp, HMAC_SECRET), $sig)) {
        header('Location: ' . $appChat); exit;
      }
    }
  }
}
if (isset($_COOKIE['vox_guest'])) { header('Location: ' . $appChat); exit; }

// ── Messages d'erreur ────────────────────────────────────────────────────────
$errorMessages = [
  'fr' => [
    'invalid'                 => 'Identifiants incorrects.',
    'rate_limit'              => 'Trop de tentatives. Réessayez dans quelques minutes.',
    'blocked'                 => 'Accès temporairement suspendu.',
    'invite_invalid'          => "Lien d'invitation invalide ou expiré.",
    'ip_blocked'              => 'Accès temporairement suspendu suite à une session expirée.',
    'max_connections_reached' => "Nombre maximal de connexions simultanées atteint. Déconnectez-vous d'un autre appareil, puis réessayez.",
    'db_error'                => 'Service momentanément indisponible. Réessayez dans un instant.',
  ],
  'en' => [
    'invalid'                 => 'Invalid credentials.',
    'rate_limit'              => 'Too many attempts. Please try again in a few minutes.',
    'blocked'                 => 'Access temporarily suspended.',
    'invite_invalid'          => 'Invalid or expired invitation link.',
    'ip_blocked'              => 'Access temporarily suspended after session expiry.',
    'max_connections_reached' => 'Maximum number of simultaneous sessions reached. Sign out on another device, then try again.',
    'db_error'                => 'Service temporarily unavailable. Please try again shortly.',
  ],
];

// ── Erreur transmise par une autre page (guest-init.php…) ───────────────────
if (isset($_GET['error'])) {
  $errKey = (string)$_GET['error'];
  $error  = $errorMessages[$lang][$errKey] ?? $errorMessages[$lang]['invalid'];
  if ($errKey === 'blocked' && isset($_GET['wait'])) {
    $wait   = (int)$_GET['wait'];
    $error .= $lang === 'fr' ? " Réessayez dans {$wait} minutes." : " Try again in {$wait} minutes.";
  }
}

// ── POST : connexion (email ou pseudo) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = '';
  $subscriber = [];
  try {
    $login = trim($_POST['login'] ?? '');
    $db = db();

    $subscriber = db_rows('SELECT id FROM waitlist_subscribers WHERE email = ? LIMIT 1', [$login]);
    $subscriberId = $subscriber[0]['id'] ?? null;

    // Limite de connexions simultanées : sessions encore valides de ce compte.
    // Les comptes figés (sans fiche en base) ne sont pas concernés.
    if ($subscriberId !== null) {
      $result = db_rows('SELECT COUNT(*) AS count FROM agenia_users_sessions WHERE subscriber_id = ? AND expires_at > NOW()', [$subscriberId]);
      $activeSessions = (int)($result[0]['count'] ?? 0);
      $settings = json_decode((string)file_get_contents(__DIR__ . '/config/settings.json'), true);
      $maxConnections = (int)($settings['user']['maxConnections'] ?? 5);

      if ($maxConnections > 0 && $activeSessions >= $maxConnections) {
        debug_log('USER_INIT', 'MAX_CONNECTIONS_REACHED', ['activeSessions' => $activeSessions, 'maxConnections' => $maxConnections]);
        header('Location: /login.php?error=max_connections_reached&lang=' . $lang);
        exit;
      }
    }
  } catch (Exception $e) {
    debug_log('USER_INIT', 'CONNECTION_CHECK_FAILED', ['err' => $e->getMessage()]);
    header('Location: /login.php?error=db_error&lang=' . $lang);
    exit;
  }

  $login = trim($_POST['login'] ?? '');
  $pass  = $_POST['password'] ?? '';
  usleep(300000);

  // Identifiant saisi → clé réelle de $_vox_users (pseudo direct ou recherche par email).
  $key = null;
  if (isset($_vox_users[$login])) {
    $key = $login;
  } else {
    foreach ($_vox_users as $k => $u) {
      if (!empty($u['email']) && strcasecmp($u['email'], $login) === 0) { $key = $k; break; }
    }
  }

  if ($key !== null && password_verify($pass, $_vox_users[$key]['password'])) {
    $exp   = time() + SESSION_TTL;
    // On signe la CLÉ ($key), pas la saisie : sinon check.php rejette le cookie.
    $sig   = hash_hmac('sha256', $key . '|' . $exp, HMAC_SECRET);
    $token = base64_encode($key . '|' . $exp . '|' . $sig);
    setcookie('vox', $token, [
      'expires' => $exp, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    $ip = get_client_ip();
    $result = session_user_create($ip, $subscriberId);

    header('Location: ' . $appChat); exit;
  }
  $error = $errorMessages[$lang]['invalid'];
}

// ── Lien d'invitation ────────────────────────────────────────────────────────
$inviteParam = isset($_GET['invite']) ? '?invite=' . urlencode($_GET['invite']) : '';
$langSwitch  = '?lang=' . ($lang === 'fr' ? 'en' : 'fr') . (isset($_GET['invite']) ? '&invite=' . urlencode($_GET['invite']) : '');

// ── Textes ───────────────────────────────────────────────────────────────────
$t = [
  'fr' => [
    'title'      => 'Connexion — Agenia',
    'kicker'     => 'AgenIA sous contrôle',
    'lead'       => "L'agent prépare, votre équipe décide.",
    'leadText'   => "Agenia met l'IA au travail dans votre entreprise, sans que personne ne perde la main.",
    'p1'         => 'Rien sans votre accord',
    'p1d'        => "L'agent propose chaque action ; elle ne s'exécute qu'après validation humaine.",
    'p2'         => 'Chaque action tracée',
    'p2d'        => "Qui a demandé quoi, quelle IA a répondu, qui a autorisé : tout est dans le journal d'audit.",
    'p3'         => 'Chez vous, ou dans le cloud',
    'p3d'        => "Vos serveurs ou le fournisseur de votre choix, avec vos propres clés.",
    'p4'         => 'Voix, documents et équipe',
    'p4d'        => 'Dictée, lecture à voix haute, images et documents ; rôles et quotas pour chacun.',
    'providers'  => 'Compatible avec',
    'video'      => 'Voir la vidéo de présentation',
    'loginTitle' => 'Se connecter',
    'username'   => 'Email ou identifiant',
    'password'   => 'Mot de passe',
    'submit'     => 'Se connecter',
    'or'         => 'ou',
    'trial'      => 'Essayer 20 minutes',
    'trialSub'   => 'Sans inscription ni carte bancaire · 10 messages',
    'demo'       => 'Démonstration guidée',
    'register'   => 'Créer un compte',
    'home'       => "← Retour à l'accueil",
    'license'    => 'Open source (AGPL) ou licence commerciale',
    'privacy'    => 'Confidentialité (RGPD)',
    'theme'      => 'Changer de thème',
  ],
  'en' => [
    'title'      => 'Sign in — Agenia',
    'kicker'     => 'AgenIA under control',
    'lead'       => 'The agent prepares, your team decides.',
    'leadText'   => 'Agenia puts AI to work across your company, without anyone losing control.',
    'p1'         => 'Nothing without your approval',
    'p1d'        => 'The agent proposes each action; it only runs once a person approves it.',
    'p2'         => 'Every action logged',
    'p2d'        => 'Who asked for what, which AI answered, who approved: it is all in the audit log.',
    'p3'         => 'On your servers, or in the cloud',
    'p3d'        => 'Your own infrastructure or the provider you choose, with your own keys.',
    'p4'         => 'Voice, documents and teams',
    'p4d'        => 'Dictation, read-aloud answers, images and documents; roles and quotas for everyone.',
    'providers'  => 'Works with',
    'video'      => 'Watch the overview video',
    'loginTitle' => 'Sign in',
    'username'   => 'Email or username',
    'password'   => 'Password',
    'submit'     => 'Sign in',
    'or'         => 'or',
    'trial'      => 'Try it for 20 minutes',
    'trialSub'   => 'No sign-up, no credit card · 10 messages',
    'demo'       => 'Guided demo',
    'register'   => 'Create an account',
    'home'       => '← Back to home',
    'license'    => 'Open source (AGPL) or commercial license',
    'privacy'    => 'Privacy (GDPR)',
    'theme'      => 'Switch theme',
  ],
][$lang];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$providers = ['Ollama', 'OpenAI', 'Anthropic', 'Mistral', 'Groq', 'Gemini', 'DeepSeek', 'Qwen', 'OpenRouter'];
?>
<!DOCTYPE html>
<html lang="<?= $h($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($t['title']) ?></title>
<script>
(function(){try{var t=localStorage.getItem('agenia-theme');if(t!=='light'&&t!=='dark'){t=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'}document.documentElement.setAttribute('data-theme',t)}catch(e){document.documentElement.setAttribute('data-theme','dark')}})();
</script>
<style>
  /* Mêmes jetons que la page d'accueil (/app). */
  :root, :root[data-theme='light'] {
    --paper:#f5f6f8; --sheet:#ffffff; --ink:#1b1f3b; --ink-2:#4a5070; --ink-3:#6b7190; --rule:#dcdfe7;
    --action:#0b63ce; --action-hover:#0950a8; --action-ink:#ffffff; --ok:#0f7b5f; --ok-bg:#e3f3ee;
    --hold:#b45309; --hold-bg:#fcefd9; --block:#b42318; --focus:#0c4a9e; --sky-bg:#e7f0fb; --sky-ink:#0c4a9e;
    --shadow:0 1px 0 var(--rule), 0 24px 48px -24px rgba(27,31,59,.25); color-scheme: light;
  }
  :root[data-theme='dark'] {
    --paper:#0f1226; --sheet:#151935; --ink:#e8eaf4; --ink-2:#b3b8d0; --ink-3:#8f95b2; --rule:#2a2f52;
    --action:#1f6feb; --action-hover:#2f7bde; --action-ink:#ffffff; --ok:#3cc59b; --ok-bg:rgba(60,197,155,.14);
    --hold:#f0a54a; --hold-bg:rgba(240,165,74,.14); --block:#f2756a; --focus:#6ea8fe; --sky-bg:#16294d; --sky-ink:#9ec5ff;
    --shadow:0 1px 0 var(--rule), 0 24px 48px -24px rgba(0,0,0,.6); color-scheme: dark;
  }
  :root { --sans:'Instrument Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; --data:'JetBrains Mono', ui-monospace, 'Cascadia Code', Consolas, monospace; --radius:12px; }
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; }
  body { min-height: 100vh; background: var(--paper); color: var(--ink); font: 16px/1.55 var(--sans); }
  a { color: var(--action); }
  :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }

  .top { max-width: 1180px; margin: 0 auto; padding: 18px clamp(16px, 4vw, 32px); display: flex; align-items: center; gap: 12px; }
  .brand { font-weight: 700; font-size: 20px; letter-spacing: -0.02em; color: var(--ink); text-decoration: none; }
  .brand span { color: var(--action); }
  .top .spacer { flex: 1; }
  .tool { display: inline-flex; align-items: center; justify-content: center; min-width: 38px; height: 38px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--rule); background: var(--sheet); color: var(--ink-2); font: 600 13px/1 var(--sans); text-decoration: none; cursor: pointer; }
  .tool:hover { color: var(--action); border-color: var(--action); }

  .wrap { max-width: 1180px; margin: 0 auto; padding: 24px clamp(16px, 4vw, 32px) 56px; display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 420px); gap: clamp(28px, 5vw, 64px); align-items: start; }
  @media (max-width: 900px) { .wrap { grid-template-columns: 1fr; } .pitch { order: 2; } }

  .kicker { display: inline-block; font: 600 13px/1 var(--data); letter-spacing: .08em; text-transform: uppercase; color: var(--sky-ink); background: var(--sky-bg); padding: 7px 10px; border-radius: 999px; }
  .pitch h1 { font-size: clamp(30px, 4.4vw, 46px); line-height: 1.08; letter-spacing: -0.03em; margin: 18px 0 12px; text-wrap: balance; }
  .pitch .lead { font-size: 18px; color: var(--ink-2); margin: 0 0 28px; max-width: 56ch; }
  .points { list-style: none; margin: 0 0 28px; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
  @media (max-width: 560px) { .points { grid-template-columns: 1fr; } }
  .points li { background: var(--sheet); border: 1px solid var(--rule); border-radius: var(--radius); padding: 16px; }
  .points b { display: flex; align-items: center; gap: 8px; font-size: 15px; margin-bottom: 4px; }
  .points b::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--ok); flex: none; }
  .points li:first-child b::before { background: var(--hold); }
  .points span { font-size: 14px; color: var(--ink-2); }
  .prov-label { font: 600 12px/1 var(--data); letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 10px; }
  .prov { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 24px; padding: 0; list-style: none; }
  .prov li { font: 500 13px/1 var(--data); color: var(--ink-2); border: 1px solid var(--rule); border-radius: 999px; padding: 7px 11px; background: var(--sheet); }
  .video-link { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; text-decoration: none; }
  .video-link::before { content: "▶"; display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--action); color: var(--action-ink); font-size: 11px; }

  .card { background: var(--sheet); border: 1px solid var(--rule); border-radius: 16px; padding: clamp(22px, 4vw, 32px); box-shadow: var(--shadow); }
  .card h2 { font-size: 22px; letter-spacing: -0.02em; margin: 0 0 18px; }
  label { display: block; font-size: 14px; font-weight: 600; color: var(--ink-2); margin: 0 0 6px; }
  input[type="text"], input[type="password"] { width: 100%; font: 16px/1.3 var(--sans); color: var(--ink); background: var(--paper); border: 1px solid var(--rule); border-radius: 10px; padding: 12px 14px; margin: 0 0 14px; }
  input:focus { border-color: var(--action); outline: none; box-shadow: 0 0 0 3px rgba(31, 111, 235, .2); }
  .btn { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 46px; border-radius: 10px; font: 600 16px/1.2 var(--sans); text-decoration: none; cursor: pointer; border: 0; }
  .btn-primary { background: var(--action); color: var(--action-ink); }
  .btn-primary:hover { background: var(--action-hover); }
  .btn-quiet { background: transparent; color: var(--ink); box-shadow: inset 0 0 0 1.5px var(--rule); }
  .btn-quiet:hover { box-shadow: inset 0 0 0 1.5px var(--action); color: var(--action); }
  .btn-demo { background: transparent; color: var(--action); box-shadow: inset 0 0 0 1.5px var(--action); }
  .btn-demo:hover { background: var(--action); color: var(--action-ink); }
  .stack { display: grid; gap: 10px; }
  .sub { text-align: center; font-size: 13px; color: var(--ink-3); margin: -2px 0 6px; }
  .sep { display: flex; align-items: center; gap: 12px; margin: 20px 0; color: var(--ink-3); font-size: 13px; }
  .sep::before, .sep::after { content: ""; flex: 1; height: 1px; background: var(--rule); }
  .error { margin: 0 0 16px; padding: 10px 12px; border-radius: 10px; background: var(--hold-bg); color: var(--block); font-size: 14px; }
  .home { display: block; text-align: center; margin-top: 18px; font-size: 14px; color: var(--ink-2); text-decoration: none; }
  .home:hover { color: var(--action); }

  .foot { max-width: 1180px; margin: 0 auto; padding: 20px clamp(16px, 4vw, 32px) 32px; border-top: 1px solid var(--rule); display: flex; flex-wrap: wrap; gap: 8px 24px; font-size: 13px; color: var(--ink-3); }
  .foot a { color: var(--ink-3); }
</style>
</head>
<body>

<header class="top">
  <a class="brand" href="<?= $h($appHome) ?>">Agen<span>IA</span></a>
  <span class="spacer"></span>
  <button class="tool" id="theme-btn" type="button" aria-label="<?= $h($t['theme']) ?>" title="<?= $h($t['theme']) ?>"><svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3.5a8.5 8.5 0 0 1 0 17z" fill="currentColor"/></svg></button>
  <a class="tool" href="<?= $h($langSwitch) ?>" hreflang="<?= $lang === 'fr' ? 'en' : 'fr' ?>"><?= $lang === 'fr' ? 'EN' : 'FR' ?></a>
</header>

<main class="wrap">
  <section class="pitch" aria-labelledby="pitch-title">
    <span class="kicker"><?= $h($t['kicker']) ?></span>
    <h1 id="pitch-title"><?= $h($t['lead']) ?></h1>
    <p class="lead"><?= $h($t['leadText']) ?></p>
    <ul class="points">
      <li><b><?= $h($t['p1']) ?></b><span><?= $h($t['p1d']) ?></span></li>
      <li><b><?= $h($t['p2']) ?></b><span><?= $h($t['p2d']) ?></span></li>
      <li><b><?= $h($t['p3']) ?></b><span><?= $h($t['p3d']) ?></span></li>
      <li><b><?= $h($t['p4']) ?></b><span><?= $h($t['p4d']) ?></span></li>
    </ul>
    <div class="prov-label"><?= $h($t['providers']) ?></div>
    <ul class="prov"><?php foreach ($providers as $p): ?><li><?= $h($p) ?></li><?php endforeach; ?></ul>
    <a class="video-link" href="<?= $h($appHome) ?>#demo"><?= $h($t['video']) ?></a>
  </section>

  <section class="card" aria-labelledby="login-title">
    <h2 id="login-title"><?= $h($t['loginTitle']) ?></h2>
    <?php if ($error): ?><p class="error" role="alert"><?= $h($error) ?></p><?php endif; ?>
    <form method="POST" autocomplete="on">
      <label for="login"><?= $h($t['username']) ?></label>
      <input type="text" id="login" name="login" required autofocus autocomplete="username">
      <label for="password"><?= $h($t['password']) ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit" class="btn btn-primary"><?= $h($t['submit']) ?></button>
    </form>

    <div class="sep"><?= $h($t['or']) ?></div>

    <div class="stack">
      <a class="btn btn-quiet" href="/guest-init.php<?= $h($inviteParam) ?>"><?= $h($t['trial']) ?></a>
      <p class="sub"><?= $h($t['trialSub']) ?></p>
      <a class="btn btn-demo" href="/demo.php?lang=<?= $h($lang) ?>"><?= $h($t['demo']) ?></a>
      <a class="btn btn-quiet" href="/register.php<?= $h($inviteParam) ?>"><?= $h($t['register']) ?></a>
    </div>
    <a class="home" href="<?= $h($appHome) ?>"><?= $h($t['home']) ?></a>
  </section>
</main>

<footer class="foot">
  <span>Agenia</span>
  <span><?= $h($t['license']) ?></span>
  <a href="/rgpd.php?lang=<?= $h($lang) ?>"><?= $h($t['privacy']) ?></a>
</footer>

<script>
document.getElementById('theme-btn').addEventListener('click', function () {
  var root = document.documentElement, next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  try { localStorage.setItem('agenia-theme', next); } catch (e) {}
});
</script>
</body>
</html>
