<?php namespace abuseio\scart\Updates;

/**
 * Create V5 tables
 *
 * 2021/5/31
 *   integration of all previous builders files
 *
 */

use Db;
use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateV6Tables extends Migration
{
    public function up() {

        Schema::create('abuseio_scart_abusecontact', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('filenumber', 255)->nullable();
            $table->string('owner', 255);
            $table->text('aliases')->nullable()->default('');
            $table->text('domains')->nullable()->default('');
            $table->string('abusecustom', 255)->nullable();
            $table->string('abusecountry', 255)->nullable();
            $table->smallInteger('groupby_hours')->default('24');
            $table->text('note')->nullable();
            $table->integer('ntd_template_id')->unsigned();
            $table->string('ntd_msg_subject', 255)->nullable();
            $table->text('ntd_msg_body')->nullable();
            $table->char('gdpr_approved',1)->default(0);
            $table->char('police_contact',1)->default(0);

        });

        Schema::create('abuseio_scart_grade_question', function($table)
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

        Schema::create('abuseio_scart_grade_question_option', function($table)
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

        Schema::create('abuseio_scart_grade_answer', function($table)
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

        Schema::create('abuseio_scart_grade_status', function($table)
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

        Schema::create('abuseio_scart_input', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('workuser_id')->nullable()->unsigned();
            $table->string('filenumber', 255)->nullable();
            $table->timestamp('received_at')->nullable();

            $table->string('url', 512);
            $table->string('url_ip',255)->nullable();
            $table->string('url_base', 255)->nullable();
            $table->string('url_host', 255)->nullable();
            $table->string('url_referer', 255)->nullable();
            $table->string('url_hash', 255)->nullable();
            $table->string('url_type', 40)->default('mainurl');
            $table->string('url_image_width', 255)->nullable();
            $table->string('url_image_height', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('type_code', 40)->nullable();
            $table->string('source_code', 40)->nullable();
            $table->string('status_code', 40)->nullable();
            $table->string('grade_code', 40)->default('unset');
            $table->integer('registrar_abusecontact_id')->nullable()->unsigned();
            $table->integer('host_abusecontact_id')->nullable()->unsigned();
            $table->text('note')->nullable();

            // general stats
            $table->smallInteger('browse_error_retry')->default(0)->nullable();
            $table->timestamp('firstseen_at')->nullable();
            $table->smallInteger('online_counter')->nullable();
            $table->timestamp('lastseen_at')->nullable();
            $table->timestamp('firstntd_at')->nullable();
            $table->smallInteger('whois_error_retry')->default(0)->nullable();
            $table->smallInteger('delivered_items')->default(0)->nullable();

            $table->string('classify_status_code', 40)->nullable();
            $table->dateTime('hashcheck_at')->nullable();
            $table->string('hashcheck_format', 20)->nullable();
            $table->boolean('hashcheck_return')->nullable();
            $table->double('checkonline_leadtime', 10, 0)->nullable();
            $table->text('ntd_note')->nullable();

            // check
            $table->integer('proxy_abusecontact_id')->nullable()->unsigned();
            $table->text('proxy_call_error')->nullable();

            $table->index('url');
            $table->index('url_hash');
            $table->index('url_type');
            $table->index('status_code');

        });

        Schema::create('abuseio_scart_input_import', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->mediumText('import_result');
        });

        Schema::create('abuseio_scart_input_source', function($table)
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

        Schema::create('abuseio_scart_input_type', function($table)
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

        Schema::create('abuseio_scart_input_parent', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('parent_id')->nullable()->unsigned();
            $table->integer('input_id')->nullable()->unsigned();
            $table->index('parent_id');
        });


        Schema::create('abuseio_scart_input_status', function($table)
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

        Schema::create('abuseio_scart_input_selected', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->integer('input_id')->unsigned();
            $table->boolean('set');
            $table->index('workuser_id');
        });


        Schema::create('abuseio_scart_log', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('user_id')->unsigned();
            $table->string('record_type', 255);
            $table->integer('record_id')->unsigned();
            $table->text('logtext');

            $table->index('record_id');

        });

        Schema::create('abuseio_scart_user_options', function($table)
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

        Schema::create('abuseio_scart_ntd', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('filenumber', 255)->nullable();
            $table->integer('abusecontact_id')->unsigned();

            $table->string('status_code',40)->default(SCART_NTD_STATUS_INIT);
            $table->timestamp('status_time')->nullable();

            $table->timestamp('groupby_start')->nullable();
            $table->smallInteger('groupby_hour_count')->default(0);
            $table->smallInteger('groupby_hour_threshold')->default(0);

            $table->timestamp('msg_queued')->nullable();
            $table->string('msg_abusecontact', 255)->nullable();
            $table->string('msg_subject', 255)->nullable();
            $table->text('msg_body')->nullable();
            $table->string('msg_ident', 255)->nullable();

            $table->index('abusecontact_id');

        });

        Schema::create('abuseio_scart_ntd_status', function($table)
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

        Schema::create('abuseio_scart_ntd_url', function($table)
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
            $table->text('note')->nullable();
            $table->timestamp('firstseen_at')->nullable();
            $table->timestamp('lastseen_at')->nullable();
            $table->smallInteger('online_counter')->nullable();
            $table->index('ntd_id');
        });

        Schema::create('abuseio_scart_ntd_template', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('title', 255);
            $table->string('subject', 255);
            $table->text('body');
            $table->char('csv_attachment',1)->default('1');
            $table->char('add_only_url',1)->default('0');
        });

        Schema::create('abuseio_scart_audittrail', function($table)
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

        Schema::create('abuseio_scart_manual', function($table)
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

        Schema::create('abuseio_scart_input_lock', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('workuser_id')->unsigned();
            $table->integer('input_id')->unsigned();
            $table->index('input_id');
        });

        Schema::create('abuseio_scart_whois', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('abusecontact_id')->unsigned();
            $table->string('whois_type', 40)->nullable();
            $table->timestamp('whois_timestamp')->nullable();
            $table->string('whois_lookup', 255);
            $table->string('name', 255)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('abusecontact', 255)->nullable();
            $table->text('rawtext')->nullable();

            $table->index(['abusecontact_id','whois_type','whois_timestamp'],'abusecontact_type_timestamp');

        });

        Schema::create('abuseio_scart_scrape_cache', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('code', 255);
            $table->longText('cached')->nullable();
            $table->index('code');
        });

        Schema::create('abuseio_scart_domainrule', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('type_code', 40)->nullable();
            $table->string('ip', 255)->nullable();
            $table->integer('abusecontact_id')->nullable()->unsigned();
            $table->mediumText('rulesetdata')->nullable();
            $table->integer('addon_id')->nullable()->unsigned();
            $table->integer('proxy_abusecontact_id')->nullable()->unsigned();
            $table->boolean('enabled')->default(true);

            $table->index(['type_code','domain'],'type_code_domain');
        });


        Schema::create('abuseio_scart_rule_type', function($table)
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

        Schema::create('abuseio_scart_importexport_job', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('interface', 40);
            $table->string('action', 40)->nullable();
            $table->string('checksum', 255)->nullable();
            $table->text('data')->nullable();
            $table->string('status', 10)->default('export');
            $table->text('status_text')->nullable();
        });

        Schema::create('abuseio_scart_alert', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('checksum', 255)->nullable();
            $table->smallInteger('level');
            $table->string('mailview', 255);
            $table->longText('parameters')->nullable();
            $table->string('status_code',40);
            $table->timestamp('status_at')->nullable();
            $table->index('checksum');
            $table->index('status_code');
        });

        Schema::create('abuseio_scart_alert_status', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr')->nullable();
            $table->string('code', 40)->default('');
            $table->string('lang', 10)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
        });

        Schema::create('abuseio_scart_blockedday', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->date('day');
            $table->string('description')->nullable();
            $table->index('day');
        });

        Schema::create('abuseio_scart_user', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->integer('be_user_id')->unsigned();
            $table->string('be_first_name')->nullable();
            $table->string('be_last_name')->nullable();
            $table->string('be_email')->nullable();
            $table->string('be_password')->nullable();
            $table->integer('be_role_id')->nullable()->unsigned();

            $table->boolean('disabled')->nullable()->default(0);
            $table->string('workschedule')->nullable();

            $table->index('be_user_id');

        });

        Schema::create('abuseio_scart_addon', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('type', 40);
            $table->string('codename');
            $table->string('title');
            $table->boolean('enabled')->default(false);
            $table->boolean('classexists')->default(false);
            $table->text('description')->nullable();
            $table->boolean('valid')->default(0);

            $table->index('type','record_type');
            $table->index('enabled','record_enabled');
        });

        Schema::create('abuseio_scart_addon_type', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->smallInteger('sortnr');
            $table->string('code', 40);
            $table->string('lang', 10)->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();


        });

        Schema::create('abuseio_scart_config', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('maintenance-mode')->default(false)->nullable();
            $table->string('whois-provider',255)->default('phpwhois')->nullable();
            $table->string('browser-provider',255)->default('BrowserBasic')->nullable();
            $table->string('errors-email',255)->nullable();
            $table->string('errors-domain',255)->nullable();
            $table->boolean('classify-show_no_registrar')->default(false)->nullable();
            $table->string('alerts-recipient',255)->nullable();
            $table->string('alerts-bcc_recipient',255)->nullable();
            $table->string('alerts-admin_recipient',255)->nullable();
            $table->smallinteger('scheduler-sendalerts-info')->default(60)->nullable();
            $table->smallinteger('scheduler-sendalerts-warning')->default(3)->nullable();
            $table->smallinteger('scheduler-sendalerts-admin')->default(1)->nullable();
            $table->string('alert-language',2)->default('en')->nullable();
            $table->smallinteger('scheduler-scrape-min_image_size')->default(1024)->nullable();
            $table->smallinteger('scheduler-checkntd-check_online_every')->default(60)->nullable();
            $table->string('scheduler-sendntd-from',255)->nullable();
            $table->string('scheduler-sendntd-reply_to',255)->nullable();
            $table->string('scheduler-sendntd-maillogfile',255)->nullable();
            $table->string('scheduler-sendntd-alt_email',255)->nullable();
            $table->string('scheduler-sendntd-bcc',255)->nullable();
            $table->smallinteger('ntd-abusecontact_default_hours')->default(24)->nullable();
            $table->boolean('ntd-registrar_active')->default(true)->nullable();
            $table->smallinteger('ntd-siteowner_interval')->default(3)->nullable();
            $table->smallinteger('ntd-registrar_interval')->default(6)->nullable();
            $table->boolean('ntd-use_blockeddays')->default(true)->nullable();
            $table->smallinteger('ntd-after_blockedday_hours')->default(12)->nullable();
            $table->smallinteger('scheduler-createreports-take')->default(1)->nullable();
            $table->string('scheduler-createreports-recipient',255)->nullable();
            $table->boolean('scheduler-archive-only_delete')->default(true)->nullable();
            $table->smallinteger('scheduler-archive-archive_time')->default(7)->nullable();
            $table->boolean('iccam-active')->default(false)->nullable();
            $table->boolean('scheduler-importexport-iccam_active')->default(true)->nullable();
            $table->smallinteger('iccam-hotlineid')->default(43)->nullable();
            $table->boolean('hashapi-active')->default(false)->nullable();

        });

        Schema::create('abuseio_scart_input_extrafield_option', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('input_extrafield_id')->unsigned();
            $table->smallInteger('sortnr');
            $table->string('value', 80);
            $table->string('label', 255)->nullable();

        });

        Schema::create('abuseio_scart_input_extrafield', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('input_id')->unsigned();
            $table->string('type', 40);
            $table->string('label', 255)->nullable();
            $table->text('value')->nullable();

            $table->index('input_id');
            $table->index(['input_id','type','label'],'input_type_field');

        });

        Schema::create('abuseio_scart_report', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('title', 255);
            $table->string('filter_grade')->nullable();
            $table->string('filter_status')->nullable();
            $table->string('filter_country')->nullable();
            $table->dateTime('filter_start')->nullable();
            $table->dateTime('filter_end')->nullable();
            $table->string('status_code')->nullable();
            $table->integer('number_of_records');
            $table->dateTime('status_at');
            $table->string('status_code', 191)->nullable(false)->default('created')->change();
            $table->integer('number_of_records')->default(0)->change();
            $table->text('filter_grade')->nullable()->unsigned(false)->default(null)->change();
            $table->text('filter_status')->nullable()->unsigned(false)->default(null)->change();


        });

        Schema::create('abuseio_scart_whois_cache', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('target', 255);
            $table->string('target_type', 10)->default('IP');
            $table->dateTime('max_age')->nullable();
            $table->integer('abusecontact_id')->unsigned()->nullable();
            $table->string('real_ip', 255)->nullable();

            $table->index(['target','target_type'],'target_type');

        });

    }

    public function down() {

        $databasename = env('DB_DATABASE', '');
        if ($databasename) {
            $tables = Db::select("show tables like 'abuseio_scart_%' ");
            foreach ($tables AS $table) {
                foreach ($table AS $fld => $val) {
                    Schema::dropIfExists($val);
                }
            }
        }

    }
}
