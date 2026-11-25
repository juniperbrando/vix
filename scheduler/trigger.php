<?php
// Fires the VIX GitHub Actions workflow once per US trading day at 15:00
// New York time (1 h before US close, ~21:00 Danish time).
//
// Run it HOURLY from cron on bick.dk; the script itself decides when to fire,
// so US/EU daylight-saving mismatches and the host's timezone don't matter:
//   0 * * * *  php /path/to/scheduler/trigger.php
//
// Test from the shell (skips the time check):  php trigger.php --now
//
// Needs scheduler/config.php (gitignored) — copy config.example.php.

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // cron only, never via the web

$config = require __DIR__ . '/config.php';

$ny    = new DateTime('now', new DateTimeZone('America/New_York'));
$force = in_array('--now', $argv ?? [], true);

if (!$force && ((int) $ny->format('N') > 5 || (int) $ny->format('G') !== 15)) {
    exit(0); // not 15:xx on a weekday in New York — nothing to do
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

fwrite(STDERR, $ny->format('Y-m-d H:i T') . " — dispatch failed (HTTP $code): " . ($body ?: curl_error($ch)) . "\n");
exit(1);
