<?php

namespace Rigid\ColumnOptions;

class DecimalOptions extends IntegerOptions {
    
    /** @var int $decimals */
    private $decimals;
    
    /** @var int $length */
    private $length;
    
    // TODO Validate parameters
    public function __construct($length, $decimals = null, $unsigned = false, $zerofill = false) {
        parent::__construct($unsigned, $zerofill);
        $this->length = is_int($length) ? $length : null;
        $this->decimals = is_int($length) && is_int($decimals) ? $decimals : null;
    }
    
    public function getLength(): int {
        return $this->length;
    }
    
    public function getDecimals(): int {
        return $this->decimals;
    }
    
    public function __toString(): string {
        if ($this->length == null)
            return parent::__toString();
        
        if ($this->decimals == null)
            return '('. $this->length .')'. parent::__toString();
        
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

