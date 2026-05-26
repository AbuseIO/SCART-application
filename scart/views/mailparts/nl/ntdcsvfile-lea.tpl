{% for header in headers %}"{{ header }}";{% endfor %}{{ crlf }}{% for line in lines %}{% for header in headers %}"{{ attribute(line,header) }}";{% endfor %}{{ crlf }}{% endfor %}
