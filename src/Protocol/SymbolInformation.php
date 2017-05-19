<?php

namespace LanguageServer\Protocol;

use PhpParser\Node;
use Microsoft\PhpParser as Tolerant;
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
     * @param Tolerant\Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return SymbolInformation|null
     */
    public static function fromNode($node, string $fqn = null)
    {
        $symbol = new self;
        if ($node instanceof Tolerant\Node\Statement\ClassDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Tolerant\Node\Statement\TraitDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Tolerant\Node\Statement\InterfaceDeclaration) {
            $symbol->kind = SymbolKind::INTERFACE;
        } else if ($node instanceof Tolerant\Node\Statement\NamespaceDefinition) {
            $symbol->kind = SymbolKind::NAMESPACE;
        } else if ($node instanceof Tolerant\Node\Statement\FunctionDeclaration) {
            $symbol->kind = SymbolKind::FUNCTION;
        } else if ($node instanceof Tolerant\Node\MethodDeclaration) {
            $symbol->kind = SymbolKind::METHOD;
        } else if ($node instanceof Tolerant\Node\Expression\Variable && $node->getFirstAncestor(Tolerant\Node\PropertyDeclaration::class) !== null) {
            $symbol->kind = SymbolKind::PROPERTY;
        } else if ($node instanceof Tolerant\Node\ConstElement) {
            $symbol->kind = SymbolKind::CONSTANT;
        }

        else if (
            (
                ($node instanceof Tolerant\Node\Expression\AssignmentExpression)
                && $node->leftOperand instanceof Tolerant\Node\Expression\Variable
            )
            || $node instanceof Tolerant\Node\UseVariableName
            || $node instanceof Tolerant\Node\Parameter
        ) {
            $symbol->kind = SymbolKind::VARIABLE;
        } else {
            return null;
        }

        if ($node instanceof Tolerant\Node\Expression\AssignmentExpression) {
            if ($node->leftOperand instanceof Tolerant\Node\Expression\Variable) {
                $symbol->name = $node->leftOperand->getName();
            } elseif ($node->leftOperand instanceof Tolerant\Token) {
                $symbol->name = trim($node->leftOperand->getText($node->getFileContents()), "$");
            }

        } else if ($node instanceof Tolerant\Node\UseVariableName) {
            $symbol->name = $node->getName();
        } else if (isset($node->name)) {
            if ($node->name instanceof Tolerant\Node\QualifiedName) {
                $symbol->name = (string)Tolerant\ResolvedName::buildName($node->name->nameParts, $node->getFileContents());
            } else {
                $symbol->name = ltrim((string)$node->name->getText($node->getFileContents()), "$");
            }
        } else if (isset($node->variableName)) {
            $symbol->name = $node->variableName->getText($node);
        } else {
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
