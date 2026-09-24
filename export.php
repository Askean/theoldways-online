<?php
// Old Ways — subscriber export for the Hermes sender.
//
// Why this exists: the capture form writes subscribers.csv ON THIS SERVER, and Hermes runs on the
// user's desktop, so something has to carry the list across. This endpoint does, without SSH, SFTP
// or a database — the site is deployed from git, so a file dropped here by hand would be lost on
// the next deploy anyway.
//
// Security shape, deliberately:
//   * the KEY ITSELF IS NOT IN THIS REPOSITORY. Only its SHA-256 is, so reading the public repo
//     reveals a hash and nothing usable. Hermes keeps the raw key locally in
//     ~/AppData/Local/hermes/secrets/oldways_export_key.txt.
//   * comparison is constant-time (hash_equals) so the response cannot be timed to recover it.
//   * a wrong or missing key gets a plain 404 — the endpoint does not advertise that it exists.
//   * read-only. It can never modify or delete the list.

declare(strict_types=1);

$EXPECTED_SHA256 = '8b2887cbacbcc18eb85949e83d17eddfc6e731c5862bdfc535f7dbdb7304059f';

$key = (string)($_GET['key'] ?? '');
if ($EXPECTED_SHA256 === '' || !hash_equals($EXPECTED_SHA256, hash('sha256', $key))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not Found\n";
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$file = __DIR__ . '/data/subscribers.csv';
if (!is_file($file)) {
    echo json_encode(['count' => 0, 'subscribers' => [], 'generated' => gmdate('c')]);
    exit;
}

// Columns are written by subscribe.php as: time, email, ip
$rows = [];
$fh = @fopen($file, 'r');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['error' => 'cannot read the list']);
    exit;
}
flock($fh, LOCK_SH);
while (($r = fgetcsv($fh)) !== false) {
    if (!is_array($r) || count($r) < 2) {
        continue;
    }
    $email = strtolower(trim((string)$r[1]));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $rows[] = ['at' => (string)$r[0], 'email' => $email];
}
flock($fh, LOCK_UN);
fclose($fh);

echo json_encode(['count' => count($rows), 'subscribers' => $rows, 'generated' => gmdate('c')]);
