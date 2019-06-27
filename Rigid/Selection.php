<?php
declare(strict_types=1);

namespace Rigid;

class Selection implements SqlRepresentable {
    
    /** @var string $targetClass */
    private $targetClass; 
    
    /** @var string[] $expand Columns to load too */
    private $expand = [];
    
    /** @var Where $where Where */
    private $where;
    
    /** @var int|false $limit */
    private $limit = false;
    
    /** @var int|false $offset */
    private $offset = false;
    
    /**
     * Create a new selection for rows of $targetClass
     *
     * @param string $targetClass The full class name of the target Row
     */
    public function __construct(string $targetClass) {
        if (!Util::hasTrait($targetClass, Row::class)) {
            throw new UnexpectedValueException();
        }
        
        $this->targetClass = $targetClass;
    }
    
    /** 
     * Creates a Selection that references a specific Row
     * @param object $row The row
     * @return Selection|null
     */
    public static function fromRow($row) {
        if (!is_object($row) || !Util::hasTrait($row, Row::class)) {
            throw new UnexpectedValueException();
        }
        
        if ($row->getIdentifier() === null)
            return null;
        return self::fromIdentifier($row->getIdentifier());
    }
    
    /**
     * Creates a Selection that references a specific Row through its identifier
     * @param Identifier $identifier The identifier of the row
     * @return Selection
     */
    public static function fromIdentifier(Identifier $identifier): Selection {
        // TODO Decide whether to use limit=1
        $select = new Selection($identifier->getClass());
        $whereGroup = $select->createWhere('AND');
        foreach($identifier->getValues() as $column => $value)
            $whereGroup->add(new Where\FieldValue($column, '=', $value));
        return $select;
    }

    /**
     * Creates a new main WhereGroup for this Selection
     * @return WhereGroup
     */
    public function createWhere($mode): WhereGroup {
        $mode = strtoupper($mode);
        if ($mode != 'OR' && $mode != 'AND')
            throw new UnexpectedValueException('Selection->createWhere expects OR or AND for $mode');
        $or = $mode == 'OR';
        if ($this->where == null) {
            $this->where = new WhereGroup($or, []);
        } elseif ($this->where->getMode() != $or) {
            $this->where = new WhereGroup($or, [$this->where]);
        }
        return $this->where;
    }
    
    /**
     * The main where group
     * @return WhereGroup|null
     */
    public function getWhere() {
        return $this->where;
    }
    
    /**
     * The target class
     * @return string
     */
    public function getTargetClass(): string {
        return $this->targetClass;
    }
    
    /**
     * @param int|false $amount
     * @return $this
     */
    public function setLimit($amount = false) {
        if (!is_int($amount) && $amount !== false)
            throw new UnexpectedValueException('setLimit expected integer or false');
        $this->limit = $amount;
        return $this;
    }
    
    /**
     * @param int|false $amount
     */
    public function setOffset($amount = false) {
        if (!is_int($amount) && $amount !== false)
            throw new UnexpectedValueException('setOffset expected integer or false');
        $this->offset = $amount;
        return $this;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $options = Util::parseOptionsArray($options, [
            'mode' => 'SELECT',
            'delete_without_constraints' => false,
            'prefix' => '',
            'columns' => [],
            'first' => false
        ], [
            'mode' => ['SELECT', 'SELECT_PRIMARY', 'SELECT_COLUMNS', 'COUNT', 'UPDATE', 'DELETE'],
            'delete_without_constraints' => [true, false],
            'first' => [true, false]
        ]);
        
        $class = $this->targetClass;
        $table = $class::rigidGetTable();
        
        // Shortcuts
        if ($options['mode'] == 'SELECT_PRIMARY') {
            $options['columns'] = $table->getPrimaryIndex()->getColumns();
            $options['mode'] = 'SELECT_COLUMNS';
        } elseif ($options['mode'] == 'SELECT') {
            $options['columns'] = $table->getColumnNames();
            $options['mode'] = 'SELECT_COLUMNS';
        }
        
        // Select
        if ($options['mode'] == 'SELECT_COLUMNS') {
            $columns = implode(', ', array_map([Util::class, 'escapeName'], $options['columns']));
            $qp = new QueryPart('SELECT '. $columns .' FROM '. self::tableName($table, $options['prefix']));
        }
        
        // Count
        elseif ($options['mode'] == 'COUNT') {
            $qp = new QueryPart('SELECT COUNT(*) FROM '. self::tableName($table, $options['prefix']));
        }
        
        // Update
        elseif ($options['mode'] == 'UPDATE') {
            $qp = new QueryPart('UPDATE '. self::tableName($table, $options['prefix']) .' SET ');
            $first = true;
            if (!isset($options['changes']) || !is_array($options['changes']))
                throw new UnexpectedValueException('Rigid\Selection->getSqlRepresentation() expects option "changes" to be an associative array');
            
            // All new column values
            foreach($options['changes'] as $column => $value) {
                if ($first) $first = false; else $qp->append(', ');
                $qp->append(Util::escapeName($column) . ' = ');
                $qp->appendQueryPart(Util::valueToQuery($value));
            }
        }
        
        // Delete
        elseif ($options['mode'] == 'DELETE') {
            $qp = new QueryPart('DELETE FROM '. self::tableName($table, $options['prefix']));
        }
        
        // Where
        if ($this->where != null && !$this->where->isEmpty()) {
            $qp->append(' WHERE ', []);
            $qp->appendQueryPart($this->where->getSqlRepresentation(['exclude_parentheses' => true, 'prefix' => $options['prefix']]));
        }
        
        // Limit + Offset
        if ($options['first']) {
            $qp->append(' LIMIT 1');
        } else {
            if ($this->limit === false) {
                if ($this->offset !== false) {
                    // http://archive.is/vAhKG | https://stackoverflow.com/questions/255517/mysql-offset-infinite-rows
                    $qp->append(' LIMIT 1000000000 OFFSET ?', [$this->offset]);                
                }
            } else {
                if ($this->offset !== false) {
                    $qp->append(' LIMIT ? OFFSET ?', [$this->limit, $this->offset]);                
                } else {
                    $qp->append(' LIMIT ?', [$this->limit]);
                }
            }
        }
        
        return $qp;
    }
    
    /**
     * @param Table $table The table
     * @param string $prefix The table prefix
     * @return string
     */
    protected function tableName(Table $table, string $prefix) {
        return Util::escapeName($prefix.$table->getName());
    }
    
}