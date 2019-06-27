<?php

namespace Rigid\ColumnOptions;

class TimestampOptions extends TimeOptions {
    
    /** @var boolean $updateAutomatically */
    private $updateAutomatically;
    
    public function __construct($updateAutomatically, $microsecondPrecission = 0) {
        parent::__construct($microsecondPrecission);
        $this->updateAutomatically = (bool)$updateAutomatically;
    }
    
    /** @return bool */
    public function isUpdateAutomatically() {
        return $this->updateAutomatically;
    }

    public function __toString(): string {
        if ($this->updateAutomatically)
            return parent::__toString() .' ON UPDATE CURRENT_TIMESTAMP';
        return parent::__toString();
    }
    
    public function isValid($value): bool {
        return is_int($value) || is_string($value);
    }
    
}