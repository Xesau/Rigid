<?php

namespace Rigid\ColumnOptions;

use Rigid\IColumnOptions;

// TODO: Maybe extends DecimalOptions?
class FloatOptions extends IntegerOptions {
    
    /** @var int $decimals */
    private $decimals;
    
    /** @var int $length */
    private $length;
    
    public function __construct($length = null, $decimals = null, $unsigned = false, $zerofill = false) {
        parent::__construct($unsigned, $zerofill);
        $this->length = is_int($length) && is_int($decimals) ? $length : null;
        $this->decimals = is_int($length) && is_int($decimals) ? $decimals : null;
    }
    
    public function getLength(): int {
        return $this->length;
    }
    
    public function getDecimals(): int {
        return $this->decimals;
    }
 
    public function __toString(): string {
        if ($this->decimals == null)
            return '';
        
        return '('. $this->length .','. $this->decimals .')'. parent::__toString();
    }
    
    public function isValid($value): bool {
        if (!is_double($value))
            return false;
        if ($this->isUnsigned() && $value < 0)
            return false;
        return true;
    }
       
}

