<?php namespace abuseio\scart\models;

use abuseio\scart\classes\iccam\scartImportICCAM;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartModel;
use Config;
use Schema;

/**
 * Model
 */
class Systemconfig extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_config';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    // ** ** //

    private static $_plugincontext = 'abuseio.scart::';
    private static $_systemconfig = null;

    static function instance() {
        if (self::$_systemconfig == null) {
            self::readDatabase();
        }
        return self::$_systemconfig;
    }

    static function readDatabase() {
        self::$_systemconfig = Systemconfig::first();
        if (!self::$_systemconfig) {
            self::$_systemconfig = null;
        }
    }

    public static function initLoad() {

        $systemconfig = self::instance();
        if ($systemconfig == null) {

            /**
             * Create new (one) record in systemconfig table
             *
             * Based on current config (ENV) settings
             *
             */

            $systemconfig = new Systemconfig();

            $table = $systemconfig->getTable();
            $columns = Schema::getColumnListing($table);
            //scartLog::logLine("D-columns=" . print_r( $columns, true ) );

            foreach ($columns AS $column) {
                if (strpos($column, '-')!==false) {
                    $field = str_replace('-','.',$column);
                    $configvar = self::$_plugincontext . $field;
                    $configval = Config::get($configvar,null);
                    scartLog::logLine("D-Column=$column, field=".self::$_plugincontext."$field, value=".(($configval===null) ? 'null' : print_r($configval,true)) );
                    $systemconfig->$column = $configval;
                }
            }

            $systemconfig->save();

            self::$_systemconfig = $systemconfig;

        }

        return $systemconfig;
    }

    public static function getSystemconfig($configvar) {

        $value = null;

        try {

            $systemconfig = self::instance();

            if ($systemconfig !== null) {

                /**
                 * Example input: abuseio.scart::grade.min_images_column_screen'
                 *
                 * transform to: grade-min_images_column_screen
                 *
                 * check with: systemconfig->grade-min_images_column_screen
                 *
                 */

                $configvar = strtolower($configvar);
                $configvar = str_replace(self::$_plugincontext,'',$configvar);
                $configvar = str_replace('.','-',$configvar);
                if (($configvar!='') && isset($systemconfig->{$configvar})) {
                    if ($systemconfig->{$configvar} !== null) {
                        $value = $systemconfig->{$configvar};
                        //scartLog::logLine("D-Systemconfig->$configvar=".print_r($value,true));
                    }
                }

            }

        } catch (Exception $err) {

            scartLog::logLine("E-Systemconfig error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $value;
    }


    public static function get($configvar,$default='') {

        if (($value = self::getSystemconfig($configvar)) === null) {
            // Config (ENV) is fallback -> always for sentive data (passwords)
            $value = Config::get($configvar,$default);
        }
        return $value;
    }

    public static function set($configvar,$value) {

        $systemconfig = self::instance();

        if ($systemconfig !== null) {

            $configvar = strtolower($configvar);
            $configvar = str_replace(self::$_plugincontext,'',$configvar);
            $configvar = str_replace('.','-',$configvar);
            if (($configvar!='') && isset($systemconfig->{$configvar})) {
                $systemconfig->{$configvar} = $value;
                $systemconfig->save();
                self::$_systemconfig = $systemconfig;
            }

        }

    }

    /**
     * Special dynamic import ICCAM last date
     *
     * add to display field and handle special saving
     *
     */

    public function afterFetch() {

        $field = 'iccam-last_date';
        $value = scartImportICCAM::getImportlast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE);
        if (empty($value)) $value = date('Y-m-d H:i:s');
        // set tmp field
        $this->$field = $value;
    }

    public function beforeSave() {

        $field = 'iccam-last_date';
        if (isset($this->$field)) {
            $value = $this->$field;
            if (($dvalue = strtotime($value)) !== false) {
                $value = date('Y-m-d H:i:s',$dvalue);
                scartImportICCAM::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE,$value);
            }
            // unset so save() is not knowing this tmp field
            unset($this->$field);
        }

    }


    public function getAlertLanguageOptions ($value='',$formData='') {

        // @TO-DO; dynamic load from map (plugin)/lang/

        $path = plugins_path('abuseio/scart/lang/');
        $dirs = dir($path);
        $langs= [];
        while (($entry = $dirs->read()) !== false) {
            if ($entry != '.' && $entry != '..') {
                if (is_dir($path . '/' .$entry)) {
                    $langs[$entry] = $entry;
                }
            }
        }
        return $langs;
    }

}
