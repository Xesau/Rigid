<?php
declare(strict_types=1);

namespace Rigid;

use Rigid\ColumnOptions;

class Util {
    
    public static function escapeName() {
        $result = '';
        foreach(func_get_args() as $arg) {
            $result .= ($result == '' ? '' : '.') .'`'. str_replace('`', '``', $arg) .'`';
        }
        return $result;
    }
    
    /**
     * @return string|QueryPart|null If null, this value is not representable as SQL
     */
    public static function valueToQuery($value, $prefix = null) {
        switch(gettype($value)) {
            case 'bool':
                return $value ? new QueryPart('1') : new QueryPart('0');
            
            case 'integer':
            case 'double':
            case 'string':
                return new QueryPart('?', [$value]);
            
            case 'NULL':
                return new QueryPart('NULL');
            
            case 'array':
                if (count($value) == 0)
                    return null;
                
                $realCount = 0;
                $qp = new QueryPart('(');
                $first = true;
                foreach($value as $element) {
                    $qpValue = self::valueToQuery($element, $prefix);

                    // TODO take into account 
                    if ($qpValue != null) {
                        $realCount++;
                        if ($first) { $first = false; }
                        else { $qp->append(', '); }
                        $qp->appendQueryPart($qpValue);
                    }
                }
                
                if ($realCount == 0)
                    return null;
                
                $qp->append(')');
                return $qp;
            case 'object':
                // Selections can be represented as (SELECT ...)
                if ($value instanceof Selection)
                    return QueryPart::combine('(', $value->getSqlRepresentation(['mode' => 'SELECT_PRIMARY', 'prefix' => $prefix]), ')');
                
                // Rows can be represented if they have a primary index on one column
                elseif (Util::hasTrait($value, Row::class)) {
                    $values = $value->getIdentifier()->getValues();
                    if (count($values) != 1)
                        return null;
                    return Util::valueToQuery(array_pop($values), $prefix);
                }
                
                // Other objects cannot be represented
                return null;
            default:
                return null;
        }
    }
    
    public static function isIntArray(array &$arr) {
        for($i = count($arr) - 1; $i >= 0; $i--)
            if (!is_int($arr[$i]))
                return false;
        return true;
    }
    
    public static function collationToCharSet($collation) {
        $pos = strpos($collation, '_');
        return substr($collation, 0, $pos);
    }
    
    public static function parseOptionsArray(array $options = null, array $defaults = [], array $possibilities = []) {
        if ($options == null) {
            return $defaults;
        }
        foreach($options as $k => $v) {
            if (isset($possibilities[$k])) {
                if (!in_array($v, $possibilities[$k])) {
                    if (isset($defaults[$k]))
                        $options[$k] = $defaults[$k];
                    else
                        unset($options[$k]);
                }
            }
        }
        return $options + $defaults;
    }
    
    /**
     * Checks whether a ColumnOptions is compatible with a type
     * @return bool 
     */
    public static function checkTypeColumnOptionsCompatibility($type, IColumnOptions $options) {
        $compatibilityTable = [
            ColumnOptions\IntegerOptions::class => ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'],
            ColumnOptions\DecimalOptions::class => ['decimal'],
            ColumnOptions\FloatOptions::class => ['double', 'float'],
            ColumnOptions\CharOptions::class => ['char', 'varchar'],
            ColumnOptions\EnumOptions::class => ['enum', 'set'],
            ColumnOptions\TextOptions::class => ['tinytext', 'text', 'mediumtext', 'longtext'],
            ColumnOptions\BinaryOptions::class => ['binary', 'varbinary'],
            ColumnOptions\TimeOptions::class => ['time'],
            ColumnOptions\TimestampOptions::class => ['datetime', 'timestamp']
        ];
        
        $compoundTypeList = [];
        foreach($compatibilityTable as $clazz => $types) {
            foreach($types as $type2)
                $compoundTypeList[$type2] = $clazz;
        }
        
        if (!isset($compoundTypeList[$type]))
            return $options == null;

        return $compoundTypeList[$type] == get_class($options);
    }
    
    public static function checkValueCompatibility($type, IColumnOptions $options, $value) {
        if (!self::checkTypeColumnOptionsCompatibility($type, $options))
            throw new UnexpectedValueException('ColumnOptions and type are incompatible');
        
    }
    
    /**
     * Checks whether a class or object has the specified trait
     * @param string|object Class name or object
     * @param string Trait name
     * @return bool
     */
    public static function hasTrait($classOrObject, $trait) {
        $class = is_object($classOrObject) ? get_class($classOrObject) : $classOrObject;
        $uses = class_uses($class);
        if ($uses === false)
            return false;
        return in_array($trait, $uses);
    }
 
    /**
     * @return string
     */
    public static function getSimpleClassName($class) {
        $pos = strrpos($class, '\\');
        return substr($class, $pos);
    }
    
    public static function getTypeName($value) {
        $type = gettype($value);
        if ($type != 'object')
            return $type;
        if ($type instanceof stdClass)
            return 'object';
        return $type;
    }
    
    public static function getTable($class) {
        return $class::rigidGetTable();
    }
 
}