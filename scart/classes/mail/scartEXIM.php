<?php

/**
 * scartEXIM - interface EXIM MTA server
 *
 * 2019/8/19/Gs:
 *
 * implementation for check if message is delivered or not
 * ubuntu 18.x, EXIM4
 *
 */
namespace abuseio\scart\classes\mail;

use Config;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;

class scartEXIM {

    private static $_buffersize = 20;
    private static $_tagindex = 2;
    private static $_tagstatus = 3;

    /**
     * EXIM FLAGS
     *
     * '=>' =>   'normal message delivery',
     * '**' =>   'delivery failed; address bounced',
     *
     * '<=' =>   'message arrival',
     * '(=' =>   'message fakereject',
     * '->' =>   'additional address in same delivery',
     * '>>' =>   'cutthrough message delivery',
     * '*>' =>   'delivery suppressed by -N',
     * '==' =>   'delivery deferred; temporary problem',
     *
     */

    private static $_mailfailed = '**';
    private static $_mailsuccess = '=>';
    private static $_mailloglines = [];

    public static function getMTAstatus($messageID) {

        $status = SCART_NTD_STATUS_NOT_YET;

        $maillog = Systemconfig::get('abuseio.scart::scheduler.sendntd.maillogfile',false);

        if ($maillog) {

            try {

                if (count(SELF::$_mailloglines) == 0) {
                    // read (entire) file in memory -> cache, multiply calls in 1 run of scheduler
                    //scartLog::logLine("D-getMTAstatus: read (cache) EXIM logfile '$maillog' " );
                    if (file_exists($maillog . '.1')) {
                        SELF::$_mailloglines = file($maillog. '.1');
                    }
                    if (file_exists($maillog) ) {
                        SELF::$_mailloglines = array_merge( SELF::$_mailloglines, file($maillog) );
                    }
                    scartLog::logLine("D-getMTAstatus: read EXIM logfile '$maillog'; total lines is: " . count(SELF::$_mailloglines) );
                } else {
                    scartLog::logLine("D-getMTAstatus: use cache of EXIM logfile(s); total lines is: " . count(SELF::$_mailloglines) );
                }

                /**
                 * Logfile in memory open (cached within same session)
                 *
                 * We hebben een message-id met een unieke (externe) referentie voor het verstuurde bericht
                 * In de logfile is deze opgenomen in een logregel met aan het begin een unieke INTERNE referentie (=tag)
                 * Op basis van deze tag kunnen we de status van een message bepalen
                 *
                 * We zoeken in de logfile van onder naar boven; dat geeft meer kans dat we de net verstuurd NTD ook direct vinden
                 * Hierbij slaan we tijdelijk de status van een tag (simple derde element op een logregel) op wanneer we deze herkennen als SUCCESS of FAILED
                 * Wanneer we vervolgens een logregel vinden met de message-id, dan  weten we de status op basis van de tijdelijk opgeslagen tag informatie.
                 *
                 * That's it.
                 *
                 */

                $found = false;
                $eximtags = [];

                for ($i=count(SELF::$_mailloglines)-1;$i>=0;$i--) {

                    // logregel bestaat uit elementen gescheiden door een spatie
                    $arr = explode(' ',SELF::$_mailloglines[$i]);

                    if (count($arr) > SELF::$_tagstatus) {

                        // valide logregel

                        // get tag
                        $tag = $arr[SELF::$_tagindex];

                        // if known EXIM status field then fill eximtags array
                        if ($arr[SELF::$_tagstatus] == SELF::$_mailsuccess) {
                            $eximtags[$tag] = SCART_NTD_STATUS_SENT_SUCCES;
                        } elseif ($arr[SELF::$_tagstatus] == SELF::$_mailfailed) {
                            $eximtags[$tag] = SCART_NTD_STATUS_SENT_FAILED;
                        }

                        // zoek in logregel naar $messageID
                        if (($found = strpos(SELF::$_mailloglines[$i], $messageID)) !== false) {
                            // if in eximtags array then status and exit
                            if (isset($eximtags[$tag])) {
                                $status = $eximtags[$tag];
                                break;
                            }
                        }

                    }

                }

                if ($status != SCART_NTD_STATUS_NOT_YET) {
                    scartLog::logLine("D-getMTAstatus: tag=$tag; found status=$status for message-id=$messageID " );
                } elseif ($found) {
                    scartLog::logLine("D-getMTAstatus: tag=$tag, not (yet) a status for message-id=$messageID  ");
                } else {
                    scartLog::logLine("D-getMTAstatus: message-id=$messageID not (yet) found in EXIM logfile ");
                }

            } catch (Exception $err) {

                scartLog::logLine("E-getMTAstatus error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

            }

        } else {

            scartLog::logLine("W-getMTAstatus: warning mail logfile NOT set (disabled) - always SCART_NTD_STATUS_SENT_SUCCES");
            $status = SCART_NTD_STATUS_SENT_SUCCES;

        }

        return $status;
    }





}
