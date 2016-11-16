<?php

namespace LanguageServer;

use PhpParser\Node;
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};
use LanguageServer\Protocol\SymbolInformation;
use Exception;
use function LanguageServer\Fqn\getDefinedFqn;

/**
 * Class used to represent definitions that can be referenced by an FQN
 */
class Definition
{
    /**
     * @var Protocol\SymbolInformation
     */
    public $symbolInformation;

    /**
     * The type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For any other declaration it will be null.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     *
     * @var \phpDocumentor\Type
     */
    public $type;

    /**
     * Returns the definition defined in a node
     *
     * @return self
     * @throws Exception If the node is not a declaration node
     */
    public static function fromNode(Node $node): self
    {
        $def = new self;
        $def->symbolInformation = SymbolInformation::fromNode($node, getDefinedFqn($node));
        $def->type = self::getTypeFromNode($node);
        return $def;
    }

    /**
     * Returns the type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For classes and interfaces, this is the class type (object).
     * Variables are not indexed for performance reasons.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     * Returns null if the node does not have a type.
     *
     * @param Node $node
     * @return \phpDocumentor\Type|null
     */
    public static function getTypeFromNode(Node $node)
    {
        if ($node instanceof Node\FunctionLike) {
            // Functions/methods
            $docBlock = $node->getAttribute('docBlock');
            if ($docBlock !== null && count($returnTags = $docBlock->getTagsByName('return')) > 0) {
                // Use @return tag
                return $returnTags[0]->getType();
            }
            if ($node->returnType !== null) {
                // Use PHP7 return type hint
                if (is_string($node->returnType)) {
                    // Resolve a string like "integer" to a type object
                    return (new TypeResolver)->resolve($node->returnType);
                }
                return new Types\Object_(new Fqsen('\\' . (string)$node->returnType));
            }
            // Unknown return type
            return new Types\Mixed;
        }
        if ($node instanceof Node\Stmt\PropertyProperty || $node instanceof Node\Const_) {
            // Property or constant
            $docBlock = $node->getAttribute('parentNode')->getAttribute('docBlock');
            if ($docBlock !== null && count($varTags = $docBlock->getTagsByName('var')) > 0) {
                // Use @var tag
                return $varTags[0]->getType();
            }
            // TODO: read @property tags of class
            // TODO: Try to infer the type from default value / constant value
            // Unknown
            return new Types\Mixed;
        }
        return null;
    }
}
