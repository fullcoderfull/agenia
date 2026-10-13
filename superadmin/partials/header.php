<?php // superadmin/partials/header.php ?>
<header class="sa-header">
  <div class="logo">Agen<span>IA</span>
    <span style="font-size:10px;color:var(--accent);margin-left:8px;">⚡ SUPER ADMIN</span>
  </div>
  <div class="header-actions">
    <span style="font-family:var(--mono);font-size:11px;color:var(--text-muted)">
      <?= htmlspecialchars($GLOBALS['authUser']) ?>
    </span>
    <a href="/index.php" class="btn-sm">← <span data-i18n="saNavInterface">Interface</span></a>
    <button class="btn-sm" onclick="toggleTheme()">◑</button>
    <a href="/logout.php" class="btn-sm danger">⏻ <span data-i18n="logout">Déconnexion</span></a>
  </div>
</header>