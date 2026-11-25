<?php
// Fires the VIX GitHub Actions workflow once per US trading day at 15:00
// New York time (1 h before US close, ~21:00 Danish time).
//
// Hosted on bick.dk (FTP-only webhotel) and called by cron-job.org:
//   URL:      https://bick.dk/<folder>/scheduler/trigger.php?key=<trigger_key>
//   Timezone: America/New_York, schedule 0 15 * * 1-5 (cron-job.org handles DST)
// The script also refuses to fire outside 15:xx New York time on weekdays, so a
// mis-set schedule can't spam runs.
//
// Test (skips the time check):  ...trigger.php?key=<trigger_key>&now=1
//                          or:  php trigger.php --now
//
// Needs scheduler/config.php (gitignored) — copy config.example.php.

$config = require __DIR__ . '/config.php';
$cli    = PHP_SAPI === 'cli';

// Over the web, require the shared secret; answer 404 otherwise so the URL
// looks like nothing is there.
if (!$cli && !hash_equals((string) ($config['trigger_key'] ?? ''), (string) ($_GET['key'] ?? ''))
          || !$cli && ($config['trigger_key'] ?? '') === '') {
    http_response_code(404);
    exit;
}
if (!$cli) header('Content-Type: text/plain; charset=utf-8');

$ny    = new DateTime('now', new DateTimeZone('America/New_York'));
$force = $cli ? in_array('--now', $argv ?? [], true) : (($_GET['now'] ?? '') === '1');

if (!$force && ((int) $ny->format('N') > 5 || (int) $ny->format('G') !== 15)) {
    echo $ny->format('Y-m-d H:i T') . " — not 15:xx on a NY weekday, nothing to do\n";
    exit(0);
}

$url = 'https://api.github.com/repos/juniperbrando/vix/actions/workflows/vix-eod.yml/dispatches';
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['ref' => 'main']),
    CURLOPT_HTTPHEADER     => [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $config['github_token'],
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: vix-trigger',
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 30,
]);

// Retry transient failures a few times; GitHub answers 204 on success.
for ($attempt = 1; $attempt <= 4; $attempt++) {
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code === 204) {
        echo $ny->format('Y-m-d H:i T') . " — VIX workflow dispatched\n";
        exit(0);
    }
    if ($code >= 400 && $code < 500) break; // bad token / permissions — retrying won't help
    sleep(10);
}

// 500 makes cron-job.org mark the run as failed (and e-mail you if enabled)
if (!$cli) http_response_code(500);
echo $ny->format('Y-m-d H:i T') . " — dispatch failed (HTTP $code): " . ($body ?: curl_error($ch)) . "\n";
exit(1);
