<?php
namespace abuseio\scart\widgets\tiles\classes\helpers;


use abuseio\scart\classes\classify\scartGrade;

class Form {



    /**
     * Set button according status
     *
     * @param $item
     * @return array
     */
    public static function setButtons($item,$workuser_id, $select='') {

        $class = '';
        $buttonsets = [];

        // selected
        if ($select==='') $select = scartGrade::getGradeSelected($workuser_id, $item->id);

        // default set
        $buttonsets = [
            'SELECT' => ($select) ? 'true' : 'false',
            'YES' => 'false',
            'IGNORE' => 'false',
            'NO' => 'false',
        ];

        // specific set
        if ($item->grade_code == SCART_GRADE_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'true',
                'IGNORE' => 'false',
                'NO' => 'false',
            ];
            $class = 'grade_button_illegal';

        } elseif ($item->grade_code == SCART_GRADE_IGNORE) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'true',
                'NO' => 'false',
            ];
            $class = 'grade_button_ignore';

        } elseif ($item->grade_code == SCART_GRADE_NOT_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'false',
                'NO' => 'true',
            ];
            $class = 'grade_button_notillegal';

        }

        $buttonsets['POLICE'] = ( ($item->classify_status_code==SCART_STATUS_FIRST_POLICE) ? 'true' : 'false');

        $buttonsets['MANUAL'] = ( ($item->classify_status_code==SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? 'true' : 'false');

        return [
            'class' => $class,
            'buttonsets' => $buttonsets,
        ];
    }

}
