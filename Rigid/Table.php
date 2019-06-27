<?php

namespace Rigid;

use Rigid\Exceptions\NotDefinedException;
use Rigid\Exceptions\NotUniqueException;
use UnexpectedValueException;

class Table implements SqlRepresentable {
    
    /** @var string $name The table name */
    private $name;
    
    /** @var Column[string] $columns The columns in this table */
    private $columns = [];
    
    /** @var Index[string] $indices The indices */
    private $indices = [];
    
    /** @var Reference[] $references The (foreign) references */
    private $references = [];
    
    /** @var Reference[string] $columnReferences References by column name */
    private $columnReferences = [];
    
    /** @var string $autoIncrement The column that auto-increments */
    private $autoIncrementColumn = null;
    
    /** @var string $collation The default string collation */
    private $collation;
    
    /** @var string $engine The table storage engine */
    private $engine;
    
    /** @var string[] $defaultExpands */
    private $defaultExpands = [];
    
    public function __construct($name, array $columns, array $indices = [], $autoIncrementColumn = null, array $defaultExpands = [], $collation = 'utf8mb4_unicode_ci', $engine = 'InnoDB') {
        $this->name = $name;
        
        // Check columns (no double names, etc.)
        foreach($columns as $column) {
            if (!($column instanceof Column))
                throw new UnexpectedValueException('Unexpected '.Util::getTypeName($column).' in array $columns, expected '. Column::class);
            
            if (isset($this->columns[$column->getName()]))
                throw new NotUniqueException();
            
            $this->columns[$column->getName()] = $column;
        }
        
        // Check indicdes
        foreach($indices as $index) {
            if (!($index instanceof Index))
                throw new UnexpectedValueException('Unexpected '.Util::getTypeName($index).' in array $indices, expected '. Index::class);
            
            if (isset($this->indicdes[$index->getName()]))
                throw new NotUniqueException();

            foreach($index->getColumns() as $column) {
                if (!isset($this->columns[$column]))
                    throw new NotDefinedException('Column "'.$column.'" not defined for "'.$this->name.'", found in index "'. $index->getName() .'"');
            }
            
            $this->indices[$index->getName()] = $index;
            
            if ($index instanceof Reference) {
                
                $this->references[] = $index;
                foreach($index->getColumns() as $column)
                    $this->columnReferences[$column] = $index;
            }
        }
        
        
        if (!isset($this->indices['PRIMARY'])) {
            throw new NotDefinedException('No PRIMARY key was defined.');
        }
        
        // Check whether auto increment column exists, is part of PRIMARY key and is of the right type
        if (isset($autoIncrementColumn)) {
            if (!isset($this->columns[$autoIncrementColumn]))
                throw new NotDefinedException('Column "'.$autoIncrementColumn.'" not defined for "'.$this->name.'", found in AUTO_INCREMENT column');
            
            if (!in_array($autoIncrementColumn, $this->indices['PRIMARY']->getColumns()))
                throw new UnexpectedValueException('Column "'.$autoIncrementColumn.'" is not part of the PRIMARY index.');
            
            if (!in_array($this->columns[$autoIncrementColumn]->getType(), ['int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'float', 'double']))
                throw new UnexpectedValueException('Column "'.$autoIncrementColumn.'" is not of a number type');
            
            $this->autoIncrementColumn = $autoIncrementColumn;
        }
        
        // Check default expands
        foreach($defaultExpands as $defaultExpand) {
            if (!isset($this->columnReferences[$defaultExpand]))
                throw new NotDefinedException('Column "'.$defaultExpand.'" not defined for "'.$this->name.'", found in $defaultExpands');
        }
        $this->defaultExpands = $defaultExpands;
        
        $this->collation = (string)$collation;
        $this->engine = (string)$engine;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'if_not_exists' => false,
            'prefix' => ''
        ], [
            'if_not_exists' => [true, false]
        ]);
        
        // Columns
        $columnQps = [];
        foreach($this->columns as $column)
            $columnQps[] = $column->getSqlRepresentation(['prefix' => $options['prefix'], 'auto_increment' => $column->getName() == $this->autoIncrementColumn]);
        $indicesQps = [];
        foreach($this->indices as $index)
            $indicesQps[] = $index->getSqlRepresentation(['prefix' => $options['prefix'], 'table_name' => $this->name]);

        // Indices can't be empty because some primary key is required; therefore append ', ' before joining the indices
        $qp = new QueryPart('CREATE TABLE ');
        if ($options['if_not_exists'])
            $qp->append('IF NOT EXISTS ');
        $qp->append(Util::escapeName($options['prefix']. $this->name). ' (');
        $qp->appendJoinQueryParts(', ', $columnQps);
        $qp->append(', ');
        $qp->appendJoinQueryParts(', ', $indicesQps);
        $qp->append(') ENGINE='. $this->engine .
            ' DEFAULT CHARSET='. Util::collationToCharSet($this->collation) .
            ' COLLATE='. $this->collation
        );
        
        return $qp;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * @return Column[]
     */
    public function getColumns() {
        return array_values($this->columns);
    }

    public function getColumn($name) {
        return $this->columns[$name];
    }
    
    public function getColumnNames() {
        return array_keys($this->columns);
    }
    
    public function hasColumn($columnName) {
        return isset($this->columns[$columnName]);
    }
    
    public function hasIndex($indexName) {
        return isset($this->indices[$indexName]);
    }
    
    public function getReferenceForColumn(string $column) {
        if (!isset($this->columnReferences[$column]))
            return null;
        
        return $this->columnReferences[$column];
    }
    
    /**
     * @return Reference[]
     */
    public function getReferences() {
        return array_values($this->references);
    }
    
    /**
     * @return Index[]
     */
    public function getIndices() {
        return array_values($this->indices);
    }
    
    /**
     * @return Index|null
     */
    public function getPrimaryIndex() {
        if (isset($this->indices['PRIMARY']))
            return $this->indices['PRIMARY'];
        return null;
    }
    
    /**
     * @return string[]
     */
    public function getDefaultExpands() {
        return $this->defaultExpands;
    }
    
    /** 
     * @return string
     */
    public function getEngine() {
        return $this->engine;
    }
    
    /**
     * @return string
     */
    public function getCollation() {
        return $this->collation;
    }
    
    /**
     * @param bool $asColumn Whether to return a Column instance
     * @return string|Column
     */
    public function getAutoIncrementColumn($asColumn = false) {
        if ($this->autoIncrementColumn == null)
            return null;
        
        if ($asColumn)
            return $this->columns[$this->autoIncrementColumn];
        
        return $this->autoIncrementColumn;
    }
    
}