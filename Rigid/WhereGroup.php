<?php
declare(strict_types=1);

namespace Rigid;

use UnexpectedValueException;

class WhereGroup implements IWhereCondition {
    
    /** @var string $mode */
    private $mode;
    
    /** @var IWhereCondition[] $elements Parts of this where group */
    private $elements;
    
    /**
     * @param bool $or Whether this WhereGroup uses OR, instead of AND
     * @param IWhereCondition[] $elements The conditions in this group
     */
    public function __construct(bool $or, array $elements = []) {
        // Verify if array only contains IWhereConditions
        foreach($elements as $element) {
            if (!($element instanceof IWhereCondition))
                throw new UnexpectedValueException('Unexpected '.Util::getTypeName($element) .' in $elements array');
        }
        
        $this->mode = $or === true ? 'OR' : 'AND';
        $this->elements = $elements;
    }
    
    /**
     * Adds a condition to the group
     * @param IWhereCondition $element 
     */
    public function add(IWhereCondition $element) {
        $this->elements[] = $element;
    }
    
    /**
     * The mode (OR or AND) of this group
     */
    public function getMode() {
        return $this->mode;
    }
    
    /**
     * Whether the group is empty
     * @return bool
     */
    public function isEmpty() {
        return 0 == count($this->elements);
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'exclude_parentheses' => false,
            'prefix' => ''
        ], [
            'exclude_parentheses' => [true, false]
        ]);
        
        // Empty groups should always evaluate to false
        if ($this->isEmpty()) {
            if ($options['exclude_parentheses'])
                return new QueryPart('0');
            return new QueryPart('(0)');
        }
        
        // Build query part
        unset($options['exclude_prefix']);
        $elementQps = array_map(function($o) use ($options) { return $o->getSqlRepresentation($options); }, $this->elements);
        
        $qp = new QueryPart($options['exclude_parentheses'] ? '' : '(');
        $qp->appendJoinQueryParts(' '. $this->mode .' ', $elementQps);
        
        if (!$options['exclude_parentheses'])
            $qp->append(')');
        return $qp;
    }
    
}