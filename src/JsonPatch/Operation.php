<?php
declare(strict_types = 1);

namespace JsonPatch;

class Operation
{
    /**
     * @var Pointer
     */
    public $path;

    public function __construct(Pointer $path)
    {
        $this->path = $path;
    }
}
