<?php

namespace gnaritas\ses;

class SesData extends \gn_PluginDB
{
    function __construct () {
        parent::__construct("gnses_");
    }
}