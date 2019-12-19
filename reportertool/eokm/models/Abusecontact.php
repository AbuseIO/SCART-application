<?php namespace ReporterTool\EOKM\Models;

use Config;
use Validator;
use reportertool\eokm\classes\ertAlerts;
use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertModel;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertMail;
use ReporterTool\EOKM\Models\Abusecontact_type;

/**
 * Model
 */
class Abusecontact extends ertModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];
    protected $jsonable = ['aliases', 'domains'];

    static private $_whoiscached = [];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_abusecontact';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'owner' => 'required',
        'police_contact' => 'required',
        'ntd_template_id' => 'required',
    ];

    public $customMessages = [
        'police_contact.police_valid' => 'Only one police contact is possible',
    ];

    public $hasMany = [
        'whoislist' => [
            'reportertool\eokm\models\Whois',
            'key' => 'abusecontact_id',
            'order' => 'whois_timestamp DESC',
            'delete' => true],
        'loglist' => [
            'reportertool\eokm\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "dbtable='reportertool_eokm_abusecontact'",
            'delete' => true],
        'domainlist' => [
            'reportertool\eokm\models\Abusecontact_domain',
            'key' => 'abusecontact_id',
            'order' => 'domains DESC',
            'delete' => true,
        ],
    ];

    /**
     * Aftercreate
     * set defaults
     *
     */
    public function afterCreate() {

        $this->filenumber = $this->generateFilenumber();
        if (empty($this->groupby_hours)) {
            $this->groupby_hours = Config::get('reportertool.eokm::NTD.abusecontact_default_hours',ERT_ABUSECONTACT_NOTSET_DEFAULT_HOURS);
        }
        $this->save();
    }

    // Special for POLICE_CONTACT

    public function beforeValidate() {

        if (isset($this->police_contact) && $this->police_contact) {
            if ($this->id == NULL) {
                if (Abusecontact::where('police_contact',true)->count() > 0) {
                    throw new \ValidationException(['police_contact' => 'Only one police contact is possible']);
                }
            } else {
                if (Abusecontact::where('police_contact',true)->where('id','<>',$this->id)->count() > 0) {
                    throw new \ValidationException(['police_contact' => 'Only one police contact is possible']);
                }
            }
        }
    }

    public function getPoliceTemplateIdOptions($value,$formData) {

        $recs = Ntd_template::select('id','title')->get();
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->id] = $rec->title;
        }
        return $ret;
    }

    public function getTypeCodeOptions($value,$formData) {

        $recs = Abusecontact_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getNtdTemplateIdOptions($value,$formData) {

        $recs = Ntd_template::select('id','title')->get();
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->id] = $rec->title;
        }
        return $ret;
    }

    public function scopeSearch($query) {
        //ertLog::logLine("D-searchContact(): " . print_r($query, true) );
        ertLog::logLine("D-Abusecontact.searchContact()"  );
        return $query->orderBy('owner', 'asc');
    }

    public function scopeSearchOwner($query,$search) {
        $search = substr($search,0,250);
        ertLog::logLine("D-Abusecontact.scopeSearchOwner($search)");
        return $query->where('owner','LIKE',"%$search%");
    }

    public static function findOwner($owner) {

        // owner max 0-250 length
        $search = substr($owner,0,250);
        $contact = Abusecontact::where('owner', $search)->orWhere('aliases','like','%"'.$search.'"%')->first();
        return ($contact) ? $contact : '';
    }

    public static function findDomain($domain) {

        $contact = Abusecontact::where('domains','like','%"'.$domain.'"%')->first();
        return ($contact) ? $contact : '';
    }

    public static function findPolice() {

        $contact = Abusecontact::where('police_contact',true)->first();
        return ($contact) ? $contact->id : '';
    }

    /**
     * FindCreateOwner (Abusecontact)
     *
     * Function for lookup abusecontact on owner (name) and abuse email address.
     * This information is coming from WHOIS info.
     *
     * So this info can also be empty -> detect and inform operator
     *
     * @param $owner
     * @param $abuseemail
     * @return Abusecontact|string
     */
    public static function findCreateOwner($owner,$abuseemail,$abusecountry,$url='') {

        $abusecontact = '';
        $createnew = false;

        if (empty($owner)) {

            $owner = ERT_ABUSECONTACT_OWNER_EMPTY;

            if (empty($abuseemail)) {

                // when not on owner (aliases) or abusemail, then try domain (if filled)

                if ($url) {

                    $arrhost = explode('.', parse_url($url, PHP_URL_HOST));
                    $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '';
                    if ($maindomain!='' && ($abusecontact = Abusecontact::findDomain($maindomain)) ) {
                        ertLog::logLine("D-Found abusecontact based on domain '$maindomain' ");
                    } else {
                        ertLog::logLine("W-Cannot find abusecontact with empty owner and/or abuseemail and also not on maindomain=$maindomain");
                    }

                } else {
                    // kan niet bepalen/instellen!
                    ertLog::logLine("W-Cannot find abusecontact with empty owner and/or abuseemail");
                }

            } else {

                $createnew = true;
                if ($abusecontact = Abusecontact::where('abusecustom', $abuseemail)->first()) {
                    ertLog::logLine("D-Use existing abusecontact; found on abusemail : " . $abuseemail);
                }

            }

        } else {

            $createnew = true;
            if ($abusecontact = Abusecontact::findOwner($owner)) {
                ertLog::logLine("D-Use existing abusecontact; found owner (alias): " . $owner);
            }

        }

        if ($abusecontact=='' && $createnew) {

            $abusecontact = new Abusecontact();
            // 0-250 length
            $abusecontact->owner = substr($owner,0,250);
            $abusecontact->abusecustom = $abuseemail;
            $abusecontact->abusecountry = $abusecountry;
            $abusecontact->police_contact = false;

            // groupby
            $abusecontact->groupby_hours = Config::get('reportertool.eokm::NTD.abusecontact_default_hours',ERT_ABUSECONTACT_NOTSET_DEFAULT_HOURS);
            $ntdtmp = Ntd_template::first();
            $abusecontact->ntd_template_id = $ntdtmp->id;
            $abusecontact->gdpr_approved = ertGrade::isNL($abusecontact->abusecountry);

            $abusecontact->save();
            ertLog::logLine("D-New abusecontact create; owner=$abusecontact->owner ");

            ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.whois_new_abusecontact',[
                'abuseowner' => $abusecontact->owner,
                'abusecountry' => $abusecontact->abusecountry,
                'abusecontact' => $abusecontact->abusecustom,
            ]);

        }


        return $abusecontact;
    }

    /**
     * Fill WhoIs info from registrar and hoster
     *
     * @param $notification or $input record
     * @return mixed
     */
    public static function getWhois($record) {

        $whois = [];

        $host = $record->url_host;
        $host_ip = $record->url_ip;

        $cachekey = 'getWhois#'.$record->host_abusecontact_id.'#'.$record->registrar_abusecontact_id;
        //ertLog::logLine("D-getWhois; cachekey=$cachekey");
        if (isset(SELF::$_whoiscached[$cachekey])) {

            $whois = SELF::$_whoiscached[$cachekey];

        } else {

            $abusecontact = Abusecontact::find($record->host_abusecontact_id);
            if (!$abusecontact) {
                // fill whois 'unknown'
                $abusecontact = (object) [
                    'id' => 0,
                    'abusecustom' => ERT_WHOIS_UNKNOWN,
                ];
            }
            $whois_type = ERT_HOSTER;
            $whois = Whois::fillWhoisArray($whois,$abusecontact->id,$whois_type);
            $whois[$whois_type.'_abusecustom'] = $abusecontact->abusecustom;
            if ($whois[$whois_type.'_lookup']=='') {
                // special when custom Abusecontact and no WhoIs info is found -> use IP in record
                $whois[$whois_type.'_lookup'] = $host_ip;
            }
            //ertLog::logLine("D-getWhois record host_lookup=".$whoisfields[$whois_type.'_lookup']);

            $abusecontact = Abusecontact::find($record->registrar_abusecontact_id);
            if (!$abusecontact) {
                // fill whois 'unknown'
                $abusecontact = (object) [
                    'id' => 0,
                    'abusecustom' => ERT_WHOIS_UNKNOWN,
                ];
            }
            $whois_type = ERT_REGISTRAR;
            $whois = Whois::fillWhoisArray($whois,$abusecontact->id,$whois_type);
            // overrule WHOIS lookup domain -> use current
            $whois[$whois_type.'_lookup'] = $host;
            $whois[$whois_type.'_abusecustom'] = $abusecontact->abusecustom;

            SELF::$_whoiscached[$cachekey] = $whois;
        }

        return $whois;
    }


}
