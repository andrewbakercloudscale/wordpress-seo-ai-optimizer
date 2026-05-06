<?php
// Copy to config.php and fill in real values — config.php is gitignored.
define('ANTHROPIC_API_KEY',      'sk-ant-...');
define('PAYFAST_MERCHANT_ID',    '');
define('PAYFAST_MERCHANT_KEY',   '');
define('PAYFAST_PASSPHRASE',     '');           // leave empty string if not set in PayFast dashboard
define('PAYFAST_TESTING',        false);        // true = sandbox mode
define('DB_PATH',                __DIR__ . '/data/licenses.db');
define('PROXY_BASE_URL',         'https://api.andrewbaker.ninja');
