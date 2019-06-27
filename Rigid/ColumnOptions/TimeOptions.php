<?php

namespace Rigid\ColumnOptions;

use Rigid\IColumnOptions;

class TimeOptions implements IColumnOptions {
    
    /** @var int $microsecondPrecission Microsecond precission */
    private $microsecondPrecission;
    
    public function __construct($microsecondPrecission = 0) {
        $this->microsecondPrecission = $microsecondPrecission;
    }
    
    public function getMicrosecondPrecission() {
        return $this->microsecondPrecission;
    }
    
    public function __toString(): string {
        return '('. $this->microsecondPrecission .')';
    }
    
    public function isValid($value): bool {
        return is_int($value) || is_string($value);
    }
    
}