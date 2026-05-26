<table class="urls" border="0" cellpadding="2" cellspacing="0">
    <tr>
        {% for header in headers %}
        <th align="left">{{header}}</th>
        {% endfor %}
    </tr>
    {% for line in lines %}
    <tr>
        {% for header in headers %}
        <td>{{ attribute(line,header) }}&nbsp;</td>
        {% endfor %}
    </tr>
    {% endfor %}
</table>
