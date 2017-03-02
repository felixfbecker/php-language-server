<?php

namespace LanguageServer;

use phpDocumentor\Reflection\Type;
use PhpParser\Node;
use Microsoft\PhpParser as Tolerant;

interface DefinitionResolverInterface
{
    /**
     * Builds the declaration line for a given node
     *
     * @param Node | Tolerant\Node $node
     * @return string
     */
    public function getDeclarationLineFromNode($node) : string;

    /**
     * Gets the documentation string for a node, if it has one
     *
     * @param Node | Tolerant\Node $node
     * @return string|null
     */
    public function getDocumentationFromNode($node);

    /**
     * Create a Definition for a definition node
     *
     * @param Node | Tolerant\Node $node
     * @param string $fqn
     * @return Definition
     */
    public function createDefinitionFromNode($node, string $fqn = null) : Definition;

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Node | Tolerant\Node $node Any reference node
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition($node);

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     *
     * @param Node | Tolerant\Node $node
     * @return string|null
     */
    public function resolveReferenceNodeToFqn($node);

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed.
     *
     * @param \PhpParser\Node\Expr | Tolerant\Node $expr
     * @return \phpDocumentor\Reflection\Type
     */
    public function resolveExpressionNodeToType($expr) : Type;

    /**
     * Returns the type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For parameters, this is the type of the parameter.
     * For classes and interfaces, this is the class type (object).
     * For variables / assignments, this is the documented type or type the assignment resolves to.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     * Returns null if the node does not have a type.
     *
     * @param Node | Tolerant\Node $node
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function getTypeFromNode($node);

    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Node | Tolerant\Node $node
     * @return string|null
     */
    public static function getDefinedFqn($node);
}