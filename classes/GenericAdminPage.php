<?php

namespace gnaritas\ses;

class GenericAdminPage extends \Voce_Settings_Page
{    
    public $template;

    public function display() {
        include($this->template);
    }
}
