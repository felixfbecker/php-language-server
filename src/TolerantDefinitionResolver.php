<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\TolerantSymbolInformation;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use phpDocumentor\Reflection\{
    DocBlock, DocBlockFactory, Types, Type, Fqsen, TypeResolver
};
use LanguageServer\Protocol\SymbolInformation;
use LanguageServer\Index\ReadableIndex;
use Microsoft\PhpParser as Tolerant;

class TolerantDefinitionResolver implements DefinitionResolverInterface
{
    /**
     * @var \LanguageServer\Index\ReadableIndex
     */
    protected $index;

    /**
     * @var \phpDocumentor\Reflection\TypeResolver
     */
    private $typeResolver;

    /**
     * @var \PhpParser\PrettyPrinterAbstract
     */
    private $prettyPrinter;

    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * @param ReadableIndex $index
     */
    public function __construct(ReadableIndex $index)
    {
        $this->index = $index;
        $this->typeResolver = new TypeResolver;
        $this->prettyPrinter = new PrettyPrinter;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * Builds the declaration line for a given node.
     *
     *
     * @param Tolerant\Node $node
     * @return string
     */
    public function getDeclarationLineFromNode($node): string
    {
        // TODO Tolerant\Node\Statement\FunctionStaticDeclaration::class

        // we should have a better way of determining whether something is a property or constant
        // If part of a declaration list -> get the parent declaration
        if (
            // PropertyDeclaration // public $a, $b, $c;
            $node instanceof Tolerant\Node\Expression\Variable &&
            ($propertyDeclaration = $node->getFirstAncestor(Tolerant\Node\PropertyDeclaration::class)) !== null
        ) {
            $defLine = $propertyDeclaration->getText();
            $defLineStart = $propertyDeclaration->getStart();

            $defLine = \substr_replace(
                $defLine,
                $node->getFullText(),
                $propertyDeclaration->propertyElements->getFullStart() - $defLineStart,
                $propertyDeclaration->propertyElements->getFullWidth()
            );
        } elseif (
            // ClassConstDeclaration or ConstDeclaration // const A = 1, B = 2;
            $node instanceof Tolerant\Node\ConstElement &&
            ($constDeclaration = $node->getFirstAncestor(Tolerant\Node\Statement\ConstDeclaration::class, Tolerant\Node\ClassConstDeclaration::class))
        ) {
            $defLine = $constDeclaration->getText();
            $defLineStart = $constDeclaration->getStart();

            $defLine = \substr_replace(
                $defLine,
                $node->getFullText(),
                $constDeclaration->constElements->getFullStart() - $defLineStart,
                $constDeclaration->constElements->getFullWidth()
            );
        }

        // Get the current node
        else {
            $defLine = $node->getText();
        }

        $defLine = \strtok($defLine, "\n");
        $defLine = \strtok($defLine, "\r");

        return $defLine;
    }

    /**
     * Gets the documentation string for a node, if it has one
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    public function getDocumentationFromNode($node)
    {
        // For properties and constants, set the node to the declaration node, rather than the individual property.
        // This is because they get defined as part of a list.
        $constOrPropertyDeclaration = $node->getFirstAncestor(
            Tolerant\Node\PropertyDeclaration::class,
            Tolerant\Node\Statement\ConstDeclaration::class,
            Tolerant\Node\ClassConstDeclaration::class
        );
        if ($constOrPropertyDeclaration !== null) {
            $node = $constOrPropertyDeclaration;
        }

        // For parameters, parse the documentation to get the parameter tag.
        if ($node instanceof Tolerant\Node\Parameter) {
            $functionLikeDeclaration = $this->getFunctionLikeDeclarationFromParameter($node);
            $variableName = substr($node->variableName->getText($node->getFileContents()), 1);
            $docBlock = $this->getDocBlock($functionLikeDeclaration);

            if ($docBlock !== null) {
                $parameterDocBlockTag = $this->getDocBlockTagForParameter($docBlock, $variableName);
                return $parameterDocBlockTag !== null ? $parameterDocBlockTag->getDescription()->render() : null;

            }
        }
        // for everything else, get the doc block summary corresponding to the current node.
        else {
            $docBlock = $this->getDocBlock($node);
            if ($docBlock !== null) {
                return $docBlock->getSummary();
            }
        }
    }

    function getDocBlock(Tolerant\Node $node) {
        // TODO make more efficient
        $namespaceDefinition = $node->getNamespaceDefinition();
        $context = null;
        if ($namespaceDefinition !== null && $namespaceDefinition->name !== null) {
            $name = (string)$namespaceDefinition->name->getNamespacedName();
            list($namespaceImportTable,,) = $namespaceDefinition->name->getImportTablesForCurrentScope($namespaceDefinition);
            foreach ($namespaceImportTable as $alias=>$name) {
                $namespaceImportTable[$alias] = (string)$name;
            }
            $context = new Types\Context($name, $namespaceImportTable);
        }

        $docCommentText = $node->getDocCommentText();
        return $docCommentText !== null ? $this->docBlockFactory->create($docCommentText, $context) : null;
    }

    /**
     * Create a Definition for a definition node
     *
     * @param Tolerant\Node $node
     * @param string $fqn
     * @return Definition
     */
    public function createDefinitionFromNode($node, string $fqn = null): Definition
    {
        $def = new Definition;

        // this determines whether the suggestion will show after "new"
        // TODO name
        $def->isClass = $node instanceof Tolerant\Node\Statement\ClassDeclaration;

        $def->isGlobal = (
            $node instanceof Tolerant\Node\Statement\InterfaceDeclaration
            || $node instanceof Tolerant\Node\Statement\ClassDeclaration
            || $node instanceof Tolerant\Node\Statement\TraitDeclaration

            // TODO namespaces?
            || $node instanceof Tolerant\Node\Statement\NamespaceDefinition && $node->name !== null

            || $node instanceof Tolerant\Node\Statement\FunctionDeclaration

            || $node instanceof Tolerant\Node\Statement\ConstDeclaration
            // || $node instanceof Tolerant\Node\ClassConstDeclaration
        );

        $def->isStatic = (
            ($node instanceof Tolerant\Node\MethodDeclaration && $node->isStatic())
            || ($node instanceof Tolerant\Node\Expression\Variable &&
                ($propertyDeclaration = $node->getFirstAncestor(Tolerant\Node\PropertyDeclaration::class)) !== null &&
                $propertyDeclaration->isStatic())
        );
        $def->fqn = $fqn;
        if ($node instanceof Tolerant\Node\Statement\ClassDeclaration) {
            $def->extends = [];
            if ($node->classBaseClause !== null && $node->classBaseClause->baseClass !== null) {
                $def->extends[] = (string)$node->classBaseClause->baseClass->getResolvedName();
            }
            // TODO what about class interfaces
        } else if ($node instanceof Tolerant\Node\Statement\InterfaceDeclaration) {
            $def->extends = [];
            if ($node->interfaceBaseClause !== null && $node->interfaceBaseClause->interfaceNameList !== null) {
                foreach ($node->interfaceBaseClause->interfaceNameList->getValues() as $n) {
                    $def->extends[] = (string)$n;
                }
            }
        }
        $def->symbolInformation = TolerantSymbolInformation::fromNode($node, $fqn);
        $def->type = $this->getTypeFromNode($node); //TODO
        $def->declarationLine = $this->getDeclarationLineFromNode($node);
        $def->documentation = $this->getDocumentationFromNode($node);
        return $def;
    }

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Tolerant\Node $node Any reference node
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition($node)
    {
        $parent = $node->getParent();
        // Variables are not indexed globally, as they stay in the file scope anyway
        if ($node instanceof Tolerant\Node\Expression\Variable && !($parent instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression)) {
            // Resolve $this
            if ($node->getName() === 'this' && $fqn = $this->getContainingClassFqn($node)) {
                return $this->index->getDefinition($fqn, false);
            }
            // TODO running throug thid for class constants or properties

            // Resolve the variable to a definition node (assignment, param or closure use)
            $defNode = $this->resolveVariableToNode($node);
            if ($defNode === null) {
                return null;
            }
            return $this->createDefinitionFromNode($defNode);
        }
        // Other references are references to a global symbol that have an FQN
        // Find out the FQN
        $fqn = $this->resolveReferenceNodeToFqn($node);
        if ($fqn === null) {
            return null;
        }
        // If the node is a function or constant, it could be namespaced, but PHP falls back to global
        // http://php.net/manual/en/language.namespaces.fallback.php
        $globalFallback = $this->isConstantFetch($node) || $node->getFirstAncestor(Tolerant\Node\Expression\CallExpression::class) !== null;
        // Return the Definition object from the index index
        return $this->index->getDefinition($fqn, $globalFallback);
    }

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     *
     * @param Node $node
     * @return string|null
     */
    public function resolveReferenceNodeToFqn($node) {
        $parent = $node->getParent();
// TODO all name tokens should be a part of a node
        if ($node instanceof Tolerant\Node\QualifiedName) {
            // For extends, implements, type hints and classes of classes of static calls use the name directly
            $name = $node->getResolvedName();

            if (($useClause = $node->getFirstAncestor(Tolerant\Node\NamespaceUseGroupClause::class, Tolerant\Node\Statement\NamespaceUseDeclaration::class)) !== null) {
                $name = (string)($name ?? $node->getText());
                if ($useClause instanceof Tolerant\Node\NamespaceUseGroupClause) {
                    $prefix = $useClause->parent->parent->namespaceName;
                    $prefix = $prefix === null ? "" : $prefix->getText();

                    $name = $prefix . "\\" . $name;

                    if ($useClause->functionOrConst === null) {
                        $useClause = $node->getFirstAncestor(Tolerant\Node\Statement\NamespaceUseDeclaration::class);
                    }
                }

                if ($useClause->functionOrConst->kind === Tolerant\TokenKind::FunctionKeyword) {
                    $name .= '()';
                }
            }
            else {
                $name = (string)($name ?? (string)$node->getNamespacedName());
            }

            if ($node->parent instanceof Tolerant\Node\Expression\CallExpression) {
                $name .= '()';
            }
// does this work for function calls?
            return $name;
        }
        /*elseif ($node instanceof Tolerant\Node\Expression\CallExpression || ($node = $node->getFirstAncestor(Tolerant\Node\Expression\CallExpression::class)) !== null) {
            if ($node->callableExpression instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression) {
                $qualifier = $node->callableExpression->scopeResolutionQualifier;
                if ($qualifier instanceof Tolerant\Token) {
                    // resolve this/self/parent
                } elseif ($qualifier instanceof Tolerant\Node\QualifiedName) {
                    $name = $qualifier->getResolvedName() ?? $qualifier->getNamespacedName();
                    $name .= "::";
                    $memberName = $node->callableExpression->memberName;
                    if ($memberName instanceof Tolerant\Token) {
                        $name .= $memberName->getText($node->getFileContents());
                    } elseif ($memberName instanceof Tolerant\Node\Expression\Variable) {
                        $name .= $memberName->getText();
                    } else {
                        return null;
                    }
                    $name .= "()";
                    return $name;
                }
            }
        }*/

        else if (($node instanceof Tolerant\Node\Expression\CallExpression &&
                ($access = $node->callableExpression) instanceof Tolerant\Node\Expression\MemberAccessExpression) || (
                ($access = $node) instanceof Tolerant\Node\Expression\MemberAccessExpression
            )) {
            if ($access->memberName instanceof Tolerant\Node\Expression) {
                // Cannot get definition if right-hand side is expression
                return null;
            }
            // Get the type of the left-hand expression
            $varType = $this->resolveExpressionNodeToType($access->dereferencableExpression);
//            var_dump($varType);
            if ($varType instanceof Types\Compound) {
                // For compound types, use the first FQN we find
                // (popular use case is ClassName|null)
                for ($i = 0; $t = $varType->get($i); $i++) {
                    if (
                        $t instanceof Types\This
                        || $t instanceof Types\Object_
                        || $t instanceof Types\Static_
                        || $t instanceof Types\Self_
                    ) {
                        $varType = $t;
                        break;
                    }
                }
            }
            if (
                $varType instanceof Types\This
                || $varType instanceof Types\Static_
                || $varType instanceof Types\Self_
            ) {
                // $this/static/self is resolved to the containing class
                $classFqn = self::getContainingClassFqn($node);
            } else if (!($varType instanceof Types\Object_) || $varType->getFqsen() === null) {
                // Left-hand expression could not be resolved to a class
                return null;
            } else {
//                var_dump("AAAHHHHH");
                $classFqn = substr((string)$varType->getFqsen(), 1);

                // TODO
//                $classFqn = $node->getNamespaceDefinition()->name->getNamespacedName() . (string)$varType->getFqsen();
                var_dump($classFqn);
            }
            $memberSuffix = '->' . (string)($access->memberName->getText() ?? $access->memberName->getText($node->getFileContents()));
            if ($node instanceof Tolerant\Node\Expression\CallExpression) {
                $memberSuffix .= '()';
            }
            // Find the right class that implements the member
            $implementorFqns = [$classFqn];

            while ($implementorFqn = array_shift($implementorFqns)) {
//                    var_dump($implementorFqn . $memberSuffix);
                // If the member FQN exists, return it
                if ($this->index->getDefinition($implementorFqn . $memberSuffix)) {

                    return $implementorFqn . $memberSuffix;
                }
                // Get Definition of implementor class
                $implementorDef = $this->index->getDefinition($implementorFqn);
                // If it doesn't exist, return the initial guess
                if ($implementorDef === null) {
                    break;
                }
                // Repeat for parent class
                if ($implementorDef->extends) {
                    foreach ($implementorDef->extends as $extends) {
                        $implementorFqns[] = $extends;
                    }
                }
            }

//            var_dump("SUFFIX");
//            var_dump($classFqn);
//            var_dump($memberSuffix);
            return $classFqn . $memberSuffix;
        }
        else if ($parent instanceof Tolerant\Node\Expression\CallExpression && $node instanceof Tolerant\Node\QualifiedName) {
            if ($parent->name instanceof Node\Expr) {
                return null;
            }
            $name = (string)($parent->getNamespacedName());
        }
        else if ($this->isConstantFetch($node)) {
            $name = (string)($node->getNamespacedName());
        }
        else if (
            ($scoped = $node) instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression ||
            ($scoped = $node->parent) instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression
            || ($node->parent instanceof Tolerant\Node\Expression\CallExpression && ($scoped = $node->parent->callableExpression) instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression)
        ) {
//            if ($scoped->memberName instanceof Tolerant\Node\Expression) {
                // Cannot get definition of dynamic names
//                return null;
//            }
            $className = $scoped->scopeResolutionQualifier->getText();
            if ($className === 'self' || $className === 'static' || $className === 'parent') {
                // self and static are resolved to the containing class
                $classNode = $node->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class);
                if ($classNode === null) {
                    return null;
                }
                if ($className === 'parent') {
                    // parent is resolved to the parent class
                    if (!isset($node->extends)) {
                        return null;
                    }
                    $className = (string)$classNode->extends->getResolvedName();
                } else {
                    $className = (string)$classNode->getNamespacedName();
                }
            } elseif ($scoped->scopeResolutionQualifier instanceof Tolerant\Node\QualifiedName) {
                $className = $scoped->scopeResolutionQualifier->getResolvedName();
            }
            if ($scoped->memberName instanceof Tolerant\Node\Expression\Variable) {
                $name = (string)$className . '::$' . $scoped->memberName->getName();
            } else {
                $name = (string)$className . '::' . $scoped->memberName->getText($node->getFileContents());
            }

            if ($scoped->parent instanceof Tolerant\Node\Expression\CallExpression) {
                $name .= '()';
            }
            return $name;
        }
        else {
            return null;
        }
        if (!isset($name)) {
            return null;
        }
        if (
            $node instanceof Tolerant\Node\Expression\CallExpression
        ) {
            $name .= '()';
        }
        return $name;
    }

    private function isConstantFetch(Tolerant\Node $node) : bool {
        return
            $node instanceof Tolerant\Node\QualifiedName &&
            ($node->parent instanceof Tolerant\Node\Statement\ExpressionStatement || $node->parent instanceof Tolerant\Node\Expression || $node->parent instanceof Tolerant\Node\DelimitedList\ExpressionList) &&
            !(
                $node->parent instanceof Tolerant\Node\Expression\MemberAccessExpression || $node->parent instanceof Tolerant\Node\Expression\CallExpression ||
                $node->parent instanceof Tolerant\Node\Expression\ObjectCreationExpression ||
                $node->parent instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression || $node->parent instanceof Tolerant\Node\Expression\AnonymousFunctionCreationExpression ||
                ($node->parent instanceof Tolerant\Node\Expression\BinaryExpression && $node->parent->operator->kind === Tolerant\TokenKind::InstanceOfKeyword)
            );
    }

    /**
     * Returns FQN of the class a node is contained in
     * Returns null if the class is anonymous or the node is not contained in a class
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    private static function getContainingClassFqn(Tolerant\Node $node)
    {
        $classNode = $node->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class);
        if ($classNode === null) {
            return null;
        }
        return (string)$classNode->getNamespacedName();
    }

    /**
     * Returns the assignment or parameter node where a variable was defined
     *
     * @param Node\Expr\Variable|Node\Expr\ClosureUse $var The variable access
     * @return Node\Expr\Assign|Node\Expr\AssignOp|Node\Param|Node\Expr\ClosureUse|null
     */
    public function resolveVariableToNode(Tolerant\Node $var)
    {
        $n = $var;
        // When a use is passed, start outside the closure to not return immediately
        // Use variable vs variable parsing?
        if ($var instanceof Tolerant\Node\UseVariableName) {
            $n = $var->getFirstAncestor(Tolerant\Node\Expression\AnonymousFunctionCreationExpression::class);
            $name = $var->getName();
        } else if ($var instanceof Tolerant\Node\Expression\Variable || $var instanceof Tolerant\Node\Parameter) {
            $name = $var->getName();
        } else {
            throw new \InvalidArgumentException('$var must be Variable, Param or ClosureUse, not ' . get_class($var));
        }
        // Traverse the AST up
        do {
            // If a function is met, check the parameters and use statements
            if (self::isFunctionLike($n)) {
                if ($n->parameters !== null) {

                    foreach ($n->parameters->getElements() as $param) {
                        if ($param->getName() === $name) {
                            return $param;
                        }
                    }
                }
                // If it is a closure, also check use statements
                if ($n instanceof Tolerant\Node\Expression\AnonymousFunctionCreationExpression) {
                    foreach ($n->anonymousFunctionUseClause->useVariableNameList->getElements() as $use) {
                        if ($use->getName() === $name) {
                            return $use;
                        }
                    }
                }
                break;
            }
            // Check each previous sibling node for a variable assignment to that variable
            while ($n->getPreviousSibling() && $n = $n->getPreviousSibling()) {
                if ($n instanceof Tolerant\Node\Statement\ExpressionStatement) {
                    $n = $n->expression;
                }
                if (
                    ($n instanceof Tolerant\Node\Expression\AssignmentExpression && $n->operator->kind === Tolerant\TokenKind::EqualsToken)
                    && $n->leftOperand instanceof Tolerant\Node\Expression\Variable && $n->leftOperand->getName() === $name
                ) {
                    return $n;
                }
            }
        } while (isset($n) && $n = $n->getParent());
        // Return null if nothing was found
        return null;
    }

    function getFunctionLikeDeclarationFromParameter(Tolerant\Node $node) {
        return $node->getFirstAncestor(
            Tolerant\Node\Statement\FunctionDeclaration::class,
            Tolerant\Node\MethodDeclaration::class,
            Tolerant\Node\Expression\AnonymousFunctionCreationExpression::class
        );
    }

    static function isFunctionLike(Tolerant\Node $node) {
        return
            $node instanceof Tolerant\Node\Statement\FunctionDeclaration ||
            $node instanceof Tolerant\Node\MethodDeclaration ||
            $node instanceof Tolerant\Node\Expression\AnonymousFunctionCreationExpression;
    }

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed.
     *
     * @param \PhpParser\Node\Expr $expr
     * @return \phpDocumentor\Reflection\Type
     */
    public function resolveExpressionNodeToType($expr): Type
    {
        if ($expr instanceof Tolerant\Node\Expression\Variable || $expr instanceof Tolerant\Node\UseVariableName) {
            if ($expr instanceof Tolerant\Node\Expression\Variable && $expr->getName() === 'this') {
                return new Types\This;
            }
            // Find variable definition
            $defNode = $this->resolveVariableToNode($expr);
            if ($defNode instanceof Tolerant\Node\Expression) {
                return $this->resolveExpressionNodeToType($defNode);
            }
            if ($defNode instanceof Tolerant\Node\Parameter) {
                return $this->getTypeFromNode($defNode);
            }
        }
        if ($expr instanceof Tolerant\Node\Expression\CallExpression &&
            !($expr->callableExpression instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression ||
                $expr->callableExpression instanceof Tolerant\Node\Expression\MemberAccessExpression)) {

            // Find the function definition
            if ($expr->callableExpression instanceof Tolerant\Node\Expression) {
                // Cannot get type for dynamic function call
                return new Types\Mixed;
            }

            if ($expr->callableExpression instanceof Tolerant\Node\QualifiedName) {
                $fqn = $expr->callableExpression->getResolvedName() ?? $expr->callableExpression->getNamespacedName();
                $fqn .= '()';
                $def = $this->index->getDefinition($fqn, true);
                if ($def !== null) {
                    return $def->type;
                }
            }

            /*
            $isScopedPropertyAccess = $expr->callableExpression instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression;
                $prefix = $isScopedPropertyAccess ?
                $expr->callableExpression->scopeResolutionQualifier : $expr->callableExpression->dereferencableExpression;

            if ($prefix instanceof Tolerant\Node\QualifiedName) {
                $name = $prefix->getNamespacedName() ?? $prefix->getText();
            } elseif ($prefix instanceof Tolerant\Token) {
                // TODO DOES THIS EVER HAPPEN?
                $name = $prefix->getText($expr->getText());
            }

            if (isset($name)) {
                $memberNameText = $expr->callableExpression->memberName instanceof Node
                    ? $expr->callableExpression->memberName->getText() : $expr->callableExpression->memberName->getText($expr->getFileContents());
                $fqn = $name . ($isScopedPropertyAccess ? "::" : "->") . $memberNameText . "()";

                $def = $this->index->getDefinition($fqn, true);
                if ($def !== null) {
                    return $def->type;
                }
            }*/
        }
        if (strtolower((string)$expr->getText()) === 'true' || strtolower((string)$expr->getText()) === 'false') {
            return new Types\Boolean;
        }

        if ($this->isConstantFetch($expr)) {
            // Resolve constant
            $fqn = (string)$expr->getNamespacedName();
            $def = $this->index->getDefinition($fqn, true);
            if ($def !== null) {
                return $def->type;
            }
        }
        if (($expr instanceof Tolerant\Node\Expression\CallExpression &&
                ($access = $expr->callableExpression) instanceof Tolerant\Node\Expression\MemberAccessExpression)
            || ($access = $expr) instanceof Tolerant\Node\Expression\MemberAccessExpression) {
            if ($access->memberName instanceof Tolerant\Node\Expression) {
                return new Types\Mixed;
            }
            $var = $access->dereferencableExpression;

            var_dump("HERE!!!");
            // Resolve object
            $objType = $this->resolveExpressionNodeToType($var);
//            var_dump($objType);
            if (!($objType instanceof Types\Compound)) {
                $objType = new Types\Compound([$objType]);
            }
            for ($i = 0; $t = $objType->get($i); $i++) {
                if ($t instanceof Types\This) {
                    $classFqn = self::getContainingClassFqn($expr);
                    if ($classFqn === null) {
                        return new Types\Mixed;
                    }
                } else if (!($t instanceof Types\Object_) || $t->getFqsen() === null) {
                    return new Types\Mixed;
                } else {
                    $classFqn = substr((string)$t->getFqsen(), 1);
                    var_dump($classFqn);
                }
                $fqn = $classFqn . '->' . $access->memberName->getText($expr->getFileContents());
                if ($expr instanceof Tolerant\Node\Expression\CallExpression) {
                    $fqn .= '()';
                }
                var_dump($fqn);
//                var_dump($fqn);
                $def = $this->index->getDefinition($fqn);
                var_dump($def);
                if ($def !== null) {
                    return $def->type;
                }
            }
        }
        if (
            $expr instanceof Tolerant\Node\Expression\CallExpression && ($scopedAccess = $expr->callableExpression) instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression
            || ($scopedAccess = $expr) instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression
        ) {
            $classType = $this->resolveClassNameToType($scopedAccess->scopeResolutionQualifier);
//            var_dump($classType);
            if (!($classType instanceof Types\Object_) || $classType->getFqsen() === null /*|| $expr->name instanceof Tolerant\Node\Expression*/) {
                return new Types\Mixed;
            }
            $fqn = substr((string)$classType->getFqsen(), 1) . '::';
//            if ($expr instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression && $expr->memberName instanceof Tolerant\Node\Expression\Variable) {
//                $fqn .= '$';
//            }
            $fqn .= $scopedAccess->memberName->getText() ?? $scopedAccess->memberName->getText($expr->getFileContents()); // TODO is there a cleaner way to do this?
            if ($expr instanceof Tolerant\Node\Expression\CallExpression) {
                $fqn .= '()';
            }
//            var_dump($fqn);
            $def = $this->index->getDefinition($fqn);
            if ($def === null) {
                return new Types\Mixed;
            }
            return $def->type;
        }
        if ($expr instanceof Tolerant\Node\Expression\ObjectCreationExpression) {
            return $this->resolveClassNameToType($expr->classTypeDesignator);
        }
        if ($expr instanceof Tolerant\Node\Expression\CloneExpression) {
            return $this->resolveExpressionNodeToType($expr->expression);
        }
        if ($expr instanceof Tolerant\Node\Expression\AssignmentExpression) {
            return $this->resolveExpressionNodeToType($expr->rightOperand);
        }
        if ($expr instanceof Tolerant\Node\Expression\TernaryExpression) {
            // ?:
            if ($expr->ifExpression === null) {
                return new Types\Compound([
                    $this->resolveExpressionNodeToType($expr->condition), // why?
                    $this->resolveExpressionNodeToType($expr->elseExpression)
                ]);
            }
            // Ternary is a compound of the two possible values
            return new Types\Compound([
                $this->resolveExpressionNodeToType($expr->ifExpression),
                $this->resolveExpressionNodeToType($expr->elseExpression)
            ]);
        }
        if ($expr instanceof Tolerant\Node\Expression\BinaryExpression && $expr->operator->kind === Tolerant\TokenKind::QuestionQuestionToken) {
            // ?? operator
            return new Types\Compound([
                $this->resolveExpressionNodeToType($expr->leftOperand),
                $this->resolveExpressionNodeToType($expr->rightOperand)
            ]);
        }
        if (
            $this->isBooleanExpression($expr)

            || ($expr instanceof Tolerant\Node\Expression\CastExpression && $expr->castType->kind === Tolerant\TokenKind::BoolCastToken)
            || ($expr instanceof Tolerant\Node\Expression\UnaryOpExpression && $expr->operator->kind === Tolerant\TokenKind::ExclamationToken)
            || $expr instanceof Tolerant\Node\Expression\EmptyIntrinsicExpression
            || $expr instanceof Tolerant\Node\Expression\IssetIntrinsicExpression
        ) {
            return new Types\Boolean;
        }
        if (
            ($expr instanceof Tolerant\Node\Expression\BinaryExpression &&
                ($expr->operator->kind === Tolerant\TokenKind::DotToken || $expr->operator->kind === Tolerant\TokenKind::DotEqualsToken)) ||
            $expr instanceof Tolerant\Node\StringLiteral ||
            ($expr instanceof Tolerant\Node\Expression\CastExpression && $expr->castType->kind === Tolerant\TokenKind::StringCastToken)

            // TODO
//            || $expr instanceof Node\Expr\Scalar\String_
//            || $expr instanceof Node\Expr\Scalar\Encapsed
//            || $expr instanceof Node\Expr\Scalar\EncapsedStringPart
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Class_
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Dir
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Function_
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Method
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Namespace_
//            || $expr instanceof Node\Expr\Scalar\MagicConst\Trait_
        ) {
            return new Types\String_;
        }
        if (
            $expr instanceof Tolerant\Node\Expression\BinaryExpression &&
            ($operator = $expr->operator->kind)
            && ($operator === Tolerant\TokenKind::PlusToken ||
                $operator === Tolerant\TokenKind::AsteriskAsteriskToken ||
                $operator === Tolerant\TokenKind::AsteriskToken ||
                $operator === Tolerant\TokenKind::MinusToken ||
                $operator === Tolerant\TokenKind::AsteriskEqualsToken||
                $operator === Tolerant\TokenKind::AsteriskAsteriskEqualsToken ||
                $operator === Tolerant\TokenKind::MinusEqualsToken ||
                $operator === Tolerant\TokenKind::PlusEqualsToken // TODO - this should be a type of assigment expression
            )
        ) {
            if (
                $this->resolveExpressionNodeToType($expr->leftOperand) instanceof Types\Integer_
                && $this->resolveExpressionNodeToType($expr->rightOperand) instanceof Types\Integer_
            ) {
                return new Types\Integer;
            }
            return new Types\Float_;
        }
        if (
            // TODO better naming
            ($expr instanceof Tolerant\Node\NumericLiteral && $expr->children->kind === Tolerant\TokenKind::IntegerLiteralToken) ||
            $expr instanceof Tolerant\Node\Expression\BinaryExpression && (
                ($operator = $expr->operator->kind)
                && ($operator === Tolerant\TokenKind::LessThanEqualsGreaterThanToken ||
                    $operator === Tolerant\TokenKind::AmpersandToken ||
                    $operator === Tolerant\TokenKind::CaretToken ||
                    $operator === Tolerant\TokenKind::BarToken)
            )
        ) {
            return new Types\Integer;
        }
        if (
            $expr instanceof Tolerant\Node\NumericLiteral && $expr->children->kind === Tolerant\TokenKind::FloatingLiteralToken
            ||
            ($expr instanceof Tolerant\Node\Expression\CastExpression && $expr->castType->kind === Tolerant\TokenKind::DoubleCastToken)
        ) {
            return new Types\Float_;
        }
        if ($expr instanceof Tolerant\Node\Expression\ArrayCreationExpression) {
            $valueTypes = [];
            $keyTypes = [];
            foreach ($expr->arrayElements->getElements() as $item) {
                $valueTypes[] = $this->resolveExpressionNodeToType($item->value);
                $keyTypes[] = $item->key ? $this->resolveExpressionNodeToType($item->key) : new Types\Integer;
            }
            $valueTypes = array_unique($keyTypes);
            $keyTypes = array_unique($keyTypes);
            if (empty($valueTypes)) {
                $valueType = null;
            } else if (count($valueTypes) === 1) {
                $valueType = $valueTypes[0];
            } else {
                $valueType = new Types\Compound($valueTypes);
            }
            if (empty($keyTypes)) {
                $keyType = null;
            } else if (count($keyTypes) === 1) {
                $keyType = $keyTypes[0];
            } else {
                $keyType = new Types\Compound($keyTypes);
            }
            return new Types\Array_($valueType, $keyType);
        }
        if ($expr instanceof Tolerant\Node\Expression\SubscriptExpression) {
//            var_dump("SUBSCRIPT");
            $varType = $this->resolveExpressionNodeToType($expr->postfixExpression);
//            var_dump($varType);
            if (!($varType instanceof Types\Array_)) {
                return new Types\Mixed;
            }
            return $varType->getValueType();
        }
        if ($expr instanceof Tolerant\Node\Expression\ScriptInclusionExpression) {
            // TODO: resolve path to PhpDocument and find return statement
            return new Types\Mixed;
        }
        return new Types\Mixed;
    }

    private function isBooleanExpression($expression) : bool {
        if (!($expression instanceof Tolerant\Node\Expression\BinaryExpression)) {
            return false;
        }
        switch ($expression->operator->kind) {
            case Tolerant\TokenKind::InstanceOfKeyword:
            case Tolerant\TokenKind::GreaterThanToken:
            case Tolerant\TokenKind::GreaterThanEqualsToken:
            case Tolerant\TokenKind::LessThanToken:
            case Tolerant\TokenKind::LessThanEqualsToken:
            case Tolerant\TokenKind::AndKeyword:
            case Tolerant\TokenKind::AmpersandAmpersandToken:
            case Tolerant\TokenKind::LessThanEqualsGreaterThanToken:
            case Tolerant\TokenKind::OrKeyword:
            case Tolerant\TokenKind::BarBarToken:
            case Tolerant\TokenKind::XorKeyword:
            case Tolerant\TokenKind::ExclamationEqualsEqualsToken:
            case Tolerant\TokenKind::ExclamationEqualsToken:
            case Tolerant\TokenKind::CaretToken:
            case Tolerant\TokenKind::EqualsEqualsEqualsToken:
            case Tolerant\TokenKind::EqualsToken:
                return true;
        }
        return false;
    }

    /**
     * Takes any class name node (from a static method call, or new node) and returns a Type object
     * Resolves keywords like self, static and parent
     *
     * @param Tolerant\Node || Tolerant\Token $class
     * @return Type
     */
    public function resolveClassNameToType($class): Type
    {
        if ($class instanceof Tolerant\Node\Expression) {
            return new Types\Mixed;
        }
        if ($class instanceof Tolerant\Token && $class->kind === Tolerant\TokenKind::ClassKeyword) {
            // Anonymous class
            return new Types\Object_;
        }
        $className = (string)$class->getResolvedName();
        if ($className === 'static') {
            return new Types\Static_;
        }
        if ($className === 'self' || $className === 'parent') {
            $classNode = $class->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class);
            if ($className === 'parent') {
                if ($classNode === null || $classNode->classBaseClause === null || $classNode->classBaseClause->baseClass === null) {
                    return new Types\Object_;
                }
                // parent is resolved to the parent class
                $classFqn = (string)$classNode->classBaseClause->baseClass->getResolvedName();
            } else {
                if ($classNode === null) {
                    return new Types\Self_;
                }
                // self is resolved to the containing class
                $classFqn = (string)$classNode->namespacedName;
            }
            return new Types\Object_(new Fqsen('\\' . $classFqn));
        }
        return new Types\Object_(new Fqsen('\\' . $className));
    }

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
     * @param Tolerant\Node $node
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function getTypeFromNode($node)
    {
        // For parameters, get the type of the parameter [first from doc block, then from param type]
        if ($node instanceof Tolerant\Node\Parameter) {
            // Parameters
            // Get the doc block for the the function call
            $functionLikeDeclaration = $this->getFunctionLikeDeclarationFromParameter($node);
            $variableName = $node->variableName->getText($node->getFileContents());
            $docBlock = $this->getDocBlock($functionLikeDeclaration);

            if ($docBlock !== null) {
                $parameterDocBlockTag = $this->getDocBlockTagForParameter($docBlock, $variableName);
                if ($parameterDocBlockTag !== null && $parameterDocBlockTag->getType() !== null) {
                    return $parameterDocBlockTag->getType();
                }
            }

            if ($node->typeDeclaration !== null) {
                // Use PHP7 return type hint
                if ($node->typeDeclaration instanceof Tolerant\Token) {
                    // Resolve a string like "bool" to a type object
                    $type = $this->typeResolver->resolve($node->typeDeclaration->getText($node->getFileContents()));
                } else {
                    $type = new Types\Object_(new Fqsen('\\' . (string)$node->typeDeclaration->getResolvedName()));
                }
            }
            if ($node->default !== null) {
                $defaultType = $this->resolveExpressionNodeToType($node->default);
                if (isset($type) && !is_a($type, get_class($defaultType))) {
                    $type = new Types\Compound([$type, $defaultType]);
                } else {
                    $type = $defaultType;
                }
            }
            return $type ?? new Types\Mixed;
        }
        // for functions and methods, get the return type [first from doc block, then from return type]
        if ($this->isFunctionLike($node)) {
            // Functions/methods
            $docBlock = $this->getDocBlock($node);
            if (
                $docBlock !== null
                && !empty($returnTags = $docBlock->getTagsByName('return'))
                && $returnTags[0]->getType() !== null
            ) {
                // Use @return tag
                return $returnTags[0]->getType();
            }
            if ($node->returnType !== null) {
                // Use PHP7 return type hint
                if ($node->returnType instanceof Tolerant\Token) {
                    // Resolve a string like "bool" to a type object
                    return $this->typeResolver->resolve($node->returnType->getText($node->getFileContents()));
                }
                return new Types\Object_(new Fqsen('\\' . (string)$node->returnType->getResolvedName()));
            }
            // Unknown return type
            return new Types\Mixed;
        }

        // for variables / assignments, get the documented type the assignment resolves to.
        if ($node instanceof Tolerant\Node\Expression\Variable) {
            $node = $node->getFirstAncestor(Tolerant\Node\Expression\AssignmentExpression::class) ?? $node;
        }
        if (
            ($declarationNode = $node->getFirstAncestor(
                Tolerant\Node\PropertyDeclaration::class,
                Tolerant\Node\Statement\ConstDeclaration::class,
                Tolerant\Node\ClassConstDeclaration::class)) !== null ||
            $node instanceof Tolerant\Node\Expression\AssignmentExpression)
        {
            $declarationNode = $declarationNode ?? $node;

            // Property, constant or variable
            // Use @var tag
            if (
                ($docBlock = $this->getDocBlock($declarationNode))
                && !empty($varTags = $docBlock->getTagsByName('var'))
                && ($type = $varTags[0]->getType())
            ) {
//                var_dump("BOOYAH");
//                var_dump($type);
                return $type;
            }
            // Resolve the expression
            if ($declarationNode instanceof Tolerant\Node\PropertyDeclaration) {
                // TODO should have default
                if (isset($node->rightOperand)) {
                    return $this->resolveExpressionNodeToType($node->rightOperand);
                }
            } else if ($node instanceof Tolerant\Node\ConstElement) {
                return $this->resolveExpressionNodeToType($node->assignment);
            } else if ($node instanceof Tolerant\Node\Expression\AssignmentExpression) {
                return $this->resolveExpressionNodeToType($node);
            }
            // TODO: read @property tags of class
            // TODO: Try to infer the type from default value / constant value
            // Unknown
            return new Types\Mixed;
        }
        return null;
    }

    /**
     * @param DocBlock $docBlock
     * @param $variableName
     * @return DocBlock\Tags\Param | null
     */
    private function getDocBlockTagForParameter($docBlock, $variableName) {
        $tags = $docBlock->getTagsByName('param');
        foreach ($tags as $tag) {
            if ($tag->getVariableName() === $variableName) {
                return $tag;
            }
        }
    }

    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    public static function getDefinedFqn($node)
    {
        $parent = $node->getParent();
        // Anonymous classes don't count as a definition
        // INPUT                    OUTPUT:
        // namespace A\B;
        // class C { }              A\B\C
        // interface C { }          A\B\C
        // trait C { }              A\B\C
        if (
            $node instanceof Tolerant\Node\Statement\ClassDeclaration ||
            $node instanceof Tolerant\Node\Statement\InterfaceDeclaration ||
            $node instanceof Tolerant\Node\Statement\TraitDeclaration
        ) {
            return (string) $node->getNamespacedName();
        }

        // INPUT                   OUTPUT:
        // namespace A\B;           A\B
        else if ($node instanceof Tolerant\Node\Statement\NamespaceDefinition && $node->name instanceof Tolerant\Node\QualifiedName) {
            return (string) $node->name;
        }
        // INPUT                   OUTPUT:
        // namespace A\B;
        // function a();           A\B\a();
        else if ($node instanceof Tolerant\Node\Statement\FunctionDeclaration) {
            // Function: use functionName() as the name
            return (string)$node->getNamespacedName() . '()';
        }
        // INPUT                        OUTPUT
        // namespace A\B;
        // class C {
        //   function a () {}           A\B\C::a()
        //   static function b() {}     A\B\C->b()
        // }
        else if ($node instanceof Tolerant\Node\MethodDeclaration) {
            // Class method: use ClassName->methodName() as name
            $class = $node->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class);
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return null;
            }
            if ($node->isStatic()) {
                return (string)$class->getNamespacedName() . '::' . $node->getName() . '()';
            } else {
                return (string)$class->getNamespacedName() . '->' . $node->getName() . '()';
            }
        }

        // INPUT                        OUTPUT
        // namespace A\B;
        // class C {
        //   static $a = 4, $b = 4      A\B\C::$a, A\B\C::$b
        //   $a = 4, $b = 4             A\B\C->$a, A\B\C->$b
        // }
        else if (
            $node instanceof Tolerant\Node\Expression\Variable &&
            ($propertyDeclaration = $node->getFirstAncestor(Tolerant\Node\PropertyDeclaration::class)) !== null &&
            ($classDeclaration = $node->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class)) !== null)
        {
            if ($propertyDeclaration->isStatic()) {
                // Static Property: use ClassName::$propertyName as name
                return (string)$classDeclaration->getNamespacedName() . '::$' . (string)$node->getName();
            } elseif (($name = $node->getName()) !== null) {
                // Instance Property: use ClassName->propertyName as name
                return (string)$classDeclaration->getNamespacedName() . '->' . $name;
            }
        }

        // INPUT                        OUTPUT
        // namespace A\B;
        // const FOO = 5;               A\B\FOO
        // class C {
        //   const $a, $b = 4           A\B\C::$a(), A\B\C::$b
        // }
        else if ($node instanceof Tolerant\Node\ConstElement) {
            $constDeclaration = $node->getFirstAncestor(Tolerant\Node\Statement\ConstDeclaration::class, Tolerant\Node\ClassConstDeclaration::class);
            if ($constDeclaration instanceof Tolerant\Node\Statement\ConstDeclaration) {
                // Basic constant: use CONSTANT_NAME as name
                return (string)$node->getNamespacedName();
            }
            if ($constDeclaration instanceof Tolerant\Node\ClassConstDeclaration) {
                // Class constant: use ClassName::CONSTANT_NAME as name
                $classDeclaration = $constDeclaration->getFirstAncestor(Tolerant\Node\Statement\ClassDeclaration::class);
                if (!isset($classDeclaration->name)) {
                    return null;
                }
                return (string)$classDeclaration->getNamespacedName() . '::' . $node->getName();
            }
        }
    }
}
