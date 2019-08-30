<?php
declare(strict_types = 1);

namespace LanguageServer;

class Configuration
{
    /**
     * @var string[]
     */
    public $excludePatterns;

    public function __construct(array $excludePatterns = [])
    {
        $this->excludePatterns = $excludePatterns;
    }
}
