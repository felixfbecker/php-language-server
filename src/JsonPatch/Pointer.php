<?php
declare(strict_types = 1);

namespace JsonPatch;

class Pointer
{
    /**
     * @var self|null
     */
    public $parent;

    /**
     * The property name or array index. The root pointer has an empty key
     *
     * @var string
     */
    public $key;

    /**
     * @var string $pointer A full JSON Pointer string
     * @return self
     */
    public static function parse(string $pointer)
    {
        $p = new self(null, '');
        // TODO unescape
        foreach (explode('/', $pointer) as $key) {
            if ((string)(int)$key === $key) {
                $key = (int)$key;
            }
            $p = new self($p, $key);
        }
        return $p;
    }

    /**
     * @param self $parent
     * @param string|number $key
     */
    public function __construct($parent, $key)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new \IllegalArgumentException('Key must be string or int');
        }
        $this->parent = $parent;
        $this->key = $key;
    }

    /**
     * Returns a reference to the value the pointer points to at the target
     *
     * @param object|array $target
     */
    public function &at($target)
    {
        if ($this->parent !== null) {
            $target = $this->parent->at($target);
        }
        $key = $this->key;
        if ($key === '') {
            return $target;
        }
        if ($key === '-') {
            if (!is_array($target)) {
                throw new \Exception('Trying to apply "-" on a non-array');
            }
            $key = count($target);
        }
        if (is_array($target)) {
            return $target[$key];
        }
        return &$target->$key;
    }

    /**
     * @param string|int $key
     * @return self
     */
    public function go($key)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new \IllegalArgumentException('Key must be string or int');
        }
        return new self($this, $key);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->parent !== null && $this->parent->key !== '') {
            return (string)($this->parent ?? '') . '/' . $this->key;
        }
    }
}
