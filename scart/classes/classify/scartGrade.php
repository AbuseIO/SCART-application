<?php
namespace abuseio\scart\classes\classify;

use abuseio\scart\models\Systemconfig;
use Db;
use Config;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_lock;
use abuseio\scart\models\Input_selected;
use abuseio\scart\models\Input_parent;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\export\scartExport;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;

class scartGrade {

    private static $_last = '';

    /**
     * Get items
     *
     * @param $input_id
     * @param bool $notignored
     * @return mixed
     */

    public static function getItems($listrecords) {

        if (!is_array($listrecords)) $listrecords = [$listrecords];
        $items = self::getItemsQuery($listrecords)
            ->orderBy(SCART_INPUT_TABLE.'.id')
            ->select(SCART_INPUT_TABLE.'.*')
            ->get();
        return $items;
    }

    public static function countItems($listrecords) {

        $itemscount = self::getItemsQuery($listrecords)
            ->orderBy(SCART_INPUT_TABLE.'.id')
            ->select(SCART_INPUT_TABLE.'.*')
            ->count();
        return $itemscount;
    }

    public static function getItemsQuery($listrecords) {

        if (!is_array($listrecords)) $listrecords = [$listrecords];

        $query = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id', $listrecords);
        return $query;
    }


    public static function getItemsFrom($input_id, $fromID, $limit) {

        $items = self::getItemsQuery($input_id)
            ->where(SCART_INPUT_TABLE.'.id', '>=', $fromID)
            ->select(SCART_INPUT_TABLE.'.*')
            ->orderBy(SCART_INPUT_TABLE.'.id')
            ->take($limit)
            ->get();
        return $items;
    }

    public static function getItemsOnIds($listrecords) {

        if (!is_array($listrecords)) $listrecords = [$listrecords];
        $query = Input::whereIn(SCART_INPUT_TABLE.'.id', $listrecords)->get();
        return $query;
    }

    public static function countItemsIllegalWithNoRegistrarHosterSet($input_id) {

        // get illegal items with status
        $itemscount = self::getItemsQuery($input_id)
            ->where(SCART_INPUT_TABLE.'.grade_code', SCART_GRADE_ILLEGAL)
            ->where(function($query) {
                $query->orWhere(SCART_INPUT_TABLE.'.registrar_abusecontact_id', 0)
                    ->orWhere(SCART_INPUT_TABLE.'.host_abusecontact_id', 0);
            })
            ->count();
        return $itemscount;
    }

    /**
     * Get items based on status and compare
     *
     * @param $input_id
     * @param $withstatus
     * @return mixed
     */
    public static function getItemsWithGrade($listrecords,$withgrade,$compare='=') {

        // get items with specific grading
        $items = self::getItemsQuery($listrecords)
            ->where(SCART_INPUT_TABLE.'.grade_code', $compare, $withgrade)
            ->orderBy(SCART_INPUT_TABLE.'.id')
            ->select(SCART_INPUT_TABLE.'.*')
            ->get();
        return $items;
    }

    public static function countItemsWithGrade($listrecords,$withgrade,$compare='=') {

        if (!is_array($listrecords)) $listrecords = [$listrecords];

        // get items with specific grading
        $itemscount = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id' , '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id',$listrecords)
            ->where(SCART_INPUT_TABLE.'.grade_code', $compare, $withgrade)
            ->count();
        return $itemscount;
    }

    /**
     * Get selected items
     *
     * @param $workuser_id
     * @param $input_id
     * @return mixed
     */
    public static function getSelectedItems($workuser_id,$listrecords) {

        if (!is_array($listrecords)) $listrecords = [$listrecords];

        $items = Input_parent::whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id',$listrecords)
            ->join(SCART_INPUT_SELECTED_TABLE, SCART_INPUT_SELECTED_TABLE.'.input_id' , '=', SCART_INPUT_PARENT_TABLE.'.input_id')
            ->where(SCART_INPUT_SELECTED_TABLE.'.workuser_id',$workuser_id)
            ->where(SCART_INPUT_SELECTED_TABLE.'.set','1')
            ->select(SCART_INPUT_PARENT_TABLE.'.input_id')
            ->get();
        return $items;
    }
    public static function countSelectedItems($workuser_id,$listrecords) {

        if (!is_array($listrecords)) $listrecords = [$listrecords];

        // count selected items
        $itemscount = Input_parent::whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id',$listrecords)
            ->join(SCART_INPUT_SELECTED_TABLE, SCART_INPUT_SELECTED_TABLE.'.input_id' , '=', SCART_INPUT_PARENT_TABLE.'.input_id')
            ->where(SCART_INPUT_SELECTED_TABLE.'.workuser_id',$workuser_id)
            ->where(SCART_INPUT_SELECTED_TABLE.'.set','1')
            ->count();
        return $itemscount;
    }

    /**
     * Next input with grading items
     *
     * If <current> then select input_id
     * Only inputs with workuser_id
     *
     * @param $input_id
     * @param $workuser_id
     * @param bool $current
     * @return \stdClass|string
     */
    public static function next($input_id,$workuser_id,$current=false) {

        $next = '';

        if ($input_id==='') $input_id = 0;

        // get first item with next (>=0) or previous (<0) input_id
        $order = ($input_id >= 0) ? 'asc' : 'desc';
        if ($current) {
            $compare = '=';
        } else {
            $compare = ($input_id >= 0) ? '>' : '<';
        }
        $input_id = abs($input_id);
        if (empty($workuser_id)) $workuser_id = scartUsers::getId();

        // 2019/5/27/Gs: select on input status_code=SCART_STATUS_GRADE, not how many open items
        if ($current) {
            $input = Input::find($input_id);
        } else {
            $input = Input
                ::where('status_code',SCART_STATUS_GRADE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->where('workuser_id',$workuser_id)
                ->where('id',$compare,$input_id)
                ->orderBy('id',$order)
                ->select('*')
                ->first();
        }

        if ($input) {

            scartLog::logLine("D-scartGrade.next: (id $compare $input_id); got items with input_id=" . $input->id);

            $next = new \stdClass();
            $next->input = $input;
            $next->items = self::getItems($input->id);

            scartLog::logLine("D-scartGrade.next: number of items: " . count($next->items) );

        } else {
            scartLog::logLine("D-scartGrade.next: no items (anymore)");
        }

        return $next;
    }

    /**
     * Get input(s) to grade
     *
     * @param int $workuser_id
     * @return array
     */

    public static function inputToGrade($workuser_id=0,$currentid=0) {

        if (empty($workuser_id)) $workuser_id = scartUsers::getId();

        // 2019/5/27/Gs: select on input status_code=SCART_STATUS_GRADE, not how many open items
        $inputids = Input
            ::where('status_code',SCART_STATUS_GRADE)
            ->where('url_type',SCART_URL_TYPE_MAINURL)
            ->where('workuser_id',$workuser_id)
            ->select('id AS input_id')
            ->get();
        $cnt = count($inputids);

        if ($cnt > 0) {

            $number = Input
                ::where('status_code',SCART_STATUS_GRADE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->where('workuser_id',$workuser_id)
                ->where('id','<=',$currentid)
                ->count();

            $ret = [
                'first' => $inputids[0]->input_id,
                'last' => $inputids[$cnt - 1]->input_id,
                'number' => $number,
                'count' => $cnt,
            ];
        } else {
            $ret = [
                'first' => 0,
                'last' => 0,
                'number' => 0,
                'count' => 0,
            ];
        }

        //scartLog::logLine("D-scartGrade.inputToGrade: workuser_id=$workuser_id, ret: " . print_r($ret,true) );
        return $ret;
    }

    /**
     * Get work for user -> input
     *
     * @param int $workuser_id
     * @return string
     */
    public static function getWorkuser($workuser_id=0) {

        if (empty($workuser_id)) $workuser_id = scartUsers::getId();
        $cnt = Input
                ::where('status_code',SCART_STATUS_GRADE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->where('workuser_id',$workuser_id)
                ->where('deleted_at',null)
                ->count();

        scartLog::logLine("D-scartGrade.getWorkuser: workuser_id=$workuser_id, cnt=$cnt " );

        if (empty($cnt)) $cnt = '0';
        return $cnt;
    }

    /**
     * Get item if selected
     *
     * @param $workuser_id
     * @param $input_id
     * @return bool
     */
    public static function getGradeSelected($workuser_id, $input_id) {

        $set = Input_selected::where('workuser_id', $workuser_id)->where('input_id',$input_id)->where('set','=',1)->first();
        return ($set!='');
    }

    /**
     * Set notifcation select
     *
     * @param $workuser_id
     * @param $input_id
     * @param $set
     */
    public static function setGradeSelected($workuser_id, $input_id, $set) {

        $rec = Input_selected::where('workuser_id', $workuser_id)->where('input_id',$input_id)->first();
        if (!$rec) {
            $rec = new Input_selected();
            $rec->workuser_id = $workuser_id;
            $rec->input_id = $input_id;
        }
        $rec->set = $set;
        $rec->save();
    }

    /**
     * Locking for grading
     *
     */

    public static function setLock($workuser_id,$listrecords,$set=true) {
        foreach ($listrecords AS $input_id) {

            // remove all lock(s)
            Input_lock::where('input_id',$input_id)->delete();

            // set new lock
            if ($set) {
                $lock = new Input_lock();
                $lock->input_id = $input_id;
                $lock->workuser_id = $workuser_id;
                $lock->save();
                $input = Input::find($input_id);
                $input->workuser_id = $workuser_id;
                $input->save();
            }

        }
    }
    public static function isLocked($workuser_id,$listrecords) {
        $lock = Input_lock::whereIn('input_id',$listrecords)
            ->where('workuser_id','<>',$workuser_id)
            ->count();
        return ($lock > 0);
    }
    public static function getLockFullnames($workuser_id,$listrecords) {
        $locks = Input_lock::whereIn('input_id',$listrecords)
            ->where('workuser_id','<>',$workuser_id)
            ->select('workuser_id')
            ->distinct()
            ->get();
        $locked_workuser = '';
        foreach ($locks AS $lock) {
            $locked_workuser .= ($locked_workuser!='') ? ' & ' : '';
            $locked_workuser .= scartUsers::getFullName($lock->workuser_id);
        }
        $locked_workuser .= (count($locks) > 1) ? ' are ' : ' is ';
        return $locked_workuser;
    }
    public static function resetLock($input_id) {
        Input_lock::where('input_id',$input_id)->delete();
    }

    // ** HELPERS **

    // local country/hotline
    public static function isLocal($val) {
        $local = Systemconfig::get('abuseio.scart::classify.detect_country', '');
        $local = explode(',',$local);
        return (in_array(strtolower($val),$local));
    }


}
