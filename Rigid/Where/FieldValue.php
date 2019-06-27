<?php

namespace Rigid\Where;

use Rigid\QueryPart;
use Rigid\Util;
use Rigid\IWhereCondition;

class FieldValue implements IWhereCondition {
    
    /** @var string $field The name of the field */
    private $field;
    
    /** @var string $operator */
    private $operator;
    
    /** @var mixed $value */
    private $value;
    
    public function __construct($field, $operator, $value) {
        $this->field = $field;
        
        // Shortcuts 
        if ($operator == '!in') $operator = 'NOT IN';
        if ($operator == '!>') $operator = '<=';
        if ($operator == '!<') $operator = '>=';

        // = null, != null
        if ($value === null) {
            if ($operator == '=') $operator = 'IS';
            if ($operator == '!=') $operator = 'IS NOT';    
        }
        
        $this->operator = $operator;
        $this->value = $value;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'prefix' => ''
        ]);
        
        // Return 0 (= FALSE) if value not representable as SQL (e.g. IN() with empty array)
        $valueQp = Util::valueToQuery($this->value, $options['prefix']);
        if ($valueQp === null)
            return '0';
        
        $qp = new QueryPart(Util::escapeName($this->field) .' '. strtoupper($this->operator) .' ');
        
        if (is_string($valueQp))
            $valueQp = new QueryPart($valueQp);
        
        $qp->appendQueryPart($valueQp);
        return $qp;
    }
    
}