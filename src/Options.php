<?php
declare(strict_types = 1);

namespace LanguageServer;

class Options
{
    /**
     * File types the indexer should process
     *
     * @var string[]
     */
    public $fileTypes = ['.php'];

    /**
     * Maximum file size to index
     *
     * @var int
     */
    public $fileSizeLimit = 150000;

    /**
     * Validate/Filter input and set options for file types
     *
     * @param string[] $fileTypes List of file types
     */
    public function setFileTypes(array $fileTypes)
    {
        $fileTypes = filter_var_array($fileTypes, FILTER_SANITIZE_STRING);
        $fileTypes = filter_var($fileTypes, FILTER_CALLBACK, ['options' => [$this, 'filterFileTypes']]);
        $fileTypes = array_filter($fileTypes, 'strlen');
        $fileTypes = array_values($fileTypes);

        $this->fileTypes = !empty($fileTypes) ? $fileTypes : $this->fileTypes;
    }

    /**
     * Validate/Filter input and set option for file size limit
     *
     * @param string $fileSizeLimit Size in human readable format or -1 for unlimited
     */
    public function setFileSizeLimit(string $fileSizeLimit)
    {
        $fileSizeLimit = filter_var($fileSizeLimit, FILTER_SANITIZE_STRING);

        if ($fileSizeLimit === '-1') {
            $this->fileSizeLimit = PHP_INT_MAX;
        } else {
            $this->fileSizeLimit = $this->convertFileSize($fileSizeLimit);
        }
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

    /**
     * Convert human readable file size to byte
     *
     * @param string $fileSize
     * @return int
     */
    private function convertFileSize(string $fileSize)
    {
        preg_match('/(\d+)(\w)/', $fileSize, $match);
        $sizes = 'KMG';
        $size = (int) $match[1];
        $factor = strpos($sizes, strtoupper($match[2])) + 1;

        return $size * pow(1000, $factor);
    }
}
