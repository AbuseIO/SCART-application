<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertModel;
use reportertool\eokm\classes\ertLog;

/**
 * Model
 */
class Ntd extends ertModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_ntd';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $hasOne = [
        'abusecontact' => [
            'reportertool\eokm\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
    ];

    public $hasMany = [
        'ntd_urls' => [
            'reportertool\eokm\models\Ntd_url',
            'key' => 'ntd_id',
            'order' => 'id DESC',
            'delete' => true],
        'logs' => [
            'reportertool\eokm\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "dbtable='reportertool_eokm_ntd'",
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
    public static function createNTDurl($abusecontact_id, $record, $initNTDstatus=ERT_NTD_STATUS_GROUPING, $interval=1) {

        $ntd = '';

        $abusecontact = Abusecontact::find($abusecontact_id);
        if ($abusecontact) {

            if (ertGrade::isNL($abusecontact->abusecountry) && $abusecontact->gdpr_approved ) {

                // create NTD if not yet ready
                if (!($ntd = Ntd::where('abusecontact_id',$abusecontact_id)->where('status_code',$initNTDstatus)->first() )) {
                    ertLog::logLine("D-Create new NTD for abusecontact '$abusecontact->owner' with status=$initNTDstatus");
                    $ntd = new Ntd();
                    $ntd->abusecontact_id = $abusecontact_id;
                    $ntd->status_code = $initNTDstatus;
                    $ntd->status_time = date('Y-m-d H:i:s');
                    $ntd->groupby_start = date('Y-m-d H:m:s');
                    $ntd->groupby_hour_count = 0;
                    if ($initNTDstatus==ERT_NTD_STATUS_QUEUE_DIRECTLY || $initNTDstatus==ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE) {
                        $ntd->groupby_hour_threshold = 0;
                    } else {
                        $ntd->groupby_hour_threshold = $abusecontact->groupby_hours * $interval;
                    }
                    $ntd->save();
                    $ntd->logText("Created NTD with status=$initNTDstatus");
                } else {
                    if (empty($ntd->groupby_hour_threshold) && $ntd->status_code!=ERT_NTD_STATUS_QUEUE_DIRECTLY && $ntd->status_code!=ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE) {
                        // threshold must always be set
                        $ntd->groupby_hour_threshold = $abusecontact->groupby_hours * $interval;
                        $ntd->save();
                    }
                }

                $url = $record->url;

                // create link if not yet there
                if (!($ntd_url = Ntd_url::where('ntd_id',$ntd->id)->where('url',$url)->first() )) {
                    ertLog::logLine("D-NTD for abusecontact '$abusecontact->owner' with status=$initNTDstatus; add '$url'");
                    $ntd->logText("Add NTD_url ($url) ");
                    $ntd_url = new Ntd_url();
                    $ntd_url->record_id = $record->id;
                    $ntd_url->record_type = strtolower(class_basename($record));
                    $ntd_url->ntd_id = $ntd->id;
                    $ntd_url->url = $url;
                    $ntd_url->note = $record->note;
                    $ntd_url->firstseen_at = $record->firstseen_at;
                }
                if ($ntd_url) {
                    // admin fields
                    $ntd_url->lastseen_at = $record->lastseen_at;
                    $ntd_url->online_counter = $record->online_counter;
                    $ntd_url->save();
                }

                // just init, counters update is done in scheduler NTDsend

            } else {
                ertLog::logLine("D-createUpdateNTD: abusecountry (=$abusecontact->abusecountry) from abusecontact '$abusecontact->owner' NOT in NL and/or GDPR approved (=$abusecontact->gdpr_approved) - skip creating NTD ");
            }

        } else {
            if ($abusecontact_id!=0) {
                ertLog::logLine("E-createUpdateNTD: Cannot find abusecontact; abusecontact_id=$abusecontact_id !?");
            }
        }

        return $ntd;
    }

    public static function removeUrl($abusecontact_id,$url) {

        $ntd = Ntd::where('abusecontact_id',$abusecontact_id)->where('status_code',ERT_NTD_STATUS_GROUPING)->first();
        if ($ntd) {
            // delete
            $ntd_url = Ntd_url::where('ntd_id',$ntd->id)->where('url',$url)->first();
            if ($ntd_url) {
                $ntd_url->delete();
            }
            // if last one, then close NTD
            if (Ntd_url::where('ntd_id',$ntd->id)->count() == 0) {
                ertLog::logLine("D-NTD set on close (ntd_id=$ntd->id); nomore urls ");
                $ntd->status_code = ERT_NTD_STATUS_CLOSE;
                $ntd->logText("Close because all ntd_urls offline");
                $ntd->save();
            }

        }
        return $ntd;
    }



}
