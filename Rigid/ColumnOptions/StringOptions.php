<?php

namespace Rigid\ColumnOptions;

use Rigid\IColumnOptions;

class StringOptions implements IColumnOptions {
    
    /** @var string|null $collation */
    private $collation;
    
    // TODO arg error check
    public function __construct($collation) {
        $this->collation = $collation;
    }
    
    /**
     * @return string
     */
    public function getCollation($collation) {
        return $this->collation;
    }
    
    public function __toString(): string {
        if ($this->collation === null)
            return '';
        
        return ' CHARACTER SET '. Util::collationToCharSet($this->collation) .' COLLATE '. $this->collation;
    }
    
    public function isValid($value): bool {
        return is_string($value);
    }
    
}