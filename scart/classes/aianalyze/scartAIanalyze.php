<?php
namespace abuseio\scart\classes\aianalyze;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Systemconfig;


class scartAIanalyze {

    public static function isActive() {

        $active = false;
        if (Systemconfig::get('abuseio.scart::AIanalyze.active',false)) {
            $AIaddon = Addon::getAddonType(SCART_ADDON_TYPE_AI_IMAGE_ANALYZER);
            $active = ($AIaddon!='');
            if (!$active) scartLog::logLine("W-scartAIanalyze; active but NO addon set?!");
        }
        return $active;
    }


}
