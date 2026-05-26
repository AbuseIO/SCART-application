<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartAbusecontact extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_abusecontact', function($table)
        {
            $table->boolean('lea_contact')->default(0);
            $table->text('aliases')->default(null)->change();
            $table->text('domains')->default(null)->change();
            $table->string('gdpr_approved', 1)->change();
            $table->string('police_contact', 1)->change();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_abusecontact', function($table)
        {
            $table->dropColumn('lea_contact');
            $table->text('aliases')->default(null)->change();
            $table->text('domains')->default(null)->change();
            $table->string('gdpr_approved', 1)->change();
            $table->string('police_contact', 1)->change();
        });
    }
}
