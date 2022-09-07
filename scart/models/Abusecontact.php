<?php namespace abuseio\scart\models;

use abuseio\scart\models\Systemconfig;
use Config;
use Validator;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;
use Winter\Storm\Exception\ApplicationException;

/**
 * Model
 */
class Abusecontact extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];
    protected $jsonable = ['aliases', 'domains'];

    static private $_whoiscached = [];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_abusecontact';

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
            'abuseio\scart\models\Whois',
            'key' => 'abusecontact_id',
            'order' => 'whois_timestamp DESC',
            'delete' => true],
        'loglist' => [
            'abuseio\scart\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "record_type='abuseio_scart_abusecontact'",
            'delete' => true],
        'domainlist' => [
            'abuseio\scart\models\Abusecontact_domain',
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
            $this->groupby_hours = Systemconfig::get('abuseio.scart::ntd.abusecontact_default_hours',SCART_ABUSECONTACT_NOTSET_DEFAULT_HOURS);
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

    public function getNtdApiAddonIdOptions($value,$formdata) {

        $recs = Addon::where('type',SCART_ADDON_TYPE_NTDAPI)->select('id','title')->get();
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->id] = $rec->title;
        }
        return $ret;
    }

    public function scopeSearch($query) {
        //scartLog::logLine("D-searchContact(): " . print_r($query, true) );
        scartLog::logLine("D-Abusecontact.searchContact()"  );
        return $query->orderBy('owner', 'asc');
    }

    public function scopeSearchOwner($query,$search) {
        $search = substr($search,0,250);
        scartLog::logLine("D-Abusecontact.scopeSearchOwner($search)");
        return $query->where('owner','LIKE',"%$search%");
    }

    public static function findOwner($owner,$country) {

        $contact = '';
        // owner max 0-250 length
        $search = substr($owner,0,250);
        $contact = Abusecontact::where('owner', $search)->orWhere('aliases','like','%"'.$search.'"%');
        if ($country) {
            $contact = $contact->where('abusecountry',$country);
        }
        $contact = $contact->first();
        return ($contact) ? $contact : '';
    }

    public static function findAbusecustom($abuseemail,$country) {

        $contact = '';
        $abusecustom = trim($abuseemail);
        if ($abusecustom) {
            $contact = Abusecontact::where('abusecustom', $abusecustom);
            if ($country) {
                $contact = $contact->where('abusecountry',$country);
            }
            $contact = $contact->first();
        }
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
     * 2020/3/26/Gs:
     * - no lookup by domain anymore
     * - find based on country;
     *   - owner + country
     *   - abuseemail + country
     *
     *
     * @param $owner
     * @param $abuseemail
     * @return Abusecontact|string
     */
    public static function findCreateOwner($owner,$abuseemail,$abusecountry,$whoistype='(unknown whoistype)') {

        $createnew = false;

        // fill if empty
        $owner = (empty($owner)) ? SCART_ABUSECONTACT_OWNER_EMPTY : $owner;
        // fill always with hotline country code
        $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
        $country = (scartGrade::isLocal($abusecountry)) ? $hotlinecountry : $abusecountry;

        // first abuse-email, then owner

        if ($abusecontact = Abusecontact::findAbusecustom($abuseemail,$country) ) {
            // abuse (email) + country
            scartLog::logLine("D-[$whoistype] Use existing abusecontact; found on abuseemail '$abuseemail' and country '$country' " );
        } elseif ($abusecontact = Abusecontact::findOwner($owner,$country) ) {
            // owner/aliases (name) + country
            scartLog::logLine("D-[$whoistype] Use existing abusecontact; found on owner (alias) '$owner' and country '$country' " );
        } else {
            scartLog::logLine("D-[$whoistype] abusecontact NOT found on owner (alias) '$owner' and abusemail '$abuseemail' and country '$country' " );
            $abusecontact = '';
        }

        if ($abusecontact=='') {

            $abusecontact = new Abusecontact();
            // 0-250 length
            $abusecontact->owner = substr($owner,0,250);
            $abusecontact->abusecustom = $abuseemail;
            // use NL country code value
            $abusecontact->abusecountry = $country;
            $abusecontact->police_contact = false;

            // groupby
            $abusecontact->groupby_hours = Systemconfig::get('abuseio.scart::ntd.abusecontact_default_hours',SCART_ABUSECONTACT_NOTSET_DEFAULT_HOURS);
            $ntdtmp = Ntd_template::first();
            $abusecontact->ntd_template_id = $ntdtmp->id;
            $abusecontact->gdpr_approved = false;

            $abusecontact->save();
            scartLog::logLine("D-[$whoistype] New abusecontact created; owner=$abusecontact->owner, country=$country, abusemail=$abuseemail ");

            scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.whois_new_abusecontact',[
                'abuseowner' => $abusecontact->owner . " ($abusecontact->filenumber)",
                'abusecountry' => $abusecontact->abusecountry,
                'abusecontact' => $abusecontact->abusecustom,
            ]);

        }

        return $abusecontact;
    }


    /**
     * Fill WhoIs info from registrar and hoster
     *
     * WITH CACHE
     *
     * @param $record
     * @return mixed
     */
    public static function fillWhois($whois,$abusecontact_id,$whois_type) {

        $abusecontact = Abusecontact::find($abusecontact_id);
        if (!$abusecontact) {
            // fill whois 'unknown'
            $abusecontact = (object) [
                'id' => 0,
                'abusecustom' => SCART_WHOIS_UNKNOWN,
            ];
        }
        $whois = Whois::fillWhoisArray($whois,$abusecontact->id,$whois_type);
        $whois[$whois_type.'_abusecustom'] = $abusecontact->abusecustom;
        return $whois;
    }

    public static function resetCache() {
        SELF::$_whoiscached = [];
    }

    /**
     * Fill WhoIs info from registrar and hoster
     *
     * WITH CACHE
     *
     * @param $record
     * @return mixed
     */
    public static function getWhois($record) {

        $cachekey = 'getWhois#'.$record->host_abusecontact_id.'#'.$record->registrar_abusecontact_id;
        //scartLog::logLine("D-getWhois; cachekey=$cachekey");
        if (isset(SELF::$_whoiscached[$cachekey])) {

            $whois = SELF::$_whoiscached[$cachekey];

        } else {

            $whois = [];

            $whois = SELF::fillWhois($whois,$record->host_abusecontact_id,SCART_HOSTER);
            if ($whois[SCART_HOSTER.'_lookup']=='') {
                // special when custom Abusecontact and no WhoIs info is found -> use IP in record
                $whois[SCART_HOSTER.'_lookup'] = $record->url_ip;
            }

            $whois[SCART_HOSTER.'_proxy_abusecontact'] = '(no proxy)';
            if ($record->proxy_abusecontact_id) {
                $abusecontact = Abusecontact::find($record->proxy_abusecontact_id);
                if ($abusecontact) {
                    $whois[SCART_HOSTER.'_proxy_abusecontact'] = $abusecontact->owner;
                }
            }

            $whois = array_merge($whois,SELF::fillWhois($whois,$record->registrar_abusecontact_id,SCART_REGISTRAR));
            // overrule WHOIS lookup domain -> use always current
            $whois[SCART_REGISTRAR.'_lookup'] = $record->url_host;

            SELF::$_whoiscached[$cachekey] = $whois;
        }

        return $whois;
    }



    public static function fillProxyservice($record,$whois) {

        if (isset($whois[SCART_HOSTER.'_proxy_abusecontact_id']) ) {
            $record->proxy_abusecontact_id = $whois[SCART_HOSTER.'_proxy_abusecontact_id'];
            if (!empty($record->proxy_abusecontact_id)) {
                $proxycontact = Abusecontact::find($record->proxy_abusecontact_id);
                if ($proxycontact) {
                    $record->logText("Got real IP from proxy service hoster: $proxycontact->owner");
                } else {
                    scartLog::logLine("E-[filenumber=$record->filenumber] CANNOT find proxy abusecontact record from record->proxy_abusecontact_id (=$record->proxy_abusecontact_id) !?");
                }
            }
        } else {
            $record->proxy_abusecontact_id = 0;
        }
        if (isset($whois[SCART_HOSTER.'_proxy_call_error'])) {
            if ($whois[SCART_HOSTER.'_proxy_call_error']) {
                // special field
                $record->proxy_call_error = $whois[SCART_HOSTER.'_proxy_call_error'];
                $record->logText("Got error from proxy service: $record->proxy_call_error");
            }
        } else {
            $record->proxy_call_error = '';
        }
        return $record;
    }

    public function beforeDelete()
    {
        $input      = Input::where('registrar_abusecontact_id',$this->id)->exists();
        $inputhost  = Input::where('host_abusecontact_id',$this->id)->exists();
        $ntd        = Ntd::where('abusecontact_id',$this->id)->exists();

        if ($input) {
            throw new ApplicationException('Abuse contact is not removed: input record found');
        } elseif ($inputhost) {
            throw new ApplicationException('Abuse contact is not removed: inputhost record found');
        }  elseif ($ntd) {
            throw new ApplicationException('Abuse contact is not removed: NTD record found');
        }

        return true;
    }


}
