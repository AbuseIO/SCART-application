<?php namespace abuseio\scart\Models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;
use Model;

/**
 * Model
 */
class Maintenance extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_maintenance';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    public static function checkICCAMdisabled() {

        $actiondisableddone = null;

        // check dynamic maintenance
        $current = date('Y-m-d H:i:s');
        $maintenance = Maintenance::where('module','ICCAM')
            ->where('start','<=',$current)
            ->where('end','>=',$current)
            ->first();
        //scartLog::logLine("D-Check ICCAM Maintenance time at $current");
        if ($maintenance) {
            $set = scartUsers::getGeneralOption('MAINTENANCE_ICCAM_DISABLED','');
            if ($set=='') {
                scartLog::logLine("D-Maintenance ICCAM time; from $maintenance->start till $maintenance->end");
                $params = [
                    'reportname' => 'ICCAM INTERFACE MAINTENANCE IS SET',
                    'report_lines' => [
                        "Maintenance from $maintenance->start till $maintenance->end"
                    ]
                ];
                $actiondisableddone = true;
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.admin_report', $params);
            }
            scartUsers::setGeneralOption('MAINTENANCE_ICCAM_DISABLED', 'SET');
        } else {
            $set = scartUsers::getGeneralOption('MAINTENANCE_ICCAM_DISABLED');
            if ($set!='') {
                scartLog::logLine("D-Maintenance ICCAM time is over on $current");
                $params = [
                    'reportname' => 'ICCAM INTERFACE MAINTENANCE IS OVER',
                    'report_lines' => [
                        "Maintenance is over on $current"
                    ]
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.admin_report', $params);
                scartUsers::setGeneralOption('MAINTENANCE_ICCAM_DISABLED', '');
                $actiondisableddone = false;
            }
        }
        return $actiondisableddone;
    }


}
