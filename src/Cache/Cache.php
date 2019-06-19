<?php
declare(strict_types=1);

namespace LanguageServer\Cache;

/**
 * A key/value store for caching purposes
 */
interface Cache
{
    /**
     * Gets a value from the cache
     *
     * @param string $key
     * @return \Generator <mixed>
     */
    public function get(string $key): \Generator;

    /**
     * Sets a value in the cache
     *
     * @param string $key
     * @param mixed $value
     * @return \Generator
     */
    public function set(string $key, $value): \Generator;
}
