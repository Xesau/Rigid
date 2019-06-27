<?php
declare(strict_types=1);

namespace Rigid;

interface SqlRepresentable {
    
    /**
     * Generates the code that creates a reprentation of this object in SQL
     *
     * @param mixed[string] $options Implementation-specific options parameter
     * @return QueryPart
     */
    public function getSqlRepresentation(array $options = null): QueryPart;
    
}