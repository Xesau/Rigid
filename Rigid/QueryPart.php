<?php
declare(strict_types=1);

namespace Rigid;
use UnexpectedValueException;

/**
 * Helper class to represent SQL queries and associated paramters
 */
class QueryPart {
    
    /** @var string $sql The SQL code */
    private $sql;
    
    /** @var mixed[] $params The parameters */
    private $params;
    
    /** 
     * @param string $sql The SQL code
     * @parram mixed[] $parameters
     */
    public function __construct(string $sql, array $params = []) {
        $this->sql = $sql;
        $this->params = $params;
    }
    
    /**
     * Combines multiple QueryParts into one
     * @return QueryPart
     */
    public static function combine(array $parts): QueryPart {
        $sql = '';
        $params = [];
        foreach($parts as $part) {
            if (is_string($part))
                $sql .= $part;
            if ($part instanceof QueryPart) {
                $sql .= $part->getSQL();
                foreach($part->getParameters() as &$v)
                    $params[] = $v;
            }
        }
        return new self($sql, $params);
    }
    
    /**
     * The SQL code
     * @return string
     */
    public function getSQL(): string {
        return $this->sql;
    }
    
    /**
     * The parameter values that should be passed on to the query
     * @return mixed[]
     */
    public function getParameters(): array {
        return $this->params;
    }
    
    /**
     * Appends SQL code and parameter values to the QueryPart
     * @return void
     */
    public function append($sql, array $params = []) {
        $this->sql .= $sql;
        foreach($params as &$v)
            $this->params[] = $v;
    }
    
    /**
     * Functions like join(), but with QueryParts
     * @param string $glue
     * @param QueryPart[] $parts
     * @return void
     */
    public function appendJoinQueryParts($glue, array $parts) {
        $first = true;
        foreach($parts as $part) {
            if ($first)
                $first = false;
            else
                $this->append($glue);
            $this->appendQueryPart($part);
        }
    }
    
    /**
     * Appends another QueryPart to the QueryPart
     * @return void
     */
    public function appendQueryPart($qp) {
        if (!($qp instanceof QueryPart))
            if ($qp instanceof SqlRepresentable)
                $qp = $qp->getSqlRepresentation();
            else
                throw UnexpectedValueException('Expected QueryPart or SqlRepresentable, got '.gettype($qp));
        $this->append($qp->getSQL(), $qp->getParameters());
    }
    
}
