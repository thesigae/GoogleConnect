<?php namespace BranMuffin\GoogleConnect\Models;

use Model;

/**
 * Model
 */
class Configs extends Model
{
    use \October\Rain\Database\Traits\Validation;
    
    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'branmuffin_googleconnect_configs';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
