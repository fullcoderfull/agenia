<?php
// =============================================================================
// lib/audit.php — Journal d'audit Agenia
// Une ligne JSON par événement, dans logs/audit_AAAA-MM-JJ.jsonl
// (dossier logs/ déjà protégé par IIS : 404 depuis Internet).
//
// Règle : on journalise des FAITS (qui, quand, quel modèle, combien de temps,
// combien de tokens, quelle commande, quelle décision), JAMAIS le contenu
// des messages échangés avec l'IA.
// =============================================================================

/** Identifiant de trace de la requête courante (16 caractères hexadécimaux).
 *  Réutilise celui transmis par l'appelant (en-tête X-Trace-Id) pour relier
 *  api.php et proxy-llm.php. */
function audit_trace_id(): string
{
    static $id = null;
    if ($id !== null) { return $id; }
    $in = (string)($_SERVER['HTTP_X_TRACE_ID'] ?? '');
    $id = audit_valid_trace($in) ? strtolower($in) : bin2hex(random_bytes(8));
    return $id;
}

function audit_valid_trace(string $t): bool
{
    return (bool)preg_match('/^[a-f0-9]{16,32}$/i', $t);
}

function audit_dir(): string
{
    return __DIR__ . '/../logs/';
}

/** Écrit un événement. Ne lève jamais d'exception : le journal ne doit pas casser le service. */
function audit_write(string $event, array $data = []): void
{
    try {
        $dir = audit_dir();
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $line = [
            'ts'    => date('c'),
            'trace' => (isset($data['trace']) && audit_valid_trace((string)$data['trace'])) ? strtolower((string)$data['trace']) : audit_trace_id(),
            'event' => $event,
            'user'  => (string)($GLOBALS['authUser'] ?? ''),
            'role'  => (string)($GLOBALS['authRole'] ?? ''),
        ];
        unset($data['trace']);
        foreach ($data as $k => $v) {
            if (is_string($v) && mb_strlen($v) > 500) { $v = mb_substr($v, 0, 500) . '…'; }
            $line[$k] = $v;
        }
        @file_put_contents(
            $dir . 'audit_' . date('Y-m-d') . '.jsonl',
            json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    } catch (\Throwable $e) {
        // silencieux par conception
    }
}

/** Lit les événements d'une journée (du plus récent au plus ancien). */
function audit_read(string $date): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return []; }
    $file = audit_dir() . 'audit_' . $date . '.jsonl';
    if (!is_file($file)) { return []; }
    $out = [];
    $fh = @fopen($file, 'r');
    if (!$fh) { return []; }
    while (($l = fgets($fh)) !== false) {
        $j = json_decode($l, true);
        if (is_array($j)) { $out[] = $j; }
    }
    fclose($fh);
    return array_reverse($out);
}
