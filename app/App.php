<?php

namespace WpRloutHtml;

// Essentials
use WpRloutHtml\Essentials\Enqueue;
use WpRloutHtml\Essentials\Menu;
use WpRloutHtml\Essentials\WpAjax;

// Apps
use WpRloutHtml\Posts;
use WpRloutHtml\Terms;

Class App {
    
    // nome do plugin
    static $name_plugin = 'Relation Output HTML';
    
    // Evitar repetições de arquivos para a estatização
    static $repeat_files_rlout = array();
    
    public function __construct(){

        // Enquee de Js e Css
        $this->enqueue = new Enqueue;

        // Configurações do menu e dos resources
        $this->menu = new Menu;

        // Iniciando actions em Ajax privada
        $this->wpajax = new WpAjax;

        // Iniciando verificação para as alterações de posts
        $this->posts = new Posts;

        // Iniciando verificação para as alterações de terms
        $this->terms = new Terms;
    }
    
}