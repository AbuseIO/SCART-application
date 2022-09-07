<?php namespace abuseio\scart\models;

use abuseio\scart\classes\iccam\scartICCAMfields;
use Db;
use Config;
use BackendAuth;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\iccam\scartICCAMmapping;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Log;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_status;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input_lock;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\Input_extrafield;
use October\Rain\Translation\Translator;
use abuseio\scart\classes\iccam\scartExportICCAM;
use abuseio\scart\models\Input_history;

/**
 * Model
 */
class Input extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input';

    /**
     * @var array Validation rules
     */
    public $rules = [
        // disable URL validation -> not all urls can be handled by this validator, eg pornjav.online is reported as invalid
        // the correct way to solve this problem, is to set a newer rule with the guidelines of Wintercms. We can maintaine this rule and share with others, without
        // change to core code of wintercms
//        'url' => 'required|URLvEOKM',
        'url' => 'required',
        'type_code' => 'required',
        'source_code' => 'required',
        'workuser_id' => 'required',
    ];

    public $hasOne = [
        'inputStatus' => [
            'abuseio\scart\models\Input_status',
            'key' => 'code',
            'otherKey' => 'status_code'
        ],
        'inputGrade' => [
            'abuseio\scart\models\Grade_status',
            'key' => 'code',
            'otherKey' => 'grade_code'
        ],
        'inputSource' => [
            'abuseio\scart\models\Input_source',
            'key' => 'code',
            'otherKey' => 'source_code'
        ],
        'inputType' => [
            'abuseio\scart\models\Input_type',
            'key' => 'code',
            'otherKey' => 'type_code'
        ],
        'workuser' => [
            'Backend\Models\User',
            'table' => 'backend_users',
            'key' => 'id',
            'otherKey' => 'workuser_id',
        ],
    ];

    public $hasMany = [
        'logs' => [
            'abuseio\scart\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "record_type='abuseio_scart_input'",
            'delete' => true],
        'history' => [
            'abuseio\scart\models\Input_history',
            'key' => 'input_id',
            'order' => 'id DESC',
            'delete' => true],
    ];

    public $belongsToMany = [
        'items' => [
            'abuseio\scart\models\Input',
            'table' => 'abuseio_scart_input_parent',
            'key' => 'parent_id',
            'otherKey' => 'id',
            ],
    ];

    // @To-do; lang select
    public function getStatusCodeOptions($value,$formData) {

        $recs = Input_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    // @To-do; lang select
    public function getGradeCodeOptions($value,$formData) {

        $recs = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    // @To-do; lang select
    public function getSourceCodeOptions($value,$formData) {

        $recs = Input_source::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getTypeCodeOptions($value,$formData) {

        $recs = Input_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getWorkuserIdOptions($value,$formData) {
        return $this->getGeneralWorkuserIdOptions($value,$formData);
    }

    public function getLockedAttribute() {
        $lock = Input_lock::where('input_id',$this->id)->first();
        return ($lock) ? scartUsers::getFullName($lock->workuser_id) : '(not locked)';
    }

    public function getNoteNotEmptyAttribute() {

        $filled = false;
        $memo = trim($this->note);
        if ($memo!='') {
            if ($memo != ';') {
                if (strpos($memo, 'Upload from ERT')===false) {
                    $filled = true;
                }
            }
        }
        return $filled;
    }

    public function getNotedisplayAttribute() {

        $memo = trim($this->note);
        $memo = str_replace("\n","&nbsp;",$memo);
        $memo = str_replace("\r","&nbsp;",$memo);
        $memo = strip_tags($memo);
        return $memo;;
    }

    // ** view_type=LIST columns ** //

    public function getUrlDataAttribute() {

        $src = SCART_IMAGE_NOT_FOUND;
        if ($this->url_type == SCART_URL_TYPE_IMAGEURL) {
            $src = scartBrowser::getImageBase64($this->url,$this->url_hash);
        } elseif ($this->url_type == SCART_URL_TYPE_VIDEOURL) {
            $src = scartBrowser::getImageBase64($this->url,$this->url_hash, false,SCART_IMAGE_IS_VIDEO);
        } else {
            $src = scartBrowser::getImageBase64($this->url,$this->url_hash, false,SCART_IMAGE_MAIN_NOT_FOUND);
        }

        if ($this->url_image_width == 0 || $this->url_image_height == 0) {
            $arr = explode(',',$src);
            if (count($arr) > 1) {
                $data = base64_decode($arr[1]);
                $imgsiz = @getimagesizefromstring($data);
                if ($imgsiz!==false) {
                    $this->url_image_width = $imgsiz[0];
                    $this->url_image_height = $imgsiz[1];
                    $this->save();
                }
            }
        }

        return $src;
    }

    private $_generalfields = [
        'url' => '#url',
        'url_referer' => 'referer',
        'filenumber' => 'filenumber',
        'type_code' => 'input',
        'source_code' => 'source',
        'url_type' => 'type',
    ];
    private $_hosterfields = [
        'host_owner' => 'hoster',
        'host_lookup' => '_IP',
        'host_country' => '_country',
        'host_abusecontact' => '_abuse',
        'host_abusecustom' => '_custom',
        'host_proxy_abusecontact' => '_proxy ',
    ];
    private $_registrarfields = [
        'registrar_owner' => 'registrar',
        'registrar_lookup' => '_domain ',
        'registrar_country' => '_country ',
        'registrar_abusecontact' => '_abuse ',
        'registrar_abusecustom' => '_custom ',
    ];

    public function getInfoDataAttribute() {

        $general = $hoster = $registrar = [];

        foreach ($this->_generalfields AS $fld => $label) {
            $link = (substr($label,0,1)=='#');
            if ($link) $label = substr($label,1);
            $field = [
                'name' => $fld,
                'label' => $label,
                'value' => (isset($this->$fld)?$this->$fld:'(unknown)'),
                'mark' => false,
                'link' => $link,
            ];
            $general[] = $field;
        }

        // get fields from Abusecontact WhoIs info
        $whoisfields = Abusecontact::getWhois($this);
        //trace_log($whoisfields);

        foreach ($this->_hosterfields AS $fld => $label) {
            $field = [
                'name' => $fld,
                'label' => $label,
                'value' => (($whoisfields[$fld])?$whoisfields[$fld]:'(unknown)'),
                'mark' => false,
                'link' => false,
            ];
            $hoster[] = $field;
        }
        $whoisraw = (isset($whoisfields[SCART_HOSTER.'_rawtext'])) ? $whoisfields[SCART_HOSTER.'_rawtext'] : '';

        $extrafields = Input_extrafield::where('input_id',$this->id)->get();
        $extra = [];
        if ($extrafields) {
            foreach ($extrafields AS $extrafield) {
                $field = [
                    'name' => $extrafield->type.'_'.$extrafield->label,
                    'label' => $extrafield->type.'_'.$extrafield->label,
                    'value' => $extrafield->value,
                    'mark' => false,
                    'link' => false,
                ];
                $extra[] = $field;
            }
        }

        $info = [
            'general' => $general,
            'hoster' => $hoster,
            'registrar' => $registrar,
            'whoisraw' => $whoisraw,
        ];
        if (count($extra) > 0) {
            $info['extra'] = $extra;
        }
        return $info;
    }

    public function getOptionDataAttribute() {

        $cssnote = ($this->note!='') ? 'grade_button_notefilled' : '';
        return $cssnote;
    }

    private $grade_question_id = 0;
    public function getPoliceReasonAttribute() {

        if ($this->grade_question_id==0) {
            // get specific question (id)
            $url_type = Grade_question::getQuestionUrlType($this->url_type,SCART_GRADE_QUESTION_GROUP_POLICE);
            $gradeQuestion = Grade_question::where('questiongroup', SCART_GRADE_QUESTION_GROUP_POLICE)
                ->where('name','reason')->where('url_type',$url_type)->first();
            $this->grade_question_id = ($gradeQuestion) ? $gradeQuestion->id : 0;
        }

        // get answer
        $reason = Grade_answer::where('record_id',$this->id)
            ->where('record_type',SCART_INPUT_TYPE)
            ->where('grade_question_id',$this->grade_question_id)
            ->first();
        $reason = ($reason) ? unserialize($reason->answer) : '';
        $reason = (is_array($reason) ? $reason[0] : '');
        // get option label
        $reason = ($reason) ? Grade_question_option::where('grade_question_id',$this->grade_question_id)
            ->where('value',$reason)
            ->first() : '';
        $reason = ($reason) ? $reason->label : '';
        //scartLog::logLine("D-Police reason=$reason" );
        return $reason;
    }

    private $_hostabusecontact = '';
    public function getHosterCountryAttribute() {

        if ($this->_hostabusecontact=='') {
            $this->_hostabusecontact = Abusecontact::find($this->host_abusecontact_id);
        }
        return ($this->_hostabusecontact) ? $this->_hostabusecontact->abusecountry : '';
    }
    public function getHosterEmailAttribute() {

        if ($this->_hostabusecontact=='') {
            $this->_hostabusecontact = Abusecontact::find($this->host_abusecontact_id);
        }
        return ($this->_hostabusecontact) ? $this->_hostabusecontact->abusecustom : '';
    }

    /**
     * filterFields
     *
     * fill defaults when not set
     *
     * @param $fields
     * @param null $context
     *
     */
    public function filterFields ($fields, $context = null) {

        if (isset($fields->workuser_id) && empty($fields->workuser_id->value)) {
            $fields->workuser_id->value = scartUsers::getId();
        }
        if (isset($fields->source_code) && empty($fields->source_code->value)) {
            $fields->source_code->value = SCART_SOURCE_CODE_DEFAULT;
        }
        if (isset($fields->type_code) && empty($fields->type_code->value)) {
            $fields->type_code->value = SCART_TYPE_CODE_DEFAULT;
        }
    }

    /**
     * Aftercreate
     *
     * generate unique filenumber
     * set default status_code op SCART_STATUS_SCHEDULER_SCRAPE
     *
     *
     */
    public function afterCreate() {

        if (empty($this->status_code)) {

            // log old/new for history
            $this->logHistory(SCART_INPUT_HISTORY_STATUS,$this->status_code,SCART_STATUS_SCHEDULER_SCRAPE,'Inputs; init record created');

            $this->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
        }
        if (empty($this->grade_code)) {
            $this->grade_code = SCART_GRADE_UNSET;
        }
        if (empty($this->url_type)) {
            $this->url_type = SCART_URL_TYPE_MAINURL;
        }
        if (is_null($this->received_at)) {
            // set/init always
            $this->received_at =  date('Y-m-d H:i:s');
        }
        $this->filenumber = $this->generateFilenumber();
        $this->save();
    }

    /**
     * When delete then:
     * - delete related
     *
     */
    public function beforeDelete() {

        parent::beforeDelete();

        // check if status is checkonline then remove from ntd
        $this->removeNtdIccam(false);

        // delete parent related items
        $this->deleteRelated();

        // delete in related tables
        Log::where('record_type',SCART_INPUT_TABLE)->where('record_id', $this->id)->delete();
        Grade_answer::where('record_type',SCART_INPUT_TABLE)->where('record_id', $this->id)->delete();
        Input_selected::where('input_id', $this->id)->delete();
        Scrape_cache::delCache($this->url_hash);
        Input_extrafield::where('input_id',$this->id)->delete();
        Input_history::where('input_id',$this->id)->delete();

        // after this, record will be deleted
    }

    /**
     * Delete related records ($this->id)
     *
     */
    public function deleteRelated() {

        $items = Input_parent::where('parent_id', $this->id)->get();
        if (count($items) > 0) {
            scartLog::logLine("D-Input.beforeDelete; delete all related records; input_id=" . $this->id);
            foreach ($items AS $itemparent) {
                // check if not connected to other input
                if (Input_parent::where('parent_id','<>',$this->id)->where('input_id',$itemparent->input_id)->count() == 0) {
                    scartLog::logLine("D-Input.deleteRelated; delete item (input_id=$itemparent->input_id)");
                    // skip own record
                    if ($itemparent->input_id!=$this->id) {
                        $item = Input::find($itemparent->input_id);
                        if ($item) {
                            $item->delete();
                        }
                    }
                } else {
                    scartLog::logLine("D-Input.deleteRelated; item (input_id=$itemparent->input_id) also connected to other input - skip delete");
                }
            }
            Input_parent::where('parent_id', $this->id)->delete();
        }
    }

    public function removeNtdIccam($iccamcr=false) {

        /**
         * SPECIAL STATUS;
         *
         * url can be part of a NTD.
         * And with ICCAM interface
         *
         */
        if ($this->status_code == SCART_STATUS_SCHEDULER_CHECKONLINE || $this->status_code == SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL ) {

            // don't forget to remove from NTD(s)
            $this->logText("Remove status (MANUAL) CHECKONLINE; remove also from any (grouping) NTD's");
            Ntd::removeUrlgrouping($this->url);

            if ($iccamcr && scartICCAMmapping::isActive()) {

                if (scartICCAMfields::getICCAMreportID($this->reference)) {

                    // report ICCAM

                    // ICCAM content removed
                    $this->logText("Remove status CHECKONLINE; inform ICCAM about CONTENT_REMOVED");
                    scartExportICCAM::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                        'record_type' => class_basename($this),
                        'record_id' => $this->id,
                        'object_id' => $this->reference,
                        'action_id' => SCART_ICCAM_ACTION_CR,
                        'country' => '',                        // hotline default
                        'reason' => 'SCART content removed',
                    ]);

                }

            }

        }

    }


    /**
     * get item record on hash
     *
     * @param $hash
     * @return mixed
     */
    public static function getItemOnHash($hash) {
        return Input::where('url_hash',$hash)->where('url_type','<>',SCART_URL_TYPE_MAINURL)->first();
    }

    /**
     * get item record on url
     *
     * @param $url
     * @return mixed
     */
    public static function getItemOnUrl($url) {
        return Input::where('url',$url)->where('url_type','<>',SCART_URL_TYPE_MAINURL)->first();
    }

    // Extra fields

    public function getExtrafieldValue($type,$field) {

        $extrafield = Input_extrafield::where('input_id',$this->id)->where('type',$type)->where('label',$field)->first();
        return ($extrafield) ? $extrafield->value : '';
    }
    public function addExtrafield($type,$field,$value) {

        try {

            //scartLog::logLine("D-input.addExtrafield; input_id=$this->id, type=$type, field=$field, value=$value ");

            $extrafield = Input_extrafield::where('input_id',$this->id)->where('type',$type)->where('label',$field)->first();
            if (!$extrafield) {
                $extrafield = new Input_extrafield();
                $extrafield->input_id = $this->id;
                $extrafield->type = $type;
                $extrafield->label = $field;
            }
            $extrafield->value = $value;
            $extrafield->save();

        } catch (\Exception $err) {

            scartLog::logLine("W-input.addExtrafield; error on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    // History

    public function logHistory($tag,$old,$new,$comment,$force=false) {

        if (($old !== $new) || $force) {
            $history = new Input_history();
            $history->input_id = $this->id;
            $history->tag = $tag;
            $history->old = $old;
            $history->new = $new;
            $history->comment = $comment;
            $history->workuser_id = scartUsers::getId();
            $history->save();
        } else {
            scartLog::logLine("W-Log history; no change in old and new; tag=$tag, old=$old, new=$new, comment=$comment");
        }
    }


    public function getGradeCodesofInputGroup($gradecodes = [])
    {
        $items = Input_parent::where([['parent_id', $this->id], ['input_id', '!=', $this->id]]);
        if ($items->count() >= 0) {
            foreach ($items->get() as $item) {
                if (!array_key_exists($item->item->grade_code, $gradecodes)) {
                    $gradecodes[$item->item->grade_code] = 1;
                } else {
                    $gradecodes[$item->item->grade_code]++;
                }
            }
        }
        return $gradecodes;
    }


}
