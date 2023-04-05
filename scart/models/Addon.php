<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;

/**
 * Model
 */
class Addon extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_addon';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    public function getTypeOptions($value,$formData) {

        $recs = Addon_type::orderBy('sortnr')->select('code','title','description')->get();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->description;
        }
        return $ret;
    }
    public function getClassname() {
        return "abuseio\\scart\\addon\\" . $this->codename;
    }

    /**
     * Valideer current (record) Addon
     *
     * @return bool|mixed
     */

    public function checkValidate() {

        $result = false;

        $class = $this->getClassname();

        if (class_exists($class)) {

            scartLog::logLine("D-Class '$class' exists");

            if (method_exists($class,'checkRequirements')) {
                $this->valid = call_user_func($class .'::checkRequirements');
                scartLog::logLine("D-Methode '$class::checkRequirements' = $this->valid");
            } else {
                $this->valid = true;
                scartLog::logLine("D-No methode '$class::checkRequirements()' ");
            }

            $result = $this->classexists = $this->valid;

        } else {
            scartLog::logLine("W-Class '$class' does NOT exists");
            $this->classexists = false;

        }

        if (!$result) $this->enabled = false;

        $this->save();

        return $result;
    }

    /**
     * Check if type addon(s) is active based
     *
     * @param $type
     * @param $record
     * @return string
     */
    private static $_addontype = [];
    public static function resetCache() {
        SELF::$_addontype = [];
    }
    public static function getAddonType($type) {
        if (!isset(SELF::$_addontype[$type])) {
            SELF::$_addontype[$type] = Addon::where('type',$type)->where('enabled',true)->first();
        }
        return SELF::$_addontype[$type];
    }

    public static function getLastError($addon) {

        $result = '';
        $class = $addon->getClassname();
        try {
            if (class_exists($class)) {
                if (method_exists($class, 'getLastError')) {
                    $result = call_user_func($class . '::getLastError');
                } else {
                    scartLog::logLine("W-Addon; getLastError methode '$class' does NOT exists!?");
                }

            }
        } catch (\Exception $err) {
            scartLog::logLine("W-Addon;getLastError; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }
        return $result;
    }

    public static function run($addon,$record,$debug=false) {

        $result = false;

        $class = $addon->getClassname();

        try {

            if (class_exists($class)) {
                if (method_exists($class, 'run')) {
                    scartLog::logLine("D-Addon; run '$class::run(record)'...");
                    $result = call_user_func($class . '::run',$record);
                    if ($debug) scartLog::logLine("D-Addon; result=" . print_r($result,true) );
                } else {
                    scartLog::logLine("E-Addon; run methode '$class' does NOT exists!?");
                }

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-Addon;run; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

        return $result;
    }



}
