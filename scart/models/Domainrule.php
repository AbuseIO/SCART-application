<?php namespace abuseio\scart\models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\rules\scartDomainOnlyOne;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Rule_type;
use abuseio\scart\classes\whois\scartWhoisCache;
use Request;

/**
 * Model
 */
class Domainrule extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_domainrule';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'domain' => 'required_if:type_code,do_not_scrape,host_whois,registrar_whois,site_owner,proxy_service,direct_classify_illegal,direct_classify_not_illegal,link_checker',
        'type_code' => 'required',
        'ip' => 'required_if:type_code,proxy_service',
        'abusecontact_id' => 'required_if:type_code,proxy_service,site_owner,host_whois,registrar_whois,proxy_service_api',
    ];

    public $hasOne = [
        'abusecontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
        'proxycontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'proxy_abusecontact_id'
        ],
        'addon' => [
            'abuseio\scart\models\Addon',
            'key' => 'id',
            'otherKey' => 'addon_id'
        ],
    ];

    private $_excludeRules = [];
    public function setRuleExclude($exclude) {
        $this->_excludeRules = $exclude;
    }

    public function beforeValidate()
    {
        //  only Ajax request, skip jobs and crons
        if (Request::ajax()) {
            //$this->rules['domain'] = [$this->rules['domain'], new scartDomainOnlyOne];
        }

    }

    public function getCoronaExclude() {
        return [
            SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL,
            SCART_RULE_TYPE_SITE_OWNER,
            SCART_RULE_TYPE_PROXY_SERVICE,
            SCART_RULE_TYPE_WHOIS_FILLED,
        ];
    }

    public function getTypeCodeOptions($value,$formData) {

        if (scartUsers::isCorona()) {
            self::setRuleExclude($this->getCoronaExclude());
        }

        $recs = Rule_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            if (!in_array($rec->code,$this->_excludeRules)) {
                $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
            }
        }
        return $ret;
    }

    public function getAddonIdOptions($value,$formData) {

        // bepaal type_code... (dependsOn for type_code is set, so we get called)
        scartLog::logLine("D-Type_code: " . print_r($this->type_code,true) );
        $recs = Addon::where('type',$this->type_code)->orderBy('title')->select('id','type','title')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            if (!in_array($rec->code,$this->_excludeRules)) {
                $ret[$rec->id] = $rec->type . ' - ' . $rec->title;
            }
        }
        return $ret;
    }

    public function getAddonDescriptionAttribute() {

        $value = '';
        //scartLog::logLine("D-getAddonDescription.addon_id: " . print_r($this->addon_id,true) );
        $addon = Addon::find($this->addon_id);
        if ($addon) {
            $value = $addon->description;
            $value = strip_tags($value);
        }
        return $value;
    }

    private $_input_ids = [];
    public function setInputrecord($listrecords) {
        $this->_input_ids = $listrecords;
    }
    public function getDomainOptions($value='',$formData='') {

        $recs = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id', $this->_input_ids)
            ->select(SCART_INPUT_TABLE.'.url_host')
            ->distinct()
            ->get();
        $ret = array();
        foreach ($recs AS $rec) {
            $domain = str_replace('www.','',$rec->url_host);
            $ret[$domain] = $domain;
        }
        ksort($ret);
        return $ret;
    }

    public function beforeSave() {

        $posts = post();

        $values = [];

        if ($this->type_code==SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL) {

            $name = 'type_code_'.SCART_GRADE_QUESTION_GROUP_ILLEGAL;
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];
            $name = 'source_code_'.SCART_GRADE_QUESTION_GROUP_ILLEGAL;
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];
            $name = 'iccam_hotline_'.SCART_GRADE_QUESTION_GROUP_ILLEGAL;
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];

            $valuegrades = [];
            $grades  = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_ILLEGAL)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();
            foreach ($grades AS $grade) {
                $valuegrades[] = [
                    'grade_question_id' => $grade->id,
                    'answer' => (isset($posts[$grade->name])) ? $posts[$grade->name] : [],
                    ];
            }
            $values['grades'] = $valuegrades;

            $name = 'police_first';
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];

            $valuegrades = [];
            $grades  = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_POLICE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();
            foreach ($grades AS $grade) {
                $valuegrades[] = [
                    'grade_question_id' => $grade->id,
                    'answer' => (isset($posts[$grade->name])) ? $posts[$grade->name] : [],
                ];
            }
            $values['police_reason'] = $valuegrades;

            $name = 'checkonline_manual';
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];

            $this->abusecontact_id = null;
            $this->ip = '';

        } elseif ($this->type_code==SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL)  {

            $name = 'type_code_'.SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL;
            $values[$name] = (isset($posts[$name])) ? $posts[$name] : [];

            $valuegrades = [];
            $grades  = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();
            foreach ($grades AS $grade) {
                $valuegrades[] = [
                    'grade_question_id' => $grade->id,
                    'answer' => (isset($posts[$grade->name])) ? $posts[$grade->name] : [],
                ];
            }
            $values['grades'] = $valuegrades;

            $this->abusecontact_id = null;
            $this->ip = '';

        }

        //trace_log($values);
        $this->rulesetdata = serialize($values);

        // check & convert
        if (empty($this->abusecontact_id)) $this->abusecontact_id = 0;
        if (empty($this->proxy_abusecontact_id)) $this->proxy_abusecontact_id = 0;
        if (empty($this->addon_id)) $this->addon_id = 0;

    }


    public static function showCheckProxy() {

        //scartLog::logLine("showCheckProxy");
        // check if domainrule PROXY_SERVICE_API is set
        return scartRules::hasProxyServiceAPI();
    }

    public static function getProxyRealIP($domain) {

        scartLog::logLine("D-getProxyRealIP; domain=$domain");

        // get url version
        $link = "https://$domain/";

        $resultproxy = [
            'proxy_service_owner' => '',
            'proxy_service_id' => '',
            'real_ip' => '',
            'real_host_contact' => '',
            'real_host_contact_id' => '',
            'error' => 'not found',
        ];

        // get hoster

        $mainip = scartWhois::getIP($domain);
        $result = scartWhois::lookupIP($mainip);
        //scartLog::logLine("D-getProxyRealIP; lookupLink.result=" . print_r($result,true) );
        if ($result && $result['status_success']) {

            $abusecontact = Abusecontact::findCreateOwner($result['host_owner'],$result['host_abusecontact'],$result['host_country'],SCART_HOSTER);

            if ($abusecontact) {

                scartLog::logLine("D-getProxyRealIP; host owner=$abusecontact->owner, host_abusecontact_id=$abusecontact->id");

                // check if proxy-service-hoster has domainrule PROXY_SERVICE_API

                if ($addon_id = scartRules::proxyServiceAPI($abusecontact->id)) {

                    scartLog::logLine("D-getProxyRealIP; found proxyServiceAPI addon_id (=$addon_id) ");

                    // if so, get real IP from this API

                    $resultproxy['error'] = '';
                    $resultproxy['proxy_service_owner'] = $abusecontact->owner;
                    $resultproxy['proxy_service_id'] = $abusecontact->id;

                    $addon = Addon::find($addon_id);
                    if ($addon) {

                        $record = new \stdClass();
                        $record->url = $link;
                        $record->filenumber = 'A' . sprintf('%010d', $addon_id);

                        $real_ip = Addon::run($addon,$record);
                        if (!$real_ip) {
                            $resultproxy['error'] = Addon::getLastError($addon);
                        } else {

                            $resultproxy['real_ip'] = $real_ip;

                            $result = scartWhois::lookupIP($real_ip);
                            //scartLog::logLine("lookupIP.result=" . print_r($result,true) );

                            $abusecontact = Abusecontact::findCreateOwner($result['host_owner'],$result['host_abusecontact'],$result['host_country'],SCART_HOSTER);
                            if ($abusecontact) {
                                $resultproxy['real_host_contact'] = $abusecontact->owner;
                                $resultproxy['real_host_contact_id'] = $abusecontact->id;
                                scartLog::logLine("D-getProxyRealIP; found real hoser '$abusecontact->owner' (id=$abusecontact->id) " );
                            } else {
                                $resultproxy['error'] = "Cannot find owner from real IP '$real_ip'  ";
                            }

                        }

                    } else {

                        scartLog::logLine("E-getProxyRealIP; cannot find proxy service API addon_id=$addon_id " );

                    }

                } else {
                    $resultproxy['error'] = 'No proxy service (API) for domain '.$domain;
                }

            } else {
                scartLog::logLine("W-getProxyRealIP; hoster not found!?");
                $resultproxy['error'] = 'Cannot find hoster information';
            }

        } else {
            scartLog::logLine("W-getProxyRealIP; whois not found!?");
            $resultproxy['error'] = 'Cannot get Whois information';
        }


        return $resultproxy;
    }

}
