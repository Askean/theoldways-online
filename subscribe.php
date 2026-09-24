<?php
// Old Ways — email capture. Stores the address, mails the starter, and tells nobody else.
// Flat-file storage on purpose: no database, no third-party service, nothing to expire.

declare(strict_types=1);

$DATA_DIR  = __DIR__ . '/data';
$CSV       = $DATA_DIR . '/subscribers.csv';
$STARTER   = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'theoldways') . '/starter.pdf';
$FROM      = 'silas@' . ($_SERVER['HTTP_HOST'] ?? 'theoldways');
$OWNER     = 'dennisreed143@gmail.com';

function back(string $why): void {
    header('Location: /thank-you.html?state=' . urlencode($why), true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    back('nothing-to-do');
}

// honeypot: a real person never fills this in
if (!empty($_POST['website'])) {
    back('sent');
}

$email = trim((string)($_POST['email'] ?? ''));
$email = filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : '';
if ($email === '') {
    back('bad-address');
}

if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0755, true);
}

$known = false;
if (is_file($CSV)) {
    foreach (file($CSV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_contains($line, ',' . $email . ',')) { $known = true; break; }
    }
}

$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$now = gmdate('c');
if (!$known) {
    $fh = @fopen($CSV, 'a');
    if ($fh === false) {
        back('try-again');
    }
    flock($fh, LOCK_EX);
    fputcsv($fh, [$now, $email, $ip]);       // no referrer, no user agent, nothing to leak
    flock($fh, LOCK_UN);
    fclose($fh);
}

// Hosting-side sending — now FALSE. The mailbox path is proven: hello@theoldways.online sends
// over smtp.hostinger.com and the daily job delivers the starter from the queue. Leaving this on
// would give every new subscriber two copies of the same email from two different senders.
$HOST_SENDS_STARTER = false;

// Starter delivery. If mail() is unavailable the address is still stored and can be sent by hand.
$subject = 'The first five old ways';
$body = "Here are the first five, as promised.\n\n"
      . "1. Wake with the light\n2. Cold water to the face\n3. Ten minutes outside first\n"
      . "4. Walk before you eat\n5. Eat food you have to chew\n\n"
      . "The whole thirty days, on paper, with a box to tick each day:\n"
      . "https://dennireed.gumroad.com/l/clewwj\n\n"
      . "One thing, so you are not misled: Silas is an AI-generated character, not a real person.\n"
      . "The habits are the real part.\n\n"
      . "Nothing here is medical, dietary or financial advice.\n";
$headers = "From: Old Ways, One A Day <{$FROM}>\r\nReply-To: {$OWNER}\r\nContent-Type: text/plain; charset=UTF-8\r\n";

if ($HOST_SENDS_STARTER) {
    @mail($email, $subject, $body, $headers);
    @mail($OWNER, 'Old Ways signup: ' . $email, $body, "From: Old Ways <{$FROM}>\r\n");
}

back('sent');
