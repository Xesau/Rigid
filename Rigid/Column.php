<?php

namespace Rigid;

use UnexpectedValueException;

class Column implements SqlRepresentable {
    
    /** @var string $name */
    private $name;
    
    /** @var string $type */
    private $type;
    
    /** @var bool $null */
    private $null;
    
    /** @var ColumnOptions $options */
    private $options;
    
    /** @var mixed $default */
    private $default;
    
    public function __construct(string $name, string $type, IColumnOptions $options = null, bool $null = false, $default = null) {
        $type = strtolower($type);
        
        // Shortcuts
        if ($options == null) {
            $hashLengths = ['sha1' => 40, 'md5' => 32, 'sha512' => 128, 'sha256' => 64];
            
            switch($type) {
                // Unsigned integers
                case 'utinyint';
                case 'usmallint';
                case 'uint':
                case 'umediumint';
                case 'ubigint';
                    $type = substr($type, 1);
                    $options = new ColumnOptions\IntegerOptions(true);
                    break;
                    
                // Unsigned floats
                case 'ufloat':
                case 'udouble':
                    $type = substr($type, 1);
                    $options = new ColumnOptions\FloatOptions(null, null, true);
                    break;
                

                // Common hash functions                
                case 'sha1':
                case 'sha256':
                case 'sha512':
                case 'md5':
                    $options = new ColumnOptions\CharOptions($hashLengths[$type]);
                    $type = 'char';
                    break;
            }
        }
        
        if ($options !== null && !Util::checkTypeColumnOptionsCompatibility($type, $options))
            throw new UnexpectedValueException('Unexpected '.get_class($options).' as options for type '. $type);
        
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
        $this->null = (bool)$null;
        $this->default = $default;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }
    
    /**
     * @return bool
     */
    public function isNullable() {
        return $this->null;
    }
    
    /**
     * @return IColumnOptions
     */
    public function getOptions() {
        return $this->options;
    }
    
    /**
     * @return mixed|null
     */
    public function getDefault() {
        return $this->default;
    }
    
    public function isValueAcceptable($value): bool {
        if ($value === null && !$this->null)
            return false;
        
        if ($this->options != null)
            return $this->options->isValid($value);
        
        // Assuming default options
        switch($this->type) {
            case 'tinyint':
                return is_int($value) && $value >= 128 && $value <= 127;
            case 'smallint':
                return is_int($value) && $value >= -32768 && $value <= 32767;
            case 'mediumint':
                return is_int($value) && $value >= -8388608 && $value <= 8388607;
            case 'int':
                return is_int($value) && $value >= -2147483648 && $value <= 2147483647;
            case 'bigint':
                return is_int($value);
            case 'double':
            case 'float':
                return is_float($value);
            case 'decimal':
                return is_float($value) || is_string($value);
            case 'tinytext':
                return is_string($value) && strlen($value) <= 255;
            case 'text':
                return is_string($value) && strlen($value) <= 65535;
            case 'mediumtext':
                return is_string($value) && strlen($value) <= 16777215;
            case 'longtext':
                return is_string($value) && strlen($value) <= 4294967295;
            case 'year':
            case 'time':
                return is_string($value) || is_int($value);
        }
        
        // All other values only as strings
        return is_string($value);
    }
    
    public function __toString() {
        return 
            Util::escapeName($this->name) . ' '.
            $this->type .
            ($this->options == null ? '' : (string)$this->options) .' '.
            ($this->null ? 'NULL' : 'NOT NULL') .
            ($this->default !== null ? ' DEFAULT \''. str_replace('\'', '\\\'', $this->default) . '\'' : '')
        ;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, ['auto_increment' => false, 'auto_increment' => [true, false]]);
        return new QueryPart($this->__toString(). ($options['auto_increment'] ? ' AUTO_INCREMENT' : ''));
    }
    
}