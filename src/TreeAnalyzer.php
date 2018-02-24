<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, TextEdit};
use LanguageServer\Index\Index;
use LanguageServer\Scope\Scope;
use LanguageServer\Scope\TreeTraverser;
use phpDocumentor\Reflection\DocBlockFactory;
use Sabre\Uri;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class TreeAnalyzer
{
    /** @var PhpParser\Parser */
    private $parser;

    /** @var DocBlockFactory */
    private $docBlockFactory;

    /** @var DefinitionResolver */
    private $definitionResolver;

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

        $traverser = new TreeTraverser($definitionResolver);
        $traverser->traverse(
            $this->sourceFileNode,
            function ($nodeOrToken, Scope $scope) {
                $this->collectDiagnostics($nodeOrToken, $scope);
                if ($nodeOrToken instanceof Node) {
                    $this->collectDefinitionsAndReferences($nodeOrToken, $scope);
                }
            }
        );
    }

    /**
     * Collects Parser diagnostic messages for the Node/Token
     * and transforms them into LSP Format
     *
     * @param Node|Token $node
     * @return void
     */
    private function collectDiagnostics($node, Scope $scope)
    {
        // Get errors from the parser.
        if (($error = PhpParser\DiagnosticsProvider::checkDiagnostics($node)) !== null) {
            $range = PhpParser\PositionUtilities::getRangeFromPosition($error->start, $error->length, $this->sourceFileNode->fileContents);

            switch ($error->kind) {
                case PhpParser\DiagnosticKind::Error:
                    $severity = DiagnosticSeverity::ERROR;
                    break;
                case PhpParser\DiagnosticKind::Warning:
                default:
                    $severity = DiagnosticSeverity::WARNING;
                    break;
            }

            $this->diagnostics[] = new Diagnostic(
                $error->message,
                new Range(
                    new Position($range->start->line, $range->start->character),
                    new Position($range->end->line, $range->start->character)
                ),
                null,
                $severity,
                'php'
            );
        }

        // Check for invalid usage of $this.
        if ($node instanceof Node\Expression\Variable &&
            !isset($scope->variables['this']) &&
            $node->getName() === 'this'
        ) {
            $this->diagnostics[] = new Diagnostic(
                "\$this can not be used in static methods.",
                Range::fromNode($node),
                null,
                DiagnosticSeverity::ERROR,
                'php'
            );
        }
    }

    /**
     * Collect definitions and references for the given node
     *
     * @param Node $node
     */
    private function collectDefinitionsAndReferences(Node $node, Scope $scope)
    {
        $fqn = $this->definitionResolver->getDefinedFqn($node, $scope);
        // Only index definitions with an FQN (no variables)
        if ($fqn !== null) {
            $this->definitionNodes[$fqn] = $node;
            $this->definitions[$fqn] = $this->definitionResolver->createDefinitionFromNode($node, $fqn, $scope);
        } else {

            $parent = $node->parent;
            if (
                (
                    // $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression ||
                    ($node instanceof Node\Expression\ScopedPropertyAccessExpression ||
                    $node instanceof Node\Expression\MemberAccessExpression)
                    && !(
                        $parent instanceof Node\Expression\CallExpression ||
                        $node->memberName instanceof PhpParser\Token
                    ))
                || ($parent instanceof Node\Statement\NamespaceDefinition && $parent->name !== null && $parent->name->getStart() === $node->getStart())
            ) {
                return;
            }

            $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node, $scope);
            if (!$fqn) {
                return;
            }

            if ($fqn === 'self' || $fqn === 'static') {
                // Resolve self and static keywords to the containing class
                // (This is not 100% correct for static but better than nothing)
                if (!$scope->currentClassLikeVariable) {
                    return;
                }
                $fqn = substr((string)$scope->currentClassLikeVariable->type->getFqsen(), 1);
            } else if ($fqn === 'parent') {
                // Resolve parent keyword to the base class FQN
                if ($scope->currentClassLikeVariable === null) {
                    return;
                }
                $classNode = $scope->currentClassLikeVariable->definitionNode;
                if (empty($classNode->classBaseClause)
                    || !$classNode->classBaseClause->baseClass instanceof Node\QualifiedName
                ) {
                    return;
                }
                $fqn = $scope->getResolvedName($classNode->classBaseClause->baseClass);
                if (!$fqn) {
                    return;
                }
            }

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
     * @return Definition[]
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
