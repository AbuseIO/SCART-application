
# own layout default sending mail

UPDATE system_mail_layouts
    SET content_css = '',
    content_html = '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
    <style>
    .urls table,.urls th,.urls td {
        border: 1px solid gray;
        padding: 2px;
        border-collapse: collapse;
    }
    </style>
</head>
<body style=\"margin:0; padding: 2px; background: white; \">
    <table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
        <tr>
            <td align=\"center\">
                <table class=\"content\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
                    <!-- Email Body -->
                    <tr>
                        <td class=\"body\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
                        {{ content|raw }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>'
WHERE `system_mail_layouts`.`name` = 'Default layout';


