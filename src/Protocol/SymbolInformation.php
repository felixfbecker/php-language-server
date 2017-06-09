<?php

namespace LanguageServer\Protocol;

use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;
use Exception;

/**
 * Represents information about programming constructs like variables, classes,
 * interfaces etc.
 */
class SymbolInformation
{
    /**
     * The name of this symbol.
     *
     * @var string
     */
    public $name;

    /**
     * The kind of this symbol.
     *
     * @var int
     */
    public $kind;

    /**
     * The location of this symbol.
     *
     * @var Location
     */
    public $location;

    /**
     * The name of the symbol containing this symbol.
     *
     * @var string|null
     */
    public $containerName;

    /**
     * Converts a Node to a SymbolInformation
     *
     * @param Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return SymbolInformation|null
     */
    public static function fromNode($node, string $fqn = null)
    {
        $symbol = new self;
        if ($node instanceof Node\Statement\ClassDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Node\Statement\TraitDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if (\LanguageServer\ParserHelpers\isConstDefineExpression($node)) {
            // constants with define() like
            // define('TEST_DEFINE_CONSTANT', false);
            $symbol->kind = SymbolKind::CONSTANT;
            $symbol->name = $node->argumentExpressionList->children[0]->expression->getStringContentsText();
        } else if ($node instanceof Node\Statement\InterfaceDeclaration) {
            $symbol->kind = SymbolKind::INTERFACE;
        } else if ($node instanceof Node\Statement\NamespaceDefinition) {
            $symbol->kind = SymbolKind::NAMESPACE;
        } else if ($node instanceof Node\Statement\FunctionDeclaration) {
            $symbol->kind = SymbolKind::FUNCTION;
        } else if ($node instanceof Node\MethodDeclaration) {
            $nameText = $node->getName();
            if ($nameText === '__construct' || $nameText === '__destruct') {
                $symbol->kind = SymbolKind::CONSTRUCTOR;
            } else {
                $symbol->kind = SymbolKind::METHOD;
            }
        } else if ($node instanceof Node\Expression\Variable && $node->getFirstAncestor(Node\PropertyDeclaration::class) !== null) {
            $symbol->kind = SymbolKind::PROPERTY;
        } else if ($node instanceof Node\ConstElement) {
            $symbol->kind = SymbolKind::CONSTANT;
        } else if (
            (
                ($node instanceof Node\Expression\AssignmentExpression)
                && $node->leftOperand instanceof Node\Expression\Variable
            )
            || $node instanceof Node\UseVariableName
            || $node instanceof Node\Parameter
        ) {
            $symbol->kind = SymbolKind::VARIABLE;
        } else {
            return null;
        }

        if ($node instanceof Node\Expression\AssignmentExpression) {
            if ($node->leftOperand instanceof Node\Expression\Variable) {
                $symbol->name = $node->leftOperand->getName();
            } elseif ($node->leftOperand instanceof PhpParser\Token) {
                $symbol->name = trim($node->leftOperand->getText($node->getFileContents()), "$");
            }
        } else if ($node instanceof Node\UseVariableName) {
            $symbol->name = $node->getName();
        } else if (isset($node->name)) {
            if ($node->name instanceof Node\QualifiedName) {
                $symbol->name = (string)PhpParser\ResolvedName::buildName($node->name->nameParts, $node->getFileContents());
            } else {
                $symbol->name = ltrim((string)$node->name->getText($node->getFileContents()), "$");
            }
        } else if (isset($node->variableName)) {
            $symbol->name = $node->variableName->getText($node);
        } else if (!isset($symbol->name)) {
            return null;
        }

        $symbol->location = Location::fromNode($node);
        if ($fqn !== null) {
            $parts = preg_split('/(::|->|\\\\)/', $fqn);
            array_pop($parts);
            $symbol->containerName = implode('\\', $parts);
        }
        return $symbol;
    }

    /**
     * @param string $name
     * @param int $kind
     * @param Location $location
     * @param string $containerName
     */
    public function __construct($name = null, $kind = null, $location = null, $containerName = null)
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->location = $location;
        $this->containerName = $containerName;
    }
}
