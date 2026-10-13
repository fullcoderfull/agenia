<?php
// =============================================================================
// rgpd.php — Page publique RGPD (accessible sans authentification)
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/license.php';
license_enforce();
require_once __DIR__ . '/config/rgpd.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= RGPD_COMPANY ?> — Politique de confidentialité</title>
<style>
  :root{--bg:#0a0a0f;--surface:#12121a;--surface2:#1a1a26;--border:#2a2a3e;--accent:#7c3aed;--accent2:#06b6d4;--text:#e8e8f0;--text-muted:#9090aa;--green:#22c55e;--mono:'Courier New',Courier,monospace;--sans:system-ui,-apple-system,sans-serif;--radius:8px}
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:var(--bg);color:var(--text);font-family:var(--sans);padding:40px 20px;line-height:1.8}
  .container{max-width:760px;margin:0 auto}
  .logo{font-family:var(--mono);font-size:18px;font-weight:700;color:var(--accent2);margin-bottom:8px}
  .logo span{color:var(--accent)}
  .version{font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-bottom:32px}
  h1{font-size:22px;margin-bottom:8px;color:var(--text)}
  .updated{font-size:11px;color:var(--text-muted);font-family:var(--mono);margin-bottom:32px}
  h2{font-size:14px;margin:32px 0 10px;color:var(--accent2);font-family:var(--mono);letter-spacing:.06em;text-transform:uppercase}
  h3{font-size:13px;margin:20px 0 8px;color:var(--text);font-family:var(--mono)}
  p,li{font-size:14px;color:var(--text-muted);margin-bottom:8px}
  ul{padding-left:20px}
  a{color:var(--accent2);text-decoration:none}
  a:hover{text-decoration:underline}
  .back{display:inline-flex;align-items:center;gap:6px;margin-bottom:28px;font-family:var(--mono);font-size:12px;color:var(--text-muted)}
  .back:hover{color:var(--accent2)}
  table{width:100%;border-collapse:collapse;margin:12px 0 20px}
  td,th{font-size:13px;padding:10px 12px;border:1px solid var(--border);color:var(--text-muted);vertical-align:top}
  th{color:var(--text);background:var(--surface);font-family:var(--mono);font-size:11px;letter-spacing:.04em}
  .badge{display:inline-block;font-family:var(--mono);font-size:10px;padding:2px 8px;border-radius:4px;border:1px solid}
  .badge-green{color:var(--green);border-color:var(--green)}
  .badge-blue{color:var(--accent2);border-color:var(--accent2)}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin:16px 0}
  .card h3{margin-top:0}
  .mic-icon{font-size:24px;margin-bottom:8px}
  hr{border:none;border-top:1px solid var(--border);margin:32px 0}
</style>
</head>
<body>
<div class="container">

  <div class="logo">VOX<span>MIND</span></div>
  <div class="version">// POLITIQUE DE CONFIDENTIALITÉ</div>

  <a class="back" href="javascript:history.back()">← Retour</a>

  <h1>Politique de confidentialité & RGPD</h1>
  <p class="updated">Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Responsable du traitement</h2>
  <div class="card">
    <table>
      <tr><th>Organisme</th><td><?= RGPD_COMPANY ?></td></tr>
      <tr><th>Site web</th><td><a href="<?= RGPD_SITE_URL ?>"><?= RGPD_SITE_URL ?></a></td></tr>
      <tr><th>Adresse</th><td><?= RGPD_ADDRESS ?></td></tr>
      <tr><th>Email</th><td><a href="mailto:<?= RGPD_CONTACT_EMAIL ?>"><?= RGPD_CONTACT_EMAIL ?></a></td></tr>
      <tr><th>Téléphone</th><td><?= RGPD_CONTACT_TEL ?></td></tr>
      <tr><th>DPO</th><td><?= RGPD_DPO_NAME ?> — <a href="mailto:<?= RGPD_DPO_EMAIL ?>"><?= RGPD_DPO_EMAIL ?></a></td></tr>
    </table>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Données collectées</h2>
  <table>
    <tr>
      <th>Donnée</th>
      <th>Finalité</th>
      <th>Base légale</th>
      <th>Durée</th>
      <th>Stockage</th>
    </tr>
    <tr>
      <td>Adresse IP (pseudonymisée /24)</td>
      <td>Sécurité, anti-abus, rate limiting</td>
      <td>Intérêt légitime</td>
      <td><?= RGPD_RETENTION_DAYS ?> jours</td>
      <td><span class="badge badge-blue">Serveur local</span></td>
    </tr>
    <tr>
      <td>Logs de connexion</td>
      <td>Audit sécurité</td>
      <td>Intérêt légitime</td>
      <td><?= RGPD_RETENTION_DAYS ?> jours</td>
      <td><span class="badge badge-blue">Serveur local</span></td>
    </tr>
    <tr>
      <td>Messages de conversation</td>
      <td>Traitement IA conversationnel</td>
      <td>Consentement</td>
      <td>Session uniquement</td>
      <td><span class="badge badge-green">Non conservé</span></td>
    </tr>
    <?php if (RGPD_FEATURE_MIC): ?>
    <tr>
      <td>Audio microphone</td>
      <td>Transcription vocale (STT)</td>
      <td>Consentement explicite</td>
      <td>Durée de l'enregistrement</td>
      <td><span class="badge badge-green">Non conservé</span></td>
    </tr>
    <?php endif; ?>
    <?php if (RGPD_FEATURE_CAM): ?>
    <tr>
      <td>Image caméra</td>
      <td>Analyse visuelle contextuelle</td>
      <td>Consentement explicite</td>
      <td>Instantané (1 frame)</td>
      <td><span class="badge badge-green">Non conservé</span></td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <?php if (RGPD_FEATURE_MIC || RGPD_FEATURE_STT): ?>
  <h2>▸ Utilisation du microphone</h2>
  <div class="card">
    <div class="mic-icon">🎤</div>
    <h3>Pourquoi nous utilisons votre microphone</h3>
    <p><?= RGPD_COMPANY ?> propose une fonctionnalité de reconnaissance vocale afin de vous permettre de dicter vos messages vocalement, sans saisie au clavier.</p>

    <h3>Comment fonctionne la transcription</h3>
    <p>Lorsque vous activez le microphone :</p>
    <ul>
      <li>Votre voix est capturée <strong>localement</strong> dans votre navigateur</li>
      <li>L'audio est transmis à notre serveur de transcription <strong>hébergé sur notre infrastructure</strong> (Faster-Whisper)</li>
      <li>Le texte transcrit vous est retourné immédiatement</li>
      <li><strong>Aucun enregistrement audio n'est conservé</strong> sur nos serveurs après la transcription</li>
      <li>Aucune donnée audio n'est transmise à des services tiers</li>
    </ul>

    <h3>Consentement</h3>
    <p>L'activation du microphone nécessite votre autorisation explicite via la boîte de dialogue de votre navigateur. Vous pouvez révoquer cet accès à tout moment dans les paramètres de votre navigateur.</p>

    <h3>Données techniques traitées</h3>
    <ul>
      <li>Format audio : WebM/Opus, MP4 ou OGG selon votre navigateur</li>
      <li>Durée maximum : limitée à votre session de dictée</li>
      <li>Modèle de transcription : Whisper (traitement 100% local)</li>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (RGPD_FEATURE_CAM): ?>
  <h2>▸ Utilisation de la caméra</h2>
  <div class="card">
    <div class="mic-icon">📷</div>
    <h3>Pourquoi nous utilisons votre caméra</h3>
    <p>La fonctionnalité vision permet à l'IA d'analyser une image capturée depuis votre caméra pour enrichir la conversation (description de scène, analyse visuelle, etc.).</p>
    <h3>Comment fonctionne la capture</h3>
    <ul>
      <li>Une seule image (frame) est capturée au moment de votre demande</li>
      <li>L'image est encodée en base64 et transmise au modèle IA local</li>
      <li><strong>Aucune image n'est conservée</strong> après traitement</li>
      <li>Aucune donnée visuelle n'est transmise à des services tiers</li>
    </ul>
    <h3>Consentement</h3>
    <p>L'accès à la caméra requiert votre autorisation explicite. La caméra est <strong>inactive par défaut</strong> et doit être activée manuellement.</p>
  </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Traitements IA locaux</h2>
  <p>Tous les traitements d'intelligence artificielle sont effectués <strong>sur notre infrastructure locale</strong> :</p>
  <ul>
    <?php if (RGPD_FEATURE_LLM): ?><li><strong>LLM</strong> — modèle de langage hébergé localement (Ollama)</li><?php endif; ?>
    <?php if (RGPD_FEATURE_TTS): ?><li><strong>TTS</strong> — synthèse vocale hébergée localement (Kokoro)</li><?php endif; ?>
    <?php if (RGPD_FEATURE_STT): ?><li><strong>STT</strong> — transcription vocale hébergée localement (Faster-Whisper)</li><?php endif; ?>
  </ul>
  <p>Aucune donnée n'est transmise à des services cloud externes (OpenAI, Google, Amazon, etc.).</p>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Cookies</h2>
  <table>
    <tr><th>Cookie</th><th>Rôle</th><th>Durée</th><th>Type</th></tr>
    <tr><td><code>vox</code></td><td>Session authentifiée (utilisateur interne)</td><td>1 heure glissante</td><td>Strictement nécessaire</td></tr>
    <tr><td><code>vox_partner</code></td><td>Session partenaire JWT</td><td>24 heures</td><td>Strictement nécessaire</td></tr>
  </table>
  <p>Ces cookies sont strictement nécessaires au fonctionnement du service et ne requièrent pas de consentement (Art. 82 Loi Informatique et Libertés).</p>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Sécurité</h2>
  <ul>
    <li>Communications chiffrées via HTTPS (TLS)</li>
    <li>Mots de passe hachés avec bcrypt</li>
    <li>Adresses IP pseudonymisées avant stockage (/24)</li>
    <li>Rate limiting et protection anti-brute force</li>
    <li>Tokens de session signés HMAC-SHA256</li>
    <li>Headers de sécurité HTTP (CSP, X-Frame-Options, etc.)</li>
  </ul>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <h2>▸ Vos droits</h2>
  <ul>
    <li><strong>Droit d'accès</strong> (Art. 15 RGPD) — connaître les données vous concernant</li>
    <li><strong>Droit de rectification</strong> (Art. 16 RGPD)</li>
    <li><strong>Droit à l'effacement</strong> (Art. 17 RGPD) — "droit à l'oubli"</li>
    <li><strong>Droit à la portabilité</strong> (Art. 20 RGPD)</li>
    <li><strong>Droit d'opposition</strong> (Art. 21 RGPD)</li>
    <li><strong>Droit de retrait du consentement</strong> à tout moment</li>
  </ul>
  <p>Pour exercer vos droits : <a href="mailto:<?= RGPD_DPO_EMAIL ?>"><?= RGPD_DPO_EMAIL ?></a> ou <?= RGPD_CONTACT_TEL ?></p>
  <p>Vous pouvez également introduire une réclamation auprès de la <a href="https://www.cnil.fr" target="_blank" rel="noopener">CNIL</a>.</p>

  <hr>
  <p style="font-size:11px;font-family:var(--mono);color:var(--text-muted)">
    <?= RGPD_COMPANY ?> — <?= RGPD_SITE_URL ?> — <?= RGPD_CONTACT_EMAIL ?> — <?= RGPD_CONTACT_TEL ?>
  </p>

</div>
</body>
</html>