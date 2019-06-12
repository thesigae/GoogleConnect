<?php namespace BranMuffin\GoogleConnect\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateBranmuffinGoogleconnectConfigs extends Migration
{
    public function up()
    {
        Schema::create('branmuffin_googleconnect_configs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('clientid', 255);
            $table->string('projectid', 255);
            $table->string('authuri', 255);
            $table->string('tokenuri', 255);
            $table->string('certurl', 255);
            $table->string('clientsecret', 255);
            $table->string('redirecturis', 255);
            $table->string('javascriptorigins', 255);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('branmuffin_googleconnect_configs');
    }
}
