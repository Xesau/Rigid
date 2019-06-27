<?php
declare(strict_types=1);

namespace Rigid;

class Identifier {
    
    /** @var string $class The class for the table of the objects */
    private $class;
    
    /** @var mixed[string] $values The values for the primary indices */
    private $values;
    
    /**
     * @param string $class The class of the Row this identifier points to
     * @param mixed[string] $values The column values of this identifier
     */
    public function __construct($class, array $values) {
        if (!Util::hasTrait($class, Row::class)) {
            throw new UnexpectedValueException('Rigid\Identifier expects a class that uses the Row trait');
        }
        
        $this->class = $class;
        
        if (count($values) < 1)
            return null;
        
        // Build a mixed[string] map of column values
        $table = $class::rigidGetTable();
        $primaryMap = [];
        foreach($table->getPrimaryIndex()->getColumns() as $i => $column) {
            $primaryMap[$column] = isset($values[$column]) 
            ?   $values[$column]
            :   (isset($values[$i]) 
                ?   $values[$i] 
                :   null)
            ;
        }
        ksort($primaryMap);
        $this->values = $primaryMap;
    }
    
    /**
     * The class for the Row this identifier points to
     * @return string
     */
    public function getClass() {
        return $this->class;
    }
    
    /**
     * The columns and values in this identifier
     * @return mixed[string]
     */
    public function getValues() {
        return $this->values;
    }
    
    public function __toString() {
        $values = $this->values;
        ksort($values);
        return json_encode($values);
    }
    
}