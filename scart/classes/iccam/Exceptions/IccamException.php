<?php
namespace abuseio\scart\classes\iccam\Exceptions;

use abuseio\scart\classes\helpers\scartLog;

class IccamException extends \Exception {

    public function logErrorMessage()
    {
        scartLog::logLine("E-scartImportICCAM; exception on line " . $this->getLine() . " in " . $this->getFile() . "; message: " . $this->getMessage());
    }

}
