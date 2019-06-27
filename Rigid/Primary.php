<?php

namespace Rigid;

class Primary extends Index {
    
    public function __construct($columns) {
        parent::__construct('PRIMARY', $columns);
    }
    
}