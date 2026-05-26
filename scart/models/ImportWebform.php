<?php namespace abuseio\scart\Models;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\Updates\CreateAbuseioScartTokens;
use Model;
use abuseio\scart\models\Input_status;
use Winter\Storm\Exception\ValidationException;
use Winter\Storm\Parse\Bracket;
use Winter\Storm\Parse\Yaml;
use ForceUTF8\Encoding;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Input_source;

/**
 * Model
 */
class ImportWebform extends Model {

    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\SoftDelete;

    // import webform debugging in system log
    protected static $_debug = true;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_import_webform';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required',
        'subject' => 'required|unique',
        'status_code' => 'required',
    ];

    public $hasMany = [
        'fields' => [
            'abuseio\scart\models\ImportWebformField',
            'key' => 'import_webform_id',
            'order' => 'id DESC',
            'delete' => true],
    ];

    public function getStatusCodeOptions($value,$formData) {

        $recs = Input_status::orderBy('sortnr')
            ->whereIn('code',[SCART_STATUS_OPEN,SCART_STATUS_SCHEDULER_SCRAPE,SCART_STATUS_GRADE,SCART_STATUS_DIRECT_POLICE])
            ->select('code','title','description')
            ->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function beforeSave() {

        scartLog::logLine("D-ImportWebform.beforeSave");

        if ($this->subject) {
            $this->subject = strtoupper($this->subject);
            $this->subject = str_replace(' ','_',$this->subject);
        }

        $systemimportsubjects = [
            SCART_MAILBOX_IMPORT_INPUT_SOURCE,
            SCART_MAILBOX_IMPORT_INPUT_SOURCE_2,
            SCART_MAILBOX_IMPORT_WEBSITE_INPUTS,
            SCART_MAILBOX_IMPORT_WEBSITE_INPUTS_2,
            SCART_MAILBOX_IMPORT_CONTENT_REMOVED,
            SCART_MAILBOX_IMPORT_CONTENT_REMOVED_2,
            SCART_MAILBOX_IMPORT_CONTENT_UNAVAILABLE,
            SCART_MAILBOX_IMPORT_CONTENT_UNAVAILABLE_2,
            // obsolute?
            SCART_MAILBOX_IMPORT_ICCAM_INPUTS,
            SCART_MAILBOX_IMPORT_HOTLINE_INPUTS,
            // system
            SCART_MAILBOX_IMPORT_SET_MAINTENANCE,
        ];
        if (in_array($this->subject,$systemimportsubjects)) {
            throw new ValidationException(['subject' => 'This subject is protected - not allowed']);
        }

    }


    public static function importWebformSubject($subject) {

        return (ImportWebform::where('subject',strtoupper($subject))->exists());
    }

    public static function importWebformMessage($msg) {

        $loglines = ['import webform with subject: '.$msg->getSubject()];

        try {

            // get on subject specific webform
            $webform = ImportWebform::where('subject',strtoupper($msg->getSubject()))->first();

            if ($webform) {

                // XML or body import
                $loglines = (self::isXMLimport($msg) ? self::importXML($msg,$webform) : self::importBody($msg,$webform));

            } else {
                scartLog::logLine("W-importWebformMessage; cannot webform definition with subject'".$msg->getSubject()."'!?");
                $loglines[] = "webform subject not found - skip import";
            }

        } catch (\Exception $err) {
            scartLog::logLine("W-importWebformMessage; error: ".$err->getMessage().', line: '.$err->getLine());
            $loglines[] = "error when importing '".$err->getMessage()."' - skip import";
        }

        return $loglines;
    }

    static function isXMLimport($msg) {

        $attachments = $msg->getAttachments();

        $isXML = false;
        if (isset($attachments[0]['filename']) && ($filename = $attachments[0]['filename'])) {
            scartLog::logLine("D-importWebformMessage; first attachment filename '$filename'");
            $parts = pathinfo($filename);
            $isXML = (strtolower($parts['extension']) == 'xml');
        }
        return $isXML;
    }

    // ** BODY import **//

    static function importBody($msg,$webform) {

        scartLog::logDump("D-importWebformMessage; body import");

        // init array with input fields
        $inputfields = $loglines = [];

        // get yaml body fields
        $fields = self::getBodyFields($msg,$webform);

        if (!empty($fields)) {
            // extract import
            $loglines[] = "import (body) fields";
            $loglines = array_merge($loglines,self::field2input($webform,$fields,$inputfields));
        }

        if (!empty($inputfields)) {
            // input fields found -> create new input record
            if ($input = self::createInput($webform,$inputfields)) {
                $logline = "input report created from (body) import - filenumber is '{$input->filenumber}'";
                scartLog::logLine("D-importWebformMessage; $logline");
            } else {
                $logline = "cannot create input report from (body) import - skip import";
                scartLog::logLine("W-importWebformMessage; $logline");
            }
            $loglines[] = $logline;
        } else {
            $loglines[] = "no input import fields found - skip import";
            scartLog::logLine("W-importWebformMessage; no input import fields found!?");
        }

        return $loglines;
    }


    public static function parseYaml($bodylines,$webfields) {

        // single level/line yaml parsing because of encode format errors

        // note: buggy
        //$body = implode("\n",$bodylines); $yaml = new Yaml(); $array = $yaml->parse($body);

        $key = '';
        $array = [];
        foreach ($bodylines as $yamlline) {
            $yamlline = trim($yamlline);
            if ($yamlline) {
                $elements = explode(':',$yamlline);
                // key element in line AND in webfields def
                if ((count($elements) > 1) && in_array(strtolower($elements[0]),$webfields)) {
                    $key = array_shift($elements);
                    $text = implode(':',$elements);
                    if ($text != '|') {
                        $val = Encoding::toUTF8($text);
                        scartLog::logLine("D-ImportWebform.parseYaml: yaml line text=$text, val=$val");
                        $array[trim($key)] = $val;
                    }
                } else {
                    if ($key) {
                        // add to last key
                        $val = Encoding::toUTF8($yamlline);
                        scartLog::logLine("D-ImportWebform.parseYaml: continue yaml line text=$yamlline, val=$val");
                        $array[trim($key)] .= "\n".$val;
                    } else {
                        scartLog::logLine("W-ImportWebform.parseYaml: invalid single yaml line");
                    }
                }
            }
        }
        return $array;
    }

    static function getWebformFieldNames($webform) {

        $names = ($webform->fields->pluck('name')->toArray());
        return array_map('strtolower',$names);
    }

    static function getBodyFields($msg,$webform) {

        // message body lines
        $bodylines = $msg->getBodyLines();

        // get names of webfields
        $webfields = self::getWebformFieldNames($webform);

        // filter empty lines
        $bodylines = array_values(array_filter($bodylines, fn($value) => !is_null($value) && $value !== ''));
        scartLog::logDump("D-importWebformMessage; body=",$bodylines);

        // parse yaml
        $fields = self::parseYaml($bodylines,$webfields);

        // return always single dimension array
        if (count($fields) == count($fields, COUNT_RECURSIVE)) {
            $fields = ['input' => $fields];
        }

        scartLog::logDump("D-getBodyFields; body fields import");

        return $fields;
    }

    // ** XML import ** //

    static function importXML($msg,$webform) {

        scartLog::logDump("D-importWebformMessage; XML attachment import");

        $loglines = $inputfields = [];

        try {

            $attachments = $msg->getAttachments();

            // proces XML file

            // get names of webfields
            $webfields = self::getWebformFieldNames($webform);
            // get XML fields
            $xmlfields = (object) self::getXMLattachmentFields($webfields,array_shift($attachments));

            if (isset($xmlfields->fields)) {
                if (self::$_debug) scartLog::logDump("D-importWebformMessage; XML fields=",$xmlfields);
                $loglines[] = "import (XML) fields";
                $fields = [
                    'input' => $xmlfields->fields
                ];
                // convert xml fields into input extra fields
                $loglines = array_merge($loglines,self::field2input($webform,$fields,$inputfields));
                if (self::$_debug) scartLog::logDump("D-importWebformMessage; input fields=",$inputfields);
            } else {
                scartLog::logLine("W-importWebformMessage; no XML fields!?");
            }

            if (!empty($inputfields)) {

                // input fields found -> create new input record
                if ($input = self::createInput($webform,$inputfields)) {
                    $logline = "input report created from (XML) import - filenumber is '{$input->filenumber}'";
                    scartLog::logLine("D-importWebformMessage; $logline");
                } else {
                    $logline = "cannot create input report from (XML) import - skip import";
                    scartLog::logLine("W-importWebformMessage; $logline");
                }
                $loglines[] = $logline;

                if ($input && isset($xmlfields->images)) {
                    // add images to mainurl input
                    $loglines = array_merge($loglines,self::addInputImages($input,$webform,$xmlfields->images,$attachments));
                }

            } else {
                $loglines[] = "no input import fields found - skip import";
                scartLog::logLine("W-importWebformMessage; no input import fields found!?");
            }

        } catch (\Exception $err) {
            scartLog::logLine("W-importWebformMessage; error: ".$err->getMessage().', line: '.$err->getLine());
            $loglines[] = "error impot XML '".$err->getMessage()."' - skip import";
        }

        return $loglines;
    }


    static function getXMLattachmentFields($webfields,$attachment) {

        $fields = [];

        if (isset($attachment['attachment'])) {

            try {

                scartLog::logLine("D-importWebformMessage; read XML file '{$attachment['filename']}' ");

                $xmldoc = new \DOMDocument();
                $xmldoc->loadXML($attachment['attachment']);
                $xmlfields = (array) simplexml_import_dom($xmldoc);
                //scartLog::logDump("D-XMLfields: ",$xmlfields);

                // <input><fields>..</fields><images>..</images></input>

                if (isset($xmlfields['fields'])) {
                    foreach ($xmlfields['fields'] as $key => $val) {
                        //scartLog::logDump("D-Element '$key': ",$val);
                        $fields['fields'][strtolower($key)] = (string)$val;
                    }
                }
                if (isset($xmlfields['images'])) {
                    foreach ($xmlfields['images'] as $key => $val) {
                        $fields['images'][strtolower($key)] = (string) $val;
                    }
                }

            } catch (\Exception $err) {
                scartLog::logLine("W-importWebformMessage; getXMLattachmentFields; error: ".$err->getMessage().', line: '.$err->getLine());
            }

        }

        return $fields;
    }

    static function addInputImages($input,$webform,$imagefilenames,$attachments) {

        $loglines = [];

        try {

            $delivered_items = 0;

            foreach ($attachments as $attachment) {

                if (in_array($attachment['filename'],$imagefilenames)) {

                    $path = pathinfo($attachment['filename']);
                    $mimeType = 'image/'.$path['extension'];

                    if (in_array($mimeType,scartBrowser::$_imageMimeTypes) ) {

                        $image = self::createScartImage($attachment,$mimeType);

//                        $show = $image;
//                        unset($show['data']);
//                        scartLog::logDump("D-createScartImage: ",$show);

                        // @todo: already existing image with same hash
                        // @todo: image hash check on hash service?

                        $url = self::generateUrl('-image');
                        $parsed = parse_url($url);

                        $imageinput = new Input();
                        $imageinput->url = $url;
                        $imageinput->url_type = SCART_URL_TYPE_IMAGEURL;
                        $imageinput->url_referer = '';
                        $imageinput->note = '';
                        $imageinput->status_code = $webform->status_code;
                        $imageinput->workuser_id = 0;
                        $imageinput->type_code = SCART_MAILBOX_IMPORT_TYPE_CODE_WEBSITE;
                        $imageinput->source_code = SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM;
                        $imageinput->received_at = date('Y-m-d H:i:s');
                        $imageinput->save();

                        if (Input_source::checkInsertCode($imageinput->source_code)) {
                            scartLog::logLine("D-importWebformMessage; add source_code '{$imageinput->source_code}' ");
                        }

                        // log old/new for history
                        $imageinput->logHistory(SCART_INPUT_HISTORY_STATUS,'',$input->status_code,"Import by email import");
                        $imageinput->logText("Added by mailbox import");

                        $imageinput->url_hash = $image['hash'];
                        $imageinput->url_base = $url;
                        $imageinput->url_host = (isset($parsed['host']) ? $parsed['host'] : '') ;
                        $imageinput->url_image_width = $image['width'];
                        $imageinput->url_image_height = $image['height'];
                        $imageinput->save();

                        if (scartBrowser::useCached()) {
                            // FILL CACHE
                            $cache = Scrape_cache::getCache($image['hash']);
                            if (!$cache) {
                                $cache = "data:" . $mimeType . ";base64," . base64_encode($image['data']);
                                Scrape_cache::addCache($image['hash'], $cache);
                            }
                        }

                        $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$imageinput->id)->first();
                        if (!$iteminp) {
                            $iteminp = new Input_parent();
                            $iteminp->parent_id = $input->id;
                            $iteminp->input_id = $imageinput->id;
                            $iteminp->save();
                            $input->logText("Add imageurl '$imageinput->filenumber' from imported attachment");
                        }

                        $delivered_items += 1;

                    } else {
                        $logline = "attachment filename '{$attachment['filename']}' not in allowed MimeTypes - skip attachment";
                        scartLog::logLine("W-importWebformMessage; $logline");
                        $loglines[] = $logline;
                    }

                } else {
                    $logline = "attachment filename '{$attachment['filename']}' not in XML images field - skip attachment";
                    scartLog::logLine("W-importWebformMessage; $logline");
                    $loglines[] = $logline;
                }

            }

            if ($delivered_items > 0) {
                $input->delivered_items = $delivered_items;
                $input->save();
                $logline = "add {$delivered_items} images to mainurl '$input->filenumber'";
                scartLog::logLine("D-importWebformMessage; $logline");
                $loglines[] = $logline;
            }

        } catch (\Exception $err) {
            $logline = "addInputImages error: ".$err->getMessage().', line: '.$err->getLine();
            scartLog::logLine("W-importWebformMessage; $logline");
            $loglines[] = $logline;
        }

        return $loglines;
    }

    static function createScartImage($attachment,$mimeType) {

        $data = $attachment['attachment'];
        $hash = scartBrowser::getImageHash($data);
        $size = strlen($data);

        $imgsiz = @getimagesizefromstring($data);
        if ($imgsiz !== false) {
            $width = $imgsiz[0];
            $height = $imgsiz[1];
            $mimeType = image_type_to_mime_type($imgsiz[2]);
        } else {
            $width = $height = 150;
        }

        return [
            'type' => SCART_URL_TYPE_IMAGEURL,
            'data' => $data,
            'hash' => $hash,
            'width' => $width,
            'height' => $height,
            'mimetype' => $mimeType,
            'isBase64' => false,
            'imgsize' => $size,
        ];
    }

    //** GENERAL IMPORT FUNCTIONS **/

    static function field2input($webform,$fields,&$inputfields) {

        $loglines = [];

        $inputfields = [
            'normal' => [
            ],
            'extra' => [
                SCART_INPUT_EXTRAFIELD_WEBFORM_SUBJECT => (object)[
                    'value' => trim($webform->subject),
                    'webform_field_id' => 0,
                ]
            ]
        ];

        foreach ($fields as $field) {

            $field = array_change_key_case($field,CASE_UPPER);

            foreach ($webform->fields as $webformfield) {

                scartLog::logLine("D-Check $webformfield->name");

                if (isset($field[strtoupper($webformfield->name)])) {

                    $value = $field[strtoupper($webformfield->name)];

                    scartLog::logLine("D-Add $webformfield->name based on $webformfield->import_fieldname");

                    if ($webformfield->import_fieldname == SCART_IMPORT_WEBFORM_ATTRIBUTE) {
                        $inputfields['extra'][$webformfield->name] = (object)[
                            'value' => $value,
                            'webform_field_id' => $webformfield->id,
                        ];
                    } elseif ($webformfield->import_fieldname == SCART_IMPORT_WEBFORM_URL) {
                        $value = trim(strip_tags($value));
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            scartLog::logLine("W-failed: import url '$value' not valid - skip this report");
                            $inputfields = [];
                            break;
                        } else {
                            $inputfields['normal']['url'] = $value;
                        }
                    } elseif ($webformfield->import_fieldname == SCART_IMPORT_WEBFORM_REFERER) {
                        $value = trim(strip_tags($value));
                        $inputfields['normal']['url_referer'] = $value;
                    } elseif ($webformfield->import_fieldname == SCART_IMPORT_WEBFORM_NOTE) {
                        $inputfields['normal']['note'] = $value;
                    } elseif ($webformfield->import_fieldname == SCART_IMPORT_WEBFORM_NTD_NOTE) {
                        $inputfields['normal']['ntd_note'] = $value;
                    }
                }
            }
        }

        // $inputfields can be empty

        return $loglines;
    }

    static function createInput($webform,$inputfields) {

        // single

        $valid = true;

        $input = new Input();
        $input->url = self::generateUrl();
        $input->url_type = SCART_URL_TYPE_MAINURL;
        $input->url_referer = '';
        $input->note = '';
        $input->status_code = $webform->status_code;
        $input->workuser_id = 0;
        $input->type_code = SCART_MAILBOX_IMPORT_TYPE_CODE_WEBSITE;
        $input->source_code = (!empty($webform->subject)? $webform->subject : SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM);
        $input->received_at = date('Y-m-d H:i:s');
        $input->save();

        if (Input_source::checkInsertCode($input->source_code)) {
            scartLog::logLine("D-importWebformMessage; add source_code '{$input->source_code}' ");
        }

        // log old/new for history
        $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',$input->status_code,"Import by email import");
        $input->logText("Added by mailbox import");

        foreach ($inputfields['normal'] as $inputfield => $inputvalue) {
            scartLog::logLine("D-importWebformMessage; $inputfield='$inputvalue' ");
            $input->{$inputfield} = $inputvalue;
        }

        if ($webform->status_code == SCART_STATUS_SCHEDULER_SCRAPE && (self::isGeneratedUrl($input->url))) {
            scartLog::logLine("W-importWebformMessage; status set on '{$webform->status_code}'; the 'url' is missing!? - skip import");
            $valid = false;
        }

        if (!$valid) {
            scartLog::logLine("W-importWebformMessage; invalid import - force delete input");
            $input->forceDelete();
        } else {
            $input->save();
            scartLog::logLine("D-importWebformMessage; import success - filenumber '$input->filenumber'");
            foreach ($inputfields['extra'] as $extraname => $extravalue) {
                // add webform_field_id as secondvalue for direct reference
                $input->addExtrafield(SCART_INPUT_EXTRAFIELD_WEBFORM,$extraname,$extravalue->value,$extravalue->webform_field_id);
            }
        }

        return ($valid) ? $input : false;
    }

    // ** special non-URL strings **//

    public static function generateUrl($sub='') {

        return "https://import{$sub}-received-at-".date('YmdHis').'-'.sprintf('%05d',rand(1,99999)).'.local';
    }

    public static function isGeneratedUrl($url) {

        return (str_starts_with($url,'https://import-') && str_ends_with($url,'.local'));
    }

}
