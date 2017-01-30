<?php
declare(strict_types = 1);

namespace JsonPatch\Operation;

/**
 * Adds a value to an object or inserts it into an array.
 * In the case of an array the value is inserted before the given index.
 * The - character can be used instead of an index to insert at the end of an array.
 */
class Add extends Operation
{
    /**
     * @var mixed
     */
    public $value;

    public function __construct(Pointer $path, $value)
    {
        parent::__construct($path);
        $this->value = $value;
    }

    /**
     * @param mixed $target
     * @return void
     */
    public function apply(&$target)
    {
        if (is_array($this->path->parent->at($target))) {
            // Numeric key
            if ($this->path->key === 0) {
                throw new \Exception('Cannot add before 0');
            }
            $this->path->parent->go($this->path->key - 1)->at($target) = $this->value;
        }
        $this->path->at($target);
    }
}
