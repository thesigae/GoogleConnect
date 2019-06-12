<?php namespace BranMuffin\GoogleConnect\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateBranmuffinGoogleconnectClient extends Migration
{
    public function up()
    {
        Schema::create('branmuffin_googleconnect_client', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('userid');
            $table->text('token');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('branmuffin_googleconnect_client');
    }
}
