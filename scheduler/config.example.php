<?php
// Copy to config.php (gitignored) on bick.dk and fill in the token.
//
// Token: GitHub → Settings → Developer settings → Fine-grained tokens
//   Repository access: only juniperbrando/vix
//   Permissions:       Actions → Read and write   (nothing else)
//
// trigger_key: any long random string; cron-job.org sends it as ?key=...
// (e.g. `openssl rand -hex 24`).
return [
    'github_token' => 'github_pat_XXXXXXXXXXXX',
    'trigger_key'  => 'CHANGE_ME_long_random_string',
];
