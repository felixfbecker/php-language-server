<?php

namespace LanguageServer;

class Options
{
    /**
     * Filetypes the indexer should process
     *
     * @var array
     */
    public $fileTypes = [".php"];

    /**
     * @param \stdClass|null $options
     */
    public function __construct(\stdClass $options = null)
    {
        // Do nothing when the $options parameter is not an object
        if (!is_object($options)) {
            return;
        }

        $this->fileTypes = $options->fileTypes ?? $this->normalizeFileTypes($this->fileTypes);
    }

    private function normalizeFileTypes(array $fileTypes): array
    {
        return array_map(function (string $fileType) {
            if (substr($fileType, 0, 1) !== '.') {
                $fileType = '.' . $fileType;
            }

            return $fileType;
        }, $fileTypes);
    }
}
