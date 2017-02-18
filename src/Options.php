<?php

namespace LanguageServer;

class Options
{
    /**
     * Filetypes the indexer should process
     *
     * @var array
     */
    private $fileTypes = [".php"];

    /**
     * @param \Traversable|\stdClass|array|null $options
     */
    public function __construct($options = null)
    {
        // Do nothing when the $options parameter is not an object
        if (!is_object($options) && !is_array($options) && (!$options instanceof \Traversable)) {
            return;
        }

        foreach ($options as $option => $value) {
            $method = 'set' . ucfirst($option);

            call_user_func([$this, $method], $value);
        }
    }

    /**
     * Validate and set options for file types
     *
     * @param array $fileTypes List of file types
     */
    public function setFileTypes(array $fileTypes)
    {
        $fileTypes = filter_var_array($fileTypes, FILTER_SANITIZE_STRING);
        $fileTypes =  filter_var($fileTypes, FILTER_CALLBACK, ['options' => [$this, 'filterFileTypes']]);
        $fileTypes = array_filter($fileTypes);

        $this->fileTypes = !empty($fileTypes) ? $fileTypes : $this->fileTypes;
    }

    /**
     * Get list of registered file types
     *
     * @return array
     */
    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    /**
     * Filter valid file type
     *
     * @param string $fileType The file type to filter
     * @return string|bool If valid it returns the file type, otherwise false
     */
    private function filterFileTypes(string $fileType)
    {
        $fileType = trim($fileType);

        if (empty($fileType)) {
            return $fileType;
        }

        if (substr($fileType, 0, 1) !== '.') {
            return false;
        }

        return $fileType;
    }
}
