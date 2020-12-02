<?php 
require "vendor/autoload.php";

use Aws\S3\S3Client;

Class WpAjax {

	public function __construct() {
        add_action('wp_ajax_static_deploy_output', array($this, 'deploy') );
        add_action('wp_ajax_nopriv_static_deploy_output', array($this, 'deploy') );
    }

    public function deploy(){
        die('teste');
    }
}