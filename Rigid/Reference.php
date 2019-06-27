<?php
declare(strict_types=1);

namespace Rigid;

class Reference extends Index {
    
    /** @var string $targetClass The target class */
    private $targetClass;
    
    /** @var string[string] $columnMap Source columns -> target columns */
    private $columnMap;
    
    /** @var string $onUpdate */
    private $onDelete;
    
    /** @var string $onDelete */
    private $onUpdate;
    
    public function __construct(string $name, string $targetClass, array $columnMap, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT') {
        parent::__construct($name, array_keys($columnMap), 'FOREIGN');
        $this->targetClass = $targetClass;
        $this->columnMap = $columnMap;
        
        $referenceOptions = ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION', 'SET DEFAULT'];
        $onDelete = strtoupper($onDelete);
        $onUpdate = strtoupper($onUpdate);
        
        if (!in_array($onDelete, $referenceOptions))
            throw new UnexpectedValueException();
        if (!in_array($onUpdate, $referenceOptions))
            throw new UnexpectedValueException();
        
        $this->onDelete = $onDelete;
        $this->onUpdate = $onUpdate;
    }
    
    public function getSourceClass(): string {
        return $this->sourceClass;
    }
    
    public function getTargetClass(): string {
        return $this->targetClass;
    }
    
    public function getTargetColumns(): array {
        return array_values($this->columnMap);
    }
    
    public function getColumnMap(): array {
        return $this->columnMap;
    }
    
    private function getConstraintName($prefix = '', $tableName = '') {
        return 'FK_'. $prefix . 
            ($tableName == '' ? '' : $tableName . '_') .
            $this->name; 
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'prefix' => '',
            'table_name' => ''
        ]);
        
        $targetClass = $this->targetClass;
        $targetTable = $targetClass::rigidGetTable();
        $sourceColumnNames = [];
        $targetColumnNames = [];
        foreach($this->columnMap as $source => $target) {
            $sourceColumnNames[] = Util::escapeName($source);
            $targetColumnNames[] = Util::escapeName($target);
        }
        return 
            new QueryPart('CONSTRAINT '. Util::escapeName($this->getConstraintName($options['prefix'], $options['table_name'])) .' FOREIGN KEY '. Util::escapeName($this->name) .' ('. implode(', ', $sourceColumnNames) .') REFERENCES '. Util::escapeName($options['prefix'].$targetTable->getName()) .' ('. implode(', ', $targetColumnNames) .') ON UPDATE '. $this->onUpdate .' ON DELETE '. $this->onDelete);
        ;
    }
    
}