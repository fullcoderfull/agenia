<?php
// =============================================================================
// checkout-email.php — Saisie email avant paiement Stripe
// Version : V1.0
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

//require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/db_helpers.php';

$lang  = defined('UI_LANG') ? UI_LANG : 'fr';
$isFr  = $lang === 'fr';
$plan  = $_GET['plan'] ?? 'pro_monthly';
$error = '';

// POST — traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $isFr ? 'Email invalide.' : 'Invalid email.';
    } else {
        // Vérifier si email déjà client
        try {
            $row = db_row(
                'SELECT plan FROM waitlist_subscribers WHERE email = ? LIMIT 1',
                [$email]
            );
            if ($row && in_array($row['plan'], ['pro', 'enterprise', 'white_label'])) {
                $error = $isFr
                    ? 'Cet email est déjà associé à un abonnement actif. <a href="/login.php">Connectez-vous</a>.'
                    : 'This email already has an active subscription. <a href="/login.php">Sign in</a>.';
            }
        } catch (Exception $e) {
            debug_log('CHECKOUT_EMAIL', 'DB_ERROR', ['err' => $e->getMessage()]);
        }

        if (empty($error)) {
            // Rediriger vers checkout.php avec l'email
            debug_log('CHECKOUT_EMAIL', 'EMAIL_OK', ['email' => $email, 'plan' => $plan]);
            header('Location: /checkout.php?plan=' . urlencode($plan) . '&email=' . urlencode($email));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenia — <?= $isFr ? 'Votre email' : 'Your email' ?></title>
<style>
  :root { --bg:#0a0a0f; --surface:#12121a; --border:#2a2a3e; --accent:#7c3aed; --accent2:#06b6d4; --text:#e8e8f0; --text-muted:#6b6b88; --red:#ef4444; --mono:'Courier New',Courier,monospace; --sans:system-ui,-apple-system,sans-serif; --radius:8px; }
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  body{min-height:100vh;background:var(--bg);color:var(--text);font-family:var(--sans);display:flex;align-items:center;justify-content:center;}
  .card{width:100%;max-width:400px;padding:40px 32px;background:var(--surface);border:1px solid var(--border);border-radius:12px;}
  .logo{font-family:var(--mono);font-size:18px;font-weight:700;color:var(--accent2);letter-spacing:.1em;margin-bottom:8px;text-align:center;}
  .logo span{color:var(--accent);}
  h2{font-family:var(--mono);font-size:13px;color:var(--text-muted);text-align:center;margin-bottom:24px;letter-spacing:.08em;}
  label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;font-family:var(--mono);}
  input[type="email"]{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 14px;outline:none;margin-bottom:16px;transition:border-color .2s;}
  input:focus{border-color:var(--accent);}
  button{width:100%;padding:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:var(--radius);color:#fff;font-family:var(--mono);font-size:13px;letter-spacing:.08em;cursor:pointer;font-weight:700;}
  .error{color:var(--red);font-family:var(--mono);font-size:11px;margin-bottom:12px;padding:8px;border:1px solid var(--red);border-radius:var(--radius);background:rgba(239,68,68,.08);}
  .note{text-align:center;font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-top:20px;}
  .plan-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.15);border:1px solid var(--accent);border-radius:var(--radius);padding:4px 12px;font-family:var(--mono);font-size:16px;color:var(--accent);margin-bottom:20px;}
</style>
<script>(function(){ var t=localStorage.getItem('vox-theme'); if(t){document.documentElement.setAttribute('data-theme',t);} })();</script>
</head>
<body>
<div class="card">
  <div class="logo">Agen<span>IA</span></div>
  <h2><?= $isFr ? '⭐ PASSER AU PLAN PRO' : '⭐ UPGRADE TO PRO' ?></h2>

  <div style="text-align:center;margin-bottom:20px,font-size:22em">
    <span class="plan-badge">
      <?= $plan === 'pro_yearly'
        ? ($isFr ? '150€/an · -10%' : '€150/year · -10%')
        : ($isFr ? '15€/mois' : '€15/month') ?>
    </span>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <label><?= $isFr ? 'Votre email' : 'Your email' ?></label>
    <input type="email" name="email" required autofocus
           placeholder="vous@example.com"
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <button type="submit">
      <?= $isFr ? '→ Continuer vers le paiement' : '→ Continue to payment' ?>
    </button>
  </form>

  <div class="note">
    <?= $isFr
      ? '🔒 Paiement sécurisé par Stripe · 30 jours d\'essai gratuit'
      : '🔒 Secure payment by Stripe · 30-day free trial' ?>
  </div>
</div>
</body>
</html>