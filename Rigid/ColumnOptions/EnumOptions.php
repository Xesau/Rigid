<?php

namespace Rigid\ColumnOptions;

use Rigid\Util;

class EnumOptions extends StringOptions {
    
    /** @var string[] $options */
    private $options;
    
    public function __construct(array $options, $collation = null) {
        parent::__construct($collation);
        $this->options = $options;
    }
    
    /** @return string[] */
    public function getOptions() {
        return $this->options;
    }
    
    public function __toString(): string {
        $escapedOptions = [];
        foreach($this->options as $option) {
            $escapedOptions[] = '\''. str_replace('\'', '\\\'', $option) . '\'';
        }
        return '('. implode(', ', $escapedOptions) .')'. parent::__toString();
    }
    
    public function isValid($value): bool {
        return is_string($value) && in_array($value, $this->options);
    }
    
}