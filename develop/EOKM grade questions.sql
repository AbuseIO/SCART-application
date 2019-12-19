
# Questions
TRUNCATE `reportertool_eokm_grade_question`;
INSERT INTO `reportertool_eokm_grade_question` (`id`, `questiongroup`, `sortnr`, `type`, `label`, `name`, `span`) VALUES
(1, 'illegal', 1, 'radio', 'Punishable', 'punishable', 'full'),
(2, 'illegal', 2, 'select', 'Sex', 'sex', 'left'),
(3, 'illegal', 3, 'select', 'Age', 'age', 'right'),
(4, 'illegal', 4, 'checkbox', 'Image', 'image', 'left'),
(5, 'illegal', 5, 'checkbox', 'Activity', 'activity', 'right'),
(6, 'not_illegal', 1, 'radio', 'Reason', 'reason', 'left');

# question options
TRUNCATE `reportertool_eokm_grade_question_option`;
INSERT INTO `reportertool_eokm_grade_question_option` (`grade_question_id`, `sortnr`, `value`, `label`) VALUES
(1, 1, 'BA', 'Baseline'),
(1, 2, 'NA', 'National'),
(2, 1, 'MA', 'Male'),
(2, 2, 'FE', 'Female'),
(2, 3, 'UN', 'Undetermined'),
(3, 1, 'IN', 'Infant'),
(3, 2, 'PP', 'Pre pubescent'),
(3, 3, 'PU', 'Pubescent'),
(4, 1, 'VI', 'Virtual'),
(4, 2, 'ST', 'Stills'),
(4, 3, 'PO', 'Posing/series'),
(4, 4, 'MO', 'Morph/tekst'),
(4, 5, 'DE', 'Depiction genitalia'),
(4, 6, 'AC', 'Activity / masturbation'),
(4, 7, 'OR', 'Oral'),
(4, 8, 'PE', 'Penetration'),
(4, 9, 'SA', 'Sadism / bestiality'),
(5, 2, 'BA', 'By adults'),
(5, 1, 'BC', 'by child'),
(5, 3, 'BU', 'By unclear'),
(5, 4, 'OC', 'On child'),
(5, 5, 'OA', 'On adult'),
(5, 6, 'OU', 'On unclear'),
(6, 1, 'AD', 'Adult'),
(6, 2, 'NU', 'Nudism'),
(6, 3, 'CH', 'Children'),
(6, 4, 'VI', 'Virtual'),
(6, 5, 'NF', 'Not found'),
(6, 6, 'CP', 'Cloud (personal)'),
(6, 7, 'NA', 'Not accessible (more info needed)'),
(6, 8, 'MA', 'Moved (After reported)'),
(6, 9, 'MB', 'Moved (Before reported)'),
(6, 10, 'NI', 'Not illegal'),
(6, 11, 'OT', 'Other');

