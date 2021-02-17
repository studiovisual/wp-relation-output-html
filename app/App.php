<?php

namespace WpRloutHtml;

// Essentials
use WpRloutHtml\Essentials\Enqueue;
use WpRloutHtml\Essentials\Menu;
use WpRloutHtml\Essentials\WpAjax;

// Apps
use WpRloutHtml\Posts;

Class App {
    
    static $name_plugin = 'Relation Output HTML';
    
    static $repeat_files_rlout = array();
    
    public function __construct(){

        $this->enqueue = new Enqueue;
        $this->menu = new Menu;
        $this->wpajax = new WpAjax;

        $this->posts = new Posts;
    }
    
}