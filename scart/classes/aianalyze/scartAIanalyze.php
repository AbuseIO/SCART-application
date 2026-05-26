<?php
namespace abuseio\scart\classes\aianalyze;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Systemconfig;


class scartAIanalyze {

    public static function isActive() {

        $active = Systemconfig::get('abuseio.scart::AIanalyze.active',false);
        if ($active) {
            $AIaddon = Addon::getAddonType(SCART_ADDON_TYPE_AI_IMAGE_ANALYZER);
            $active = ($AIaddon!='');
            if (!$active) scartLog::logLine("W-scartAIanalyze; active but NO addon set?! - switch AI off");
        }
        return $active;
    }

    public static function validAIinput($input) {

        $webformonly = Systemconfig::get('abuseio.scart::AIanalyze.only_webform_input',true);
        $result = (!$webformonly || ($webformonly && ($input->source_code == SCART_SOURCE_CODE_WEBFORM)));
        scartLog::logDump("D-validAIinput(source_code={$input->source_code},webformonly=$webformonly)=result=$result");
        return $result;
    }


}
