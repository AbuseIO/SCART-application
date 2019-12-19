<?php
namespace reportertool\eokm\classes;

use Db;
use Config;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Input_lock;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_selected;
use ReporterTool\EOKM\Models\Notification_input;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Grade_question;
use ReporterTool\EOKM\Models\Grade_question_option;

class ertGrade {

    private static $_last = '';

    /**
     * Get notifications
     *
     * @param $input_id
     * @param bool $notignored
     * @return mixed
     */

    public static function getNotifications($input_id,$notignored=false) {

        if ($notignored) {
            $notifications = self::getNotificationsWithGrade($input_id, ERT_GRADE_IGNORE, '<>' );
        } else {
            $notifications = Notification
                ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
                ->where('reportertool_eokm_notification_input.deleted_at',null)
                ->where('reportertool_eokm_notification_input.input_id', $input_id)
                ->orderBy('reportertool_eokm_notification.id')
                ->select('reportertool_eokm_notification.*')
                ->get();
        }
        return $notifications;
    }
    public static function getNotificationsFrom($input_id, $fromID, $limit) {

        $notifications = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.id', '>=', $fromID)
            ->select('reportertool_eokm_notification.*')
            ->orderBy('reportertool_eokm_notification.id')
            ->take($limit)
            ->get();
        return $notifications;
    }

    /**
     * Get notifications based on status and compare
     *
     * @param $input_id
     * @param $withstatus
     * @return mixed
     */
    public static function getNotificationsWithStatus($input_id,$withstatus,$compare='=') {

        // get notifications with status

        // NB:
        // 2019/6/5/Gs: if NOT select(reportertool_eokm_notification.*) in this statement, then returned ID
        // is from reportertool_eokm_notification_input, not reportertool_eokm_notification (!?)

        $notifications = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.status_code', $compare, $withstatus)
            ->orderBy('reportertool_eokm_notification.id')
            ->select('reportertool_eokm_notification.*')
            ->get();
        return $notifications;
    }

    public static function getIllegalNotificationsWithStatus($input_id,$withstatus,$compare='=') {

        // get illegal notifications with status

        $notifications = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.status_code', $compare, $withstatus)
            ->where('reportertool_eokm_notification.grade_code', ERT_GRADE_ILLEGAL)
            ->orderBy('reportertool_eokm_notification.id')
            ->select('reportertool_eokm_notification.*')
            ->get();
        return $notifications;
    }

    public static function getIllegalNotificationsWithNoRegistrarHosterSet($input_id) {

        // get illegal notifications with status

        $count = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.grade_code', ERT_GRADE_ILLEGAL)
            ->where(function($query) {
                $query->orWhere('reportertool_eokm_notification.registrar_abusecontact_id', 0)->orWhere('reportertool_eokm_notification.host_abusecontact_id', 0);
            })
            ->orderBy('reportertool_eokm_notification.id')
            ->select('reportertool_eokm_notification.*')
            ->count();
        return $count;
    }

    /**
     * Get notifications based on status and compare
     *
     * @param $input_id
     * @param $withstatus
     * @return mixed
     */
    public static function getNotificationsWithGrade($input_id,$withgrade,$compare='=') {

        // get notifications with specific grading

        // NB:
        // 2019/6/5/Gs: if NOT select(reportertool_eokm_notification.*) in this statement, then returned ID
        // is from reportertool_eokm_notification_input, not reportertool_eokm_notification (!?)

        $notifications = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.grade_code', $compare, $withgrade)
            ->orderBy('reportertool_eokm_notification.id')
            ->select('reportertool_eokm_notification.*')
            ->get();
        return $notifications;
    }
    public static function countNotificationsWithGrade($input_id,$withgrade,$compare='=') {

        // get notifications with specific grading

        // NB:
        // 2019/6/5/Gs: if NOT select(reportertool_eokm_notification.*) in this statement, then returned ID
        // is from reportertool_eokm_notification_input, not reportertool_eokm_notification (!?)

        $count = Notification
            ::join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.notification_id', '=', 'reportertool_eokm_notification.id')
            ->where('reportertool_eokm_notification_input.deleted_at',null)
            ->where('reportertool_eokm_notification_input.input_id', $input_id)
            ->where('reportertool_eokm_notification.grade_code', $compare, $withgrade)
            ->orderBy('reportertool_eokm_notification.id')
            ->select('reportertool_eokm_notification.*')
            ->count();
        return $count;
    }

    /**
     * Next input with grading notifications
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

        // get first notification with next (>=0) or previous (<0) input_id
        $order = ($input_id >= 0) ? 'asc' : 'desc';
        if ($current) {
            $compare = '=';
        } else {
            $compare = ($input_id >= 0) ? '>' : '<';
        }
        $input_id = abs($input_id);
        if (empty($workuser_id)) $workuser_id = ertUsers::getId();

        // 2019/5/27/Gs: select on input status_code=ERT_STATUS_GRADE, not how many open notifications
        if ($current) {
            $input = Input::find($input_id);
        } else {
            $input = Input
                ::where('status_code',ERT_STATUS_GRADE)
                ->where('workuser_id',$workuser_id)
                ->where('id',$compare,$input_id)
                ->orderBy('id',$order)
                ->select('*')
                ->first();
        }

        if ($input) {

            ertLog::logLine("D-ertGrade.next: (id $compare $input_id); got notifications with input_id=" . $input->id);

            $next = new \stdClass();
            $next->input = $input;
            $next->notifications = self::getNotifications($input->id);

            ertLog::logLine("D-ertGrade.next: number of notifications: " . count($next->notifications) );

        } else {
            ertLog::logLine("D-ertGrade.next: no notifications (anymore)");
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

        if (empty($workuser_id)) $workuser_id = ertUsers::getId();

        // 2019/5/27/Gs: select on input status_code=ERT_STATUS_GRADE, not how many open notifications
        $inputids = Input
            ::where('status_code',ERT_STATUS_GRADE)
            ->where('workuser_id',$workuser_id)
            ->select('id AS input_id')
            ->get();
        $cnt = count($inputids);

        if ($cnt > 0) {

            $number = Input
                ::where('status_code',ERT_STATUS_GRADE)
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

        //ertLog::logLine("D-ertGrade.inputToGrade: workuser_id=$workuser_id, ret: " . print_r($ret,true) );
        return $ret;
    }

    /**
     * Get work for user -> notifications
     *
     * @param int $workuser_id
     * @return string
     */
    public static function getWorkuser($workuser_id=0) {

        if (empty($workuser_id)) $workuser_id = ertUsers::getId();
        $cnt = Notification
                ::where('status_code',ERT_STATUS_GRADE)
                ->where('workuser_id',$workuser_id)
                ->where('deleted_at',null)
                ->count();

        ertLog::logLine("D-ertGrade.getWorkuser: workuser_id=$workuser_id, cnt=$cnt " );

        if (empty($cnt)) $cnt = '0';
        return $cnt;
    }

    /**
     * Get notification if selected
     *
     * @param $workuser_id
     * @param $notification_id
     * @return bool
     */
    public static function getGradeSelected($workuser_id, $notification_id) {

        $set = Notification_selected::where('workuser_id', $workuser_id)->where('notification_id',$notification_id)->where('set','=',1)->first();
        return ($set!='');
    }

    /**
     * Set notifcation select
     *
     * @param $workuser_id
     * @param $notification_id
     * @param $set
     */
    public static function setGradeSelected($workuser_id, $notification_id, $set) {

        $rec = Notification_selected::where('workuser_id', $workuser_id)->where('notification_id',$notification_id)->first();
        if ($rec) {
            $rec->set = $set;
            $rec->save();
        } else {
            $rec = new Notification_selected();
            $rec->workuser_id = $workuser_id;
            $rec->notification_id = $notification_id;
            $rec->set = $set;
            $rec->save();
        }
    }

    /**
     * Get selected notifications
     *
     * @param $workuser_id
     * @param $input_id
     * @return mixed
     */
    public static function getSelectedNotifications($workuser_id,$input_id) {

        // selected notifications
        //trace_sql();
        $nots = Notification_input::where('reportertool_eokm_notification_input.input_id',$input_id)
            ->join('reportertool_eokm_notification_selected', 'reportertool_eokm_notification_selected.notification_id' , '=', 'reportertool_eokm_notification_input.notification_id')
            ->where('reportertool_eokm_notification_selected.workuser_id',$workuser_id)
            ->where('reportertool_eokm_notification_selected.set','1')
            ->select('reportertool_eokm_notification_input.notification_id')
            ->get();
        //trace_log($nots);

        return $nots;
    }
    public static function countSelectedNotifications($workuser_id,$input_id) {

        // count selected notifications
        $count = Notification_input::where('reportertool_eokm_notification_input.input_id',$input_id)
            ->join('reportertool_eokm_notification_selected', 'reportertool_eokm_notification_selected.notification_id' , '=', 'reportertool_eokm_notification_input.notification_id')
            ->where('reportertool_eokm_notification_selected.workuser_id',$workuser_id)
            ->where('reportertool_eokm_notification_selected.set','1')
            ->select('reportertool_eokm_notification_input.notification_id')
            ->count();
        return $count;
    }

    /**
     * Locking for grading
     *
     */

    public static function setLock($input_id,$workuser_id) {
        if (!ertUsers::isAdmin()) {
            $lock = Input_lock::where('input_id',$input_id)->first();
            if ($lock=='') {
                $lock = new Input_lock();
                $lock->input_id = $input_id;
            }
            $lock->workuser_id = $workuser_id;
            $lock->save();
        }
    }

    public static function getLockByUser($input_id) {
        $lock = Input_lock::where('input_id',$input_id)->first();
        return ($lock!='') ? ertUsers::getFullName($lock->workuser_id) : '';
    }

    public static function getLock($input_id) {
        $lock = Input_lock::where('input_id',$input_id)->first();
        return $lock;
    }

    public static function resetLock($input_id) {
        Input_lock::where('input_id',$input_id)->delete();
    }

    // ** HELPERS **

    // NL or not...
    public static function isNL($val) {
        return (strtolower($val)=='nl' || strtolower($val)=='netherlands');
    }

    // grade questions
    //

    public static function getGradeHeaders($grade_code) {

        /**
         * vragen verzamelen; afhankelijk van type header;
         * - radio + text = 1 header
         * - selectie/checkbox = per mogelijk antwoord header kolome
         *
         */

        $group = ($grade_code == ERT_GRADE_ILLEGAL) ? ERT_GRADE_QUESTION_GROUP_ILLEGAL : ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL;
        $grades = Grade_question::where('questiongroup', $group)->orderBy('sortnr')->get();
        $gradelabels = $gradevalues = $gradetypes = [];

        foreach ($grades AS $grade) {
            $gradetypes[$grade->id] = $grade->type;
            if ($grade->type == 'select' || $grade->type == 'checkbox' || $grade->type == 'radio') {
                $gradevalues[$grade->id] = [];
                $opts = Grade_question_option::where('grade_question_id', $grade->id)->orderBy('sortnr')->get();
                foreach ($opts AS $opt) {
                    $gradevalues[$grade->id][$opt->value] = $opt->label;
                    if ($grade->type == 'select' || $grade->type == 'checkbox') $gradelabels['grade_'.$grade->id.'_'.$opt->value] = "[$grade->label] " . $opt->label;
                }
                if ($grade->type == 'radio') $gradelabels['grade_'.$grade->id] = $grade->label;
            } elseif ($grade->type == 'text') {
                $gradelabels['grade_'.$grade->id] = $grade->label;
            }
        }

        return [
            'types' => $gradetypes,
            'values' => $gradevalues,
            'labels' => $gradelabels,
        ];
    }



}
