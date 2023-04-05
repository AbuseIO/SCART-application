<?php namespace abuseio\scart\Updates;

use Seeder;
use Db;

class SeederSystemTables extends Seeder
{
    public function run() {

        // Note: always reset with auto-incrementing id to zero

        Db::table('backend_user_roles')->truncate();
        Db::table('backend_user_roles')->insert([
            ['name' => "Publisher",'code' => "publisher",'description' => "Site editor with access to publishing tools.",'permissions' => "",'is_system' => "1",],
            ['name' => "Developer",'code' => "developer",'description' => "Site administrator",'permissions' => "",'is_system' => "1",],
            ['name' => "SCARTmanager",'code' => "SCARTmanager",'description' => "Manager role",'permissions' => "{\"abuseio.scart.startpage\":\"1\",\"abuseio.scart.input_manage\":\"1\",\"abuseio.scart.grade_notifications\":\"1\",\"abuseio.scart.police\":\"1\",\"abuseio.scart.ntds\":\"1\",\"abuseio.scart.changed\":\"1\",\"abuseio.scart.reporting\":\"1\",\"abuseio.scart.utility\":\"1\",\"abuseio.scart.rules\":\"1\",\"abuseio.scart.abusecontact_manage\":\"1\",\"abuseio.scart.ntdtemplate_manage\":\"1\",\"abuseio.scart.manual_read\":\"1\",\"abuseio.scart.manual_write\":\"1\",\"abuseio.scart.blocked_days\":\"1\"}",'is_system' => "0",],
            ['name' => "SCARTuser",'code' => "SCARTuser",'description' => "User role",'permissions' => "{\"abuseio.scart.startpage\":\"1\",\"abuseio.scart.grade_notifications\":\"1\"}",'is_system' => "0",],
            ['name' => "SCARTscheduler",'code' => "SCARTscheduler",'description' => "SCARTscheduler role",'permissions' => "",'is_system' => "0",],
            ['name' => "SCARTadmin",'code' => "SCARTadmin",'description' => "Administrator",'permissions' => "{\"abuseio.scart.startpage\":\"1\",\"abuseio.scart.input_manage\":\"1\",\"abuseio.scart.grade_notifications\":\"1\",\"abuseio.scart.police\":\"1\",\"abuseio.scart.ntds\":\"1\",\"abuseio.scart.changed\":\"1\",\"abuseio.scart.reporting\":\"1\",\"abuseio.scart.utility\":\"1\",\"abuseio.scart.rules\":\"1\",\"abuseio.scart.abusecontact_manage\":\"1\",\"abuseio.scart.grade_questions\":\"1\",\"abuseio.scart.ntdtemplate_manage\":\"1\",\"abuseio.scart.whois\":\"1\",\"abuseio.scart.manual_read\":\"1\",\"abuseio.scart.manual_write\":\"1\",\"abuseio.scart.whois_cache\":\"1\",\"abuseio.scart.blocked_days\":\"1\",\"abuseio.scart.exporterrors\":\"1\",\"abuseio.scart.user_write\":\"1\",\"abuseio.scart.checkonline\":\"1\",\"media.manage_media\":\"1\"}",'is_system' => "0",],
            ['name' => "SCARTcorona",'code' => "SCARTcorona",'description' => "SCART light version",'permissions' => "{\"abuseio.scart.startpage\":\"1\",\"abuseio.scart.rules\":\"1\",\"abuseio.scart.abusecontact_manage\":\"1\",\"abuseio.scart.manual_read\":\"1\"}",'is_system' => "0",],
        ]);


        Db::table('backend_user_roles')->truncate();
        Db::table('backend_user_groups')->insert([
            ['name' => "Owners",'code' => "owners",'description' => "Default group for website owners.",'is_new_user_default' => "0",],
            ['name' => "SCARTworkuser",'code' => "SCARTworkuser",'description' => "SCARTworkuser can have work",'is_new_user_default' => "0",],
        ]);

        Db::table('system_mail_layouts')->where('name',"Default layout")->update(
            ['content_html' => "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <style>\r\n    .urls table,.urls th,.urls td {\r\n        border: 1px solid gray;\r\n        padding: 2px;\r\n        border-collapse: collapse;\r\n    }\r\n    </style>\r\n</head>\r\n<body style=\"margin:0; padding: 2px; background: white; \">\r\n    <table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n        <tr>\r\n            <td align=\"center\">\r\n                <table class=\"content\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">\r\n                    <!-- Email Body -->\r\n                    <tr>\r\n                        <td class=\"body\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">\r\n                        {{ content|raw }}\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>",'content_text' => "{{ content|raw }}",'content_css' => "",'is_locked' => "1",'options' => "",]
        );

        Db::table('abuseio_scart_ntd_template')->truncate();
        Db::table('abuseio_scart_ntd_template')->insert([
            ['title' => "Standaard NTD template",'subject' => "Notice & Take Down message from Meldpunt Kinderporno",'body' => "<p>Dear Sir/Madam,</p>\r\n\r\n<p><strong>Who we are</strong>\r\n    <br>The Dutch Hotline combating Child Pornography on the Internet (Meldpunt Kinderporno) is an independent private foundation. Meldpunt receives subsidies to support its activities from the Dutch Ministry of Security and Justice and the European Commission. Since the start of the Dutch Hotline there has been a close cooperation between the Hotline and the Dutch Police. We are also one on the founders of <a href=\"http://www.inhope.org/\">http://www.inhope.org/&nbsp;</a>INHOPE, the international network of Internet hotline against child sexual abuse on the Internet. The report procedure, together with supporting information, is published on our website.\r\n        <br>\r\n        <br><strong>Reporting child sexual abuse content</strong>\r\n   <br>Meldpunt Kinderporno would like to make you aware by reporting the URL below as containing child sexual abuse content as assessed under Dutch Law.</p>\r\n\r\n<p>{{abuselinks}}</p>\r\n\r\n<p>\r\n  <br>\r\n</p>\r\n\r\n<p>Should you require any further information regarding this matter, please contact us by email <a href=\"mailto:info@meldpunt-kinderporno.nl\">info@meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n <br>\r\n</p>\r\n\r\n<p>Kind regards,</p>\r\n\r\n<p>Meldpunt Kinderporno op Internet | Dutch hotline against child sexual abuse material on the Internet <a href=\"http://www.meldpunt-kinderporno.nl\">www.meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n       <br>\r\n</p>",],
            ['title' => "Standaard POLICE temlate",'subject' => "Illegal content found",'body' => "<p>Dear Sir/Madam,</p>\r\n\r\n<p><strong>Reporting child sexual abuse content</strong>\r\n       <br>Meldpunt Kinderporno would like to make you aware by reporting the URL below as containing child sexual abuse content as assessed under Dutch Law.\r\n      <br>\r\n        <br>BEGIN</p>\r\n\r\n<p>{{abuselinks}}</p>\r\n\r\n<p>END</p>\r\n\r\n<p>Should you require any further information regarding this matter, please contact us by email <a href=\"mailto:info@meldpunt-kinderporno.nl\">info@meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n <br>\r\n</p>\r\n\r\n<p>Kind regards,</p>\r\n\r\n<p>Meldpunt Kinderporno op Internet | Dutch hotline against child sexual abuse material on the Internet <a href=\"http://www.meldpunt-kinderporno.nl\">www.meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n    <br>\r\n</p>\r\n\r\n<p><strong>Who we are</strong> The Dutch Hotline combating Child Pornography on the Internet (Meldpunt Kinderporno) is an independent private foundation. Meldpunt receives subsidies to support its activities from the Dutch Ministry of Security and Justice and the European Commission. Since the start of the Dutch Hotline there has been a close cooperation between the Hotline and the Dutch Police. We are also one on the founders of <a href=\"http://www.inhope.org/\">http://www.inhope.org/</a>INHOPE, the international network of Internet hotline against child sexual abuse on the Internet. The report procedure, together with supporting information, is published on our website.</p>",],
        ]);

        Db::table('abuseio_scart_grade_question')->truncate();
        Db::table('abuseio_scart_grade_question')->insert([
            ['questiongroup' => "illegal",'sortnr' => "1",'type' => "radio",'label' => "Punishable",'name' => "punishable",'span' => "full",'url_type' => "mainurl",'iccam_field' => "ClassificationID",],
            ['questiongroup' => "illegal",'sortnr' => "2",'type' => "select",'label' => "Sex",'name' => "sex",'span' => "left",'url_type' => "mainurl",'iccam_field' => "GenderID",],
            ['questiongroup' => "illegal",'sortnr' => "3",'type' => "select",'label' => "Age",'name' => "age",'span' => "right",'url_type' => "mainurl",'iccam_field' => "AgeGroupID",],
            ['questiongroup' => "not_illegal",'sortnr' => "1",'type' => "radio",'label' => "Reason",'name' => "reason",'span' => "left",'url_type' => "mainurl",'iccam_field' => "",],
            ['questiongroup' => "police",'sortnr' => "1",'type' => "radio",'label' => "Reason",'name' => "reason",'span' => "full",'url_type' => "mainurl",'iccam_field' => "",],
        ]);

        Db::table('abuseio_scart_grade_question_option')->truncate();
        Db::table('abuseio_scart_grade_question_option')->insert([

            ['grade_question_id' => "1",'sortnr' => "1",'value' => "BA",'label' => "Baseline CSAM",],
            ['grade_question_id' => "1",'sortnr' => "2",'value' => "NA",'label' => "National CSAM",],

            ['grade_question_id' => "2",'sortnr' => "1",'value' => "MA",'label' => "Male",],
            ['grade_question_id' => "2",'sortnr' => "2",'value' => "FE",'label' => "Female",],
            ['grade_question_id' => "2",'sortnr' => "3",'value' => "UN",'label' => "Not Determined",],
            ['grade_question_id' => "2",'sortnr' => "4",'value' => "BO",'label' => "Both",],

            ['grade_question_id' => "3",'sortnr' => "1",'value' => "ND",'label' => "Not Determined",],
            ['grade_question_id' => "3",'sortnr' => "2",'value' => "IN",'label' => "Infant",],
            ['grade_question_id' => "3",'sortnr' => "3",'value' => "PP",'label' => "Pre-pubescent",],
            ['grade_question_id' => "3",'sortnr' => "4",'value' => "PU",'label' => "Pubescent",],

            ['grade_question_id' => "4",'sortnr' => "1",'value' => "AD",'label' => "Adult",],
            ['grade_question_id' => "4",'sortnr' => "2",'value' => "NU",'label' => "Nudism",],
            ['grade_question_id' => "4",'sortnr' => "3",'value' => "CH",'label' => "Children",],
            ['grade_question_id' => "4",'sortnr' => "4",'value' => "VI",'label' => "Virtual",],
            ['grade_question_id' => "4",'sortnr' => "5",'value' => "NF",'label' => "Not found",],
            ['grade_question_id' => "4",'sortnr' => "6",'value' => "CP",'label' => "Cloud (personal)",],
            ['grade_question_id' => "4",'sortnr' => "7",'value' => "NA",'label' => "Not accessible (more info needed)",],
            ['grade_question_id' => "4",'sortnr' => "8",'value' => "NI",'label' => "Not illegal",],
            ['grade_question_id' => "4",'sortnr' => "9",'value' => "OT",'label' => "Other",],
            ['grade_question_id' => "4",'sortnr' => "10",'value' => "Li",'label' => "Linking",],

            ['grade_question_id' => "5",'sortnr' => "1",'value' => "R1",'label' => "Bad hoster",],
            ['grade_question_id' => "5",'sortnr' => "2",'value' => "R2",'label' => "New hoster with attention",],
            ['grade_question_id' => "5",'sortnr' => "3",'value' => "R3",'label' => "Country related - please soon FEEDBACK",],
            ['grade_question_id' => "5",'sortnr' => "4",'value' => "R4",'label' => "Possible new victium - please soon FEEDBACK",],
            ['grade_question_id' => "5",'sortnr' => "5",'value' => "R5",'label' => "No INHOPE country",],
            ['grade_question_id' => "5",'sortnr' => "6",'value' => "R6",'label' => "TOR - not traceble ",],
            ['grade_question_id' => "5",'sortnr' => "7",'value' => "R8",'label' => "Hoster unreachable",],
        ]);

    }
}
