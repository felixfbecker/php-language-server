<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, TextEdit};
use LanguageServer\Index\Index;
use phpDocumentor\Reflection\DocBlockFactory;
use Sabre\Uri;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;

class TreeAnalyzer
{
    /** @var PhpParser\Parser */
    private $parser;

    /** @var Node\SourceFileNode */
    private $sourceFileNode;

    /** @var Diagnostic[] */
    private $diagnostics;

    /** @var string */
    private $content;

    /** @var Node[] */
    private $referenceNodes;

    /** @var Definition[] */
    private $definitions;

    /** @var Node[] */
    private $definitionNodes;

    /**
     * @param PhpParser\Parser $parser
     * @param string $content
     * @param DocBlockFactory $docBlockFactory
     * @param DefinitionResolver $definitionResolver
     * @param string $uri
     */
    public function __construct(PhpParser\Parser $parser, string $content, DocBlockFactory $docBlockFactory, DefinitionResolver $definitionResolver, string $uri)
    {
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->definitionResolver = $definitionResolver;
        $this->sourceFileNode = $this->parser->parseSourceFile($content, $uri);

        // TODO - docblock errors

        $this->collectDefinitionsAndReferences($this->sourceFileNode);
    }

    private function collectDefinitionsAndReferences(Node $sourceFileNode)
    {
        foreach ($sourceFileNode::CHILD_NAMES as $name) {
            $node = $sourceFileNode->$name;

            if ($node === null) {
                continue;
            }

            if (\is_array($node)) {
                foreach ($node as $child) {
                    if ($child instanceof Node) {
                        $this->update($child);
                    }
                }
                continue;
            }

            if ($node instanceof Node) {
                $this->update($node);
            }

            if (($error = PhpParser\DiagnosticsProvider::checkDiagnostics($node)) !== null) {
                $range = PhpParser\PositionUtilities::getRangeFromPosition($error->start, $error->length, $this->sourceFileNode->fileContents);

                $this->diagnostics[] = new Diagnostic(
                    $error->message,
                    new Range(
                        new Position($range->start->line, $range->start->character),
                        new Position($range->end->line, $range->start->character)
                    ),
                    null,
                    DiagnosticSeverity::ERROR,
                    'php'
                );
            }
        }
    }

    /**
     * Collect definitions and references for the given node
     *
     * @param Node $node
     */
    private function update(Node $node)
    {
        $fqn = ($this->definitionResolver)::getDefinedFqn($node);
        // Only index definitions with an FQN (no variables)
        if ($fqn !== null) {
            $this->definitionNodes[$fqn] = $node;
            $this->definitions[$fqn] = $this->definitionResolver->createDefinitionFromNode($node, $fqn);
        } else {
            $parent = $node->parent;
            if (!(
                (
                    // $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression ||
                    ($node instanceof Node\Expression\ScopedPropertyAccessExpression ||
                    $node instanceof Node\Expression\MemberAccessExpression)
                    && !(
                        $node->parent instanceof Node\Expression\CallExpression ||
                        $node->memberName instanceof PhpParser\Token
                    ))
                || ($parent instanceof Node\Statement\NamespaceDefinition && $parent->name !== null && $parent->name->getStart() === $node->getStart()))
            ) {
                $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node);
                if ($fqn !== null) {
                    $this->addReference($fqn, $node);

                    if (
                        $node instanceof Node\QualifiedName
                        && ($node->isQualifiedName() || $node->parent instanceof Node\NamespaceUseClause)
                        && !($parent instanceof Node\Statement\NamespaceDefinition && $parent->name->getStart() === $node->getStart()
                        )
                    ) {
                        // Add references for each referenced namespace
                        $ns = $fqn;
                        while (($pos = strrpos($ns, '\\')) !== false) {
                            $ns = substr($ns, 0, $pos);
                            $this->addReference($ns, $node);
                        }
                    }

                    // Namespaced constant access and function calls also need to register a reference
                    // to the global version because PHP falls back to global at runtime
                    // http://php.net/manual/en/language.namespaces.fallback.php
                    if (ParserHelpers\isConstantFetch($node) ||
                        ($parent instanceof Node\Expression\CallExpression
                            && !(
                                $node instanceof Node\Expression\ScopedPropertyAccessExpression ||
                                $node instanceof Node\Expression\MemberAccessExpression
                            ))) {
                        $parts = explode('\\', $fqn);
                        if (count($parts) > 1) {
                            $globalFqn = end($parts);
                            $this->addReference($globalFqn, $node);
                        }
                    }
                }
            }
        }
        $this->collectDefinitionsAndReferences($node);
    }

    /**
     * @return Diagnostic[]
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics ?? [];
    }

    /**
     * @return void
     */
    private function addReference(string $fqn, Node $node)
    {
        if (!isset($this->referenceNodes[$fqn])) {
            $this->referenceNodes[$fqn] = [];
        }
        $this->referenceNodes[$fqn][] = $node;
    }

    /**
     * @return Definition
     */
    public function getDefinitions()
    {
        return $this->definitions ?? [];
    }

    /**
     * @return Node[]
     */
    public function getDefinitionNodes()
    {
        return $this->definitionNodes ?? [];
    }

    /**
     * @return Node[]
     */
    public function getReferenceNodes()
    {
        return $this->referenceNodes ?? [];
    }

    /**
     * @return Node\SourceFileNode
     */
    public function getSourceFileNode()
    {
        return $this->sourceFileNode;
    }
}
