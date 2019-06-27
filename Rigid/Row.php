<?php
declare(strict_types=1);

namespace Rigid;

use Rigid\Exceptions\NotImplementedException;

trait Row {
    /** @var mixed[string] $rigidOriginalData */
    private $rigidOriginalData;
    
    /** @var string[] $rigidChangedData */
    private $rigidChangedData = [];
    
    /** @var Identifier $rigidIdentifier */
    private $rigidIdentifier;

    /** @var ORM $rigidOrm */
    private $rigidOrm;
    
    /** @var Table $rigidCachedTable Cached Table instnace from ::rigidDefineTable */
    private static $rigidCachedTable;
    
    /**
     * Generates a Table instance that describes the type of this Row
     * @return Table
     */
    protected abstract static function rigidDefineTable();
    
    public final function __construct(ORM $orm) {
        $this->rigidOrm = $orm;
        $this->rigidOriginalData = [];
        foreach(self::rigidGetTable()->getColumns() as $column) {
            $this->rigidOriginalData[$column->getName()] = $column->getDefault();
        }
    }
    
    /**
     * The unique database identifier for this row
     *
     * @return Identifier|null 
     */
    public final function getIdentifier() {
        return $this->rigidIdentifier;
    }
    
    /**
     * Whether this row has a database identifier
     */
    public final function hasIdentifier() {
        return $this->rigidIdentifier !== null;
    }
    
    /**
     * Sets the value of a field in this row
     *
     * @param string $column The column in the database
     * @param mixed $value The new value
     * @param bool $ignoreForeignKeys Whether to ignore foreign keys. When false, foreign keys cannot be modified using this 
     * @return void
     */
    protected final function setRaw(string $columnName, $value, bool $ignoreForeignKeys = false) {
        $table = self::rigidGetTable();
        if (!$table->hasColumn($columnName))
            throw new NotDefinedException('Column "'.$columnName.'" is not defined for '.get_called_class());
        
        $column = $table->getColumn($columnName);
        if (!$column->isValueAcceptable($value))
            throw new UnexpectedValueException('Value for column "'.$columnName.'" invalid in '.get_called_class());
        
        if (!$ignoreForeignKeys) {
            if ($table->getReferenceForColumn($columnName) !== null)
                throw new UnexpectedValueException('Column "'.$columnName.'" is in a foreign key, setRaw called without $ignoreForeignKeys = true in '.get_called_class());
        }
        
        // TODO Fix $column vs $columnName
        
        $this->rigidChangedData[$column->getName()] = $value;
        return true;
    }   
    
    /** 
     * Gets the raw field value for a given column
     *
     * @param string $column The column
     * @param bool $cached If true, returns the cached value (set using ->set or ->setRaw), before it's saved in the database. 
     *      If false, the 'original' value 
     * @return mixed
     */
    protected final function getRaw(string $column, bool $cached = true) {
        $table = self::rigidGetTable();
        if (!$table->hasColumn($column))
            throw new NotDefinedException('Column "'.$column.'" is not defined for '.get_called_class());
        
        if ($cached) {
            if (isset($this->rigidChangedData[$column]))
                return $this->rigidChangedData[$column];
        }
        
        return $this->rigidOriginalData[$column];
    }
    
    /**
     *
     * @return bool
     */
    protected final function set(string $column, $value): bool {        
        $reference = self::rigidGetTable()->getReferenceForColumn($column);
        if ($reference !== null) {
            if ($value === null) {
                $this->setRaw($column, null, true);
                return true;
            }
            if (!is_object($value))
                throw new UnexpectedValueException();
            $class = get_class($value);
            $targetClass = $reference->getTargetClass();
            if ($class !== $targetClass)
                throw new UnexpectedValueException('Rigid\Row->set expects $value to be of type "'.$targetClass.'", got "'.$class.'"');
            if ($this->rigidOrm !== $value->rigidGetORM())
                throw new UnexpectedValueException('Rigid\Row->set expects $value be in same Rigid\ORM');
            $ok = true;
            foreach($reference->getColumnMap() as $columnSource => $columnTarget) {
                $ok = $ok && $this->setRaw($columnSource, $value->get($columnTarget));
            }
            return $ok;
        }
        return $this->setRaw($column, $value);
    }
    
    protected final function rigidTransaction(callable $transaction): bool {
        $cache = $this->rigidChangedData;
        try {
            $transaction($this);
            return true;
        } catch (Exception $ex) {            
            $this->rigidChangedData = $cache;
            return false;
        }
    }
    
    /**
     *
     * @param string $column
     * @param bool $cached
     * @return mixed
     */
    protected final function get(string $column, bool $cached = true) {
        $value = $this->getRaw($column, $cached);
        $reference = self::rigidGetTable()->getReferenceForColumn($column);
        if ($reference !== null) {
            $tClass = $reference->getTargetClass();
            $targetColumn = $reference->getColumnMap()[$column];
            if ($value == null)
                return null;
            $object = $this->rigidOrm->find(new Identifier($tClass, [$targetColumn => $value]));
            return $object;
        }
        return $value;
    }
    
    /**
     * Retrieves a cached Table instance that describes the type of this Row, or generates a new instance
     * @return Table
     */
    public final static function rigidGetTable() {
        if (self::$rigidCachedTable == null)
            self::$rigidCachedTable = self::rigidDefineTable();
        return self::$rigidCachedTable;
    }
    
    /** 
     * Creates a new Row based on default and provided values
     *
     * @param mixed[string] $values Column values
     * @return self
     */
    public final static function rigidCreate(ORM $orm = null, array $values = []) {
        $row = new static($orm);
        foreach($values as $column => $value)
            $row->setRaw($column, $value);
        
        $row->rigidDatabaseUpdated();
        return $row;
    }
    
    /** 
     * Called after the row has been inserted into the database
     *
     * @param ORM $orm
     * @param int|null $insertId The AUTO_INCREMENT value of this row
     */
    public final function rigidDatabaseInserted(int $insertId = null) {
        $autoIncrementColumn = self::rigidGetTable()->getAutoIncrementColumn();
        if ($autoIncrementColumn != null)
            $this->setRaw($autoIncrementColumn, $insertId);
        
        $this->rigidDatabaseUpdated();
    }
    
    /**
     * Called after the modified row has been updated or inserted into in the database.
     * Migrates info in 'changed data' to 'original data'
     *
     * @return void
     */
    public final function rigidDatabaseUpdated() {
        foreach($this->rigidChangedData as $column => $value)
            $this->rigidOriginalData[$column] = $value;
        
        $this->rigidChangedData = [];
        
        // Assign identifier
        $this->rigidIdentifier = null;
        $identifierValues = [];
        foreach(self::rigidGetTable()->getPrimaryIndex()->getColumns() as $column) {
            $value = $this->getRaw($column);
            if ($value === null)
                return;
            $identifierValues[$column] = $value;
        }
        $this->rigidIdentifier = new Identifier(self::class, $identifierValues);
    }
    
    /**
     * Called when the object has been deleted from the database
     */
    public final function rigidDatabaseDeleted() {
        $this->rigidIdentifier = null;
    }

    /**
     * Checks if any field has been modified
     * @return bool
     */
    public final function rigidIsChanged(): bool {
        return count($this->rigidChangedData) !== 0;
    }
    
    public final function rigidGetORM() {
        return $this->rigidOrm;
    }
    
    /**
     * @return mixed[string]
     */
    public final function rigidGetChanges(): array {
        return $this->rigidChangedData;
    }
    
}