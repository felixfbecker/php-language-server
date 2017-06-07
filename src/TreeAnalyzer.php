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
    private $parser;

    /** @var Node */
    private $stmts;

    /** @var Diagnostic[] */
    private $diagnostics;

    /** @var string */
    private $content;

    /**
     * TreeAnalyzer constructor.
     * @param PhpParser\Parser $parser
     * @param $content
     * @param $docBlockFactory
     * @param DefinitionResolver $definitionResolver
     * @param $uri
     */
    public function __construct($parser, $content, $docBlockFactory, $definitionResolver, $uri)
    {
        $this->uri = $uri;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->definitionResolver = $definitionResolver;
        $this->content = $content;
        $this->stmts = $this->parser->parseSourceFile($content, $uri);

        // TODO - docblock errors

        $this->collectDefinitionsAndReferences($this->stmts);
    }

    public function collectDefinitionsAndReferences(Node $stmts)
    {
        foreach ($stmts::CHILD_NAMES as $name) {
            $node = $stmts->$name;

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
                $range = PhpParser\PositionUtilities::getRangeFromPosition($error->start, $error->length, $this->content);

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
                    if (ParserHelpers::isConstantFetch($node) ||
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

    public function getDiagnostics()
    {
        return $this->diagnostics ?? [];
    }

    private function addReference(string $fqn, Node $node)
    {
        if (!isset($this->referenceNodes[$fqn])) {
            $this->referenceNodes[$fqn] = [];
        }
        $this->referenceNodes[$fqn][] = $node;
    }

    public function getDefinitions()
    {
        return $this->definitions ?? [];
    }

    public function getDefinitionNodes()
    {
        return $this->definitionNodes ?? [];
    }

    public function getReferenceNodes()
    {
        return $this->referenceNodes ?? [];
    }

    public function getStmts()
    {
        return $this->stmts;
    }
    /**
     * Returns the URI of the document
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}
