<?php

namespace Rigid\ColumnOptions;

use Rigid\IColumnOptions;

class IntegerOptions implements IColumnOptions {
    
    private $unsigned;
    private $zerofill;
    
    /**
     * @param boolean $unsigned Unsigned
     * @param int|boolean $zerofill false for no zerofill. int for how many positions
     */
    public function __construct($unsigned = false, $zerofill = false) {
        $this->unsigned = (bool)$unsigned;
        $this->zerofill = is_int($zerofill) ? $zerofill : false;
    }
    
    public function isUnsigned() {
        return $this->unsigned;
    }
    
    public function isZerofill() {
        return $this->zerofill !== false;
    }
    
    public function getLength() {
        if ($this->zerofill !== false)
            return $this->zerofill;
        
        return 20;
    }
    
    public function isValid($value): bool {
        return is_int($value);
    }
    
    public function __toString(): string {
        return 
            ($this->unsigned ? ' UNSIGNED' : '') .
            ($this->zerofill ? ' ZEROFILL' : '')
        ;
    }
    
}