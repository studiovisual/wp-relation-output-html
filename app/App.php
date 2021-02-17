<?php

namespace WpRloutHtml;

// Essentials
use WpRloutHtml\Enqueue;
use WpRloutHtml\Menu;

// Apps
use WpRloutHtml\Posts;

Class App {
    
    static $name_plugin = 'Relation Output HTML';
    
    static $repeat_files_rlout = array();
    
    public function __construct(){

        $this->enqueue = new Enqueue;
        $this->menu = new Menu;

        $this->posts = new Posts;
    }
    
}