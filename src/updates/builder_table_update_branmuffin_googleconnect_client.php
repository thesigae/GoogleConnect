<?php namespace BranMuffin\GoogleConnect\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateBranmuffinGoogleconnectClient extends Migration
{
    public function up()
    {
        Schema::table('branmuffin_googleconnect_client', function($table)
        {
            $table->string('service');
        });
    }
    
    public function down()
    {
        Schema::table('branmuffin_googleconnect_client', function($table)
        {
            $table->dropColumn('service');
        });
    }
}
