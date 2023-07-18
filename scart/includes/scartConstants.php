<?php

define ('CRLF_NEWLINE', "<br />\n");

define ('SCART_AUDIT_TABLE' , 'abuseio_scart_audittrail');
define ('SCART_INPUT_TABLE' , 'abuseio_scart_input');
define ('SCART_INPUT_PARENT_TABLE' , 'abuseio_scart_input_parent');
define ('SCART_INPUT_SELECTED_TABLE' , 'abuseio_scart_input_selected');
define ('SCART_NTD_TABLE' , 'abuseio_scart_ntd');
define ('SCART_NTD_URL_TABLE' , 'abuseio_scart_ntd_url');
define ('SCART_SCRAPE_CACHE_TABLE' , 'abuseio_scart_scrape_cache');
define ('SCART_USER_TABLE' , 'abuseio_scart_user');
define ('SCART_CONFIG_TABLE' , 'abuseio_scart_config');

define ('SCART_NOTIFICATION_TABLE' , 'abuseio_scart_notification');
define ('SCART_NOTIFICATION_INPUT_TABLE' , 'abuseio_scart_notification_input');

define ('SCART_SYSTEM_EVENT_LOGS' , 'system_event_logs');
define ('SCART_SYSTEM_LOG_FILE' , '/storage/logs/system');

define ('SCART_YES' , 'y');
define ('SCART_NO' , 'n');

define('SCART_ALERT_LEVEL_INFO',0);
define('SCART_ALERT_LEVEL_WARNING',1);
define('SCART_ALERT_LEVEL_ADMIN',2);

define ('SCART_ROLE_USER' , 'SCARTuser');
define ('SCART_ROLE_MANAGER' , 'SCARTmanager');
define ('SCART_ROLE_SCHEDULER' , 'SCARTscheduler');
define ('SCART_GROUP_WORKUSER' , 'SCARTworkuser');

define ('SCART_INPUT_TYPE' , 'input');
define ('SCART_INPUT_TYPE_VERIFY' , 'input_verify');
define ('SCART_NOTIFICATION_TYPE' , 'notification');

define ('SCART_STATUS_OPEN', 'open');
define ('SCART_STATUS_SCHEDULER_SCRAPE', 'scheduler_scrape');
define ('SCART_STATUS_SCHEDULER_AI_ANALYZE', 'scheduler_ai_analyze');
define ('SCART_STATUS_CANNOT_SCRAPE', 'cannot_scrape');
define ('SCART_STATUS_WORKING', 'work');
define ('SCART_STATUS_AI_ANALYZE', 'ai_analyze');
define ('SCART_STATUS_GRADE', 'grade');
define ('SCART_STATUS_FIRST_POLICE', 'first_police');
define ('SCART_STATUS_ABUSECONTACT_CHANGED', 'abusecontact_changed');
define ('SCART_STATUS_SCHEDULER_CHECKONLINE', 'scheduler_checkonline');
define ('SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL', 'scheduler_checkonline_manual');
define ('SCART_STATUS_CLOSE_OFFLINE', 'close_offline');
define ('SCART_STATUS_CLOSE_OFFLINE_MANUAL', 'close_offline_manual');
define ('SCART_STATUS_CLOSE', 'close');
define ('SCART_STATUS_CLOSE_DOUBLE', 'close_double');

define ('SCART_ALSCART_STATUS_INIT', 'init');
define ('SCART_ALSCART_STATUS_CREATED', 'created');
define ('SCART_ALSCART_STATUS_SENT', 'sent');
define ('SCART_ALSCART_STATUS_SKIPPED', 'skipped');
define ('SCART_ALSCART_STATUS_CLOSE', 'close');

define ( 'SCART_STATUS_REPORT_CREATED', 'CREATED');
define ( 'SCART_STATUS_REPORT_WORKING', 'WORKING');
define ( 'SCART_STATUS_REPORT_DONE', 'DONE');
define ( 'SCART_STATUS_REPORT_FAILED', 'FAILED');

define ( 'SCART_REPORT_TYPE_URL', 'exporturl');
define ( 'SCART_REPORT_TYPE_ATTRIBUTE', 'exportatt');

define ('SCART_URL_TYPE_MAINURL' , 'mainurl');
define ('SCART_URL_TYPE_IMAGEURL' , 'imageurl');
define ('SCART_URL_TYPE_VIDEOURL' , 'videourl');
define ('SCART_URL_TYPE_SCREENSHOT' , 'screenshot');
define ('SCART_URL_TYPE_UNKNOWNURL' , 'unknownurl');

define ('SCART_INPUT_ONCE_NUMBER', '15');

define ('SCART_SOURCE_CODE_DEFAULT', 'webform');
define ('SCART_SOURCE_CODE_WEBFORM', 'webform');

define ('SCART_TYPE_CODE_DEFAULT', 'website');
define ('SCART_TYPE_CODE_IMAGEHOSTER', 'imagehost');

define('SCART_HOSTER','host');
define('SCART_REGISTRAR','registrar');

define('SCART_WHOIS_UNKNOWN','(UNKNOWN)');
define('SCART_NOT_SET', '(not set)');

define('SCART_WHOIS_TARGET_IP','IP');
define('SCART_WHOIS_TARGET_DOMAIN','DOMAIN');
define('SCART_WHOIS_TARGET_PROXY','PROXY');

define('SCART_BROWSE_ERROR_MAX', 3);
define('SCART_WHOIS_ERROR_MAX', 3);

define ('SCART_GRADE_UNSET', 'unset');
define ('SCART_GRADE_ILLEGAL', 'illegal');
define ('SCART_GRADE_IGNORE', 'ignore');
define ('SCART_GRADE_NOT_ILLEGAL', 'not_illegal');

define ('SCART_GRADE_QUESTION_GROUP_ILLEGAL', 'illegal');
define ('SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL', 'not_illegal');
define ('SCART_GRADE_QUESTION_GROUP_POLICE', 'police');

define ('SCART_GRADE_LOAD_IMAGE_NUMBER', 25);

define ('SCART_ABUSECONTACT_NOTSET_DEFAULT_HOURS', '24');
define ('SCART_ABUSECONTACT_NOTSET_DEFAULT_NUMBER', '99999');
define ('SCART_NTD_REGISTRAR_INTERVAL', '5');

define('SCART_ABUSECONTACT_OWNER_EMPTY', '(OWNER EMPTY?!)');
//define('SCART_ABUSECONTACT_TYPE_PROVIDER', 'provider');
//define('SCART_ABUSECONTACT_TYPE_DOMAIN_OWNER', 'domain_owner');

define ('SCART_NTD_STATUS_INIT','init');
define ('SCART_NTD_STATUS_GROUPING','grouping');
define ('SCART_NTD_STATUS_QUEUED','queued');
define ('SCART_NTD_STATUS_QUEUE_DIRECTLY','queue_directly');
define ('SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE','queue_directly_police');
define ('SCART_NTD_STATUS_SENT_FAILED','sent_failed');
define ('SCART_NTD_STATUS_SENT_SUCCES','sent_succes');
define ('SCART_NTD_STATUS_SENT_API_FAILED','sent_api_failed');
define ('SCART_NTD_STATUS_SENT_API_SUCCES','sent_api_succes');
define ('SCART_NTD_STATUS_CLOSE','close');
define ('SCART_NTD_STATUS_NOT_YET','');

define ('SCART_IMAGE_NOT_FOUND', '/abuseio/scart/assets/images/imagenotfound.png');
define ('SCART_IMAGE_MAIN_NOT_FOUND', '/abuseio/scart/assets/images/mainurlnotfound.png');
define ('SCART_IMAGE_IS_VIDEO', '/abuseio/scart/assets/images/imageisvideo.png');

define ('SCART_USER_OPTION_SCREENCOLS','USER_OPTION_SCREENCOLS');
define ('SCART_USER_OPTION_DISPLAYRECORDS','USER_OPTION_DISPLAYRECORDS');
define ('SCART_USER_OPTION_SORTRECORDS','USER_OPTION_SORTRECORDS');
define ('SCART_USER_OPTION_PAGINATION','USER_OPTION_PAGINATION');
define ('SCART_USER_OPTION_INPUTS','USER_OPTION_INPUTS');

define ('SCART_USER_OPTION_CLASSIFY_VIEWTYPE','USER_OPTION_CLASSIFY_VIEWTYPE');
define ('SCART_CLASSIFY_VIEWTYPE_GRID','GRID');
define ('SCART_CLASSIFY_VIEWTYPE_LIST','LIST');

define('SCART_EXPORT_CSV_DELIMIT',';');

define ('SCART_MAILBOX_IMPORT_INPUT_SOURCE', 'ERT-INPUT-SOURCE');
define ('SCART_MAILBOX_IMPORT_WEBSITE_INPUTS', 'ERT-INPUT');
define ('SCART_MAILBOX_IMPORT_CONTENT_REMOVED', 'ERT-CONTENTREMOVED');
define ('SCART_MAILBOX_IMPORT_CONTENT_UNAVAILABLE', 'ERT-CONTENTUNAVAILABLE');
define ('SCART_MAILBOX_IMPORT_ICCAM_INPUTS', 'ERT-ICCAM-INPUT');
define ('SCART_MAILBOX_IMPORT_HOTLINE_INPUTS', 'ERT-HOTLINE-INPUT');
define ('SCART_MAILBOX_IMPORT_SET_MAINTENANCE', 'SET_MAINTENANCE');

define ('SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM', 'webform');
define ('SCART_MAILBOX_IMPORT_TYPE_CODE_WEBSITE', 'website');
define ('SCART_MAILBOX_IMPORT_SOURCE_CODE_HOTLINE', 'analyst');

define ('SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM', 'iccam');
define ('SCART_ICCAM_IMPORT_TYPE_CODE_ICCAM', 'notdetermined');

define ('SCART_IMPORTEXPORT_STATUS_EXPORT', 'export');
define ('SCART_IMPORTEXPORT_STATUS_IMPORTED', 'imported');
define ('SCART_IMPORTEXPORT_STATUS_SUCCESS', 'success');
define ('SCART_IMPORTEXPORT_STATUS_SKIP', 'skip');
define ('SCART_IMPORTEXPORT_STATUS_ERROR', 'error');

define('SCART_RULE_TYPE_WHOIS_FILLED','whois_filled_by_rules');
define('SCART_RULE_TYPE_NONOTSCRAPE','do_not_scrape');
define('SCART_RULE_TYPE_HOST_WHOIS','host_whois');
define('SCART_RULE_TYPE_REGISTRAR_WHOIS','registrar_whois');
define('SCART_RULE_TYPE_SITE_OWNER','site_owner');
define('SCART_RULE_TYPE_PROXY_SERVICE','proxy_service');
define('SCART_RULE_TYPE_DIRECT_CLASSIFY','direct_classify');
define('SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL','direct_classify_illegal');
define('SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL','direct_classify_not_illegal');
define('SCART_RULE_TYPE_LINK_CHECKER','link_checker');
define('SCART_RULE_TYPE_PROXY_SERVICE_API','proxy_service_api');

define ('SCART_TYPE_CODE_WEBSITE', 'website');

define('SCART_INTERFACE_IMPORTMAIL','imapmail');
define('SCART_INTERFACE_IMPORTMAIL_ACTION','mailbox_read');

define('SCART_INTERFACE_EXPORTREPORT','export');
define('SCART_INTERFACE_EXPORTREPORT_ACTION','export_report');

define('SCART_INTERFACE_ICCAM','iccam');
define('SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT','iccam_exportreport');
define('SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION','iccam_exportaction');
define('SCART_INTERFACE_ICCAM_ACTION_IMPORTLAST','iccam_importlast');
define('SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE','iccam_importlastdate');
define('SCART_INTERFACE_ICCAM_ACTION_IMPORTBACKDATE','iccam_importbackdate');

define('SCART_ICCAM_ACTION_LEA',1);   // message to Law Enforcement
define('SCART_ICCAM_ACTION_ISP',2);   // message to provider(s)
define('SCART_ICCAM_ACTION_CR',3);    // content removed
define('SCART_ICCAM_ACTION_CU',4);    // content unavailable
define('SCART_ICCAM_ACTION_MO',5);    // content moved
define('SCART_ICCAM_ACTION_NI',7);    // content not illegal

define('SCART_ICCAM_ACTION_SETHOTLINE',11);    // own actions

define('SCART_ICCAM_REPORTSTATUS_OPEN',0);
define('SCART_ICCAM_REPORTSTATUS_CLOSED',1);
define('SCART_ICCAM_REPORTSTATUS_EITHER',2);

define('SCART_ICCAM_REPORTORIGIN_USERREPORTED',0);    // Reported by user’s hotline
define('SCART_ICCAM_REPORTORIGIN_USERCOUNTRY',1);     // Hosted in user’s country
define('SCART_ICCAM_REPORTORIGIN_USEREITHER',2);      // Either

define('SCART_ICCAM_REPORTSTAGE_CLASSIFICATON',1);      // v2
define('SCART_ICCAM_REPORTSTAGE_MONITOR',2);            // v2
define('SCART_ICCAM_REPORTSTAGE_COMPLETED',3);          // v2

define('SCART_ICCAM_REPORTSTAGE_UNACTIONED','Unactioned');   // v3
define('SCART_ICCAM_REPORTSTAGE_UNASSESSED','Unassessed');   // v3
define('SCART_ICCAM_REPORTSTAGE_UNREFERENCE','noreference');   // v3

define('SCART_ICCAM_CONTENTTYPE_MEDIA',1);              // v3
define('SCART_ICCAM_CONTENTTYPE_WEBSITE',2);            // v3

define('SCART_ADDON_TYPE_LINK_CHECKER','link_checker');
define('SCART_ADDON_TYPE_PROXY_SERVICE_API','proxy_service_api');
define('SCART_ADDON_TYPE_AI_IMAGE_ANALYZER','ai_image_analyzer');
define('SCART_ADDON_TYPE_NTDAPI','ntd_api');

define('SCART_INPUT_EXTRAFIELD_ICCAM','ICCAM');
define('SCART_INPUT_EXTRAFIELD_PWCAI','PWCAI');

define('SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION','classify');
define('SCART_INPUT_EXTRAFIELD_ICCAM_HOTLINEID','HotlineID');
define('SCART_INPUT_EXTRAFIELD_ICCAM_ANALYST','Analyst');

define('SCART_NTD_TYPE_EMAIL','email');
define('SCART_NTD_TYPE_API','api');

define('SCART_CHECKNTD_MODE_CRON','CRON');
define('SCART_CHECKNTD_MODE_REALTIME','REALTIME');

define('SCART_INPUT_HISTORY_STATUS','STATUS');
define('SCART_INPUT_HISTORY_IP','IP');
define('SCART_INPUT_HISTORY_HOSTER','HOSTER');
define('SCART_INPUT_HISTORY_GRADE','CLASSIFY');
define('SCART_INPUT_HISTORY_ICCAM','ICCAM');

define('SCART_MAX_TIME_AI_ANALYZE',(4 * 60 * 60));  // 4 hour

define('SCART_NTD_TYPE_UNKNOWN','unknown');
define('SCART_NTD_ABUSECONTACT_TYPE_POLICE','police');
define('SCART_NTD_ABUSECONTACT_TYPE_HOSTER','hoster');
define('SCART_NTD_ABUSECONTACT_TYPE_REGISTRAR','registrar');
define('SCART_NTD_ABUSECONTACT_TYPE_SITEOWNER','site_owner');

define('SCART_SENT_FAILED','sent_failed');
define('SCART_SENT_SUCCES','sent_succes');
define('SCART_SENT_API_FAILED','sent_api_failed');
define('SCART_SENT_API_SUCCES','sent_api_succes');

define('SCART_VERIFICATION_VERIFY','verify');
define('SCART_VERIFICATION_COMPLETE','verified_succes');
define('SCART_VERIFICATION_ORIGINAL','original');
define('SCART_VERIFICATION_DONE','done');
define('SCART_VERIFICATION_VALIDATE','validate');
define('SCART_VERIFICATION_FAILED','verified_failed');

