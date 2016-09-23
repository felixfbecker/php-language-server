<?php

namespace LanguageServer\Server;

use Symfony\CS\Config;
use Symfony\CS\ConfigurationResolver;
use LanguageServer\Protocol\TextEdit;
use LanguageServer\Protocol\Range;
use LanguageServer\Protocol\Position;

class Formatter
{
    /**
     * @var CustomCSFixer
     */
    private $fixer;

    public function __construct()
    {
        $this->fixer = new CustomCSFixer();
        $this->fixer->registerBuiltInFixers();
        $this->fixer->registerBuiltInConfigs();
    }

    /**
     * @param string $content
     *
     * @return TextEdit[]
     */
    public function format(string $content, string $uri)
    {
        // remove 'file://' prefix
        $uri = substr($uri, 7);

        $config = new Config();

        // TODO read user configuration from workspace root (.php_cs file)

        // register custom fixers from config
        $this->fixer->registerCustomFixers($config->getCustomFixers());

        $resolver = new ConfigurationResolver();
        $resolver->setAllFixers($this->fixer->getFixers())
        ->setConfig($config)
        //->setOptions(array('level' => 'psr2'))
        ->resolve();

        $config->fixers($resolver->getFixers());
        $formatted = $this->fixer->fixContent($content, $uri, $config);
        if ($formatted == null) {
            return [];
        }

        $edit = new TextEdit();
        $edit->range = new Range(new Position(0, 0), new Position(PHP_INT_MAX, PHP_INT_MAX));
        $edit->newText = $formatted;
        return [$edit];
    }
}
