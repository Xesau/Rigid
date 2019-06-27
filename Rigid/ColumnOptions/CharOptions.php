<?php

namespace Rigid\ColumnOptions;

class CharOptions extends StringOptions {
    
    /** @var int $length Max length */
    private $length;
    
    public function __construct($length, $collation = null) {
        parent::__construct($collation);
        $this->length = (int)$length;
    }
    
    public function getLength(): int {
        return $this->length;
    }
    
    public function __toString(): string {
        return '('. $this->length .')'. parent::__toString();
    }
    
    public function isValid($value): bool {
        return is_string($value) && strlen($value) <= $this->length;
    }
    
}