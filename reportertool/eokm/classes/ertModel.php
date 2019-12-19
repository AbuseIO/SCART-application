<?php

namespace reportertool\eokm\classes;

use Db;
use Lang;
use Model;
use Session;
use Backend\Facades\BackendAuth;
use ReporterTool\EOKM\Models\Audittrail;
use ReporterTool\EOKM\Models\Log;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Notification_input;

class ertModel Extends Model {

    public function setAudittrail($on) {
        $old = Session::get('audittrail_on',true);
        Session::put('audittrail_on',$on);
        return $old;
    }

    private function doAudittrail() {
        return Session::get('audittrail_on',true);
    }

    public function __construct() {
        parent::__construct();
    }

    public function logText($logtext) {

        $user_id = ertUsers::getId();

        $log = new Log();
        $log->dbtable = $this->table;
        $log->record_id = $this->id;
        $log->user_id = $user_id;
        $log->logtext = $logtext;
        $log->save();

        // debug log logtext
        ertLog::logLine("D-ertModel.LogText ($log->dbtable); $logtext");

    }

    public function getGeneralWorkuserIdOptions($value,$formData) {

        $workusers = ertUsers::getWorkusers();
        // zet om naar [$key]->title
        $ret = array();
        foreach ($workusers AS $workuser) {
            $ret[$workuser->id] = $workuser->first_name . ' ' . $workuser->last_name . ' (' . $workuser->email . ')';
        }
        return $ret;
    }

    public function generateFilenumber() {

        $filenumber = '';
        if (class_basename($this) == 'Abusecontact') {
            $filenumber = 'A' . sprintf('%010d', $this->id);
        } elseif (class_basename($this) == 'Input') {
            $filenumber = 'I' . sprintf('%010d', $this->id);
        } elseif (class_basename($this) == 'Notification') {
            $filenumber = 'N' .sprintf('%010d', $this->id);
        } elseif (class_basename($this) == 'Ntd') {
            $filenumber = 'M' .sprintf('%010d', $this->id);
        }
        return $filenumber;
    }

    //** AUDITTRAIL **/

    /**
     * beforeUpdate
     * - old, new
     *
     * beforeCreate
     * - new
     *
     * beforeDelete
     * - old
     *
     * Function
     * - INSERT
     * - UPDATE
     * - DELETE
     *
     * ignore
     * - reportertool_eokm_log, reportertool_eokm_audittrail
     *
     */

    private $_debuglogaudit = false;        // a lot of debug log traffic if true ;)
    private $_ignoretables = ['reportertool_eokm_log','reportertool_eokm_audittrail','reportertool_eokm_scrape_cache'];

    private function _auditRecToValues($fieldlist,$rec) {

        // can be a query object
        if (is_object($rec)) $rec = (array) $rec;

        $auditrec = [];
        foreach ($fieldlist AS $key => $val) {
            $value = isset($rec[$key]) ? $rec[$key] : '';
            $auditrec[$key] = $value;
        }
        return serialize($auditrec);
    }

    private function _insertAudittrail($table,$function,$fieldlist,$oldrec,$newrec) {

        /**
         * Make independed audit trail record
         * Can be exported and analyzed without futher db information
         * Can also push this record to external (non-updatable) storage
         *
         */

        if ($this->_debuglogaudit) ertLog::logLine("D-insertAudittrail: table=$table, function=$function; fieldlist=" . implode(',',array_keys($fieldlist)) );
        $audittrail = new Audittrail();
        $audittrail->user = ertUsers::getLogin();
        $audittrail->remote_address = ertUsers::getRemoteAddress();
        $audittrail->dbtable = $table;
        $audittrail->dbfunction = $function;
        $audittrail->fieldlist = implode(',',array_keys($fieldlist));
        $audittrail->oldvalues = ($function!='INSERT') ? $this->_auditRecToValues($fieldlist,$oldrec) : '';
        $audittrail->newvalues = $this->_auditRecToValues($fieldlist,$newrec);
        $audittrail->save();
    }

    public function beforeCreate() {

        if ($this->doAudittrail() && !in_array($this->table,$this->_ignoretables)) {
            $fieldvaluelist = $this->attributesToArray();
            $this->_insertAudittrail($this->table,'INSERT',$fieldvaluelist, '', $fieldvaluelist);
        }
    }

    public function beforeUpdate() {

        if ($this->doAudittrail() && !in_array($this->table,$this->_ignoretables)) {
            $fieldvaluelist = $this->attributesToArray();
            $oldrec = Db::table($this->table)
                ->where('id', $this->id)
                ->first();
            $this->_insertAudittrail($this->table, 'UPDATE', $fieldvaluelist, $oldrec, $fieldvaluelist);
        }
    }

    public function beforeDelete() {

        if ($this->doAudittrail() && !in_array($this->table,$this->_ignoretables)) {
            $fieldvaluelist = $this->attributesToArray();
            $oldrec = Db::table($this->table)
                ->where('id', $this->id)
                ->first();
            $this->_insertAudittrail($this->table, 'DELETE', $fieldvaluelist, $oldrec, $fieldvaluelist);
        }
    }



}
