<?php

/**
 * System configuration
 *
 * Some support by Setting/Config function, some only ENV varsd
 *
 * Note: ALL keys are lowercase
 *
 */

return [

    'release' => [
        'version' => '6.5',
        'build' => 'Build 42',  // 2026/2/4; read also M356 attachments
        'title' => env('APP_TITLE', 'Sexual Child Abuse Reporter Tool (SCART)'),
    ],

    'maintenance' => [
        'mode' => env('MAINTENANCE_MODE', false),
        // example: 'scrape;createreports;importexport;sendalerts;sendntd;checkntd'
        'monitor_schedulers' => env('MONITOR_SCHEDULERS', ''),
    ],

    'whois' => [
        'provider' => env('WHOIS_PROVIDER', 'phpWhois'),
        'auth_url' => env('WHOIS_AUTH_URL', ''),
        'auth_user' => env('WHOIS_AUTH_USER', ''),
        'auth_pass' => env('WHOIS_AUTH_PASS', ''),
        'whois_url' => env('WHOIS_WHOIS_URL', ''),
        'whois_cache_max_age' => env('WHOIS_CACHE_MAX_AGE', '12'),
    ],

    'browser' => [
        'provider' => env('BROWSER_PROVIDER', 'BrowserBasic'),                  // BrowserBasic, BrowserDragon
        'provider_cache' => env('BROWSER_PROVIDER_CACHE', false),               // use scrape_cache
        'provider_api' => env('BROWSER_PROVIDER_API', ''),                      // provider (eg DataDragon) API
        'use_curl_for_image' => env('BROWSER_USE_CURL_FOR_IMAGE', false),       // use curl for fetching image data (eg provider=Chrome)
    ],

    'AIanalyze' => [
        'active' => env('AIANALYZE_ACTIVE', false),
        'show_correction' => env('AIANALYZE_SHOW_CORRECTION', true),            // show correction fields in Clasify image viewer
        'report_correction' => env('AIANALYZE_REPORT_CORRECTION', true),        // show report option for export AI attributes (correction)
        'only_webform_input' => env('AIANALYZE_ONLY_WEBFORM_INPUT', true),      // default only webform input
        'max_time_ai_analyze' => env('AIANALYZE_MAX_TIME_AI_ANALYZE', (4 * 3600)),     // default max time analyze
    ],

    // force env settings for own mailer
    'mail' => [
        'host' => env('MAIL_HOST', 'mail.domain.com'),
        'port' => env('MAIL_PORT', 25),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
    ],

    'errors' => [
        'domain' => env('ERROR_DOMAIN', 'local.domain'),
        'email' => env('ERROR_EMAIL', 'support@svsnet.nl'),
        'error_display_user' => 'found: please contact support',
        'max_error_pm' => 10,       // max error (mails) within 1 minute to operator
        'pauze_errors_min' => 10,   // pauze mins after detection of error mailing looping
    ],

    'classify' => [
        'viewtype_default' => env('CLASSIFY_VIEWTYPE_DEFAULT', SCART_CLASSIFY_VIEWTYPE_GRID),
        'hotline_country' => env('CLASSIFY_HOTLINE_COUNTRY', 'NL'),                          // classify country
        'detect_country' => env('CLASSIFY_DETECT_COUNTRY', 'nl,netherlands'),        // lowercase strings for detecting local country
        'add_edit_media' => env('CLASSIFY_ADD_EDIT_MEDIA', false),        // lowercase strings for detecting local country
    ],

    'verify' => [
        'active' => env('VERIFY_OPTION_ACTIVE', false),
    ],

    'alerts' => [
        'recipient' => env('ALERT_RECIPIENT', 'support@svsnet.nl'),                // recipient
        'bcc_recipient' => env('ALERT_BCC_RECIPIENT', ''),                         // if filled then BCC
        'level' => env('ALERT_LEVEL', SCART_ALERT_LEVEL_INFO),                       // default alert INFO
        'admin_recipient' => env('ALERT_ADMIN_RECIPIENT', 'support@svsnet.nl'),    // default alert ADMIN
        'language' => env('ALERT_LANGUAGE', 'en'),                                 // default language
    ],

    // SCART general options
    'options' => [
        'import_mail_direct_Scrape' => env('OPTION_IMPORT_MAIL_DIRECT_SCRAPE', false),  // if true then direct scrape, else status=open (manual action required)
        'own_work_default' => env('OPTION_OWN_WORK_DEFAULT', true),                // if true then UI own_work option default OFF
        'lea_function' => env('OPTION_LEA_FUNCTION', false),                // support of LEA direct send function (POLICE menu)
    ],

    'scheduler' => [
        'job_name' => env('SCHEDULER_JOB_NAME', 'cron_normal'),
        'login' => env('SCHEDULER_LOGIN', ''),                                     // scheduler account
        'password' => env('SCHEDULER_PASSWORD', ''),
        'scheduler_process_count' => env('SCHEDULER_PROCESS_COUNT', 15),           // max number of records in each scheduler time
        'scheduler_memory_limit' => env('SCHEDULER_MEMORY_LIMIT', '1G'),           // min memmory
        'scrape' => [
            'active' => env('SCHEDULER_SCRAPE_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_SCRAPE_DEBUG', true),                   // debug logging
            'audittrail_mode' => false,                                                                // audittrail on/off
            'min_image_size' => env('SCHEDULER_SCRAPE_MIN_IMAGE_SIZE', 1024),               // min size in B(yte)
            'scheduler_process_count' => env('SCHEDULER_SCRAPE_PROCESS_COUNT', ''),         // max number of records in each scheduler time
            'scheduler_process_minutes' => env('SCHEDULER_SCRAPE_PROCESS_MINUTES', ''),         // max minutes of scraping process each cycle
            'ai_analyze_addon' => env('SCHEDULER_SCRAPE_AI_ANALYZE_ADDON', ''),     // AI ANALYZE AFTER SCRAPE
        ],
        'checkntd' => [
            'active' => env('SCHEDULER_CHECKNTD_ACTIVE', true),
            'mode' => env('SCHEDULER_CHECKNTD_MODE', SCART_CHECKNTD_MODE_CRON),
            'debug_mode' => env('SCHEDULER_CHECKNTD_DEBUG', true),                     // debug logging
            'audittrail_mode' => false,                                                           // audittrail on/off
            'check_online_every' => env('SCHEDULER_CHECKNTD_EVERY_MIN', 60),           // minimum time between check online of url
            'scheduler_process_count' => env('SCHEDULER_CHECKNTD_PROCESS_COUNT', ''),  // max number of records in each scheduler time
            'realtime_inputs_max' => env('SCHEDULER_CHECKNTD_RT_INPUTS_MAX', 10),      // max number of inputs each minute for every task
            'realtime_min_diff_spindown' => env('SCHEDULER_CHECKNTD_RT_MIN_SPINDOWN', 15),      // time in minutes before spinning down tasks
            'realtime_look_again' => env('SCHEDULER_CHECKNTD_RT_LOOK_AGAIN', 120),     // within (max) 120 mins check each record (url) again
            'realtime_memory_limit' => env('SCHEDULER_CHECKNTD_RT_MEMORY_LIMIT', '1G'),  // min memory
            'realtime_reset_cache' => env('SCHEDULER_CHECKNTD_RT_RESET_CACHE', '100'), // number of records after local cache reset
        ],
        'sendntd' => [
            'active' => env('SCHEDULER_SENDNTD_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_SENDNTD_DEBUG', true),
            'audittrail_mode' => false,
            'from' => env('SCHEDULER_NTDSEND_FROM', 'from@svsnet.nl'),
            'envelope_from' => env('SCHEDULER_NTDSEND_ENVELOPE_FROM', ''),
            'reply_to' => env('SCHEDULER_NTDSEND_REPLY_TO', 'reply_to@svsnet.nl'),
            'maillogfile' => env('SCHEDULER_NTDSEND_MAILLOGFILE', false),
            'alt_email' => env('SCHEDULER_NTDSEND_ALT_EMAIL', ''),                   // [TEST-MODE] if filled, then all NTD will be send to this email address
            'bcc_email' => env('SCHEDULER_NTDSEND_BCC', ''),
        ],
        'import' => [
            'active' => env('SCHEDULER_IMPORT_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_IMPORT_DEBUG', true),
            'audittrail_mode' => false,
            'readmailbox' => [
                'mode' => env('SCHEDULER_READIMPORT_MAILBOX_MODE', ''),
                'imap' => [
                    'host' => env('SCHEDULER_READIMPORT_HOST', ''),
                    'port' => env('SCHEDULER_READIMPORT_PORT', ''),
                    'sslflag' => env('SCHEDULER_READIMPORT_SSLFLAG', ''),
                    'username' => env('SCHEDULER_READIMPORT_USERNAME', ''),
                    'password' => env('SCHEDULER_READIMPORT_PASSWORD', ''),
                    'quoted' => env('SCHEDULER_READIMPORT_QUOTED', true),
                ],
                'm356' => [
                    'tenantId' => env('SCHEDULER_READIMPORT_M365_tenantId', ''),
                    'appId' => env('SCHEDULER_READIMPORT_M365_appId', ''),
                    'clientSecret' => env('SCHEDULER_READIMPORT_M365_clientSecret', ''),
                    'principal' => env('SCHEDULER_READIMPORT_M365_principal', ''),
                ]
            ],
            'iccam_active' => env('SCHEDULER_IMPORTEXPORT_ICCAM_ACTIVE', false),    // default no ICCAM
        ],
        'export' => [
            'active' => env('SCHEDULER_EXPORT_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_EXPORT_DEBUG', true),
            'audittrail_mode' => false,
            'iccam_active' => env('SCHEDULER_IMPORTEXPORT_ICCAM_ACTIVE', false),    // default no ICCAM
        ],
        'cleanup' => [
            'active' => env('SCHEDULER_CLEANUP_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_CLEANUP_DEBUG', true),
            'audittrail_mode' => false,
            'memory_limit' => env('SCHEDULER_CLEANUP_MEMORY_LIMIT', '4G'),
            'grade_status_timeout' => env('SCHEDULER_GRADE_STATUS_TIMEOUT', 24),    // hours
            'closed_retention' => env('SCHEDULER_CLEANUP_CLOSED_RETENTION', ''),    // if filled then offset
            'deleted_records' => env('SCHEDULER_CLEANUP_DELETED_RECORDS', '-1 week'),
        ],
        'sendalerts' => [
            'active' => env('SCHEDULER_SENDALERTS_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_SENDALERTS_DEBUG', true),
            'audittrail_mode' => false,
            'info' => env('SCHEDULER_SENDALERTS_INFO', 60),             // minutes
            'warning' => env('SCHEDULER_SENDALERTS_WARNING', 3),        // minutes
            'admin' => env('SCHEDULER_SENDALERTS_ADMIN', 1),          // minutes
        ],
        'updatewhois' => [
            'active' => env('SCHEDULER_UPDATEWHOIS_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_UPDATEWHOIS_DEBUG', true),
            'audittrail_mode' => false,
        ],
        'createreports' => [
            'active' => env('SCHEDULER_CREATEREPORT_ACTIVE', true),
            'debug_mode' => env('SCHEDULER_CREATEREPORT_DEBUG', true),
            'take' => env('SCHEDULER_CREATEREPORT_TAKE', true),
            'recipient' => env('SCHEDULER_CREATEREPORT_RECIPIENT', ''),                // recipient
            'anonymous' => env('SCHEDULER_CREATEREPORT_ANONYMOUS', false),
            'sendpolice' => env('SCHEDULER_CREATEREPORT_SENDPOLICE', false),
            'extrafields' => env('SCHEDULER_CREATEREPORT_EXTRAFIELDS', false),
        ],
        // default OFF
        'archive' => [
            'active' => env('SCHEDULER_ARCHIVE_ACTIVE', false),
            'debug_mode' => env('SCHEDULER_ARCHIVE_DEBUG', true),
            'audittrail_mode' => env('SCHEDULER_ARCHIVE_AUDITTRAIL', false),
            'database_connection' => [
                'driver' => 'mysql',
                'engine' => 'InnoDB',
                'strict' => false,
                'database' => env('SCHEDULER_ARCHIVE_DB_DATABASE', 'winter'),
                'host' => env('SCHEDULER_ARCHIVE_DB_HOST', '127.0.0.1'),
                'port' => env('SCHEDULER_ARCHIVE_DB_PORT', '3306'),
                'username' => env('SCHEDULER_ARCHIVE_DB_USERNAME', ''),
                'password' => env('SCHEDULER_ARCHIVE_DB_PASSWORD', ''),
            ],
            'archive_time_offset' => env('SCHEDULER_ARCHIVE_TIME', '-1 years'),       // default is before 1 jan -1 year
            'move_chunk_size' => env('SCHEDULER_ARCHIVE_MOVE_CHUNK_SIZE', ''),
            'delete_chunk_size' => env('SCHEDULER_ARCHIVE_DELETE_CHUNK_SIZE', ''),
        ],
    ],

    'ntd' => [
        'abusecontact_default_hours' => env('NTD_ABUSECONTACT_DEFAULT_HOURS', 24),    // NTD default hours to send
        'registrar_active' => env('NTD_REGISTRAR_ACTIVE', false),                     // lookup/sendNTD registrar
        'siteowner_interval' => env('NTD_SITEOWNER_INTERVAL', 3),                     // send sitewowner NTD (sitewowner_interval) x (abusecontact_default_hours)
        'registrar_interval' => env('NTD_REGISTRAR_INTERVAL', 6),                     // send registrar NTD  (registrar_interval) x (abusecontact_default_hours)
        'use_blockeddays' => env('NTD_USE_BLOCKEDDAYS', true),                        // use blocked days funcion
        'after_blockedday_hours' => env('NTD_AFTER_BLOCKEDDAY_HOURS', '12'),          // after blocked day begin not before hours
        'before_blockedday_hours' => env('NTD_BEFORE_BLOCKEDDAY_HOURS', '17'),        // after blocked day begin not before hours
        'after_hours' => env('NTD_AFTER_HOURS', '11:00'),                             // after hour every working day before start sending NTD's
    ],

    'iccam' => [
        'active' => env('ICCAM_ACTIVE', false),                                       // default no ICCAM
        'version' => env('ICCAM_VERSION', 'v3'),                                      // ICCAM version
        'hotlineid' => env('ICCAM_HOTLINEID', ''),                                    // hotline ICCAM ID code
        'urlroot' => env('ICCAM_URLROOT', ''),
        'urlport' => env('ICCAM_URLPORT', '443'),
        'apiuser' => env('ICCAM_APIUSER', ''),
        'apipass' => env('ICCAM_APIPASS', ''),
        'cacert' => env('ICCAM_CACERT', ''),
        'sslcert' => env('ICCAM_SSLCERT', ''),
        'sslcertpw' => env('ICCAM_SSLCERTPW', ''),
        'sslkey' => env('ICCAM_SSLKEY', ''),
        'sslkeypw' => env('ICCAM_SSLKEYPW', ''),
        'verifypeer' => env('ICCAM_VERIFYPEER', true),
        'cookie' => env('ICCAM_COOKIEFILE', ''),
        'readimportmax' => env('ICCAM_READIMPORTMAX', '500'),                         // import read max
        'readimportperiod' => env('ICCAM_READIMPORTPERIODE', '30'),                   // import each X minute
        'exportmax' => env('ICCAM_EXPORTMAX', '20'),                                  // export max
        'export_error_max_time' => env('ICCAM_EXPORT_ERROR_MAX_TIME', SCART_IMPORTEXPORT_STATUS_ERROR_RETRY_TIME),     // max time in error
        'debug_mode' => env('ICCAM_DEBUG_MODE', false),
    ],

    'hashapi' => [
        'active' => env('HASHAPI_ACTIVE', false),
        'test' => env('HASHAPI_TEST', false),
        'format' => env('HASHAPI_FORMAT', 'md5'),
        'username' => env('HASHAPI_USERNAME', ''),
        'password' => env('HASHAPI_PASSWORD', ''),
    ],

];

