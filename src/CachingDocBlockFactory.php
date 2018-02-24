<?php
declare(strict_types=1);

namespace LanguageServer;

use Microsoft\PhpParser\Node;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types;

/**
 * Caches DocBlocks by node start position and file URI.
 */
class CachingDocBlockFactory
{
    /**
     * Maps file + node start positions to DocBlocks.
     */
    private $cache = [];

    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;


    public function __construct() {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @return DocBlock|null
     */
    public function getDocBlock(Node $node)
    {
        $cacheKey = $node->getStart() . ':' . $node->getUri();
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }
        $text = $node->getDocCommentText();
        return $this->cache[$cacheKey] = $text === null ? null : $this->createDocBlockFromNodeAndText($node, $text);
    }

    public function clearCache() {
        $this->cache = [];
    }

    /**
     * @return DocBlock|null
     */
    private function createDocBlockFromNodeAndText(Node $node, string $text)
    {
        list($namespaceImportTable,,) = $node->getImportTablesForCurrentScope();
        $namespaceImportTable = array_map('strval', $namespaceImportTable);
        $namespaceDefinition = $node->getNamespaceDefinition();
        if ($namespaceDefinition !== null && $namespaceDefinition->name !== null) {
            $namespaceName = (string)$namespaceDefinition->name->getNamespacedName();
        } else {
            $namespaceName = 'global';
        }
        $context = new Types\Context($namespaceName, $namespaceImportTable);
        try {
            // create() throws when it thinks the doc comment has invalid fields.
            // For example, a @see tag that is followed by something that doesn't look like a valid fqsen will throw.
            return $this->docBlockFactory->create($text, $context);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
