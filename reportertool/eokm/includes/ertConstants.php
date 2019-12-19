<?php

define ('CRLF_NEWLINE', "<br />\n");

define ('ERT_INPUT_TABLE' , 'reportertool_eokm_input');
define ('ERT_SCRAPE_CACHE_TABLE' , 'reportertool_eokm_scrape_cache');
define ('ERT_NOTIFICATION_TABLE' , 'reportertool_eokm_notification');
define ('ERT_NOTIFICATION_INPUT_TABLE' , 'reportertool_eokm_notification_input');
define ('ERT_NTD_TABLE' , 'reportertool_eokm_ntd');
define ('ERT_NTD_URL_TABLE' , 'reportertool_eokm_ntd_url');

define ('ERT_YES' , 'y');
define ('ERT_NO' , 'n');

define('ERT_ALERT_LEVEL_INFO',0);
define('ERT_ALERT_LEVEL_WARNING',1);
define('ERT_ALERT_LEVEL_ERROR',2);

define ('ERT_ROLE_USER' , 'ERTuser');
define ('ERT_ROLE_MANAGER' , 'ERTmanager');

define ('ERT_INPUT_TYPE' , 'input');
define ('ERT_NOTIFICATION_TYPE' , 'notification');

define ('ERT_STATUS_OPEN', 'open');
define ('ERT_STATUS_SCHEDULER_SCRAPE', 'scheduler_scrape');
define ('ERT_STATUS_CANNOT_SCRAPE', 'cannot_scrape');
define ('ERT_STATUS_WORKING', 'work');
define ('ERT_STATUS_GRADE', 'grade');
define ('ERT_STATUS_FIRST_POLICE', 'first_police');
define ('ERT_STATUS_ABUSECONTACT_CHANGED', 'abusecontact_changed');
define ('ERT_STATUS_SCHEDULER_CHECKONLINE', 'scheduler_checkonline');
define ('ERT_STATUS_CLOSE_OFFLINE', 'close_offline');
define ('ERT_STATUS_CLOSE', 'close');

define ('ERT_ALERT_STATUS_INIT', 'init');
define ('ERT_ALERT_STATUS_CREATED', 'created');
define ('ERT_ALERT_STATUS_SENT', 'sent');
define ('ERT_ALERT_STATUS_SKIPPED', 'skipped');
define ('ERT_ALERT_STATUS_CLOSE', 'close');

define ('ERT_URL_TYPE_MAINURL' , 'mainurl');
define ('ERT_URL_TYPE_IMAGEURL' , 'imageurl');
define ('ERT_URL_TYPE_VIDEOURL' , 'videourl');
define ('ERT_URL_TYPE_SCREENSHOT' , 'screenshot');

define ('ERT_INPUT_ONCE_NUMBER', '15');

define ('ERT_SOURCE_CODE_DEFAULT', 'webform');
define ('ERT_TYPE_CODE_DEFAULT', 'website');

define('ERT_HOSTER','host');
define('ERT_REGISTRAR','registrar');

define('ERT_WHOIS_UNKNOWN','(UNKNOWN)');
define('ERT_NOT_SET', '(not set)');

define('ERT_BROWSE_ERROR_MAX', 3);
define('ERT_WHOIS_ERROR_MAX', 3);

define ('ERT_GRADE_UNSET', 'unset');
define ('ERT_GRADE_ILLEGAL', 'illegal');
define ('ERT_GRADE_IGNORE', 'ignore');
define ('ERT_GRADE_NOT_ILLEGAL', 'not_illegal');

define ('ERT_GRADE_QUESTION_GROUP_ILLEGAL', 'illegal');
define ('ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL', 'not_illegal');

define ('ERT_GRADE_LOAD_IMAGE_NUMBER', 25);

define ('ERT_ABUSECONTACT_NOTSET_DEFAULT_HOURS', '24');
define ('ERT_ABUSECONTACT_NOTSET_DEFAULT_NUMBER', '99999');
define ('ERT_NTD_REGISTRAR_INTERVAL', '5');

define('ERT_ABUSECONTACT_OWNER_EMPTY', '(OWNER EMPTY?!)');
//define('ERT_ABUSECONTACT_TYPE_PROVIDER', 'provider');
//define('ERT_ABUSECONTACT_TYPE_DOMAIN_OWNER', 'domain_owner');

define ('ERT_NTD_STATUS_INIT','init');
define ('ERT_NTD_STATUS_GROUPING','grouping');
define ('ERT_NTD_STATUS_QUEUED','queued');
define ('ERT_NTD_STATUS_QUEUE_DIRECTLY','queue_directly');
define ('ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE','queue_directly_police');
define ('ERT_NTD_STATUS_SENT_FAILED','sent_failed');
define ('ERT_NTD_STATUS_SENT_SUCCES','sent_succes');
define ('ERT_NTD_STATUS_CLOSE','close');
define ('ERT_NTD_STATUS_NOT_YET','');

define ('ERT_IMAGE_NOT_FOUND', '/reportertool/eokm/assets/images/imagenotfound.png');
define ('ERT_IMAGE_IS_VIDEO', '/reportertool/eokm/assets/images/imageisvideo.png');

define ('ERT_USER_OPTION_SCREENCOLS','USER_OPTION_SCREENCOLS');

define('ERT_EXPORT_CSV_DELIMIT',';');

define ('ERT_MAILBOX_IMPORT_SUBJECT', 'ERT-INPUT');
define ('ERT_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM', 'webform');
define ('ERT_MAILBOX_IMPORT_TYPE_CODE_WEBSITE', 'website');

define ('ERT_ICCAM_IMPORT_SOURCE_CODE_ICCAM', 'iccam');
define ('ERT_ICCAM_IMPORT_TYPE_CODE_ICCAM', 'notdetermined');

define('ERT_RULE_TYPE_WHOIS_FILLED','whois_filled_by_rules');
define('ERT_RULE_TYPE_NONOTSCRAPE','do_not_scrape');
define('ERT_RULE_TYPE_HOST_WHOIS','host_whois');
define('ERT_RULE_TYPE_REGISTRAR_WHOIS','registrar_whois');
define('ERT_RULE_TYPE_SITE_OWNER','site_owner');
define('ERT_RULE_TYPE_PROXY_SERVICE','proxy_service');

define('ERT_INTERFACE_ICCAM','iccam');
define('ERT_INTERFACE_ICCAM_ACTION_EXPORTREPORT','iccam_exportreport');
define('ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION','iccam_exportaction');
define('ERT_INTERFACE_ICCAM_ACTION_IMPORTLAST','iccam_importlast');

define('ERT_ICCAM_ACTION_LEA',1);   // message to Law Enforcement
define('ERT_ICCAM_ACTION_ISP',2);   // messgae to provider(s)
define('ERT_ICCAM_ACTION_CR',3);    // content removed
define('ERT_ICCAM_ACTION_CU',4);    // content unavailable
define('ERT_ICCAM_ACTION_MO',5);    // content moved
define('ERT_ICCAM_ACTION_NI',7);    // content not illegal

define('ERT_ICCAM_REPORTSTATUS_OPEN',0);    // open
define('ERT_ICCAM_REPORTSTATUS_CLOSED',1);    // closed
define('ERT_ICCAM_REPORTSTATUS_EITHER',2);    // either

define('ERT_ICCAM_REPORTORIGIN_USERREPORTED',0);    // Reported by user’s hotline
define('ERT_ICCAM_REPORTORIGIN_USERCOUNTRY',1);    // Hosted in user’s country
define('ERT_ICCAM_REPORTORIGIN_USEREITHER',2);    // Either

