<?php

namespace gnaritas\ses;

class GenericAdminPage extends Voce_Settings_Page
{    
    public $template;
    public $plugin;
    public $action = "";

    public function display() {       
        $this->renderTemplate();
    }

    protected function renderTemplate () {
        $context = apply_filters("gn_admin_page_data_{$this->page_key}", $_REQUEST);

        $plugin = $this->plugin;
        $optionsKey = $plugin->optionsKey;
        $prefix = $plugin->prefix;
        $action = $this->action;
        
        ob_start();
        include($this->template);
        $content = ob_get_clean();

        foreach($context as $key=>$value) {
            $content = str_replace("{{".$key."}}", htmlspecialchars($value), $content);
        }

        $content = preg_replace('/{{[a-z][0-9a-z_]+}}/i', "", $content);

        echo $content;
    }
}
