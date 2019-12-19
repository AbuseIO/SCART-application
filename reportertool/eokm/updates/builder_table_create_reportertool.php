<?php namespace ReporterTool\EOKM\Updates;

/**
 * Create ERT tables
 *
 * 2019
 *
 *
 */

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateReportertool extends Migration
{
    public function up() {

        Schema::create('reportertool_eokm_abusecontact', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('filenumber', 255);
            $table->string('owner', 255);
            $table->text('aliases')->nullable()->default('');
            $table->text('domains')->nullable()->default('');
            $table->string('abusecustom', 255);
            $table->string('abusecountry', 255);
            $table->smallInteger('groupby_hours')->default('24');
            $table->text('note');
            $table->integer('ntd_template_id')->unsigned();
            $table->string('ntd_msg_subject', 255);
            $table->text('ntd_msg_body');
            $table->char('gdpr_approved',1);
            $table->char('police_contact',0);

        });

        Schema::create('reportertool_eokm_grade_question', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('questiongroup', 40);
            $table->smallInteger('sortnr');
            $table->string('type', 20);
            $table->string('label', 255);
            $table->string('name', 80);
            $table->string('span', 20);

        });

        Schema::create('reportertool_eokm_grade_question_option', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('grade_question_id')->unsigned();
            $table->smallInteger('sortnr');
            $table->string('value', 80);
            $table->string('label', 255);
            $table->index('grade_question_id');
        });

        Schema::create('reportertool_eokm_grade_answer', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('record_type');
            $table->integer('record_id');
            $table->integer('grade_question_id');
            $table->text('answer');

            $table->index(['record_type','record_id'],'record_type_id');

        });

        Schema::create('reportertool_eokm_grade_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr');
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_input', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('workuser_id')->nullable()->unsigned();
            $table->string('filenumber', 255);
            $table->timestamp('received_at')->nullable();

            $table->string('url', 512);
            $table->string('url_ip',255);
            $table->string('url_base', 255);
            $table->string('url_host', 255);
            $table->string('url_referer', 255);
            $table->string('url_hash', 255);
            $table->string('url_type', 40)->default('mainurl');
            $table->string('url_image_width', 255);
            $table->string('url_image_height', 255);
            $table->string('reference', 255);
            $table->string('type_code', 40);
            $table->string('source_code', 40);
            $table->string('status_code', 40);
            $table->string('grade_code', 40)->default('unset');
            $table->integer('registrar_abusecontact_id')->nullable()->unsigned();
            $table->integer('host_abusecontact_id')->nullable()->unsigned();
            $table->text('note');

            // general stats
            $table->smallInteger('browse_error_retry')->default(0);
            $table->timestamp('firstseen_at')->nullable();
            $table->smallInteger('online_counter')->nullable();
            $table->timestamp('lastseen_at')->nullable();
            $table->timestamp('firstntd_at')->nullable();
            $table->smallInteger('whois_error_retry')->default(0);

            $table->index('url');
            $table->index('url_hash');
            $table->index('url_type');

        });

        Schema::create('reportertool_eokm_input_import', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->mediumText('import_result');
        });

        Schema::create('reportertool_eokm_input_source', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->smallInteger('sortnr');
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_input_type', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->smallInteger('sortnr');
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_input_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_log', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('user_id')->unsigned();
            $table->string('dbtable', 255);
            $table->integer('record_id')->unsigned();
            $table->text('logtext');

            $table->index('record_id');

        });

        Schema::create('reportertool_eokm_notification', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('workuser_id')->nullable()->unsigned();
            $table->string('filenumber', 255);
            $table->timestamp('received_at')->nullable();

            $table->string('url', 512);
            $table->string('url_ip',255);
            $table->string('url_base', 255);
            $table->string('url_referer', 255);
            $table->string('url_host', 255);
            $table->string('url_hash', 255);
            $table->string('url_type', 40)->default('imageurl');
            $table->integer('url_image_width')->nullable()->unsigned();
            $table->integer('url_image_height')->nullable()->unsigned();
            $table->string('reference', 255);
            $table->string('source_code', 40);
            $table->string('status_code', 40);
            $table->string('grade_code', 40)->default('unset');
            $table->string('type_code', 40);

            $table->integer('registrar_abusecontact_id')->nullable()->unsigned();
            $table->integer('host_abusecontact_id')->nullable()->unsigned();

            $table->text('note');

            // general stats
            $table->smallInteger('browse_error_retry')->default(0);
            $table->timestamp('firstseen_at')->nullable();
            $table->smallInteger('online_counter')->nullable();
            $table->timestamp('lastseen_at')->nullable();
            $table->timestamp('firstntd_at')->nullable();
            $table->smallInteger('whois_error_retry')->default(0);

            $table->index('url');
            $table->index('url_hash');
            $table->index('url_type');

        });

        Schema::create('reportertool_eokm_notification_input', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->integer('input_id')->nullable()->unsigned();
            $table->integer('notification_id')->nullable()->unsigned();

        });

        Schema::create('reportertool_eokm_notification_selected', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->integer('notification_id')->unsigned();
            $table->boolean('set');
        });

        Schema::create('reportertool_eokm_notification_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_user_options', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('user_id')->unsigned();
            $table->string('name', 255);
            $table->text('value');
        });

        Schema::create('reportertool_eokm_ntd', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('filenumber', 255);
            $table->integer('abusecontact_id')->unsigned();

            $table->string('status_code',40)->default(ERT_NTD_STATUS_INIT);
            $table->timestamp('status_time')->nullable();

            $table->timestamp('groupby_start')->nullable();
            $table->smallInteger('groupby_hour_count');
            $table->smallInteger('groupby_hour_threshold');

            $table->timestamp('msg_queued')->nullable();
            $table->string('msg_abusecontact', 255);
            $table->string('msg_subject', 255);
            $table->text('msg_body');
            $table->string('msg_ident', 255);

            $table->index('abusecontact_id');

        });

        Schema::create('reportertool_eokm_ntd_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_ntd_url', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('ntd_id')->unsigned();
            $table->string('record_type', 40);
            $table->integer('record_id')->unsigned();
            $table->string('url', 255);
            $table->text('note');
            $table->timestamp('firstseen_at')->nullable();
            $table->timestamp('lastseen_at')->nullable();
            $table->smallInteger('online_counter')->nullable();
            $table->index('ntd_id');
        });

        Schema::create('reportertool_eokm_ntd_template', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('title', 255);
            $table->string('subject', 255);
            $table->text('body');
        });

        Schema::create('reportertool_eokm_audittrail', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->string('user', 255);
            $table->string('remote_address', 255);
            $table->string('dbtable', 255);
            $table->string('dbfunction', 20);
            $table->string('fieldlist', 255);
            $table->text('oldvalues');
            $table->text('newvalues');
        });

        Schema::create('reportertool_eokm_manual', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('lang', 10)->default('NL');
            $table->smallInteger('chapter');
            $table->smallInteger('section')->nullable(true)->default(null);
            $table->string('title', 191);
            $table->text('text');

        });

        Schema::create('reportertool_eokm_input_lock', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->integer('input_id')->unsigned();
            $table->index('input_id');
        });

        Schema::create('reportertool_eokm_whois', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('abusecontact_id')->unsigned();
            $table->string('whois_type', 40);
            $table->timestamp('whois_timestamp');
            $table->string('whois_lookup', 255);
            $table->string('name', 255);
            $table->string('country', 255);
            $table->string('abusecontact', 255);
            $table->text('rawtext');

            $table->index(['abusecontact_id','whois_type','whois_timestamp'],'abusecontact_type_timestamp');

        });

        Schema::create('reportertool_eokm_scrape_cache', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('code', 255);
            $table->longText('cached');
            $table->index('code');
        });

        Schema::create('reportertool_eokm_domainrule', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('domain', 255);
            $table->string('type_code', 40);
            $table->string('ip', 255);
            $table->integer('abusecontact_id')->nullable()->unsigned();

            $table->index(['type_code','domain'],'type_code_domain');
        });


        Schema::create('reportertool_eokm_rule_type', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr')->nullable();
            $table->string('code', 40);
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');

        });

        Schema::create('reportertool_eokm_importexport_job', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('interface', 40);
            $table->string('action', 40);
            $table->string('checksum', 255);
            $table->text('data')->nullable();
        });

        Schema::create('reportertool_eokm_alert', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('checksum', 255);
            $table->smallInteger('level');
            $table->string('mailview', 255);
            $table->longText('parameters');
            $table->string('status_code',40);
            $table->timestamp('status_at')->nullable();
            $table->index('checksum');
            $table->index('status_code');
        });

        Schema::create('reportertool_eokm_alert_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr');
            $table->string('code', 40)->default('');
            $table->string('lang', 10);
            $table->string('title', 255);
            $table->text('description');
        });

        Schema::create('reportertool_eokm_blockedday', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->date('day');
            $table->string('description');
            $table->index('day');
        });

    }
    
    public function down() {

        Schema::dropIfExists('reportertool_eokm_abusecontact');
        Schema::dropIfExists('reportertool_eokm_grade_question');
        Schema::dropIfExists('reportertool_eokm_grade_question_option');
        Schema::dropIfExists('reportertool_eokm_grade_answer');
        Schema::dropIfExists('reportertool_eokm_grade_status');
        Schema::dropIfExists('reportertool_eokm_importexport_job');
        Schema::dropIfExists('reportertool_eokm_input');
        Schema::dropIfExists('reportertool_eokm_input_import');
        Schema::dropIfExists('reportertool_eokm_input_type');
        Schema::dropIfExists('reportertool_eokm_input_source');
        Schema::dropIfExists('reportertool_eokm_input_status');
        Schema::dropIfExists('reportertool_eokm_log');
        Schema::dropIfExists('reportertool_eokm_notification');
        Schema::dropIfExists('reportertool_eokm_notification_input');
        Schema::dropIfExists('reportertool_eokm_notification_selected');
        Schema::dropIfExists('reportertool_eokm_notification_status');
        Schema::dropIfExists('reportertool_eokm_user_options');
        Schema::dropIfExists('reportertool_eokm_ntd');
        Schema::dropIfExists('reportertool_eokm_ntd_status');
        Schema::dropIfExists('reportertool_eokm_ntd_url');
        Schema::dropIfExists('reportertool_eokm_ntd_template');
        Schema::dropIfExists('reportertool_eokm_audittrail');
        Schema::dropIfExists('reportertool_eokm_manual');
        Schema::dropIfExists('reportertool_eokm_input_lock');
        Schema::dropIfExists('reportertool_eokm_whois');
        Schema::dropIfExists('reportertool_eokm_scrape_cache');
        Schema::dropIfExists('reportertool_eokm_domainrule');
        Schema::dropIfExists('reportertool_eokm_rule_type');
        Schema::dropIfExists('reportertool_eokm_alert');
        Schema::dropIfExists('reportertool_eokm_alert_status');
        Schema::dropIfExists('reportertool_eokm_blockedday');

    }
}
