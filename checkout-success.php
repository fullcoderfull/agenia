<?php
// =============================================================================
// checkout-success.php — Confirmation paiement Stripe
// Version : V1.0
// Modifié : 2025-06-11
// Le webhook a déjà mis à jour le plan — on affiche juste la confirmation
// =============================================================================
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';

$lang = defined('UI_LANG') ? UI_LANG : 'fr';
$isFr = $lang === 'fr';

//debug_log('CHECKOUT_SUCCESS', 'OK', ['user' => $GLOBALS['authUser']]);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenia — <?= $isFr ? 'Paiement confirmé' : 'Payment confirmed' ?></title>
<style>
  :root { --bg:#0a0a0f; --surface:#12121a; --border:#2a2a3e; --accent:#7c3aed; --accent2:#06b6d4; --text:#e8e8f0; --green:#10b981; --mono:'Courier New',Courier,monospace; --sans:system-ui,-apple-system,sans-serif; --radius:8px; }
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  body { min-height:100vh; background:var(--bg); color:var(--text); font-family:var(--sans); display:flex; align-items:center; justify-content:center; }
  .card { max-width:440px; width:90%; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:48px 36px; text-align:center; }
  .icon { font-size:48px; margin-bottom:20px; }
  h1 { font-family:var(--mono); font-size:20px; color:var(--green); margin-bottom:12px; }
  p  { font-size:14px; color:#9090aa; line-height:1.7; margin-bottom:24px; }
  .btn { display:inline-block; padding:12px 28px; background:var(--accent); color:#fff; border-radius:var(--radius); font-family:var(--mono); font-size:13px; text-decoration:none; transition:background .2s; }
  .btn:hover { background:#6d28d9; }
  .note { font-family:var(--mono); font-size:10px; color:#4a4a65; margin-top:20px; }
</style>
<script>(function(){ var t=localStorage.getItem('vox-theme'); if(t){document.documentElement.setAttribute('data-theme',t);} })();</script>
</head>
<body>
<div class="card">
  <div class="icon">✓</div>
  <h1><?= $isFr ? 'Paiement confirmé !' : 'Payment confirmed!' ?></h1>
  <p>
    <?= $isFr
      ? 'Votre abonnement Pro est actif. Vous avez accès à 1 000 000 tokens par mois.'
      : 'Your Pro subscription is active. You have access to 1,000,000 tokens per month.' ?>
  </p>
  <a href="/index.php" class="btn">
    <?= $isFr ? '→ Accéder à Agenia' : '→ Go to Agenia' ?>
  </a>
  <div class="note">
    <?= $isFr
      ? 'Une confirmation a été envoyée à votre email.'
      : 'A confirmation has been sent to your email.' ?>
  </div>
</div>
<script>
  // Redirection automatique après 5s
  setTimeout(function() { window.location.href = '/index.php'; }, 5000);
</script>
</body>
</html>
