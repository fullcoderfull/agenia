<?php
// =============================================================================
// lib/jwt.php — JWT minimaliste HMAC-SHA256 (sans dépendance externe)
// =============================================================================

function jwt_encode(array $payload, string $secret): string {
  $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
  $body    = base64url_encode(json_encode($payload));
  $sig     = base64url_encode(hash_hmac('sha256', $header . '.' . $body, $secret, true));
  return $header . '.' . $body . '.' . $sig;
}

function jwt_decode(string $token, string $secret): array|false {
  $parts = explode('.', $token);
  if (count($parts) !== 3) { return false; }
  [$header, $body, $sig] = $parts;
  $expected = base64url_encode(hash_hmac('sha256', $header . '.' . $body, $secret, true));
  if (!hash_equals($expected, $sig)) { return false; }
  $payload = json_decode(base64url_decode($body), true);
  if (!is_array($payload)) { return false; }
  if (isset($payload['exp']) && $payload['exp'] < time()) { return false; }
  return $payload;
}

function base64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
  return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
