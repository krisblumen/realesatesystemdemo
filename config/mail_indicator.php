<?php

return [
    'domain' => env('MAIL_INDICATOR_DOMAIN', 'landracore.com'),
    'script_path' => env('MAIL_INDICATOR_SCRIPT_PATH', '/usr/local/bin/mail-unseen.sh'),
    'sudo_user' => env('MAIL_INDICATOR_SUDO_USER', 'vmail'),
    'webmail_url' => env('MAIL_INDICATOR_WEBMAIL_URL', 'https://webmail.landracore.com'),
];
