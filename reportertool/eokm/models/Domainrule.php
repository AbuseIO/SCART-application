<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;
use ReporterTool\EOKM\Models\Rule_type;

/**
 * Model
 */
class Domainrule extends ertModel
{
    use \October\Rain\Database\Traits\Validation;
    
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_domainrule';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'domain' => 'required',
        'type_code' => 'required',
        'ip' => 'required_if:type_code,proxy_service|ip',
        'abusecontact_id' => 'required_if:type_code,proxy_service,site_owner,host_whois,registrar_whois',
    ];

    public $hasOne = [
        'abusecontact' => [
            'reportertool\eokm\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
    ];

    private $_excludeRules = [];
    public function setRuleExclode($exclude) {
        $this->_excludeRules = $exclude;
    }

    public function getTypeCodeOptions($value,$formData) {

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

    private $_input_id = '';
    public function setInputrecord($input_id) {
        $this->_input_id = $input_id;
    }
    public function getDomainSelectOptions($value,$formData) {

        $recs = Input::where('id',$this->_input_id)
            ->select('url_host')
            ->distinct()
            ->get();
        $ret = array();
        foreach ($recs AS $rec) {
            $domain = str_replace('www.','',$rec->url_host);
            $ret[$domain] = $domain;
        }
        $recs = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $this->_input_id)
            ->select('reportertool_eokm_notification.url_host')
            ->distinct()
            ->get();
        foreach ($recs AS $rec) {
            $domain = str_replace('www.','',$rec->url_host);
            if (!isset($ret[$domain])) {
                $ret[$domain] = $domain;
            }
        }
        ksort($ret);
        return $ret;
    }


    }
