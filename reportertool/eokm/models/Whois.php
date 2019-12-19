<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertWhois;
use ReporterTool\EOKM\Models\Ntd;
use reportertool\eokm\classes\ertAnalyzeInput;
use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertMail;
use reportertool\eokm\classes\ertModel;
use reportertool\eokm\classes\ertLog;

/**
 * WHoIS model
 *
 * Connect to AbuseContact;
 * - detect change of abuse email info
 * - send ALERT when change found or default filled
 *
 */
class Whois extends ertModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_whois';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    private static $_whoisarrayfields = [
        'name' => '_owner',
        'whois_lookup' => '_lookup',
        'country' => '_country',
        'abusecontact' => '_abusecontact',
        'rawtext' => '_rawtext',
    ];

    /**
     * Find the most actual WHOIS record for Abusecontact (AC)
     *
     * @param $abusecontact_id
     * @param $whois_type
     * @return mixed
     */

    public static function findAC($abusecontact_id, $whois_type) {

        // find most ACTUAL record
        return Whois::where('abusecontact_id',$abusecontact_id)->where('whois_type',$whois_type)->orderBy('whois_timestamp','desc')->first();
    }

    /**
     * Connect abusecontact (AC) to Whois record
     * Create new whois record when not exists
     *
     * @param $abusecontact
     * @param $whois_type
     * @param $whois
     */
    public static function connectAC($abusecontact, $whois_type, $whois) {

        $foundabusecontact = trim($whois[$whois_type.'_abusecontact']);

        if ($whoisrecord = SELF::findAC($abusecontact->id,$whois_type)) {

            // found whois -> check if changed (IF NOT EMPTY)

            if ($foundabusecontact != '' && $whoisrecord->abusecontact != $foundabusecontact) {

                // new whois (timestamp)

                ertLog::logLine("D-Add WhoIs; type=$whois_type; abuse email ($whoisrecord->abusecontact) is changed to: $foundabusecontact" );

                $newwhois = new Whois();
                $newwhois->abusecontact_id = $abusecontact->id;
                $newwhois->whois_type = $whois_type;
                $newwhois->whois_timestamp = date('Y-m-d H:m:s');
                foreach (SELF::$_whoisarrayfields AS $rfield => $afield) {
                    $newwhois->$rfield = trim($whois[$whois_type.$afield]);
                }
                $newwhois->save();

                // Log change of whois
                $abusecontact->logText("Add WhoIs info; type=$whois_type; abuse email: $foundabusecontact ");

            }

        } else {

            // not found whois -> new one

            ertLog::logLine("D-Add first WhoIs record; type=$whois_type; abusecontact: $foundabusecontact " );

            $newwhois = new Whois();
            $newwhois->abusecontact_id = $abusecontact->id;
            $newwhois->whois_type = $whois_type;
            $newwhois->whois_timestamp = date('Y-m-d H:m:s');
            foreach (SELF::$_whoisarrayfields AS $rfield => $afield) {
                $newwhois->$rfield = trim($whois[$whois_type.$afield]);
            }
            $newwhois->save();

            // Log create of whois
            $abusecontact->logText("Add first WhoIs info; type=$whois_type; abuse email is '$newwhois->abusecontact'");

        }

        // Send warning when last WhoIs has different abuse contact
        if ($whois_type == ERT_HOSTER && $abusecontact->abusecustom!='' && $abusecontact->abusecustom != $foundabusecontact) {

            ertLog::logLine("D-Changed abusecontact HOSTER; old=$abusecontact->abusecustom, new=$foundabusecontact " );

            // 2019/12/9/Gs: GOOGLE use 2 toggling abuse emailaddresses -> SPAM emailsss.

            /*
            ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.whois_changed_abusecontact',[
                'whois_type' => strtoupper($whois_type),
                'abuseowner' => $abusecontact->owner,
                'filenumber' => $abusecontact->filenumber,
                'abusecontact' => $abusecontact->abusecustom,
                'whoisabusecontact' => $foundabusecontact,
            ]);
            */

            // Log change of abusecontact
            $abusecontact->logText("Last WhoIs has different abuse emailaddress - please check");

            // NB: we do not set abusecontact email here, this can be set (overruled) by user

        }

        // set ABUSE contact if empty

        if (trim($abusecontact->abusecustom)=='' && $foundabusecontact != '') {

            ertLog::logLine("D-Fill abusecontact; new=$foundabusecontact " );

            $abusecontact->abusecustom = $foundabusecontact;
            $abusecontact->abusecountry = $whois[$whois_type.'_country'];
            $abusecontact->gdpr_approved = ertGrade::isNL($abusecontact->abusecountry);
            $abusecontact->save();
            // Log change of abusecontact
            $abusecontact->logText("Set abusecontact on '$abusecontact->abusecustom' (country=$abusecontact->abusecountry) ");

            // CHANGED -> SEND ALERT

            ertAlerts::insertAlert(ERT_ALERT_LEVEL_WARNING,'reportertool.eokm::mail.whois_set_abusecontact',[
                'whois_type' => strtoupper($whois_type),
                'abuseowner' => $abusecontact->owner,
                'filenumber' => $abusecontact->filenumber,
                'abusecountry' => $abusecontact->abusecountry,
                'whoisabusecontact' => $abusecontact->abusecustom,
            ]);

        }

        return $whois;
    }

    /**
     * Fill WhoIs array
     *
     * @param $whois
     * @param $abusecontact_id
     * @param $whois_type
     * @return mixed
     */
    public static function fillWhoisArray($whois,$abusecontact_id,$whois_type) {

        if ($whois_record = SELF::findAC($abusecontact_id,$whois_type)) {
            foreach (SELF::$_whoisarrayfields AS $rfield => $afield) {
                $whois[$whois_type.$afield] = $whois_record->$rfield;
            }
        } else {
            // no whois record -> can be custom abusecontact -> fill directly
            $abusecontact = Abusecontact::find($abusecontact_id);
            if ($abusecontact) {
                $whois = array_merge($whois,[
                    $whois_type.'_owner' => $abusecontact->owner,
                    $whois_type.'_lookup' => '',
                    $whois_type.'_country' => $abusecontact->abusecountry,
                    $whois_type.'_abusecontact' => $abusecontact->abusecustom,
                    $whois_type.'_rawtext' => strtoupper($whois_type)." LOOKUP\n\nCustom abusecontact, no WhoIs data",
                ]);
            } else {
                $whois = array_merge($whois,[
                    $whois_type.'_owner' => ERT_WHOIS_UNKNOWN,
                    $whois_type.'_lookup' => '',
                    $whois_type.'_country' => '',
                    $whois_type.'_abusecontact' => '',
                    $whois_type.'_rawtext' => strtoupper($whois_type)." LOOKUP\n\nCustom abusecontact, no WhoIs data",
                ]);
                ertLog::logLine("W-Cannot find Abusecontact/WhoIs record for abusecontact_id=$abusecontact_id, type=$whois_type");
            }
        }
        return $whois;
    }


}
