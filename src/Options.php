<?php

namespace LanguageServer;

class Options
{
    /**
     * Filetypes the indexer should process
     *
     * @var string[]
     */
    public $fileTypes = ['.php'];

    /**
     * Validate/Filter input and set options for file types
     *
     * @param array $fileTypes List of file types
     */
    public function setFileTypes(array $fileTypes)
    {
        $fileTypes = filter_var_array($fileTypes, FILTER_SANITIZE_STRING);
        $fileTypes =  filter_var($fileTypes, FILTER_CALLBACK, ['options' => [$this, 'filterFileTypes']]); // validate file type format
        $fileTypes = array_filter($fileTypes, 'strlen'); // filter empty items
        $fileTypes = array_values($fileTypes); //rebase indexes

        $this->fileTypes = !empty($fileTypes) ? $fileTypes : $this->fileTypes;
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
