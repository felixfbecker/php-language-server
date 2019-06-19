<?php
declare(strict_types=1);

namespace LanguageServer\Cache;

/**
 * Caches content on the file system
 */
class FileSystemCache implements Cache
{
    /**
     * @var string
     */
    public $cacheDir;

    public function __construct()
    {
        if (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN') {
            $this->cacheDir = getenv('LOCALAPPDATA') . '\\PHP Language Server\\';
        } else if (getenv('XDG_CACHE_HOME')) {
            $this->cacheDir = getenv('XDG_CACHE_HOME') . '/phpls/';
        } else {
            $this->cacheDir = getenv('HOME') . '/.phpls/';
        }
    }

    /**
     * Gets a value from the cache
     *
     * @param string $key
     * @return \Generator <mixed>
     */
    public function get(string $key): \Generator
    {
        try {
            $file = $this->cacheDir . urlencode($key);
            $content = yield \Amp\File\get($file);
            return unserialize($content);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sets a value in the cache
     *
     * @param string $key
     * @param mixed $value
     * @return \Generator
     */
    public function set(string $key, $value): \Generator
    {
        $file = $this->cacheDir . urlencode($key);
        $dir = dirname($file);
        if (yield \Amp\File\isfile($dir)) {
            yield \Amp\File\unlink($dir);
        }
        if (!yield \Amp\File\exists($dir)) {
            yield \Amp\File\mkdir($dir, 0777, true);
        }
        yield \Amp\File\put($file, serialize($value));
    }
}
