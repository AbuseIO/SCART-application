<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Scrape_cache
 *
 * note: in AuditTrail ignoretables
 *
 */

class Scrape_cache extends scartModel {
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_scrape_cache';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    // **

    public static function addCache($code,$cached) {
        $cache = new Scrape_cache();
        $cache->code = $code;
        $cache->cached = $cached;
        $cache->save();
    }

    public static function getCache($code) {
        $cache = Scrape_cache::where('code',$code)->first();
        return ($cache) ? $cache : false;
    }

    public static function delCache($code) {
        // force delete -> no need for softdelete this -> all records with this code
        //if ($cache) $cache->delete();
        //$cache = Scrape_cache::where('code',$code)->first();
        //if ($cache) $cache->forceDelete();
        Scrape_cache::where('code',$code)->forceDelete();
    }

}
