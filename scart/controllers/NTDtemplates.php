<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Ntd_template;
use abuseio\scart\models\Systemconfig;
use Backend\Classes\Controller;
use BackendMenu;
use BackendAuth;
use Lang;
use Winter\Storm\Parse\Bracket;
use Winter\Storm\Support\Facades\Flash;

class NTDtemplates extends scartController
{
    public $requiredPermissions = ['abuseio.scart.ntdtemplate_manage'];

    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'NTDtemplates');
    }

    function getTestEmail() {

        $user = BackendAuth::getUser();
        return ($user) ? $user->email : Systemconfig::get('abuseio.scart::alerts.recipient','');
    }

    public function update($recordId, $context=null) {

        $this->vars['sendEmail'] = $this->getTestEmail();
        $this->vars['recordId'] = $recordId;

        return $this->asExtension('FormController')->update($recordId, $context=null);
    }


    public function onTestNTD() {

        $email = input('sendEmail');
        $recordId = input('recordId');

        scartLog::logLine("D-onTestNTD; recordId=$recordId, sendEmail=$email");

        if ($email && $recordId) {

            $msg = Ntd_template::where('id',$recordId)->first();
            $lang = Lang::getLocale();

            $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
            $csvtemp .= 'ntdbody-onlyurl.tpl';

            $lines = [
                [
                    'url' => 'https://www.domain.nl/image1.jpg',
                    'reason' => 'Example reason 1',
                    'url_ip' => '1.2.3.1',
                    'firstseen_at' => date('Y-m-d H:i:s'),
                    'lastseen_at' => date('Y-m-d H:i:s'),
                ],
                [
                    'url' => 'https://www.domain.nl/image2.jpg',
                    'reason' => 'Example reason 2',
                    'url_ip' => '1.2.3.2',
                    'firstseen_at' => date('Y-m-d H:i:s'),
                    'lastseen_at' => date('Y-m-d H:i:s'),
                ],
                [
                    'url' => 'https://www.domain.nl/image3.jpg',
                    'reason' => 'Example reason 3',
                    'url_ip' => '1.2.3.3',
                    'firstseen_at' => date('Y-m-d H:i:s'),
                    'lastseen_at' => date('Y-m-d H:i:s'),
                ],
            ];

            if ($msg->csv_attachment) {

                $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                $csvtemp .= (($msg->add_only_url) ? 'ntdcsvfile-onlyurl.tpl' : 'ntdcsvfile.tpl' );

                $tmpdata = Bracket::parse(file_get_contents($csvtemp),['lines' => $lines]);
                $tmpfile = temp_path() . '/urls-'.time().'.csv';
                file_put_contents($tmpfile, $tmpdata);

                $abuselinks = '(abuse urls in CSV attachment)';
                scartLog::logLine("D-onTestNTD; send TEST NTD to '$email' with attachment '$tmpfile' ");

            } else {

                $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                $csvtemp .= (($msg->add_only_url) ? 'ntdbody-onlyurl.tpl' : 'ntdbody.tpl' );
                $abuselinks = Bracket::parse(file_get_contents($csvtemp),['lines' => $lines]);
                $tmpfile = '';
                scartLog::logLine("D-onTestNTD; send TEST NTD to '$email' with urls included in body ");

            }


            $abuselinks = Bracket::parse(file_get_contents($csvtemp),['lines' => $lines]);
            //scartLog::logDump("D-abuseLinks=",$abuselinks);

            $msg_body = str_replace('<p>{{'.'abuselinks'.'}}</p>', $abuselinks, $msg->body);

            scartMail::sendNTD($email,$msg->subject,$msg_body,'',$tmpfile,true);

            if ($tmpfile) {
                @unlink($tmpfile);
            }

            Flash::info('Test NTD send to '.$email);
        }

    }

}
