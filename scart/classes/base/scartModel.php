<?php

namespace abuseio\scart\classes\base;

use abuseio\scart\classes\helpers\scartString;
use Db;
use Lang;
use Model;
use Session;
use Backend\Facades\BackendAuth;
use abuseio\scart\models\Audittrail;
use abuseio\scart\models\Log;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;

class scartModel Extends Model {

    public function setAudittrail($on) {
        $old = Session::get('audittrail_on',true);
        Session::put('audittrail_on',$on);
        return $old;
    }

    private function doAudittrail() {
        // default=off
        return Session::get('audittrail_on',false);
    }

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    public function logText($logtext) {

        $user = scartUsers::getUser();

        $log = new Log();
        $log->record_type = $this->table;
        $log->record_id = $this->id;
        // note: sometimes we are scheduler or a console job; then no login available
        $log->user_id = ($user) ? $user->id : 0;
        $log->logtext = $logtext;
        $log->save();

        // debug log logtext
        scartLog::logLine("D-scartModel.LogText [type=$log->record_type, id=$log->record_id]; $logtext");

    }

    public function getGeneralWorkuserIdOptions($value,$formData) {

        $workusers = scartUsers::getWorkusers();
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
            // mainurl different prefix then items
            $prefix = ($this->url_type==SCART_URL_TYPE_MAINURL) ? 'I' : 'N';
            $filenumber = $prefix . sprintf('%010d', $this->id);
        } elseif (class_basename($this) == 'Notification') {
            // abosulete
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
     * - abuseio_scart_log, abuseio_scart_audittrail, abuseio_scart_scrape_cache
     *
     */

    private $_debuglogaudit = false;        // a lot of debug log traffic if true ;)
    private $_ignoretables = ['abuseio_scart_log','abuseio_scart_audittrail','abuseio_scart_scrape_cache'];

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

        if ($this->_debuglogaudit) scartLog::logLine("D-insertAudittrail: table=$table, function=$function; fieldlist=" . implode(',',array_keys($fieldlist)) );
        $audittrail = new Audittrail();
        $audittrail->user = scartUsers::getLogin();
        $audittrail->remote_address = scartUsers::getRemoteAddress();
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


    /**
     * @description Get the data for the timeline, This method is develop for all the models.
     * @param $id
     * @param string $param
     * @param array $return
     * @return array
     */
    public function getTimeLineData($id, $param = '', $return = []) {
        foreach ($param['query'] as $key => $query) {

            // apply filters
            $records = $this->select($param['select']);

            // inner join

            if (isset($param['join'])) {
                foreach ($param['join'] as $join) {
                    $records->join($join['table'], $join['relation'], $join['operator'], $join['otherrelation'],);
                }
            }

            foreach ($query as $filterkey => $filter) {
                $records->where($filterkey, ($filter != 'id') ? $filter : $id);
            }


            if (isset($param['order']))
            {
                if ($param['order'] == 'latest' ) {
                    $records->latest();
                } else {
                    $records->oldest();
                }

                $records = $records->first();
                $recordsarray[] = $records;
                $totalrecords = 1;
            } else {
                $recordsarray = $records->get();
                $totalrecords = $records->count();
            }



            if($totalrecords > 0) {
                foreach ($recordsarray  as $record) {

                    if(empty($record)) continue;
                    // loop through the found records.
                    foreach ($param['show'] as  $show) {

                        // check if the correct message by query
                        if (isset($show['query']) && $show['query'] != $key) {
                            continue;
                        }
                        // init variables
                        $stdob = new \stdClass();
                        $bool = false;
                        // loop through the pre defined txt, see config
                        foreach ($show as $showkey => $show2) {
                            // key eruit halen,
                            if (strpos($show2, '#') !== false) {
                                $words = scartString::get_strings_between($show2, '#', '#');
                                $string = $show2;
                                // replace words
                                if (count($words) > 0) {
                                    foreach ($words as $Word) {
                                        if (!empty($record) && isset($record->$Word)){
                                            $string = str_replace('#' . $Word . '#', $record->$Word, $string);
                                        }
                                    }
                                    $stdob->{$showkey} = $string;
                                    $bool = true;
                                }

                            } elseif($showkey != 'query') {
                                $stdob->{$showkey} = $show2;
                                $bool = true;
                            }
                        } // foreach show
                        if($bool) {
                            $return[] = $stdob;
                        }
                    } // foreach
                } // foreach
            }
        }
        return $return;
    }
}
