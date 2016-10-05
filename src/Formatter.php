<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{TextEdit, Range, Position, ErrorCode};
use AdvancedJsonRpc\ResponseError;

class Formatter
{

    /**
     *
     * @param string $content            
     * @param string $uri            
     *
     * @return \LanguageServer\Protocol\TextEdit[]
     * @throws \AdvancedJsonRpc\ResponseError
     */
    public function format(string $content, string $uri)
    {
        $path = uriToPath($uri);
        $cs = new \PHP_CodeSniffer();
        $cs->initStandard($this->findConfiguration($path));
        $file = $cs->processFile(null, $content);
        $fixed = $file->fixer->fixFile();
        if (!$fixed && $file->getErrorCount() > 0) {
            throw new ResponseError('Unable to format file', ErrorCode::INTERNAL_ERROR);
        }
        
        $new = $file->fixer->getContents();
        if ($content === $new) {
            return [];
        }
        return [new TextEdit(new Range(new Position(0, 0), $this->calculateEndPosition($content)), $new)];
    }

    /**
     * @param string $content
     * @return \LanguageServer\Protocol\Position
     */
    private function calculateEndPosition(string $content): Position
    {
        $lines = explode("\n", $content);
        return new Position(sizeof($lines) - 1, strlen(end($lines)));
    }
    
    /**
     *
     * @param string $uri            
     * @return string[]
     */
    private function findConfiguration(string $uri)
    {
        $currentDir = dirname($uri);
        do {
            $default = $currentDir . DIRECTORY_SEPARATOR . 'phpcs.xml';
            if (is_file($default) === true) {
                return [$default];
            }
            
            $default = $currentDir . DIRECTORY_SEPARATOR . 'phpcs.xml.dist';
            if (is_file($default) === true) {
                return [$default];
            }
            
            $lastDir = $currentDir;
            $currentDir = dirname($currentDir);
        } while ($currentDir !== '.' && $currentDir !== $lastDir);
        
        $standard = \PHP_CodeSniffer::getConfigData('default_standard') ?? 'PSR2';
        return explode(',', $standard);
    }
    
}
