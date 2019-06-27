<?php
declare(strict_types=1);

namespace Rigid;

use PDO;
use PDOStatement;
use Rigid\NotSupportedException;
use UnexpectedValueException;

class ORM {
    
    /** @var PDO $pdo The PDO */
    private $pdo;
    
    /** @var string $prefix The table prefix */
    private $prefix;
    
    /** @var object[string] Cached rows */
    private $rows = [];
    
    /** @var boolean[string] Is row class? */
    private static $isRowClass = [];
    
    /**
     * Initiates new ORM object and applies appropriate PDO attributes to $pdo
     *
     * @param PDO $pdo The PDO
     * @param string $prefix The table prefix
     */
    public function __construct(PDO $pdo, string $prefix = '') {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }
    
    /**
     * The PDO
     * @return PDO
     */
    public function getPDO(): PDO {
        return $this->pdo;
    }
    
    /**
     * The Prefix
     * @return string 
     */
    public function getPrefix(): string {
        return $this->prefix;
    }
    
    /**
     * Save a new object in the database
     * @return bool
     */
    public function save($row): bool {
        self::checkIsRowClass(get_class($row));
        
        // Build INSERT query
        $table = $row::rigidGetTable();
        $changes = $row->rigidGetChanges();
        $qp = new QueryPart(
            'INSERT INTO '. Util::escapeName($this->prefix . $table->getName()) .'('.
            implode(', ', array_map([Util::class, 'escapeName'], array_keys($changes))).
            ') VALUES '
        );
        
        // Get QueryParts for VALUES. If null (empty), add ()
        $qpValues = Util::valueToQuery(array_values($changes));
        if ($qpValues == null)
            $qp->append('()');
        else
            $qp->appendQueryPart($qpValues);        
        
        $stmt = $this->pdo->prepare($qp->getSql());

        try {
            $stmt->execute($qp->getParameters());
        } catch (PDOException $ex) {
            // Return false if insert failed
            return false;
        }
        
        // Return false if insert failed
        if ($stmt->rowCount() == 0)
            return false;
        
        // If succesfull, assign A_I
        $id = $table->getAutoIncrementColumn() === null ? null : (int)$this->pdo->lastInsertId();
        $row->rigidDatabaseInserted($id);
        
        // Save row in cache and return true
        $class = get_class($row);
        $this->rows[$class][(string)$row->getIdentifier()] = $row;
        return true;
    }
    
    /**
     * Delete a specific object from the database
     *
     * @param Identifier|Row $identifier The row or identifier of the row
     * @return bool True if deleted, false if not deleted
     * @throws UnexpectedValueException If 
     */
    public function delete($identifier): bool {
        // Get identifier
        if (!($identifier instanceof Identifier)) {
            self::checkIsRowClass(get_class($identifier));
            $identifier = $identifier->getIdentifier();
        }
        
        // Generate select query
        $select = new Selection($identifier->getClass());
        $whereGroup = $select->createWhere('AND');
        foreach($identifier->getValues() as $column => $value)
            $whereGroup->add(new Where\FieldValue($column, '=', $value));
        
        // Exceute select query
        $stmt = $this->executeQuery($select->getSqlRepresentation([
            'mode' => 'DELETE', 
            'prefix' => $this->prefix,
            'first' => true
        ]));

        // If rowCount == 0, no row was deleted
        if ($stmt->rowCount() == 0)
            return false;
        
        // Unset cached row, if it exists
        unset($this->rows[$identifier->getClass()][(string)$identifier]);
        return true;
    }
    
    /**
     * Deletes all objects from a selection
     *
     * @param bool $lazy Whether to skip the deletion of cached objects, and notifying deleted objects of their deletion in the database. If true, saves 1 query, but can cause conflicts.
     * @return bool Whether any row was deleted
     */
    public function deleteAll(Selection $selection, bool $lazy = false): bool {
        $class = $selection->getTargetClass();
        
        // Find primary values so cached objects can be removed if delete succeeds
        if (!$lazy) {
            $identifiers = [];
            $stmt1 = $this->executeQuery($selection->getSqlRepresentation(['mode' => 'SELECT_PRIMARY', 'prefix' => $this->prefix]));
            while($result = $stmt1->fetch(PDO::FETCH_ASSOC))
                $identifiers[] = new Identifier($class, $result);
        }
        
        // Try deleting
        $stmt2 = $this->executeQuery($selection->getSqlRepresentation(['mode' => 'DELETE', 'prefix' => $this->prefix]));
        if ($stmt2->rowCount() != 0) {
            // Try deleting cached objects
            if (!$lazy) {
                foreach($identifiers as $identifier) {
                    if (isset($this->rows[$class][(string)$identifier])) {
                        $this->rows[$class][(string)$identifier]->rigidDatabaseDeleted();
                        unset($this->rows[$class][(string)$identifier]);
                    }
                }
            }
            
            return true;
        }
        return false;
    }
    
    /**
     * Updates row in the database
     *
     * @param Row|Identifier $row
     * @return bool Whether the row could be updated
     */
    public function update($row): bool {
        if ($row instanceof Identifier) {
            $row = $this->find($row);
            if ($row === null)
                return false;
        }
        else
            self::checkIsRowClass(get_class($row));
        
        if (!$row->rigidIsChanged())
            return false;
        
        $changes = $row->rigidGetChanges();
        $select = Selection::fromRow($row);        
        $qp = $select->getSqlRepresentation([
            'mode' => 'UPDATE',
            'changes' => $changes,
            'prefix' => $this->prefix
        ]);
        
        $stmt = $this->executeQuery($qp);
        return $stmt->rowCount() == 1;
    }
    
    /**
     * Updates all cached instances in the database
     *
     * @param string|null $class If not null, only instances of this class are updated
     * @return bool Whether the operation could be completed
     */
    public function updateAll($class = null): bool {
        if ($class != null) {
            if (!isset($this->rows[$class]))
                return false;
            
            foreach($this->rows[$class] as $row)
                $this->update($row);
                
            return true;
        }
        
        foreach(array_keys($this->rows) as $class) {
            $this->updateAll($class);
        }
        
        return true;
    }
    
    /**
     * Find object by identifier
     *
     * @param Identifier $identifier
     * @return object|null
     */
    public function find(Identifier $identifier) {
        $class = $identifier->getClass();
        if (!isset($this->rows[$class][(string)$identifier])) {      
            // Generate select query
            $select = Selection::fromIdentifier($identifier);
            
            // Exceute select query
            $stmt = $this->executeQuery($select->getSqlRepresentation([
                'prefix' => $this->prefix,
                'first' => true
            ]));
            
            // Inject results
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res == false)
                return null;

            $identifier = $this->inject($class, $res);
        }
        
        if (!$this->rows[$class])
            return null;
        
        if (!$this->rows[$class][(string)$identifier])
            return null;
        
        return $this->rows[$class][(string)$identifier];
    }
    
    /**
     * Find and load all objects from a Selection
     * 
     * @param Selection $selection The selection
     * @param string[]|null $expand Fields to load with the selection. If null, Table->getDefaultExpands() is used
     * @param int $returnAs 0 = objects, 1 = identifiers, ? = null
     * @throws NotSupportedException When an expand depends on more than 1 column
     * @return mixed
     */
    public function findAll(Selection $selection, array $expand = null, int $returnAs = 0) {
        $class = $selection->getTargetClass();
        
        // Expand
        $expandSelections = [];
        $table = $class::rigidGetTable();
        $expands = $expand === null ? $table->getDefaultExpands() : $expand;
        foreach($expands as $expand) {
            $reference = $table->getReferenceForColumn($expand);
            $class2 = $reference->getTargetClass();
            $table2 = $class2::rigidGetTable();
            
            $columnMap = $reference->getColumnMap();
            if (count($columnMap) != 1)
                throw new NotSupportedException('Rigid\ORM->findAll currently does not support expands that rely on more than 1 column');
            
            $selection2 = new Selection($class2);
            $whereGroup = $selection2->createWhere('AND');
            // Should be only 1
            foreach($columnMap as $source => $target)
                $whereGroup->add(new Where\FieldInSelection($target, $selection, $source));

            $expandSelections[] = $selection2;
        }
        
        // Load expands
        foreach($expandSelections as $expandSelection)
            $this->findAll($expandSelection, []);
        
        // Exceute select query
        $stmt = $this->executeQuery($selection->getSqlRepresentation(['prefix' => $this->prefix]));
        
        // Inject results
        $identifiers = $this->injectIteratively($class, $stmt);
        
        // Appropriate return
        if ($returnAs == 1)
            return $identifiers;
        
        if ($returnAs == 0) {
            $objects = [];
            foreach($identifiers as $identifier)
                $objects[] = $this->rows[$class][(string)$identifier];
            
            return $objects;
        }
        
        // Else reutrn null
        return null;
    }
    /**
     * Find and load first object from a Selection
     * 
     * @param Selection $selection The selection
     * @param int $returnAs 0 = object, 1 = identifier, ? = null
     * @throws NotSupportedException When an expand depends on more than 1 column
     * @return mixed
     */
    public function findFirst(Selection $selection, int $returnAs = 0) {
        $class = $selection->getTargetClass();
        
        // Exceute select query
        $stmt = $this->executeQuery($select->getSqlRepresentation([
            'prefix' => $this->prefix,
            'first' => true
        ]));
        
        // Inject results
        $identifier = $this->inject($class, $stmt->fetch(PDO::FETCH_ASSOC));
        if ($identifier == null)
            return null;
        
        // Appropriate return
        if ($returnAs == 1)
            return $identifier;
        
        if ($returnAs == 0)
            return $this->rows[$class][(string)$identifier];
        
        return null;
    }
    
    public function count(Selection $selection) {
        // Exceute select query
        $stmt = $this->executeQuery($selection->getSqlRepresentation([
            'prefix' => $this->prefix,
            'mode' => 'COUNT'
        ]));
        
        // Fetch results
        $res = $stmt->fetch(PDO::FETCH_COLUMN);
        if ($res === false)
            return false;
        return (int)$res;
    }
    
    /**
     * Creates a table for the given class
     * 
     * @param string $class The class for which to create the table
     * @param bool $ifNotExists Whether to skip creating the table if one exists already. Defaults to true.
     * @return void
     */
    public function createTable(string $class, bool $ifNotExists = true) {
        self::checkIsRowClass($class);
        
        $table = $class::rigidGetTable();
        $createCode = $table->getSqlRepresentation([
            'if_not_exists' => (bool)$ifNotExists,
            'prefix' => $this->prefix
        ]);
        $this->executeQuery($createCode);
    }
    
    /**
     * Executes a QueryPart
     * @param QueryPart $qp The query part
     * @return PDOStatement
     */
    protected function executeQuery(QueryPart $qp): PDOStatement {
        $stmt = $this->pdo->prepare($qp->getSQL());
        $stmt->execute($qp->getParameters());
        return $stmt;
    }
    
    /**
     * Create an object from associative array
     * @param string $class The class name of the data type
     * @param mixed[string] $data The associative array
     * @throws UnexpectedValueException
     * @return Identifier
     */
    protected function inject($class, array $data) {
        self::checkIsRowClass($class);
        
        // TODO What if there is already an object with that identifier
        $row = $class::rigidCreate($this, $data);
        $this->rows[$class][(string)$row->getIdentifier()] = $row;
        return $row->getIdentifier();
    }
    
    /**
     * Create objects from array of associative arrays 
     *
     * @param string $class The class name of the Row type
     * @param mixed[string][] $data The array of data arrays
     * @return Identifier[] the identifiers of the objects that were injected
     */
    protected function injectMany(string $class, array $data): array {
        $identifiers = [];
        foreach($data as $o)
            $identifiers[] = $this->inject($class, $o);
        return $identifiers;
    }
    
    /**
     * Create and load objects from PDOStatement results 
     *
     * @param string $class The class name of the Row type
     * @param PDOStatement $stmt The PDOStatement
     * @return Identifier[] the identifiers of the objects that were injected
     */
    protected function injectIteratively($class, PDOStatement $stmt) {
        $identifiers = [];
        while($data = $stmt->fetch(PDO::FETCH_ASSOC))
            $identifiers[] = $this->inject($class, $data);
        return $identifiers;
    }
    
    protected static function checkIsRowClass($class) {       
        if (!isset(self::$isRowClass[$class]))
            self::$isRowClass[$class] = Util::hasTrait($class, Row::class);
        
        if (!self::$isRowClass[$class])
            throw new UnexpectedValueException('Rigid\ORM expects $class to use Rigid\Row trait, got "'.$class.'"');
    }
    
    public function __destruct() {
        $this->updateAll();
    }
    
    protected function &getRowReference(Identifier $identifier) {
        return $this->rows[$identifier->getClass()][(string)$identifier];
    }
    
}