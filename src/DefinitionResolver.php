<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\SymbolInformation;
use LanguageServer\Scope\Scope;
use function LanguageServer\Scope\getScopeAtNode;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\FunctionLike;
use phpDocumentor\Reflection\{
    DocBlock, DocBlockFactory, Fqsen, Type, TypeResolver, Types
};

class DefinitionResolver
{
    /**
     * The current project index (for retrieving existing definitions)
     *
     * @var \LanguageServer\Index\ReadableIndex
     */
    private $index;

    /**
     * Resolves strings to a type object.
     *
     * @var \phpDocumentor\Reflection\TypeResolver
     */
    private $typeResolver;

    /**
     * Parses Doc Block comments given the DocBlock text and import tables at a position.
     *
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * Creates SignatureInformation
     *
     * @var SignatureInformationFactory
     */
    private $signatureInformationFactory;

    /**
     * @param ReadableIndex $index
     */
    public function __construct(ReadableIndex $index)
    {
        $this->index = $index;
        $this->typeResolver = new TypeResolver;
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->signatureInformationFactory = new SignatureInformationFactory($this);
    }

    /**
     * Builds the declaration line for a given node. Declarations with multiple lines are trimmed.
     *
     * @param Node $node
     * @return string
     */
    public function getDeclarationLineFromNode($node): string
    {
        // If node is part of a declaration list, build a declaration line that discludes other elements in the list
        //  - [PropertyDeclaration] // public $a, [$b = 3], $c; => public $b = 3;
        //  - [ConstDeclaration|ClassConstDeclaration] // "const A = 3, [B = 4];" => "const B = 4;"
        if (
            ($declaration = ParserHelpers\tryGetPropertyDeclaration($node)) && ($elements = $declaration->propertyElements) ||
            ($declaration = ParserHelpers\tryGetConstOrClassConstDeclaration($node)) && ($elements = $declaration->constElements)
        ) {
            $defLine = $declaration->getText();
            $defLineStart = $declaration->getStart();

            $defLine = \substr_replace(
                $defLine,
                $node->getFullText(),
                $elements->getFullStart() - $defLineStart,
                $elements->getFullWidth()
            );
        } else {
            $defLine = $node->getText();
        }

        // Trim string to only include first line
        $defLine = \rtrim(\strtok($defLine, "\n"), "\r");

        // TODO - pretty print rather than getting text

        return $defLine;
    }

    /**
     * Gets the documentation string for a node, if it has one
     *
     * @param Node $node
     * @return string|null
     */
    public function getDocumentationFromNode($node)
    {
        // Any NamespaceDefinition comments likely apply to the file, not the declaration itself.
        if ($node instanceof Node\Statement\NamespaceDefinition) {
            return null;
        }

        // For properties and constants, set the node to the declaration node, rather than the individual property.
        // This is because they get defined as part of a list.
        $constOrPropertyDeclaration = ParserHelpers\tryGetPropertyDeclaration($node) ?? ParserHelpers\tryGetConstOrClassConstDeclaration($node);
        if ($constOrPropertyDeclaration !== null) {
            $node = $constOrPropertyDeclaration;
        }

        // For parameters, parse the function-like declaration to get documentation for a parameter
        if ($node instanceof Node\Parameter) {
            $variableName = $node->getName();

            $functionLikeDeclaration = ParserHelpers\getFunctionLikeDeclarationFromParameter($node);
            $docBlock = $this->getDocBlock($functionLikeDeclaration);

            $parameterDocBlockTag = $this->tryGetDocBlockTagForParameter($docBlock, $variableName);
            return $parameterDocBlockTag !== null ? $parameterDocBlockTag->getDescription()->render() : null;
        }

        // For everything else, get the doc block summary corresponding to the current node.
        $docBlock = $this->getDocBlock($node);
        if ($docBlock !== null) {
            // check whether we have a description, when true, add a new paragraph
            // with the description
            $description = $docBlock->getDescription()->render();

            if (empty($description)) {
                return $docBlock->getSummary();
            }

            return $docBlock->getSummary() . "\n\n" . $description;
        }
        return null;
    }

    /**
     * Gets Doc Block with resolved names for a Node
     *
     * @param Node $node
     * @return DocBlock|null
     */
    private function getDocBlock(Node $node)
    {
        // TODO make more efficient (caching, ensure import table is in right format to begin with)
        $docCommentText = $node->getDocCommentText();
        if ($docCommentText !== null) {
            list($namespaceImportTable,,) = $node->getImportTablesForCurrentScope();
            foreach ($namespaceImportTable as $alias => $name) {
                $namespaceImportTable[$alias] = (string)$name;
            }
            $namespaceDefinition = $node->getNamespaceDefinition();
            if ($namespaceDefinition !== null && $namespaceDefinition->name !== null) {
                $namespaceName = (string)$namespaceDefinition->name->getNamespacedName();
            } else {
                $namespaceName = 'global';
            }
            $context = new Types\Context($namespaceName, $namespaceImportTable);

            try {
                // create() throws when it thinks the doc comment has invalid fields.
                // For example, a @see tag that is followed by something that doesn't look like a valid fqsen will throw.
                return $this->docBlockFactory->create($docCommentText, $context);
            } catch (\InvalidArgumentException $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Create a Definition for a definition node
     *
     * @param Node $node
     * @param string $fqn
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return Definition
     */
    public function createDefinitionFromNode(Node $node, string $fqn = null, Scope $scope = null): Definition
    {
        if ($scope === null) {
            $scope = getScopeAtNode($this, $node);
        }

        $def = new Definition;
        $def->fqn = $fqn;

        // Determines whether the suggestion will show after "new"
        $def->canBeInstantiated = (
            $node instanceof Node\Statement\ClassDeclaration &&
            // check whether it is not an abstract class
            ($node->abstractOrFinalModifier === null || $node->abstractOrFinalModifier->kind !== PhpParser\TokenKind::AbstractKeyword)
        );

        // Interfaces, classes, traits, namespaces, functions, and global const elements
        $def->isMember = !(
            $node instanceof PhpParser\ClassLike ||

            ($node instanceof Node\Statement\NamespaceDefinition && $node->name !== null) ||

            $node instanceof Node\Statement\FunctionDeclaration ||

            ($node instanceof Node\ConstElement && $node->parent->parent instanceof Node\Statement\ConstDeclaration)
        );

        // Definition is affected by global namespace fallback if it is a global constant or a global function
        $def->roamed = (
            $fqn !== null
            && strpos($fqn, '\\') === false
            && (
                ($node instanceof Node\ConstElement && $node->parent->parent instanceof Node\Statement\ConstDeclaration)
                || $node instanceof Node\Statement\FunctionDeclaration
            )
        );

        // Static methods and static property declarations
        $def->isStatic = (
            ($node instanceof Node\MethodDeclaration && $node->isStatic()) ||

            (($propertyDeclaration = ParserHelpers\tryGetPropertyDeclaration($node)) !== null
            && $propertyDeclaration->isStatic())
        );

        if ($node instanceof Node\Statement\ClassDeclaration &&
            // TODO - this should be better represented in the parser API
            $node->classBaseClause !== null && $node->classBaseClause->baseClass !== null) {
            $def->extends = [$scope->getResolvedName($node->classBaseClause->baseClass)];
        } elseif (
            $node instanceof Node\Statement\InterfaceDeclaration &&
            // TODO - this should be better represented in the parser API
            $node->interfaceBaseClause !== null && $node->interfaceBaseClause->interfaceNameList !== null
        ) {
            $def->extends = [];
            foreach ($node->interfaceBaseClause->interfaceNameList->getValues() as $n) {
                $def->extends[] = $scope->getResolvedName($n);
            }
        }

        $def->symbolInformation = SymbolInformation::fromNode($node, $fqn);

        if ($def->symbolInformation !== null) {
            $def->type = $this->getTypeFromNode($node, $scope);
            $def->declarationLine = $this->getDeclarationLineFromNode($node);
            $def->documentation = $this->getDocumentationFromNode($node);
        }

        if ($node instanceof FunctionLike) {
            $def->signatureInformation = $this->signatureInformationFactory->create($node, $scope);
        }

        return $def;
    }

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Node $node Any reference node
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition(Node $node, Scope $scope = null)
    {
        if ($scope === null) {
            $scope = getScopeAtNode($this, $node);
        }

        $parent = $node->parent;
        // Variables are not indexed globally, as they stay in the file scope anyway.
        // Ignore variable nodes that are part of ScopedPropertyAccessExpression,
        // as the scoped property access expression node is handled separately.
        if ($node instanceof Node\Expression\Variable &&
            !($parent instanceof Node\Expression\ScopedPropertyAccessExpression)) {
            $name = $node->getName();
            // Resolve $this to the containing class definition.
            if ($name === 'this') {
                if ($scope->currentSelf === null) {
                    return null;
                }
                $fqn = substr((string)$scope->currentSelf->type->getFqsen(), 1);
                return $this->index->getDefinition($fqn, false);
            }

            // Resolve the variable to a definition node (assignment, param or closure use)
            if (!isset($scope->variables[$name])) {
                return null;
            }
            return $this->createDefinitionFromNode($scope->variables[$name]->definitionNode, null, $scope);
        }
        // Other references are references to a global symbol that have an FQN
        // Find out the FQN
        $fqn = $this->resolveReferenceNodeToFqn($node, $scope);

        if ($fqn === 'self' || $fqn === 'static') {
            // Resolve self and static keywords to the containing class
            // (This is not 100% correct for static but better than nothing)
            if ($scope->currentSelf === null) {
                return null;
            }
            $fqn = substr((string)$scope->currentSelf->type->getFqsen(), 1);
        } else if ($fqn === 'parent') {
            if ($scope->currentSelf === null) {
                return null;
            }
            // Resolve parent keyword to the base class FQN
            $classNode = $scope->currentSelf->definitionNode;
            if (!$classNode->classBaseClause || !$classNode->classBaseClause->baseClass) {
                return null;
            }
            $fqn = $scope->getResolvedName($classNode->classBaseClause->baseClass);
        }

        if (!$fqn) {
            return;
        }

        // If the node is a function or constant, it could be namespaced, but PHP falls back to global
        // http://php.net/manual/en/language.namespaces.fallback.php
        // TODO - verify that this is not a method
        $globalFallback = ParserHelpers\isConstantFetch($node) || $parent instanceof Node\Expression\CallExpression;

        // Return the Definition object from the index index
        return $this->index->getDefinition($fqn, $globalFallback);
    }

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     * May also return "static", "self" or "parent"
     *
     * @param Node $node
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return string|null
     */
    public function resolveReferenceNodeToFqn(Node $node, Scope $scope = null)
    {
        if ($scope === null) {
            $scope = getScopeAtNode($this, $node);
        }
        // TODO all name tokens should be a part of a node
        if ($node instanceof Node\QualifiedName) {
            return $this->resolveQualifiedNameNodeToFqn($node, $scope);
        } else if ($node instanceof Node\Expression\MemberAccessExpression) {
            return $this->resolveMemberAccessExpressionNodeToFqn($node, $scope);
        } else if (ParserHelpers\isConstantFetch($node)) {
            return (string)($node->getNamespacedName());
        } else if (
            // A\B::C - constant access expression
            $node instanceof Node\Expression\ScopedPropertyAccessExpression
            && !($node->memberName instanceof Node\Expression\Variable)
        ) {
            return $this->resolveScopedPropertyAccessExpressionNodeToFqn($node, $scope);
        } else if (
            // A\B::$c - static property access expression
            $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression
        ) {
            return $this->resolveScopedPropertyAccessExpressionNodeToFqn($node->parent, $scope);
        }

        return null;
    }

    private function resolveQualifiedNameNodeToFqn(Node\QualifiedName $node, Scope $scope)
    {
        $parent = $node->parent;

        if ($parent instanceof Node\TraitSelectOrAliasClause) {
            return null;
        }
        // Add use clause references
        if (($useClause = $parent) instanceof Node\NamespaceUseGroupClause
            || $useClause instanceof Node\NamespaceUseClause
        ) {
            $contents = $node->getFileContents();
            if ($useClause instanceof Node\NamespaceUseGroupClause) {
                $prefix = $useClause->parent->parent->namespaceName;
                if ($prefix === null) {
                    return null;
                }
                $name = PhpParser\ResolvedName::buildName($prefix->nameParts, $contents);
                $name->addNameParts($node->nameParts, $contents);
                $name = (string)$name;

                if ($useClause->functionOrConst === null) {
                    $useClause = $node->getFirstAncestor(Node\Statement\NamespaceUseDeclaration::class);
                    if ($useClause->functionOrConst !== null && $useClause->functionOrConst->kind === PhpParser\TokenKind::FunctionKeyword) {
                        $name .= '()';
                    }
                }
                return $name;
            } else {
                $name = (string) PhpParser\ResolvedName::buildName($node->nameParts, $contents);
                if ($useClause->groupClauses === null && $useClause->parent->parent->functionOrConst !== null && $useClause->parent->parent->functionOrConst->kind === PhpParser\TokenKind::FunctionKeyword) {
                    $name .= '()';
                }
            }

            return $name;
        }

        // For extends, implements, type hints and classes of classes of static calls use the name directly
        $name = $scope->getResolvedName($node) ?? (string)$node->getNamespacedName();

        if ($node->parent instanceof Node\Expression\CallExpression) {
            $name .= '()';
        }
        return $name;
    }

    private function resolveMemberAccessExpressionNodeToFqn(Node\Expression\MemberAccessExpression $access, Scope $scope) {
        if ($access->memberName instanceof Node\Expression) {
            // Cannot get definition if right-hand side is expression
            return null;
        }
        // Get the type of the left-hand expression
        $varType = $this->resolveExpressionNodeToType($access->dereferencableExpression, $scope);

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
            if ($scope->currentSelf === null) {
                return null;
            }
            $classFqn = substr((string)$scope->currentSelf->type->getFqsen(), 1);
        } else if (!($varType instanceof Types\Object_) || $varType->getFqsen() === null) {
            // Left-hand expression could not be resolved to a class
            return null;
        } else {
            $classFqn = substr((string)$varType->getFqsen(), 1);
        }
        $memberSuffix = '->' . (string)($access->memberName->getText()
            ?? $access->memberName->getText($access->getFileContents()));
        if ($access->parent instanceof Node\Expression\CallExpression) {
            $memberSuffix .= '()';
        }

        // Find the right class that implements the member
        $implementorFqns = [$classFqn];

        while ($implementorFqn = array_shift($implementorFqns)) {
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

        return $classFqn . $memberSuffix;
    }

    private function resolveScopedPropertyAccessExpressionNodeToFqn(
        Node\Expression\ScopedPropertyAccessExpression $scoped,
        Scope $scope
    ) {
        if ($scoped->scopeResolutionQualifier instanceof Node\Expression\Variable) {
            $varType = $this->getTypeFromNode($scoped->scopeResolutionQualifier, $scope);
            if ($varType === null) {
                return null;
            }
            $className = substr((string)$varType->getFqsen(), 1);
        } elseif ($scoped->scopeResolutionQualifier instanceof Node\QualifiedName) {
            $className = $scope->getResolvedName($scoped->scopeResolutionQualifier);
        } else {
            return null;
        }

        if ($className === 'self' || $className === 'static' || $className === 'parent') {
            // self and static are resolved to the containing class
            $classNode = $scope->currentSelf->definitionNode ?? null;
            if ($classNode === null) {
                return null;
            }
            if ($className === 'parent') {
                // parent is resolved to the parent class
                if (!isset($classNode->extends)) {
                    return null;
                }
                $className = $scope->getResolvedName($classNode->extends);
            } else {
                $className = substr((string)$scope->currentSelf->type->getFqsen(), 1);
            }
        } elseif ($scoped->scopeResolutionQualifier instanceof Node\QualifiedName) {
            $className = $scope->getResolvedName($scoped->scopeResolutionQualifier);
        }
        if ($scoped->memberName instanceof Node\Expression\Variable) {
            if ($scoped->parent instanceof Node\Expression\CallExpression) {
                return null;
            }
            $memberName = $scoped->memberName->getName();
            if (empty($memberName)) {
                return null;
            }
            $name = (string)$className . '::$' . $memberName;
        } else {
            $name = (string)$className . '::' . $scoped->memberName->getText($scoped->getFileContents());
        }
        if ($scoped->parent instanceof Node\Expression\CallExpression) {
            $name .= '()';
        }
        return $name;
    }

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed_.
     *
     * @param Node|Token $expr
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function resolveExpressionNodeToType($expr, Scope $scope = null)
    {
        if ($scope === null) {
            $scope = getScopeAtNode($this, $expr);
        }

        // PARENTHESIZED EXPRESSION
        // Retrieve inner expression from parenthesized expression
        while ($expr instanceof Node\Expression\ParenthesizedExpression) {
            $expr = $expr->expression;
        }

        if ($expr == null || $expr instanceof PhpParser\MissingToken || $expr instanceof PhpParser\SkippedToken) {
            // TODO some members are null or Missing/SkippedToken
            // How do we handle this more generally?
            return new Types\Mixed_;
        }

        // VARIABLE
        //   $this -> Type\this
        //   $myVariable -> type of corresponding assignment expression
        if ($expr instanceof Node\Expression\Variable || $expr instanceof Node\UseVariableName) {
            $name = $expr->getName();
            return isset($scope->variables[$name])
                ? $scope->variables[$name]->type
                : new Types\Mixed_;
        }

        // FUNCTION CALL
        // Function calls are resolved to type corresponding to their FQN
        if ($expr instanceof Node\Expression\CallExpression &&
            !(
                $expr->callableExpression instanceof Node\Expression\ScopedPropertyAccessExpression ||
                $expr->callableExpression instanceof Node\Expression\MemberAccessExpression)
        ) {
            // Find the function definition
            if ($expr->callableExpression instanceof Node\Expression) {
                return new Types\Mixed_;
            }

            if ($expr->callableExpression instanceof Node\QualifiedName) {
                $fqn = $scope->getResolvedName($expr->callableExpression) ?? $expr->callableExpression->getNamespacedName();
                $fqn .= '()';
                $def = $this->index->getDefinition($fqn, true);
                if ($def !== null) {
                    return $def->type;
                }
            }
            return new Types\Mixed_;
        }

        // TRUE / FALSE / NULL
        // Resolve true and false reserved words to Types\Boolean
        if ($expr instanceof Node\ReservedWord) {
            $token = $expr->children->kind;
            if ($token === PhpParser\TokenKind::TrueReservedWord || $token === PhpParser\TokenKind::FalseReservedWord) {
                return new Types\Boolean;
            }

            if ($token === PhpParser\TokenKind::NullReservedWord) {
                return new Types\Null_;
            }
            return new Types\Mixed_;
        }

        // CONSTANT FETCH
        // Resolve constants by retrieving corresponding definition type from FQN
        if (ParserHelpers\isConstantFetch($expr)) {
            $fqn = (string)$expr->getNamespacedName();
            $def = $this->index->getDefinition($fqn, true);
            if ($def !== null) {
                return $def->type;
            }
            return new Types\Mixed_;
        }

        // MEMBER CALL EXPRESSION/SCOPED PROPERTY CALL EXPRESSION
        //   The type of the member/scoped property call expression is the type of the method, so resolve the
        //   type of the callable expression.
        if ($expr instanceof Node\Expression\CallExpression && (
            $expr->callableExpression instanceof Node\Expression\MemberAccessExpression ||
            $expr->callableExpression instanceof Node\Expression\ScopedPropertyAccessExpression)
        ) {
            return $this->resolveExpressionNodeToType($expr->callableExpression, $scope);
        }

        // MEMBER ACCESS EXPRESSION
        if ($expr instanceof Node\Expression\MemberAccessExpression) {
            if ($expr->memberName instanceof Node\Expression) {
                return new Types\Mixed_;
            }
            $var = $expr->dereferencableExpression;

           // Resolve object
            $objType = $this->resolveExpressionNodeToType($var, $scope);
            if ($objType === null) {
                return null;
            }
            if (!($objType instanceof Types\Compound)) {
                $objType = new Types\Compound([$objType]);
            }
            for ($i = 0; $t = $objType->get($i); $i++) {
                if ($t instanceof Types\This) {
                    if ($scope->currentSelf === null) {
                        return new Types\Mixed_;
                    }
                    $classFqn = substr((string)$scope->currentSelf->type->getFqsen(), 1);
                } else if (!($t instanceof Types\Object_) || $t->getFqsen() === null) {
                    return new Types\Mixed_;
                } else {
                    $classFqn = substr((string)$t->getFqsen(), 1);
                }
                $add = '->' . $expr->memberName->getText($expr->getFileContents());
                if ($expr->parent instanceof Node\Expression\CallExpression) {
                    $add .= '()';
                }
                $classDef = $this->index->getDefinition($classFqn);
                if ($classDef !== null) {
                    foreach ($classDef->getAncestorDefinitions($this->index, true) as $fqn => $def) {
                        $def = $this->index->getDefinition($fqn . $add);
                        if ($def !== null) {
                            if ($def->type instanceof Types\This || $def->type instanceof Types\Self_) {
                                return new Types\Object_(new Fqsen('\\' . $classFqn));
                            }
                            return $def->type;
                        }
                    }
                }
            }
        }

        // SCOPED PROPERTY ACCESS EXPRESSION
        if ($expr instanceof Node\Expression\ScopedPropertyAccessExpression) {
            $classType = $this->resolveClassNameToType($expr->scopeResolutionQualifier, $scope);
            if (!($classType instanceof Types\Object_) || $classType->getFqsen() === null) {
                return new Types\Mixed_;
            }
            $fqn = substr((string)$classType->getFqsen(), 1) . '::';

            // TODO is there a cleaner way to do this?
            $fqn .= $expr->memberName->getText() ?? $expr->memberName->getText($expr->getFileContents());
            if ($expr->parent instanceof Node\Expression\CallExpression) {
                $fqn .= '()';
            }

            $def = $this->index->getDefinition($fqn);
            if ($def === null) {
                return new Types\Mixed_;
            }
            return $def->type;
        }

        // OBJECT CREATION EXPRESSION
        //   new A() => resolves to the type of the class type designator (A)
        //   TODO: new $this->a => resolves to the string represented by "a"
        if ($expr instanceof Node\Expression\ObjectCreationExpression) {
            return $this->resolveClassNameToType($expr->classTypeDesignator, $scope);
        }

        // CLONE EXPRESSION
        //   clone($a) => resolves to the type of $a
        if ($expr instanceof Node\Expression\CloneExpression) {
            return $this->resolveExpressionNodeToType($expr->expression, $scope);
        }

        // ASSIGNMENT EXPRESSION
        //   $a = $myExpression => resolves to the type of the right-hand operand
        if ($expr instanceof Node\Expression\AssignmentExpression) {
            return $this->resolveExpressionNodeToType($expr->rightOperand, $scope);
        }

        // TERNARY EXPRESSION
        //   $condition ? $ifExpression : $elseExpression => reslves to type of $ifCondition or $elseExpression
        //   $condition ?: $elseExpression => resolves to type of $condition or $elseExpression
        if ($expr instanceof Node\Expression\TernaryExpression) {
            // ?:
            if ($expr->ifExpression === null) {
                return new Types\Compound([
                    $this->resolveExpressionNodeToType($expr->condition, $scope), // TODO: why?
                    $this->resolveExpressionNodeToType($expr->elseExpression, $scope)
                ]);
            }
            // Ternary is a compound of the two possible values
            return new Types\Compound([
                $this->resolveExpressionNodeToType($expr->ifExpression, $scope),
                $this->resolveExpressionNodeToType($expr->elseExpression, $scope)
            ]);
        }

        // NULL COALLESCE
        //   $rightOperand ?? $leftOperand => resolves to type of $rightOperand or $leftOperand
        if ($expr instanceof Node\Expression\BinaryExpression && $expr->operator->kind === PhpParser\TokenKind::QuestionQuestionToken) {
            // ?? operator
            return new Types\Compound([
                $this->resolveExpressionNodeToType($expr->leftOperand, $scope),
                $this->resolveExpressionNodeToType($expr->rightOperand, $scope)
            ]);
        }

        // BOOLEAN EXPRESSIONS: resolve to Types\Boolean
        //   (bool) $expression
        //   !$expression
        //   empty($var)
        //   isset($var)
        //   >, >=, <, <=, &&, ||, AND, OR, XOR, ==, ===, !=, !==
        if (
            ParserHelpers\isBooleanExpression($expr)

            || ($expr instanceof Node\Expression\CastExpression && $expr->castType->kind === PhpParser\TokenKind::BoolCastToken)
            || ($expr instanceof Node\Expression\UnaryOpExpression && $expr->operator->kind === PhpParser\TokenKind::ExclamationToken)
            || $expr instanceof Node\Expression\EmptyIntrinsicExpression
            || $expr instanceof Node\Expression\IssetIntrinsicExpression
        ) {
            return new Types\Boolean;
        }

        // STRING EXPRESSIONS: resolve to Types\String
        //   [concatenation] .=, .
        //   [literals] "hello", \b"hello", \B"hello", 'hello', \b'hello', HEREDOC, NOWDOC
        //   [cast] (string) "hello"
        //
        //   TODO: Magic constants (__CLASS__, __DIR__, __FUNCTION__, __METHOD__, __NAMESPACE__, __TRAIT__, __FILE__)
        if (
            ($expr instanceof Node\Expression\BinaryExpression &&
                ($expr->operator->kind === PhpParser\TokenKind::DotToken || $expr->operator->kind === PhpParser\TokenKind::DotEqualsToken)) ||
            $expr instanceof Node\StringLiteral ||
            ($expr instanceof Node\Expression\CastExpression && $expr->castType->kind === PhpParser\TokenKind::StringCastToken)
        ) {
            return new Types\String_;
        }

        // BINARY EXPRESSIONS:
        // Resolve to Types\Integer if both left and right operands are integer types, otherwise Types\Float
        //   [operator] +, -, *, **
        //   [assignment] *=, **=, -=, +=
        // Resolve to Types\Float
        //   [assignment] /=
        if (
            $expr instanceof Node\Expression\BinaryExpression &&
            ($operator = $expr->operator->kind)
            && ($operator === PhpParser\TokenKind::PlusToken ||
                $operator === PhpParser\TokenKind::AsteriskAsteriskToken ||
                $operator === PhpParser\TokenKind::AsteriskToken ||
                $operator === PhpParser\TokenKind::MinusToken ||

                // Assignment expressions (TODO: consider making this a type of AssignmentExpression rather than kind of BinaryExpression)
                $operator === PhpParser\TokenKind::AsteriskEqualsToken||
                $operator === PhpParser\TokenKind::AsteriskAsteriskEqualsToken ||
                $operator === PhpParser\TokenKind::MinusEqualsToken ||
                $operator === PhpParser\TokenKind::PlusEqualsToken
            )
        ) {
            if (
                $this->resolveExpressionNodeToType($expr->leftOperand, $scope) instanceof Types\Integer
                && $this->resolveExpressionNodeToType($expr->rightOperand, $scope) instanceof Types\Integer
            ) {
                return new Types\Integer;
            }
            return new Types\Float_;
        } else if (
            $expr instanceof Node\Expression\BinaryExpression &&
            $expr->operator->kind === PhpParser\TokenKind::SlashEqualsToken
        ) {
            return new Types\Float_;
        }

        // INTEGER EXPRESSIONS: resolve to Types\Integer
        //   [literal] 1
        //   [operator] <=>, &, ^, |
        //   TODO: Magic constants (__LINE__)
        if (
            // TODO: consider different Node types of float/int, also better property name (not "children")
            ($expr instanceof Node\NumericLiteral && $expr->children->kind === PhpParser\TokenKind::IntegerLiteralToken) ||
            $expr instanceof Node\Expression\BinaryExpression && (
                ($operator = $expr->operator->kind)
                && ($operator === PhpParser\TokenKind::LessThanEqualsGreaterThanToken ||
                    $operator === PhpParser\TokenKind::AmpersandToken ||
                    $operator === PhpParser\TokenKind::CaretToken ||
                    $operator === PhpParser\TokenKind::BarToken)
            )
        ) {
            return new Types\Integer;
        }

        // FLOAT EXPRESSIONS: resolve to Types\Float
        //   [literal] 1.5
        //   [operator] /
        //   [cast] (double)
        if (
            $expr instanceof Node\NumericLiteral && $expr->children->kind === PhpParser\TokenKind::FloatingLiteralToken ||
            ($expr instanceof Node\Expression\CastExpression && $expr->castType->kind === PhpParser\TokenKind::DoubleCastToken) ||
            ($expr instanceof Node\Expression\BinaryExpression && $expr->operator->kind === PhpParser\TokenKind::SlashToken)
        ) {
            return new Types\Float_;
        }

        // ARRAY CREATION EXPRESSION:
        // Resolve to Types\Array (Types\Compound of value and key types)
        //  [a, b, c]
        //  [1=>"hello", "hi"=>1, 4=>[]]s
        if ($expr instanceof Node\Expression\ArrayCreationExpression) {
            $valueTypes = [];
            $keyTypes = [];
            if ($expr->arrayElements !== null) {
                foreach ($expr->arrayElements->getElements() as $item) {
                    $valueTypes[] = $this->resolveExpressionNodeToType($item->elementValue, $scope);
                    $keyTypes[] = $item->elementKey ? $this->resolveExpressionNodeToType($item->elementKey, $scope) : new Types\Integer;
                }
            }
            $valueTypes = array_unique($valueTypes);
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

        // SUBSCRIPT EXPRESSION
        // $myArray[3]
        // $myArray{"hello"}
        if ($expr instanceof Node\Expression\SubscriptExpression) {
            $varType = $this->resolveExpressionNodeToType($expr->postfixExpression, $scope);
            if (!($varType instanceof Types\Array_)) {
                return new Types\Mixed_;
            }
            return $varType->getValueType();
        }

        // SCRIPT INCLUSION EXPRESSION
        //   include, require, include_once, require_once
        if ($expr instanceof Node\Expression\ScriptInclusionExpression) {
            // TODO: resolve path to PhpDocument and find return statement
            return new Types\Mixed_;
        }

        if ($expr instanceof Node\QualifiedName) {
            return $this->resolveClassNameToType($expr, $scope);
        }

        return new Types\Mixed_;
    }


    /**
     * Takes any class name node (from a static method call, or new node) and returns a Type object
     * Resolves keywords like self, static and parent
     *
     * @param Node|PhpParser\Token $class
     * @return Type
     */
    public function resolveClassNameToType($class, Scope $scope = null): Type
    {
        if ($class instanceof Node\Expression || $class instanceof PhpParser\MissingToken) {
            return new Types\Mixed_;
        }
        if ($class instanceof PhpParser\Token && $class->kind === PhpParser\TokenKind::ClassKeyword) {
            // Anonymous class
            return new Types\Object_;
        }
        if ($class instanceof PhpParser\Token && $class->kind === PhpParser\TokenKind::StaticKeyword) {
            // `new static`
            return new Types\Static_;
        }
        if ($scope === null) {
            $scope = getScopeAtNode($this, $class);
        }
        $className = $scope->getResolvedName($class);

        if ($className === 'self') {
            if ($scope->currentSelf === null) {
                return new Types\Self_;
            }
            return $scope->currentSelf->type;
        } else if ($className === 'parent') {
            if ($scope->currentSelf === null) {
                return new Types\Object_;
            }
            $classNode = $scope->currentSelf->definitionNode;
            if (empty($classNode->classBaseClause)
                || !$classNode->classBaseClause->baseClass instanceof Node\QualifiedName
            ) {
                return new Types\Object_;
            }
            // parent is resolved to the parent class
            $classFqn = $scope->getResolvedName($classNode->classBaseClause->baseClass);
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
     * If it is unknown, will be Types\Mixed_.
     * Returns null if the node does not have a type.
     *
     * @param Node $node
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function getTypeFromNode($node, Scope $scope = null)
    {
        if ($scope === null) {
            $scope = getScopeAtNode($this, $node);
        }

        if (ParserHelpers\isConstDefineExpression($node)) {
            // constants with define() like
            // define('TEST_DEFINE_CONSTANT', false);
            return $this->resolveExpressionNodeToType($node->argumentExpressionList->children[2]->expression, $scope);
        }

        // PARAMETERS
        // Get the type of the parameter:
        //   1. Doc block
        //   2. Parameter type and default
        if ($node instanceof Node\Parameter) {
            // Parameters
            // Get the doc block for the the function call
            // /**
            //  * @param MyClass $myParam
            //  */
            //  function foo($a)
            $variableName = $node->getName();
            if (isset($scope->variables[$variableName])) {
                return $scope->variables[$variableName]->type;
            }
            $functionLikeDeclaration = ParserHelpers\getFunctionLikeDeclarationFromParameter($node);
            $docBlock = $this->getDocBlock($functionLikeDeclaration);
            $parameterDocBlockTag = $this->tryGetDocBlockTagForParameter($docBlock, $variableName);
            if ($parameterDocBlockTag !== null && ($type = $parameterDocBlockTag->getType())) {
                // Doc block comments supercede all other forms of type inference
                return $type;
            }

            // function foo(MyClass $a)
            if ($node->typeDeclaration !== null) {
                // Use PHP7 return type hint
                if ($node->typeDeclaration instanceof PhpParser\Token) {
                    // Resolve a string like "bool" to a type object
                    $type = $this->typeResolver->resolve($node->typeDeclaration->getText($node->getFileContents()));
                } else {
                    $type = new Types\Object_(new Fqsen('\\' . $scope->getResolvedName($node->typeDeclaration)));
                }
            }
            // function foo($a = 3)
            if ($node->default !== null) {
                $defaultType = $this->resolveExpressionNodeToType($node->default, $scope);
                if (isset($type) && !is_a($type, get_class($defaultType))) {
                    // TODO - verify it is worth creating a compound type
                    return new Types\Compound([$type, $defaultType]);
                }
                $type = $defaultType;
            }
            return $type ?? new Types\Mixed_;
        }

        // FUNCTIONS AND METHODS
        // Get the return type
        //   1. doc block
        //   2. return type hint
        //   3. TODO: infer from return statements
        if ($node instanceof PhpParser\FunctionLike) {
            // Functions/methods
            $docBlock = $this->getDocBlock($node);
            if (
                $docBlock !== null
                && !empty($returnTags = $docBlock->getTagsByName('return'))
                && ($returnType = $returnTags[0]->getType()) !== null
            ) {
                // Use @return tag
                if ($returnType instanceof Types\Self_ && null !== $scope->currentSelf) {
                    return $scope->currentSelf->type;
                }
                return $returnType;
            }
            if ($node->returnType !== null && !($node->returnType instanceof PhpParser\MissingToken)) {
                // Use PHP7 return type hint
                if ($node->returnType instanceof PhpParser\Token) {
                    // Resolve a string like "bool" to a type object
                    return $this->typeResolver->resolve($node->returnType->getText($node->getFileContents()));
                } else if ($scope->currentSelf !== null && $scope->getResolvedName($node->returnType) === 'self') {
                    return $scope->currentSelf->type;
                }
                return new Types\Object_(new Fqsen('\\' . $scope->getResolvedName($node->returnType)));
            }
            // Unknown return type
            return new Types\Mixed_;
        }

        // FOREACH KEY/VARIABLE
        if ($node instanceof Node\ForeachKey || $node->parent instanceof Node\ForeachKey) {
            $foreach = $node->getFirstAncestor(Node\Statement\ForeachStatement::class);
            $collectionType = $this->resolveExpressionNodeToType($foreach->forEachCollectionName, $scope);
            if ($collectionType instanceof Types\Array_) {
                return $collectionType->getKeyType();
            }
            return new Types\Mixed_();
        }

        // FOREACH VALUE/VARIABLE
        if ($node instanceof Node\ForeachValue
            || ($node instanceof Node\Expression\Variable && $node->parent instanceof Node\ForeachValue)
        ) {
            $foreach = $node->getFirstAncestor(Node\Statement\ForeachStatement::class);
            $collectionType = $this->resolveExpressionNodeToType($foreach->forEachCollectionName, $scope);
            if ($collectionType instanceof Types\Array_) {
                return $collectionType->getValueType();
            }
            return new Types\Mixed_();
        }

        // PROPERTIES, CONSTS, CLASS CONSTS, ASSIGNMENT EXPRESSIONS
        // Get the documented type the assignment resolves to.
        if (
            ($declarationNode =
                ParserHelpers\tryGetPropertyDeclaration($node) ??
                ParserHelpers\tryGetConstOrClassConstDeclaration($node)
            ) !== null ||
            ($node = $node->parent) instanceof Node\Expression\AssignmentExpression) {
            $declarationNode = $declarationNode ?? $node;

            // Property, constant or variable
            // Use @var tag
            if (
                ($docBlock = $this->getDocBlock($declarationNode))
                && !empty($varTags = $docBlock->getTagsByName('var'))
                && ($type = $varTags[0]->getType())
            ) {
                return $type;
            }

            // Resolve the expression
            if ($declarationNode instanceof Node\PropertyDeclaration) {
                // TODO should have default
                if (isset($node->parent->rightOperand)) {
                    return $this->resolveExpressionNodeToType($node->parent->rightOperand, $scope);
                }
            } else if ($node instanceof Node\ConstElement) {
                return $this->resolveExpressionNodeToType($node->assignment, $scope);
            } else if ($node instanceof Node\Expression\AssignmentExpression) {
                return $this->resolveExpressionNodeToType($node->rightOperand, $scope);
            }
            // TODO: read @property tags of class
            // TODO: Try to infer the type from default value / constant value
            // Unknown
            return new Types\Mixed_;
        }

        // The node does not have a type
        return null;
    }

    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Node $node
     * @param Scope|null $scope Scope at the point of Node. If not provided, will be computed from $node.
     * @return string|null
     */
    public function getDefinedFqn($node, Scope $scope = null)
    {
        $parent = $node->parent;
        // Anonymous classes don't count as a definition
        // INPUT                    OUTPUT:
        // namespace A\B;
        // class C { }              A\B\C
        // interface C { }          A\B\C
        // trait C { }              A\B\C
        if (
            $node instanceof PhpParser\ClassLike
        ) {
            return (string) $node->getNamespacedName();
        }

        // INPUT                   OUTPUT:
        // namespace A\B;           A\B
        if ($node instanceof Node\Statement\NamespaceDefinition && $node->name instanceof Node\QualifiedName) {
            $name = (string) PhpParser\ResolvedName::buildName($node->name->nameParts, $node->getFileContents());
            return $name === "" ? null : $name;
        }

        // INPUT                   OUTPUT:
        // namespace A\B;
        // function a();           A\B\a();
        if ($node instanceof Node\Statement\FunctionDeclaration) {
            // Function: use functionName() as the name
            $name = (string)$node->getNamespacedName();
            return $name === "" ? null : $name . '()';
        }

        if ($scope === null) {
            $scope = getScopeAtNode($this, $node);
        }

        // INPUT                        OUTPUT
        // namespace A\B;
        // class C {
        //   function a () {}           A\B\C->a()
        //   static function b() {}     A\B\C::b()
        // }
        if ($node instanceof Node\MethodDeclaration) {
            // Class method: use ClassName->methodName() as name
            if ($scope->currentSelf === null) {
                return;
            }
            $className = substr((string)$scope->currentSelf->type->getFqsen(), 1);
            if (!$className) {
                // Ignore anonymous classes
                return null;
            }
            if ($node->isStatic()) {
                return $className . '::' . $node->getName() . '()';
            } else {
                return $className . '->' . $node->getName() . '()';
            }
        }

        // INPUT                        OUTPUT
        // namespace A\B;
        // class C {
        //   static $a = 4, $b = 4      A\B\C::$a, A\B\C::$b
        //   $a = 4, $b = 4             A\B\C->$a, A\B\C->$b // TODO verify variable name
        // }
        if (
            ($propertyDeclaration = ParserHelpers\tryGetPropertyDeclaration($node)) !== null &&
            $scope->currentSelf !== null &&
            isset($scope->currentSelf->definitionNode->name)
        ) {
            $className = substr((string)$scope->currentSelf->type->getFqsen(), 1);
            $name = $node->getName();
            if ($propertyDeclaration->isStatic()) {
                // Static Property: use ClassName::$propertyName as name
                return $className . '::$' . $name;
            }

            // Instance Property: use ClassName->propertyName as name
            return $className . '->' . $name;
        }

        // INPUT                        OUTPUT
        // namespace A\B;
        // const FOO = 5;               A\B\FOO
        // class C {
        //   const $a, $b = 4           A\B\C::$a(), A\B\C::$b
        // }
        if (($constDeclaration = ParserHelpers\tryGetConstOrClassConstDeclaration($node)) !== null) {
            if ($constDeclaration instanceof Node\Statement\ConstDeclaration) {
                // Basic constant: use CONSTANT_NAME as name
                return (string)$node->getNamespacedName();
            }

            if ($scope->currentSelf === null || !isset($scope->currentSelf->definitionNode->name)
            ) {
                // Class constant: use ClassName::CONSTANT_NAME as name
                return null;
            }
            $className = substr((string)$scope->currentSelf->type->getFqsen(), 1);
            return $className . '::' . $node->getName();
        }

        if (ParserHelpers\isConstDefineExpression($node)) {
            return $node->argumentExpressionList->children[0]->expression->getStringContentsText();
        }

        return null;
    }

    /**
     * @param DocBlock|null $docBlock
     * @param string|null $variableName
     * @return DocBlock\Tags\Param|null
     */
    private function tryGetDocBlockTagForParameter($docBlock, $variableName)
    {
        if ($docBlock === null || $variableName === null) {
            return null;
        }
        $tags = $docBlock->getTagsByName('param');
        foreach ($tags as $tag) {
            if ($tag->getVariableName() === \ltrim($variableName, "$")) {
                return $tag;
            }
        }
        return null;
    }
}
