<?php

namespace Rigid;

use UnexpectedValueException;

class Index implements SqlRepresentable {
    
    /** @var string[] $columns The columns this index applies to **/
    protected $columns;
    
    /** @var string $name The name of the key */
    protected $name;
    
    /** @var string $type The type of key */
    protected $type;
    
    public function __construct(string $name, $columns, string $type = 'INDEX') {
        if (static::class == Primary::class) {
            $name = 'PRIMARY';
            $type = 'PRIMARY';
        } elseif ($name == 'PRIMARY') {
            throw new UnexpectedValueException('Non-primary index cannot be called PRIMARY. Use Rigid\Primary');
        } elseif (static::class == Reference::class) {
            $type = 'FOREIGN';
        } else {
            $type = strtoupper($type);
            if (!in_array($type, ['INDEX', 'SPATIAL', 'UNIQUE', 'FULLTEXT']))
                throw new UnexpectedValueException('Invalid index type '.$type);
        }
        
        $this->name = $name;
        $this->columns = (array)$columns;
        $this->type = $type;
    }
    
    /** 
     * The name of this index
     */
    public function getName(): string {
        return $this->name;
    }
    
    /**
     * The type of this index
     * @return string PRIMARY, UNIQUE, INDEX, FOREIGN, SPATIAL, FULLTEXT
     */
    public function getType(): string {
        return $this->type;
    }
    
    /**
     * The columns this index applies to
     * @return string[]
     */
    public function getColumns(): array {
        return $this->columns;
    }
    
    public function getSqlRepresentation(array $options = null): QueryPart {
        $parts = [];
        foreach($this->columns as $column)
            $parts[] = Util::escapeName($column);
        $columnList = implode(', ', $parts);
        
        if ($this->type == 'PRIMARY')
            return new QueryPart('PRIMARY KEY('. $columnList. ')');
        
        if ($this->type == 'INDEX')
            return new QueryPart('KEY '. Util::escapeName($this->name) .'('. $columnList .')');
        
        return new QueryPart($this->type .' KEY '. Util::escapeName($this->name) .'(' . implode(', ', $parts) .')');
    }
    
}