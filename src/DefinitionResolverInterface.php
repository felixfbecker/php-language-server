<?php

namespace LanguageServer;

use phpDocumentor\Reflection\Type;
use PhpParser\Node;

interface DefinitionResolverInterface
{
    /**
     * Builds the declaration line for a given node
     *
     * @param Node $node
     * @return string
     */
    public function getDeclarationLineFromNode(Node $node) : string;

    /**
     * Gets the documentation string for a node, if it has one
     *
     * @param Node $node
     * @return string|null
     */
    public function getDocumentationFromNode(Node $node);

    /**
     * Create a Definition for a definition node
     *
     * @param Node $node
     * @param string $fqn
     * @return Definition
     */
    public function createDefinitionFromNode(Node $node, string $fqn = null) : Definition;

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Node $node Any reference node
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition(Node $node);

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     *
     * @param Node $node
     * @return string|null
     */
    public function resolveReferenceNodeToFqn(Node $node);

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed.
     *
     * @param \PhpParser\Node\Expr $expr
     * @return \phpDocumentor\Reflection\Type
     */
    public function resolveExpressionNodeToType(Node\Expr $expr) : Type;

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
     * @param Node $node
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function getTypeFromNode(Node $node);
}