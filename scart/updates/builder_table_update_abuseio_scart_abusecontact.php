<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartAbusecontact extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_abusecontact', function($table)
        {
            $table->string('ntd_type', 20)->default('email');
            $table->integer('ntd_api_addon_id')->nullable()->unsigned();
            $table->string('gdpr_approved', 1)->change();
            $table->string('police_contact', 1)->change();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_abusecontact', function($table)
        {
            $table->dropColumn('ntd_type');
            $table->dropColumn('ntd_api_addon_id');
            $table->string('gdpr_approved', 1)->change();
            $table->string('police_contact', 1)->change();
        });
    }
}
