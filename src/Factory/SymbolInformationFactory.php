<?php

namespace LanguageServer\Factory;

use LanguageServerProtocol\Location;
use LanguageServerProtocol\SymbolInformation;
use LanguageServerProtocol\SymbolKind;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\ResolvedName;
use LanguageServer\Factory\LocationFactory;

class SymbolInformationFactory
{
    /**
     * Converts a Node to a SymbolInformation
     *
     * @param Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return SymbolInformation|null
     */
    public static function fromNode($node, string $fqn = null)
    {
        $symbol = new SymbolInformation();
        if ($node instanceof Node\Statement\ClassDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } elseif ($node instanceof Node\Statement\TraitDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } elseif (\LanguageServer\ParserHelpers\isConstDefineExpression($node)) {
            // constants with define() like
            // define('TEST_DEFINE_CONSTANT', false);
            $symbol->kind = SymbolKind::CONSTANT;
            $symbol->name = $node->argumentExpressionList->children[0]->expression->getStringContentsText();
        } elseif ($node instanceof Node\Statement\InterfaceDeclaration) {
            $symbol->kind = SymbolKind::INTERFACE;
        } elseif ($node instanceof Node\Statement\NamespaceDefinition) {
            $symbol->kind = SymbolKind::NAMESPACE;
        } elseif ($node instanceof Node\Statement\FunctionDeclaration) {
            $symbol->kind = SymbolKind::FUNCTION;
        } elseif ($node instanceof Node\MethodDeclaration) {
            $nameText = $node->getName();
            if ($nameText === '__construct' || $nameText === '__destruct') {
                $symbol->kind = SymbolKind::CONSTRUCTOR;
            } else {
                $symbol->kind = SymbolKind::METHOD;
            }
        } elseif (
            $node instanceof Node\Expression\Variable &&
            $node->getFirstAncestor(Node\PropertyDeclaration::class) !== null
        ) {
            $symbol->kind = SymbolKind::PROPERTY;
        } elseif ($node instanceof Node\ConstElement) {
            $symbol->kind = SymbolKind::CONSTANT;
        } elseif (
            ($node instanceof Node\Expression\AssignmentExpression &&
                $node->leftOperand instanceof Node\Expression\Variable) ||
            $node instanceof Node\UseVariableName ||
            $node instanceof Node\Parameter
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
        } elseif ($node instanceof Node\UseVariableName) {
            $symbol->name = $node->getName();
        } elseif (isset($node->name)) {
            if ($node->name instanceof Node\QualifiedName) {
                $symbol->name = (string) ResolvedName::buildName($node->name->nameParts, $node->getFileContents());
            } else {
                $symbol->name = ltrim((string) $node->name->getText($node->getFileContents()), "$");
            }
        } elseif (isset($node->variableName)) {
            $symbol->name = $node->variableName->getText($node);
        } elseif (!isset($symbol->name)) {
            return null;
        }

        $symbol->location = LocationFactory::fromNode($node);
        if ($fqn !== null) {
            $parts = preg_split('/(::|->|\\\\)/', $fqn);
            array_pop($parts);
            $symbol->containerName = implode('\\', $parts);
        }
        return $symbol;
    }
}
