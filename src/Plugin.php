<?php namespace BranMuffin\GoogleConnect;

use System\Classes\PluginBase;

require 'vendor/autoload.php';

class Plugin extends PluginBase
{
    public function registerComponents()
    {
        return [
            'BranMuffin\GoogleConnect\Components\UserCalendar' => 'userCalendar',
            'BranMuffin\GoogleConnect\Components\GetCalendar' => 'getCalendar'
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Google Connect',
                'description' => 'Manage Google Connect Settings.',
                'category'    => "Bran's Google Connect",
                'icon'        => 'icon-google',
                'class'       => 'Branmuffin\GoogleConnect\Models\Settings',
                'order'       => 500,
                'keywords'    => 'google connect',
                'permissions' => ['branmuffin.googleconnect.access_settings']
            ]
        ];
    }
}
