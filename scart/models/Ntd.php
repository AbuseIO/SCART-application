<?php namespace abuseio\scart\models;

use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;

/**
 * Model
 */
class Ntd extends scartModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_ntd';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $hasOne = [
        'abusecontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
        'template' => [
            'abuseio\scart\models\NTDtemplate',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
    ];

    public $hasMany = [
        'ntd_urls' => [
            'abuseio\scart\models\Ntd_url',
            'key' => 'ntd_id',
            'order' => 'id DESC',
            'delete' => true],
        'logs' => [
            'abuseio\scart\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "record_type='abuseio_scart_ntd'",
            'delete' => true],
    ];

    public function getStatusCodeOptions($value,$formData) {

        $recs = Ntd_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getAbusecontactOwnerAttribute($value) {
        $ac = Abusecontact::find($this->abusecontact_id);
        return ($ac) ? $ac->owner : '';
    }

    public function getAbusecontactMailAttribute($value) {
        return (isset($this->abusecontact->id)) ? $this->abusecontact->abusecustom : '';
    }

    public function getAbusecontactInterfaceAttribute($interface = '') {

        if(isset($this->status_code) && in_array($this->status_code, [SCART_SENT_SUCCES, SCART_SENT_FAILED, SCART_SENT_API_FAILED, SCART_SENT_API_SUCCES]) ) {
            $interface = (in_array($this->status_code, [SCART_SENT_SUCCES, SCART_SENT_FAILED])) ? 'Mail' : 'Api';
        }
        return $interface;
    }

    public function getAbusecontactSenddateAttribute($value) {
        return (isset($this->status_code) && in_array($this->status_code, [SCART_SENT_SUCCES, SCART_SENT_API_SUCCES])) ? $this->created_at->format('Y-m-d H:i:s') : '';
    }

    /**
     * Aftercreate
     * generate unique filenumber
     */
    public function afterCreate() {
        $this->filenumber = $this->generateFilenumber();
        $this->save();
    }

    /**
     * create NTD (url)
     * create ntd_url record if not found
     *
     * @param $abusecontact_id
     * @param $url
     * @param $record
     * @param $initNTDstatus
     * @param $interval
     *
     * @return Ntd|string
     */
    public static function createNTDurl($abusecontact_id, $record, $initNTDstatus=SCART_NTD_STATUS_GROUPING, $interval=1, $type=SCART_NTD_TYPE_UNKNOWN) {

        $ntd = '';

        $abusecontact = Abusecontact::find($abusecontact_id);
        if ($abusecontact) {

            if (scartGrade::isLocal($abusecontact->abusecountry) && $abusecontact->gdpr_approved ) {

                /**
                 * 2-1-2020;
                 * - converted SCART_NTD_STATUS_QUEUE_DIRECTLY/SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE to SCART_NTD_STATUS_GROUPING with 1 hour
                 *
                 * 26-6-2020
                 * - keep SCART_NTD_STATUS_QUEUE_DIRECTLY && SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE -> trigger 1 hour is done in SendNTD
                 *
                 */

                $directntd = ($initNTDstatus==SCART_NTD_STATUS_QUEUE_DIRECTLY || $initNTDstatus==SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE);
                if ($directntd) {
                    $groupby_hour_threshold = 1;
                    //$initNTDstatus = SCART_NTD_STATUS_GROUPING;
                } else {
                    $groupby_hour_threshold = $abusecontact->groupby_hours * $interval;
                }

                // create NTD if not yet ready
                if (!($ntd = Ntd::where('abusecontact_id',$abusecontact_id)->where('status_code',$initNTDstatus)->first() )) {
                    scartLog::logLine("D-Create new NTD for abusecontact '$abusecontact->owner' (type=$type) with status=$initNTDstatus");
                    $ntd = new Ntd();
                    $ntd->abusecontact_id = $abusecontact_id;
                    $ntd->abusecontact_type = $type;
                    $ntd->status_time = date('Y-m-d H:i:s');
                    $ntd->groupby_start = date('Y-m-d H:i:s');
                    $ntd->groupby_hour_count = 0;
                    $ntd->groupby_hour_threshold = $groupby_hour_threshold;
                    $ntd->status_code = $initNTDstatus;
                    $ntd->save();
                    $ntd->logText("Created NTD with status=$initNTDstatus");
                } else {

                    // 26-5-2020; obsolute

                    // if already grouping and not already reset, then reset groupby_hour_threshold for next hour
                    /*
                    if ($directntd && ($ntd->groupby_hour_threshold == $abusecontact->groupby_hours * $interval)) {
                        $ntd->groupby_hour_threshold = $groupby_hour_threshold;
                        $ntd->save();
                        $ntd->logText("Reset groupby_hour_threshold on '$ntd->groupby_hour_threshold' (next hour) because of DIRECT queuing");
                    }
                    */
                }

                $url = $record->url;

                // create link if not yet there
                $ntd_url = Ntd_url::where('ntd_id',$ntd->id)->where('url',$url)->first();
                if (!($ntd_url)) {
                    scartLog::logLine("D-NTD for abusecontact '$abusecontact->owner' with status=$initNTDstatus; add '$url'");
                    $ntd->logText("Add NTD_url ($url) ");
                    $ntd_url = new Ntd_url();
                    $ntd_url->record_id = $record->id;
                    $ntd_url->record_type = strtolower(class_basename($record));
                    $ntd_url->ntd_id = $ntd->id;
                    $ntd_url->url = $url;
                    $ntd_url->note = $record->ntd_note;
                    $ntd_url->firstseen_at = $record->firstseen_at;
                }
                // current IP
                $ntd_url->ip = $record->url_ip;
                // current action time is lastseen
                $ntd_url->lastseen_at = date('Y-m-d H:i:s');
                // if not set then (min) 1 time
                $ntd_url->online_counter = ($record->online_count) ? $record->online_counter : 1;
                $ntd_url->save();

                // just init, counters update is done in scheduler NTDsend

            } else {
                scartLog::logLine("D-createUpdateNTD: abusecountry (=$abusecontact->abusecountry) from abusecontact '$abusecontact->owner' NOT in NL and/or GDPR approved (=$abusecontact->gdpr_approved) - skip creating NTD ");
            }

        } else {
            if ($abusecontact_id!=0) {
                scartLog::logLine("E-createUpdateNTD: Cannot find abusecontact; abusecontact_id=$abusecontact_id !?");
            }
        }

        return $ntd;
    }

    // OBSOLUTE - replaced by removeUrlgrouping, don't miss any NTD

    public static function removeUrl($abusecontact_id,$url) {

        $ntd = Ntd::where('abusecontact_id',$abusecontact_id)->where('status_code',SCART_NTD_STATUS_GROUPING)->first();
        if ($ntd) {
            // delete
            $ntd_url = Ntd_url::where('ntd_id',$ntd->id)->where('url',$url)->first();
            if ($ntd_url) {
                $ntd_url->delete();
            }
            // if last one, then close NTD
            if (Ntd_url::where('ntd_id',$ntd->id)->count() == 0) {
                scartLog::logLine("D-NTD set on close (ntd_id=$ntd->id); nomore urls ");
                $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                $ntd->logText("Close because all ntd_urls offline");
                $ntd->save();
            }

        }
        return $ntd;
    }

    public static function removeUrlgrouping($url) {

        $ntdgroupings = Ntd_url::join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
            ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_GROUPING,SCART_NTD_STATUS_QUEUE_DIRECTLY,SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE])
            ->where(SCART_NTD_URL_TABLE.'.url',$url)
            ->select(SCART_NTD_URL_TABLE.'.id AS ntd_url_id',SCART_NTD_TABLE.'.id AS ntd_id')
            ->get();

        foreach ($ntdgroupings AS $ntdgrouping) {

            // delete url
            $ntd_url = Ntd_url::find($ntdgrouping->ntd_url_id);
            if ($ntd_url) {
                $ntd_url->delete();
            }

            $ntd = Ntd::find($ntdgrouping->ntd_id);
            if ($ntd) {
                // log url remove
                $ntd->logText("Url (image) '$url' removed from NTD");
                // if last one, then close NTD
                if (Ntd_url::where('ntd_id',$ntdgrouping->ntd_id)->count() == 0) {
                    scartLog::logLine("D-NTD set on close (ntd_id=$ntdgrouping->ntd_id); nomore urls ");
                    $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                    $ntd->logText("Close because all ntd_urls offline");
                    $ntd->save();
                }
            }

        }
    }


}
