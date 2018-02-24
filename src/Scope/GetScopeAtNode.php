<?php
declare(strict_types=1);

namespace LanguageServer\Scope;

use LanguageServer\DefinitionResolver;
use Microsoft\PhpParser\ClassLike;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;

/**
 * Returns the scope at the start of $node.
 */
function getScopeAtNode(DefinitionResolver $definitionResolver, Node $targetNode): Scope
{
    /** @var SourceFileNode The source file. */
    $sourceFile = $targetNode->getRoot();
    /** @var FunctionDeclaration|null The first function declaration met, excluding anonymous functions. */
    $nearestFunctionDeclarationParent = $targetNode->getFirstAncestor(FunctionDeclaration::class);
    /** @var ClassLike|null The first class met. */
    $nearestClassLike = $targetNode->getFirstAncestor(ClassLike::class);

    $traverser = new TreeTraverser($definitionResolver);
    $resultScope = null;
    $traverser->traverse(
        $sourceFile,
        function (
            $nodeOrToken,
            Scope $scope
        ) use (
            &$resultScope,
            $targetNode,
            $nearestFunctionDeclarationParent,
            $nearestClassLike
        ): int {
            if ($nodeOrToken instanceof FunctionDeclaration && $nodeOrToken !== $nearestFunctionDeclarationParent) {
                // Skip function declarations which do not contain the target node.
                return TreeTraverser::ACTION_SKIP;
            }

            if ($nodeOrToken instanceof ClassLike && $nodeOrToken !== $nearestClassLike) {
                // Skip classes which are not the nearest parent class.
                return TreeTraverser::ACTION_SKIP;
            }

            if ($nodeOrToken === $targetNode) {
                $resultScope = $scope;
                return TreeTraverser::ACTION_END;
            }

            return TreeTraverser::ACTION_CONTINUE;
        }
    );

    return $resultScope;
}
