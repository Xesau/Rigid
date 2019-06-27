<?php

namespace Rigid\Where;

use Rigid\QueryPart;
use Rigid\Util;
use Rigid\IWhereCondition;

class FieldInSelection implements IWhereCondition {
    
    /** @var string $field The name of the field */
    private $field;
    
    /** @var string $targetSelection */
    private $targetSelection;
    
    /** @var string $targetField */
    private $targetField;
    
    public function __construct($field, $targetSelection, $targetField) {
        $this->field = $field;
        $this->targetSelection = $targetSelection;
        $this->targetField = $targetField;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'prefix' => ''
        ]);
        
        $qp = new QueryPart(Util::escapeName($this->field) .' IN (');
        $qp->appendQueryPart($this->targetSelection->getSqlRepresentation(['mode' => 'SELECT_COLUMNS', 'columns' => [$this->targetField], 'prefix' => $options['prefix']]));
        $qp->append(')');
        return $qp;
    }
    
}