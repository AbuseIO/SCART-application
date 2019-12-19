<?php

return [

    'release' => [
        'version' => '3.0.2',
        'build' => 'Build 2',
    ],

    'whois' => [
        'provider' => env('WHOIS_PROVIDER', 'phpWhois'),
        'auth_url' => env('WHOIS_AUTH_URL', ''),
        'auth_user' => env('WHOIS_AUTH_USER', ''),
        'auth_pass' => env('WHOIS_AUTH_PASS', ''),
        'whois_url' => env('WHOIS_WHOIS_URL', ''),
    ],

    'browser' => [
        'provider' => env('BROWSER_PROVIDER', 'BrowserBasic'),                  // BrowserBasic, BrowserDragon
        'provider_cache' => env('BROWSER_PROVIDER_CACHE', false),               // use scrape_cache; no option when BrowserDragon
        'provider_api' => env('BROWSER_PROVIDER_API', ''),                      // DataDragin api
    ],

    // force env settings for own mailer
    'mail' => [
        'host' => env('MAIL_HOST', 'support.svsnet.nl'),
        'port' => env('MAIL_PORT', 25),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
    ],

    'errors' => [
        'domain' => env('ERROR_DOMAIN', 'local.com'),
        'email' => env('ERROR_EMAIL', 'support@svsnet.nl'),
        'error_display_user' => 'found: please contact support',
    ],

    'alerts' => [
        'recipient' => env('ALERT_RECIPIENT', 'support@svsnet.nl'),                // recipient
        'bcc_recipient' => env('ALERT_BCC_RECIPIENT', ''),                         // if filled then BCC
        'level' => env('ALERT_LEVEL', ERT_ALERT_LEVEL_INFO),                       // default alert INFO
    ],

    'scheduler' => [
        'login' => env('SCHEDULER_LOGIN', ''),                                     // scheduler account
        'password' => env('SCHEDULER_PASSWORD', ''),
        'scheduler_process_count' => env('SCHEDULER_PROCESS_COUNT', 15),           // max number of records in each scheduler time
        'scheduler_memory_limit' => env('SCHEDULER_MEMORY_LIMIT', '2G'),           // min memmory
        'scrape' => [
            'debug_mode' => env('SCHEDULER_SCRAPE_DEBUG', true),                   // debug logging
            'audittrail_mode' => true,                                                          // audittrail on/off
            'min_image_size' => env('SCHEDULER_SCRAPE_MIN_IMAGE_SIZE', 1024),      // min size in B(yte)
        ],
        'checkntd' => [
            'debug_mode' => env('SCHEDULER_CHECKNTD_DEBUG', true),                 // debug logging
            'audittrail_mode' => true,                                                          // audittrail on/off
            'check_online_every' => env('CHECK_ONLINE_EVERY', 60),                 // in minutes
        ],
        'sendntd' => [
            'debug_mode' => env('SCHEDULER_SENDNTD_DEBUG', true),
            'audittrail_mode' => true,
            'from' => env('SCHEDULER_NTDSEND_FROM', 'from@svsnet.nl'),
            'envelope_from' => env('SCHEDULER_NTDSEND_ENVELOPE_FROM', 'envelope_from@svsnet.nl'),
            'reply_to' => env('SCHEDULER_NTDSEND_REPLY_TO', 'reply_to@svsnet.nl'),
            'messages' => env('SCHEDULER_NTDSEND_REPLY_TO', 'reply_to@svsnet.nl'),
            'maillogfile' => env('SCHEDULER_NTDSEND_MAILLOGFILE', false),
            'alt_email' => env('SCHEDULER_NTDSEND_ALT_EMAIL', ''),                   // [TEST-MODE] if filled, then all NTD will be send to this email address
        ],
        'importExport' => [
            'debug_mode' => env('SCHEDULER_READIMPORT_DEBUG', true),
            'audittrail_mode' => true,
            'readmailbox' => [
                'host' => env('SCHEDULER_READIMPORT_HOST', ''),
                'port' => env('SCHEDULER_READIMPORT_PORT', ''),
                'sslflag' => env('SCHEDULER_READIMPORT_SSLFLAG', ''),
                'username' => env('SCHEDULER_READIMPORT_USERNAME', ''),
                'password' => env('SCHEDULER_READIMPORT_PASSWORD', ''),
            ],
        ],
        'cleanup' => [
            'debug_mode' => env('SCHEDULER_CLEANUP_DEBUG', true),
            'audittrail_mode' => true,
            'grade_status_timeout' => env('SCHEDULER_GRADE_STATUS_TIMEOUT', 24),    // hours
        ],
        'sendalerts' => [
            'debug_mode' => env('SCHEDULER_SENDALERTS_DEBUG', true),
            'audittrail_mode' => true,
            'send_alerts_info' => env('SCHEDULER_SENDALERTS_INFO', 60),             // minutes
            'send_alerts_warning' => env('SCHEDULER_SENDALERTS_WARNING', 1),        // minutes
        ],
    ],

    'NTD' => [
        'abusecontact_default_hours' => env('NTD_ABUSECONTACT_DEFAULT_HOURS', 24),    // versturen hoster NTD om de (abusecontact_default_hours)
        'registrar_interval' => env('NTD_REGISTRAR_INTERVAL', 6),                     // versturen registrar NTD om de (registrar_interval) x (abusecontact_default_hours)
        'use_blockeddays' => env('NTD_USE_BLOCKEDDAYS', true),                        // use blocked days funcion
        'after_blockedday_hours' => env('NTD_AFTER_BLOCKEDDAY_HOURS', 12),            // after blocked day begin not before hours
    ],

    'iccam' => [
        'active' => env('ICCAM_ACTIVE', false),
        'HotlineID' => env('ICCAM_HOTLINEID', '43'),                                  // default first user EOKM
        'sslcert' => env('ICCAM_SSLCERT', ''),
        'sslcertpw' => env('ICCAM_SSLCERTPW', ''),
        'sslkey' => env('ICCAM_SSLKEY', ''),
        'sslkeypw' => env('ICCAM_SSLKEYPW', ''),
        'cookie' => env('ICCAM_COOKIEFILE', ''),
        'urlroot' => env('ICCAM_URLROOT', ''),
        'apiuser' => env('ICCAM_APIUSER', ''),
        'apipass' => env('ICCAM_APIPASS', ''),
        'readimportmax' => env('ICCAM_READIMPORTMAX', '10'),                                      // import read max
    ]

];

