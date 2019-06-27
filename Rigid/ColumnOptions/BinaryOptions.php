<?php

namespace Rigid\ColumnOptions;

use Rigid\IColumnOptions;

class BinaryOptions implements IColumnOptions {
    
    /** @var int $length Max length */
    private $length;
    
    public function __construct($length) {
        $this->length = (int)$length;
    }
    
    public function getLength(): int {
        return $this->length;
    }
    
    public function __toString(): string {
        return '('. $this->length .')';
    }
    
    public function isValid($value): bool {
        return is_string($value);
    }
    
}