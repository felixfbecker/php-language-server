<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, TextEdit};
use LanguageServer\NodeVisitor\{
    NodeAtPositionFinder,
    ReferencesAdder,
    DocBlockParser,
    DefinitionCollector,
    ColumnCalculator,
    ReferencesCollector,
    VariableReferencesCollector
};
use PhpParser\{Error, Node, NodeTraverser, Parser};
use PhpParser\NodeVisitor\NameResolver;
use phpDocumentor\Reflection\DocBlockFactory;

class PhpDocument
{
    /**
     * The LanguageClient instance (to report errors etc)
     *
     * @var LanguageClient
     */
    private $client;

    /**
     * The Project this document belongs to (to register definitions etc)
     *
     * @var Project
     */
    public $project;
    // for whatever reason I get "cannot access private property" error if $project is not public
    // https://github.com/felixfbecker/php-language-server/pull/49#issuecomment-252427359

    /**
     * The PHPParser instance
     *
     * @var Parser
     */
    private $parser;

    /**
     * The DocBlockFactory instance to parse docblocks
     *
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * The URI of the document
     *
     * @var string
     */
    private $uri;

    /**
     * The content of the document
     *
     * @var string
     */
    private $content;

    /**
     * The AST of the document
     *
     * @var Node[]
     */
    private $stmts;

    /**
     * Map from fully qualified name (FQN) to Node
     *
     * @var Node[]
     */
    private $definitions;

    /**
     * Map from fully qualified name (FQN) to array of nodes that reference the symbol
     *
     * @var Node[][]
     */
    private $references;

    /**
     * @param string          $uri             The URI of the document
     * @param string          $content         The content of the document
     * @param Project         $project         The Project this document belongs to (to register definitions etc)
     * @param LanguageClient  $client          The LanguageClient instance (to report errors etc)
     * @param Parser          $parser          The PHPParser instance
     * @param DocBlockFactory $docBlockFactory The DocBlockFactory instance to parse docblocks
     */
    public function __construct(string $uri, string $content, Project $project, LanguageClient $client, Parser $parser, DocBlockFactory $docBlockFactory)
    {
        $this->uri = $uri;
        $this->project = $project;
        $this->client = $client;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->updateContent($content);
    }

    /**
     * Get all references of a fully qualified name
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return Node[]
     */
    public function getReferencesByFqn(string $fqn)
    {
        return isset($this->references) && isset($this->references[$fqn]) ? $this->references[$fqn] : null;
    }

    /**
     * Updates the content on this document.
     * Re-parses a source file, updates symbols and reports parsing errors
     * that may have occured as diagnostics.
     *
     * @param string $content
     * @return void
     */
    public function updateContent(string $content)
    {
        $this->content = $content;
        $stmts = null;
        $errors = [];
        try {
            $stmts = $this->parser->parse($content);
        } catch (\PhpParser\Error $e) {
            // Lexer can throw errors. e.g for unterminated comments
            // unfortunately we don't get a location back
            $errors[] = $e;
        }

        $errors = array_merge($this->parser->getErrors(), $errors);

        $diagnostics = [];
        foreach ($errors as $error) {
            $diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::ERROR, 'php');
        }

        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;

            // Resolve aliased names to FQNs
            $traverser->addVisitor(new NameResolver);

            // Add parentNode, previousSibling, nextSibling attributes
            $traverser->addVisitor(new ReferencesAdder($this));

            // Add column attributes to nodes
            $traverser->addVisitor(new ColumnCalculator($content));

            // Parse docblocks and add docBlock attributes to nodes
            $docBlockParser = new DocBlockParser($this->docBlockFactory);
            $traverser->addVisitor($docBlockParser);

            $traverser->traverse($stmts);

            // Report errors from parsing docblocks
            foreach ($docBlockParser->errors as $error) {
                $diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::WARNING, 'php');
            }

            $traverser = new NodeTraverser;

            // Collect all definitions
            $definitionCollector = new DefinitionCollector;
            $traverser->addVisitor($definitionCollector);

            // Collect all references
            $referencesCollector = new ReferencesCollector($this->definitions);
            $traverser->addVisitor($referencesCollector);

            $traverser->traverse($stmts);

            // Register this document on the project for all the symbols defined in it
            $this->definitions = $definitionCollector->definitions;
            foreach ($definitionCollector->definitions as $fqn => $node) {
                $this->project->setDefinitionUri($fqn, $this->uri);
            }

            // Register this document on the project for references
            $this->references = $referencesCollector->references;
            foreach ($referencesCollector->references as $fqn => $nodes) {
                $this->project->addReferenceUri($fqn, $this->uri);
            }

            $this->stmts = $stmts;
        }

        $this->client->textDocument->publishDiagnostics($this->uri, $diagnostics);
    }

    /**
     * Returns array of TextEdit changes to format this document.
     *
     * @return \LanguageServer\Protocol\TextEdit[]
     */
    public function getFormattedText()
    {
        if (empty($this->content)) {
            return [];
        }
        return Formatter::format($this->content, $this->uri);
    }

    /**
     * Returns this document's text content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the URI of the document
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns the AST of the document
     *
     * @return Node[]
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    /**
     * Returns the node at a specified position
     *
     * @param Position $position
     * @return Node|null
     */
    public function getNodeAtPosition(Position $position)
    {
        $traverser = new NodeTraverser;
        $finder = new NodeAtPositionFinder($position);
        $traverser->addVisitor($finder);
        $traverser->traverse($this->stmts);
        return $finder->node;
    }

    /**
     * Returns the definition node for a fully qualified name
     *
     * @param string $fqn
     * @return Node|null
     */
    public function getDefinitionByFqn(string $fqn)
    {
        return $this->definitions[$fqn] ?? null;
    }

    /**
     * Returns a map from fully qualified name (FQN) to Nodes defined in this document
     *
     * @return Node[]
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Returns true if the given FQN is defined in this document
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return bool
     */
    public function isDefined(string $fqn): bool
    {
        return isset($this->definitions[$fqn]);
    }

    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Examples of FQNs:
     *  - testFunction()
     *  - TestNamespace\TestClass
     *  - TestNamespace\TestClass::TEST_CONSTANT
     *  - TestNamespace\TestClass::staticTestProperty
     *  - TestNamespace\TestClass::testProperty
     *  - TestNamespace\TestClass::staticTestMethod()
     *  - TestNamespace\TestClass::testMethod()
     *
     * @param Node $node
     * @return string|null
     */
    public function getDefinedFqn(Node $node)
    {
        // Anonymous classes don't count as a definition
        if ($node instanceof Node\Stmt\ClassLike && isset($node->name)) {
            // Class, interface or trait declaration
            return (string)$node->namespacedName;
        } else if ($node instanceof Node\Stmt\Function_) {
            // Function: use functionName() as the name
            return (string)$node->namespacedName . '()';
        } else if ($node instanceof Node\Stmt\ClassMethod) {
            // Class method: use ClassName::methodName() as name
            $class = $node->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return null;
            }
            return (string)$class->namespacedName . '::' . (string)$node->name . '()';
        } else if ($node instanceof Node\Stmt\PropertyProperty) {
            // Property: use ClassName::propertyName as name
            $class = $node->getAttribute('parentNode')->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return null;
            }
            return (string)$class->namespacedName . '::' . (string)$node->name;
        } else if ($node instanceof Node\Const_) {
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Stmt\Const_) {
                // Basic constant: use CONSTANT_NAME as name
                return (string)$node->namespacedName;
            }
            if ($parent instanceof Node\Stmt\ClassConst) {
                // Class constant: use ClassName::CONSTANT_NAME as name
                $class = $parent->getAttribute('parentNode');
                if (!isset($class->name) || $class->name instanceof Node\Expr) {
                    return null;
                }
                return (string)$class->namespacedName . '::' . $node->name;
            }
        }
    }

    /**
     * Returns the FQN that is referenced by a node
     *
     * @param Node $node
     * @return string|null
     */
    public function getReferencedFqn(Node $node)
    {
        $parent = $node->getAttribute('parentNode');

        if (
            $node instanceof Node\Name && (
                $parent instanceof Node\Stmt\ClassLike
                || $parent instanceof Node\Param
                || $parent instanceof Node\Stmt\Function_
                || $parent instanceof Node\Expr\StaticCall
                || $parent instanceof Node\Expr\ClassConstFetch
                || $parent instanceof Node\Expr\StaticPropertyFetch
            )
        ) {
            // For extends, implements, type hints and classes of classes of static calls use the name directly
            $name = (string)$node;
        // Only the name node should be considered a reference, not the UseUse node itself
        } else if ($parent instanceof Node\Stmt\UseUse) {
            $name = (string)$parent->name;
            $grandParent = $parent->getAttribute('parentNode');
            if ($grandParent instanceof Node\Stmt\GroupUse) {
                $name = $grandParent->prefix . '\\' . $name;
            }
        // Only the name node should be considered a reference, not the New_ node itself
        } else if ($parent instanceof Node\Expr\New_) {
            if (!($parent->class instanceof Node\Name)) {
                // Cannot get definition of dynamic calls
                return null;
            }
            $name = (string)$parent->class;
        } else if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
            if ($node->name instanceof Node\Expr || !($node->var instanceof Node\Expr\Variable)) {
                // Cannot get definition of dynamic calls
                return null;
            }
            // Need to resolve variable to a class
            $varDef = $this->getVariableDefinition($node->var);
            if (!isset($varDef)) {
                return null;
            }
            if ($varDef instanceof Node\Param) {
                if (!isset($varDef->type)) {
                    // Cannot resolve to class without a type hint
                    // TODO: parse docblock
                    return null;
                }
                $name = (string)$varDef->type;
            } else if ($varDef instanceof Node\Expr\Assign) {
                if ($varDef->expr instanceof Node\Expr\New_) {
                    if (!($varDef->expr->class instanceof Node\Name)) {
                        // Cannot get definition of dynamic calls
                        return null;
                    }
                    $name = (string)$varDef->expr->class;
                } else {
                    return null;
                }
            } else {
                return null;
            }
            $name .= '::' . (string)$node->name;
        } else if ($parent instanceof Node\Expr\FuncCall) {
            if ($parent->name instanceof Node\Expr) {
                return null;
            }
            $name = (string)($node->getAttribute('namespacedName') ?? $parent->name);
        } else if ($parent instanceof Node\Expr\ConstFetch) {
            $name = (string)($node->getAttribute('namespacedName') ?? $parent->name);
        } else if (
            $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\StaticCall
        ) {
            if ($node->class instanceof Node\Expr || $node->name instanceof Node\Expr) {
                // Cannot get definition of dynamic names
                return null;
            }
            $name = (string)$node->class . '::' . $node->name;
        } else {
            return null;
        }
        if (
            $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\StaticCall
            || $parent instanceof Node\Expr\FuncCall
        ) {
            $name .= '()';
        }
        if (!isset($name)) {
            return null;
        }
        return $name;
    }

    /**
     * Returns the definition node for any node
     * The definition node MAY be in another document, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Node|null
     */
    public function getDefinitionByNode(Node $node)
    {
        // Variables always stay in the boundary of the file and need to be searched inside their function scope
        // by traversing the AST
        if ($node instanceof Node\Expr\Variable) {
            return $this->getVariableDefinition($node);
        }
        $fqn = $this->getReferencedFqn($node);
        if (!isset($fqn)) {
            return null;
        }
        $document = $this->project->getDefinitionDocument($fqn);
        if (!isset($document)) {
            // If the node is a function or constant, it could be namespaced, but PHP falls back to global
            // http://php.net/manual/en/language.namespaces.fallback.php
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Expr\ConstFetch || $parent instanceof Node\Expr\FuncCall) {
                $parts = explode('\\', $fqn);
                $fqn = end($parts);
                $document = $this->project->getDefinitionDocument($fqn);
            }
        }
        if (!isset($document)) {
            return null;
        }
        return $document->getDefinitionByFqn($fqn);
    }

    /**
     * Returns the reference nodes for any node
     * The references node MAY be in other documents, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Node[]
     */
    public function getReferencesByNode(Node $node)
    {
        // Variables always stay in the boundary of the file and need to be searched inside their function scope
        // by traversing the AST
        if ($node instanceof Node\Expr\Variable || $node instanceof Node\Param) {
            if ($node->name instanceof Node\Expr) {
                return null;
            }
            // Find function/method/closure scope
            $n = $node;
            while (isset($n) && !($n instanceof Node\FunctionLike)) {
                $n = $n->getAttribute('parentNode');
            }
            if (!isset($n)) {
                $n = $node->getAttribute('ownerDocument');
            }
            $traverser = new NodeTraverser;
            $refCollector = new VariableReferencesCollector($node->name);
            $traverser->addVisitor($refCollector);
            $traverser->traverse($n->getStmts());
            return $refCollector->references;
        }
        // Definition with a global FQN
        $fqn = $this->getDefinedFqn($node);
        if ($fqn === null) {
            return [];
        }
        $refDocuments = $this->project->getReferenceDocuments($fqn);
        $nodes = [];
        foreach ($refDocuments as $document) {
            $refs = $document->getReferencesByFqn($fqn);
            if ($refs !== null) {
                foreach ($refs as $ref) {
                    $nodes[] = $ref;
                }
            }
        }
        return $nodes;
    }

    /**
     * Returns the assignment or parameter node where a variable was defined
     *
     * @param Node\Expr\Variable $n The variable access
     * @return Node\Expr\Assign|Node\Param|Node\Expr\ClosureUse|null
     */
    public function getVariableDefinition(Node\Expr\Variable $var)
    {
        $n = $var;
        // Traverse the AST up
        do {
            // If a function is met, check the parameters and use statements
            if ($n instanceof Node\FunctionLike) {
                foreach ($n->getParams() as $param) {
                    if ($param->name === $var->name) {
                        return $param;
                    }
                }
                // If it is a closure, also check use statements
                if ($n instanceof Node\Expr\Closure) {
                    foreach ($n->uses as $use) {
                        if ($use->var === $var->name) {
                            return $use;
                        }
                    }
                }
                break;
            }
            // Check each previous sibling node for a variable assignment to that variable
            while ($n->getAttribute('previousSibling') && $n = $n->getAttribute('previousSibling')) {
                if ($n instanceof Node\Expr\Assign && $n->var instanceof Node\Expr\Variable && $n->var->name === $var->name) {
                    return $n;
                }
            }
        } while (isset($n) && $n = $n->getAttribute('parentNode'));
        // Return null if nothing was found
        return null;
    }
}
