<?php
namespace abuseio\scart\classes\whois;

use Config;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Input;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\mail\scartAlerts;

class scartUpdateWhois {

    public static function checkProxyServices()
    {

        /**
         * Get enabled proxy services RULES
         *   every 12 hour
         *
         * RULE;
         *   check if proxy-service abuse contact and addon API
         *   check if domain has PROXY whois cache
         *
         *   check if domain has still active reports
         *     if not
         *       disable RULE
         *
         *     if yes
         *       refresh real IP
         *
         *   clear type=PROXY whois cache
         *
         *
         * Note: other whois cache wil follow IP/domain question, no need to clear or update
         *
         */

        $job_records = $admin_report = [];

        $proxyrules = scartRules::getProxyServiceRules();
        scartLog::logLine("D-checkProxyServices; count(domain PROXY rules)=" . count($proxyrules));
        foreach ($proxyrules as $proxyrule) {

            // check if ProxyService API enabled
            if ($proxyrule->proxy_abusecontact_id) {

                $proxycontact = Abusecontact::find($proxyrule->proxy_abusecontact_id);
                $proxycontact = ($proxycontact) ? $proxycontact->owner : '???';

                // check if domain still active

                //trace_sql();
                $cnt = Input::whereIn('status_code', [
                        SCART_STATUS_CANNOT_SCRAPE,
                        SCART_STATUS_GRADE,
                        SCART_STATUS_FIRST_POLICE,
                        SCART_STATUS_ABUSECONTACT_CHANGED,
                        SCART_STATUS_SCHEDULER_CHECKONLINE,
                        SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,
                    ])
                    ->where(function ($query) use ($proxyrule) {
                        $query
                            ->where('url_host', 'LIKE', $proxyrule->domain)
                            ->orWhere('url_host', 'LIKE', '%.' . $proxyrule->domain);
                    })
                    ->count();

                if ($cnt == 0) {

                    // No records with this domain active anymore -> disable rule

                    scartLog::logLine("D-checkProxyServices; proxy rule for domain '$proxyrule->domain' has no active reports anymore - disable ");

                    $proxyrule->enabled = false;
                    $proxyrule->save();

                    $admin_report[] = "proxy contact '$proxycontact'; domain '$proxyrule->domain' has no active reports anymore - disable";

                    $job_records[] = [
                        'domain' => $proxyrule->domain,
                        'type_code' => $proxyrule->type_code,
                        'message' => "proxy contact '$proxycontact'; domain has no active reports anymore - rule disabled ",
                    ];

                } else {

                    scartLog::logLine("D-Proxy rule for domain '$proxyrule->domain' has active reports - check REAL IP ");

                    if ($addon_id = scartRules::proxyServiceAPI($proxyrule->proxy_abusecontact_id)) {

                        // enabled
                        scartLog::logLine("D-checkProxyServices; found proxyServiceAPI addon_id (=$addon_id) ");

                        $addon = Addon::find($addon_id);
                        if ($addon) {

                            $record = new \stdClass();
                            $record->url = "https://$proxyrule->domain/";
                            $record->filenumber = 'A' . sprintf('%010d', $addon_id);

                            $real_ip = Addon::run($addon, $record);
                            if ($real_ip) {

                                if ($proxyrule->ip != $real_ip) {
                                    $admin_report[] = "proxy contact '$proxycontact'; real IP for domain '$proxyrule->domain' is changed, was '$proxyrule->ip', update with new REAL ip=$real_ip ";
                                    scartLog::logLine("D-checkProxyServices; real IP for domain '$proxyrule->domain' is changed, was '$proxyrule->ip', update with new REAL ip=$real_ip ");
                                    // update
                                    $proxyrule = self::updateProxyIP($proxyrule,$real_ip);
                                    $proxyrule->save();
                                    $job_real_update = 'changed';
                                } else {
                                    //$admin_report[] = "proxy contact '$proxycontact'; real IP for domain '$proxyrule->domain' is unchanged; REAL ip=$real_ip ";
                                    scartLog::logLine("D-checkProxyServices; real IP for domain '$proxyrule->domain' is unchanged; REAL ip=$real_ip ");
                                    $job_real_update = 'checked (unchanged)';
                                }

                                $job_records[] = [
                                    'domain' => $proxyrule->domain,
                                    'type_code' => $proxyrule->type_code,
                                    'message' => "proxy contact '$proxycontact'; domain has active reports - real IP (with abusecontact) $job_real_update",
                                ];

                                // update real IP
                                scartWhoisCache::setWhoisCacheRealIP($proxyrule->domain, SCART_WHOIS_TARGET_PROXY, $real_ip);

                            } else {

                                $job_records[] = [
                                    'domain' => $proxyrule->domain,
                                    'type_code' => $proxyrule->type_code,
                                    'message' => "proxy contact '$proxycontact'; domain has active reports - CAN NOT refresh IP; error=".Addon::getLastError($addon),
                                ];

                                // remove from CACHE
                                scartWhoisCache::delWhoisCache($proxyrule->domain, SCART_WHOIS_TARGET_PROXY);

                            }

                        }

                    } else {
                        scartLog::logLine("D-checkProxyServices; NO proxy abusecontact API active ");
                    }

                }

            }

        }

        if (count($admin_report) > 0) {

            // report admin
            $params = [
                'reportname' => 'Update WHOIS PROXY service rules',
                'report_lines' => $admin_report,
            ];
            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.admin_report', $params);

        }

        return $job_records;
    }

    public static function checkUpdateProxyAPIrealIP($domain) {

        // check if PROXY domainrule active

        $proxyrule = scartRules::getRule($domain,SCART_RULE_TYPE_PROXY_SERVICE);

        if ($proxyrule) {

            if ($proxyrule->proxy_abusecontact_id) {

                if ($addon_id = scartRules::proxyServiceAPI($proxyrule->proxy_abusecontact_id)) {

                    $proxycontact = Abusecontact::find($proxyrule->proxy_abusecontact_id);
                    $proxycontact = ($proxycontact) ? $proxycontact->owner : '???';

                    scartLog::logLine("D-checkUpdateProxyAPIrealIP; proxy service API rule for domain '$proxyrule->domain' with proxy contact '$proxycontact' is active - check if real IP not changed");

                    $addon = Addon::find($addon_id);
                    if ($addon) {

                        $record = new \stdClass();
                        $record->url = "https://$domain/";
                        $record->filenumber = 'A' . sprintf('%010d', $addon_id);

                        $real_ip = Addon::run($addon, $record);
                        if ($real_ip) {
                            if ($proxyrule->ip != $real_ip) {
                                scartLog::logLine("D-checkUpdateProxyAPIrealIP; real IP for domain '$proxyrule->domain' is changed, was '$proxyrule->ip', update with new REAL ip=$real_ip ");
                                // update
                                $proxyrule = self::updateProxyIP($proxyrule,$real_ip);
                                $proxyrule->save();
                            } else {
                                scartLog::logLine("D-checkUpdateProxyAPIrealIP; real IP for domain '$proxyrule->domain' is NOT changed; REAL ip=$real_ip ");
                            }
                        }
                    } else {
                        scartLog::logLine("W-checkUpdateProxyAPIrealIP; addon for proxy service API rule for domain '$proxyrule->domain' not active");
                    }

                }
            }
        }

    }

    public static function updateProxyIP($proxyrule,$real_ip) {

        $proxyrule->ip = $real_ip;

        // update abusecontact from this IP
        $result = scartWhois::lookupIP($real_ip);
        if ($result['status_success']) {
            $abusecontact = Abusecontact::findCreateOwner($result['host_owner'],$result['host_abusecontact'],$result['host_country'],SCART_HOSTER);
            if ($abusecontact) {
                $proxyrule->abusecontact_id = $abusecontact->id;
                scartLog::logLine("D-updateProxyIP; update proxy rule with abuse contact host owner: ".$result['host_owner']);
            } else {
                scartLog::logLine("W-updateProxyIP; cannot get abuse contact from host owner: ".$result['host_owner'].' ?!');
            }
        } else {
            scartLog::logLine("W-updateProxyIP; cannot find abuse contact of IP '$real_ip' ?!");
        }

        // update real IP
        scartWhoisCache::setWhoisCacheRealIP($proxyrule->domain, SCART_WHOIS_TARGET_PROXY, $real_ip);

        // update action responsibility in calling function
        return $proxyrule;
    }

}
