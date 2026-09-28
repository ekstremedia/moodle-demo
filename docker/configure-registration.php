<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

// Moodle needs both the email authentication plugin and the signup setting.
$auth = array_filter(explode(',', (string) get_config('core', 'auth')));
if (!in_array('email', $auth, true)) {
    $auth[] = 'email';
    set_config('auth', implode(',', $auth));
}
set_config('registerauth', 'email');

// The local default sends to Mailpit. A real SMTP server can be set in .env.
$host = getenv('MAIL_HOST') ?: 'mailpit';
$port = getenv('MAIL_PORT') ?: '1025';
$security = strtolower(getenv('MAIL_ENCRYPTION') ?: 'none');
if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('MAIL_PORT must be a valid TCP port');
}
if (!in_array($security, ['none', 'ssl', 'tls'], true)) {
    throw new RuntimeException('MAIL_ENCRYPTION must be none, ssl, or tls');
}
set_config('smtphosts', $host . ':' . $port);
set_config('smtpsecure', $security === 'none' ? '' : $security);
set_config('smtpuser', getenv('MAIL_USERNAME') ?: '');
set_config('smtppass', getenv('MAIL_PASSWORD') ?: '');

$from = getenv('MAIL_FROM_ADDRESS') ?: (getenv('MAIL_USERNAME') ?: '');
if ($from !== '') {
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MAIL_FROM_ADDRESS must be a valid email address');
    }
    set_config('noreplyaddress', $from);
    set_config('supportemail', $from);
}

echo "Email self-registration and outgoing SMTP are configured.\n";
