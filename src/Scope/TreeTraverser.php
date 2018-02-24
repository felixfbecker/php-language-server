<?php
declare(strict_types=1);

namespace LanguageServer\Scope;

use LanguageServer\DefinitionResolver;
use Microsoft\PhpParser\ClassLike;
use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\Types;

/**
 * Traversers AST with Scope information.
 */
class TreeTraverser
{
    /**
     * Descend into the node being parsed. The default action.
     */
    public const ACTION_CONTINUE = 0;

    /**
     * Do not descend into the node being parsed. Traversal will continue after the node.
     */
    public const ACTION_SKIP = 1;

    /**
     * Stop parsing entirely. `traverse` will return immediately.
     */
    public const ACTION_END = 2;

    private $definitionResolver;

    public function __construct(DefinitionResolver $definitionResolver)
    {
        $this->definitionResolver = $definitionResolver;
    }

    /**
     * Calls visitor for each node or token with the node or token and the scope at that point.
     *
     * @param Node|Token $node Node or token to traverse.
     * @param callable $visitor function(Node|Token, Scope). May return one of the ACTION_ constants.
     */
    public function traverse($node, callable $visitor)
    {
        try {
            $this->traverseRecursive($node, $visitor, new Scope);
        } catch (TraversingEndedException $e) {
        }
    }

    private function traverseRecursive($node, callable $visitor, Scope $scope)
    {
        $visitorResult = $visitor($node, $scope);
        if ($visitorResult === self::ACTION_END) {
            throw new TraversingEndedException;
        }
        if (!$node instanceof Node || $visitorResult === self::ACTION_SKIP) {
            return;
        }

        foreach ($node::CHILD_NAMES as $childName) {
            $child = $node->$childName;

            if ($child === null) {
                continue;
            }

            $childScope = $this->getScopeInChild($node, $childName, $scope);

            if (\is_array($child)) {
                foreach ($child as $actualChild) {
                    $this->traverseRecursive($actualChild, $visitor, $childScope);
                }
            } else {
                $this->traverseRecursive($child, $visitor, $childScope);
            }
        }

        $this->modifyScopeAfterNode($node, $scope);
    }

    /**
     * E.g. in function body, gets the scope consisting of parameters and used names.
     *
     * @return Scope
     *   The new scope, or the same scope instance if the child does not has its own scope.
     */
    private function getScopeInChild(Node $node, string $childName, Scope $scope): Scope
    {
        if ($node instanceof FunctionLike
            && $childName === 'compoundStatementOrSemicolon'
            && $node->compoundStatementOrSemicolon instanceof Node\Statement\CompoundStatementNode
        ) {
            $childScope = new Scope;
            $childScope->currentClassLikeVariable = $scope->currentClassLikeVariable;
            $childScope->resolvedNameCache = $scope->resolvedNameCache;
            $isStatic = $node instanceof Node\MethodDeclaration ? $node->isStatic() : !empty($node->staticModifier);
            if (!$isStatic && isset($scope->variables['this'])) {
                $childScope->variables['this'] = $scope->variables['this'];
            }

            if ($node->parameters !== null) {
                foreach ($node->parameters->getElements() as $param) {
                    $childScope->variables[$param->getName()] = new Variable(
                        // Pass the child scope when getting parameters - the outer scope cannot affect
                        // any parameters of the function declaration.
                        $this->definitionResolver->getTypeFromNode($param, $childScope),
                        $param
                    );
                }
            }

            if ($node instanceof Node\Expression\AnonymousFunctionCreationExpression
                && $node->anonymousFunctionUseClause !== null
                && $node->anonymousFunctionUseClause->useVariableNameList !== null) {
                foreach ($node->anonymousFunctionUseClause->useVariableNameList->getElements() as $use) {
                    $name = $use->getName();
                    // Used variable in an anonymous function. Same as parent type, Mixed if not defined in parent.
                    $childScope->variables[$name] = new Variable(
                        isset($scope->variables[$name]) ? $scope->variables[$name]->type : new Types\Mixed_,
                        $use
                    );
                }
            }

            return $childScope;
        }

        if ($node instanceof ClassLike
            && (in_array($childName, ['classMembers', 'interfaceMembers','traitMembers'], true))
        ) {
            $childScope = new Scope;
            $childScope->resolvedNameCache = $scope->resolvedNameCache;
            $thisVar = new Variable(
                new Types\Object_(new Fqsen('\\' . (string)$node->getNamespacedName())),
                $node
            );
            $childScope->variables['this'] = $thisVar;
            $childScope->currentClassLikeVariable = $thisVar;
            return $childScope;
        }

        return $scope;
    }

    /**
     * Adds any variables declared by $node to $scope.
     *
     * Note that functions like extract and parse_str are not handled.
     *
     * @return void
     */
    private function modifyScopeAfterNode(Node $node, Scope $scope)
    {
        if ($node instanceof Expression\AssignmentExpression) {
            if ($node->operator->kind !== TokenKind::EqualsToken
                || !$node->leftOperand instanceof Expression\Variable
                || $node->rightOperand === null
                || $node->rightOperand instanceof MissingToken
            ) {
                return;
            }
            $scope->variables[$node->leftOperand->getName()] = new Variable(
                $this->definitionResolver->resolveExpressionNodeToType($node->rightOperand, $scope),
                $node
            );
        } else if (($node instanceof Node\ForeachValue || $node instanceof Node\ForeachKey)
            && $node->expression instanceof Node\Expression\Variable
        ) {
            $scope->variables[$node->expression->getName()] = new Variable(
                $this->definitionResolver->getTypeFromNode($node, $scope),
                $node
            );
        } else if ($node instanceof Statement\NamespaceDefinition) {
            // After a new namespace A\B;, the current alias table is flushed.
            $scope->clearResolvedNameCache();
        }


        // TODO: Handle use (&$x) when $x is not defined in scope.
        // TODO: Handle list(...) = $a;
        // TODO: Handle foreach ($a as list(...))
        // TODO: Handle unset($var)
        // TODO: Handle global $var
    }
}
