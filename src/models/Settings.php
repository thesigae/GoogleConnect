<?php namespace Branmuffin\GoogleConnect\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = 'brans_Googleconnect_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';
}