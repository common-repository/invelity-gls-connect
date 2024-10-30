<?php
/*
Plugin Name: Invelity GLS connect
Plugin URI: https://www.invelity.com/sk/sluzby
Description: Plugin Invelity GLS export je vytvorený pre obchodníkov na platforme Woocommerce ktorý potrebuju automaticky exportovat údaje o objednávkach do systému GLS online za účelom vytlačenia doručovacích lístkov.
Author: Invelity
Author URI: https://www.invelity.com
Version: 1.1.7
*/
defined('ABSPATH') or die('No script kiddies please!');

require_once('classes/class.invelityGlsExportAdmin.php');
require_once('classes/class.invelityGlsExportProcess.php');
if (!class_exists('InvelityPluginsAdmin')) {
    require_once('classes/class.invelityPluginsAdmin.php');
}

class InvelityGlsConnect
{
    public $settings = [];

    public function __construct()
    {
        $this->settings['plugin-slug'] = 'invelity-gls-connect';
        $this->settings['old-plugin-slug'] = 'finest-gls-connect';
        $this->settings['plugin-path'] = plugin_dir_path(__FILE__);
        $this->settings['plugin-url'] = plugin_dir_url(__FILE__);
        $this->settings['plugin-name'] = 'Invelity Gls Connect';
        $this->settings['plugin-license-version'] = '1.x.x';
        $this->initialize();
    }

    private function initialize()
    {
        new InvelityPluginsAdmin($this);
        new InvelityGlsConnectAdmin($this);
        new InvelityGlsConnectProcess($this);

    }

    public function getPluginSlug()
    {
        return $this->settings['plugin-slug'];
    }

    public function getPluginPath()
    {
        return $this->settings['plugin-path'];
    }

    public function getPluginUrl()
    {
        return $this->settings['plugin-url'];
    }

    public function getPluginName()
    {
        return $this->settings['plugin-name'];
    }

    public function getOldPluginSlug()
    {
        return $this->settings['old-plugin-slug'];
    }

}

new InvelityGlsConnect();




