<?php

namespace Rigid;

interface IColumnOptions {
    
    public function __toString(): string;
    
    public function isValid($value): bool;
    
}