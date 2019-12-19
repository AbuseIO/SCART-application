<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;
use BackendAuth;
use Db;

use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Log;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_input;
use ReporterTool\EOKM\Models\Notification_selected;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Grade_status;
use ReporterTool\EOKM\Models\Input_lock;
use ReporterTool\EOKM\Models\Scrape_cache;

/**
 * Model
 */
class Input extends ertModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_input';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'url' => 'required|url',
        'type_code' => 'required',
        'source_code' => 'required',
        'workuser_id' => 'required',
    ];

    public $hasOne = [
        'inputStatus' => [
            'reportertool\eokm\models\Input_status',
            'key' => 'code',
            'otherKey' => 'status_code'
        ],
        'inputGrade' => [
            'reportertool\eokm\models\Grade_status',
            'key' => 'code',
            'otherKey' => 'grade_code'
        ],
        'inputSource' => [
            'reportertool\eokm\models\Input_source',
            'key' => 'code',
            'otherKey' => 'source_code'
        ],
        'inputType' => [
            'reportertool\eokm\models\Input_type',
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
            'reportertool\eokm\models\Log',
            'key' => 'record_id',
            'order' => 'id DESC',
            'conditions' => "dbtable='reportertool_eokm_input'",
            'delete' => true],
    ];

    public $belongsToMany = [
        'notifications' => [
            'reportertool\eokm\models\Notification',
            'table' => 'reportertool_eokm_notification_input',
            'conditions' => 'reportertool_eokm_notification_input.deleted_at is null',
            'key' => 'input_id',
            'otherKey' => 'notification_id',
            'order' => 'reportertool_eokm_notification.updated_at DESC, reportertool_eokm_notification.id DESC',
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
        return ($lock) ? ertUsers::getFullName($lock->workuser_id) : '(not locked)';
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
            $fields->workuser_id->value = ertUsers::getId();
        }
        if (isset($fields->source_code) && empty($fields->source_code->value)) {
            $fields->source_code->value = ERT_SOURCE_CODE_DEFAULT;
        }
        if (isset($fields->type_code) && empty($fields->type_code->value)) {
            $fields->type_code->value = ERT_TYPE_CODE_DEFAULT;
        }
    }

    /**
     * Aftercreate
     *
     * generate unique filenumber
     * set default status_code op ERT_STATUS_SCHEDULER_SCRAPE
     *
     *
     */
    public function afterCreate() {

        $this->filenumber = $this->generateFilenumber();
        if (empty($this->status_code)) {
            $this->status_code = ERT_STATUS_SCHEDULER_SCRAPE;
        }
        if (empty($this->grade_code)) {
            $this->grade_code = ERT_GRADE_UNSET;
        }
        if (empty($this->url_type)) {
            $this->url_type = ERT_URL_TYPE_MAINURL;
        }
        if (is_null($this->received_at)) {
            // set/init always
            $this->received_at =  date('Y-m-d H:i:s');
        }
        $this->save();
    }

    /**
     * When delete then:
     * - set status_code on close
     * - delete related
     *
     * Note: delete based on Model class-> can be a soft delete
     *
     */
    public function beforeDelete() {

        parent::beforeDelete();

        $this->status_code = ERT_STATUS_CLOSE;
        $this->save();

        ertLog::logLine("D-Input.beforeDelete; delete all related records; input_id=" . $this->id);
        $this->deleteRelated();

        Log::where('dbtable',ERT_INPUT_TABLE)->where('record_id', $this->id)->delete();
    }

    /**
     * Delete related records
     *
     * @param $input_id
     */
    public function deleteRelated() {

        $nots = Notification_input::where('input_id', $this->id)->get();
        foreach ($nots AS $not) {
            // check if not connected to other input
            if (Notification_input::where('input_id','<>',$this->id)->where('notification_id',$not->notification_id)->count() == 0) {
                ertLog::logLine("D-deleteRelated; delete notification (notification_id=$not->notification_id)");
                Log::where('dbtable',ERT_NOTIFICATION_TABLE)->where('record_id', $not->notification_id)->delete();
                Grade_answer::where('record_type',ERT_NOTIFICATION_TYPE)->where('record_id', $not->notification_id)->delete();
                Notification_selected::where('notification_id', $not->notification_id)->delete();
                $notification = Notification::find($not->notification_id);
                if ($notification) {
                    Scrape_cache::where('code', $notification->url_hash)->delete();
                    $notification->delete();
                }
            } else {
                ertLog::logLine("D-deleteRelated; notification (notification_id=$not->notification_id) also connected to other input - skip delete");
            }
            Notification_input::where('input_id',$this->id)->delete();
        }
        Notification_input::where('input_id',$this->id)->delete();
    }

}
