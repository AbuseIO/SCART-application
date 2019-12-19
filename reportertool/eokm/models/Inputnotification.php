<?php
namespace ReporterTool\EOKM\Models;

use ReporterTool\EOKM\Models\Grade_status;
use ReporterTool\EOKM\Models\Input_type;
use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertUsers;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertModel;
use BackendAuth;
use Redirect;
use Db;

class Inputnotification extends ertModel {

    protected $dates = ['deleted_at'];

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    // defaults
    public $attributes = [
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_inputnotification';

    public $hasOne = [
        'notificationStatus' => [
            'reportertool\eokm\models\Notification_status',
            'key' => 'code',
            'otherKey' => 'status_code',
        ],
        'notificationGrade' => [
            'reportertool\eokm\models\Grade_status',
            'key' => 'code',
            'otherKey' => 'grade_code',
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
            'order' => 'created_at DESC, id DESC',
            'conditions' => "dbtable='reportertool_eokm_notification' OR dbtable='reportertool_eokm_input'",
            'delete' => true],
    ];

    public function getStatusCodeOptions($value,$formData) {

        $recs = Notification_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getGradeCodeOptions($value,$formData) {

        $recs = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
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

    // after create, always generate unique filenumber for this record
    public function afterCreate() {
    }


}
