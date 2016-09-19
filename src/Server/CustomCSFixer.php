<?php

namespace LanguageServer\Server;

use Symfony\CS\ConfigAwareInterface;
use Symfony\CS\ConfigInterface;
use Symfony\CS\Fixer;
use Symfony\CS\FixerInterface;
use Symfony\CS\Tokenizer\Tokens;
use Symfony\CS\Utils;

class CustomCSFixer extends Fixer
{
    /**
     * @param string          $content
     * @param string          $uri
     * @param ConfigInterface $config
     *
     * @return string|null formated source code or null if no change was made
     */
    public function fixContent(string $content, string $uri, ConfigInterface $config)
    {
        $fixers = $this->prepareFixers($config);
        $fixers = $this->sortFixers($fixers);

        return $this->doFixContent($content, $uri, $fixers);
    }

    /**
     * @param string $content
     * @param string $uri
     * @param array  $fixers
     *
     * @return string|null formated source code or null if no change was made
     */
    private function doFixContent(string $content, string $uri, array $fixers)
    {
        $new = $old = $content;

        if ('' === $old ||
        // PHP 5.3 has a broken implementation of token_get_all when the file uses __halt_compiler() starting in 5.3.6
        (PHP_VERSION_ID >= 50306 && PHP_VERSION_ID < 50400 && false !== stripos($old, '__halt_compiler()'))) {
            return null;
        }

        // we do not need Tokens to still caching previously fixed file - so clear the cache
        Tokens::clearCache();

        try {
            $file = new \SplFileInfo($uri);
            foreach ($fixers as $fixer) {
                if (!$fixer->supports($file)) {
                    continue;
                }

                $newest = $fixer->fix($file, $new);
                $new = $newest;
            }
        } catch (\ParseError $e) {
            return null;
        } catch (\Error $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }

        if ($new !== $old) {
            return $new;
        }

        return null;
    }

    /**
     * @param FixerInterface[] $fixers
     *
     * @return FixerInterface[]
     */
    private function sortFixers(array $fixers)
    {
        usort($fixers, function (FixerInterface $a, FixerInterface $b) {
            return Utils::cmpInt($b->getPriority(), $a->getPriority());
        });

        return $fixers;
    }

    /**
     * @param ConfigInterface $config
     *
     * @return FixerInterface[]
     */
    private function prepareFixers(ConfigInterface $config)
    {
        $fixers = $config->getFixers();

        foreach ($fixers as $fixer) {
            if ($fixer instanceof ConfigAwareInterface) {
                $fixer->setConfig($config);
            }
        }

        return $fixers;
    }
}
