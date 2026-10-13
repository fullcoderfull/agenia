<?php
// =============================================================================
// modules/terminal/module.php — Module Terminal (refactorisation de proxy-mcp.php)
// Exécution de commandes système avec whitelist et sanitization
// =============================================================================

class Mod_terminal implements VoxModule {

  private array $config = [];

  public function setConfig(array $cfg): void {
    $this->config = $cfg;
  }

  public function init(): bool {
    return true;
  }

  public function execute(string $action, array $params): array {
    $whitelist = $this->config['whitelist'] ?? ['info'];
    $timeout   = $this->config['timeout']   ?? 30;

    // Vérifier que l'action est dans la whitelist
    if (!in_array($action, $whitelist)) {
      return ['output' => '', 'error' => 'Action non autorisée : ' . $action, 'status' => 403];
    }

    $command = $params['command'] ?? '';
    if (!$command) {
      return ['output' => '', 'error' => 'Commande vide', 'status' => 400];
    }

    // Sanitization stricte
    $command = $this->sanitizeCommand($command);
    if (!$command) {
      return ['output' => '', 'error' => 'Commande non valide', 'status' => 400];
    }

    // Exécution selon le type
    switch ($action) {
      case 'powershell':
        $fullCmd = 'powershell.exe -NoProfile -NonInteractive -Command ' . escapeshellarg($command);
        break;
      case 'cmd':
        $fullCmd = 'cmd.exe /C ' . escapeshellarg($command);
        break;
      case 'info':
        return $this->systemInfo();
      default:
        return ['output' => '', 'error' => 'Action inconnue', 'status' => 400];
    }

    // Exécuter avec timeout
    $output  = '';
    $retCode = -1;
    $desc    = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc    = proc_open($fullCmd, $desc, $pipes);

    if (!is_resource($proc)) {
      return ['output' => '', 'error' => 'Impossible de démarrer le processus', 'status' => 500];
    }

    fclose($pipes[0]);
    stream_set_timeout($pipes[1], $timeout);
    $output  = stream_get_contents($pipes[1]);
    $stderr  = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $retCode = proc_close($proc);

    if ($stderr) { $output .= "\n[STDERR] " . $stderr; }

    return [
      'output' => mb_substr($output, 0, 8192),  // max 8KB de sortie
      'error'  => $retCode !== 0 ? 'Code de retour : ' . $retCode : null,
      'status' => $retCode === 0 ? 200 : 500,
    ];
  }

  public function cleanup(): void {}

  public function requires_consent(): bool { return true; }

  public function info(): array {
    return [
      'name'        => 'Terminal',
      'version'     => '1.0.0',
      'description' => 'Exécution de commandes système (PowerShell, CMD)',
    ];
  }

  // ── Helpers ─────────────────────────────────────────────────────────────

  private function sanitizeCommand(string $cmd): string {
    // Bloquer les commandes dangereuses
    $blocked = ['rm -rf', 'format ', 'del /s', 'shutdown', 'restart',
                'net user', 'net localgroup', 'reg delete', 'reg add',
                'schtasks', 'wmic', 'bcdedit', 'diskpart'];
    $lower = strtolower($cmd);
    foreach ($blocked as $b) {
      if (strpos($lower, $b) !== false) { return ''; }
    }
    // Max 500 chars
    return mb_substr(trim($cmd), 0, 500);
  }

  private function systemInfo(): array {
    $info = [
      'os'       => php_uname(),
      'php'      => phpversion(),
      'memory'   => round(memory_get_usage(true) / 1024 / 1024) . ' MB',
      'time'     => date('Y-m-d H:i:s'),
      'timezone' => date_default_timezone_get(),
    ];
    return ['output' => json_encode($info, JSON_PRETTY_PRINT), 'error' => null, 'status' => 200];
  }
}