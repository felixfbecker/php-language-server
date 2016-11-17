<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{
    TextEdit,
    Range,
    Position
};
use Exception;
use PHP_CodeSniffer\{
    Config,
    Ruleset
};
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Util\Tokens;

abstract class Formatter
{

    /**
     * Generate array of TextEdit changes for content formatting.
     *
     * @param string $content source code to format
     * @param string $uri URI of document
     *
     * @return \LanguageServer\Protocol\TextEdit[]
     * @throws \Exception
     */
    public static function format(string $content, string $uri)
    {
        if (!defined('PHP_CODESNIFFER_CBF')) {
            define('PHP_CODESNIFFER_CBF', true);
        }

        if (!defined('PHP_CODESNIFFER_VERBOSITY')) {
            define('PHP_CODESNIFFER_VERBOSITY', false);
        }

        $path = uriToPath($uri);
        $config = new Config(['dummy'], false);
        $config->standards = self::findConfiguration($path);

        // Autoload class to set up a bunch of PHP_CodeSniffer-specific token type constants
        spl_autoload_call(Tokens::class);

        $file = new DummyFile($content, new Ruleset($config), $config);
        $file->process();
        $fixed = $file->fixer->fixFile();
        if (!$fixed && $file->getErrorCount() > 0) {
            throw new Exception('Unable to format file');
        }

        $new = $file->fixer->getContents();
        if ($content === $new) {
            return [];
        }
        return [new TextEdit(new Range(new Position(0, 0), self::calculateEndPosition($content)), $new)];
    }

    /**
     * Calculate position of last character.
     *
     * @param string $content document as string
     *
     * @return \LanguageServer\Protocol\Position
     */
    private static function calculateEndPosition(string $content): Position
    {
        $lines = explode("\n", $content);
        return new Position(count($lines) - 1, strlen(end($lines)));
    }

    /**
     * Search for PHP_CodeSniffer configuration file at given directory or its parents.
     * If no configuration found then PSR2 standard is loaded by default.
     *
     * @param string $path path to file or directory
     * @return string[]
     */
    private static function findConfiguration(string $path)
    {
        if (is_dir($path)) {
            $currentDir = $path;
        } else {
            $currentDir = dirname($path);
        }
        do {
            $default = $currentDir . DIRECTORY_SEPARATOR . 'phpcs.xml';
            if (is_file($default)) {
                return [$default];
            }

            $default = $currentDir . DIRECTORY_SEPARATOR . 'phpcs.xml.dist';
            if (is_file($default)) {
                return [$default];
            }

            $lastDir = $currentDir;
            $currentDir = dirname($currentDir);
        } while ($currentDir !== '.' && $currentDir !== $lastDir);

        $standard = Config::getConfigData('default_standard') ?? 'PSR2';
        return explode(',', $standard);
    }
}
