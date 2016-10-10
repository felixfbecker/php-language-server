<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, SymbolInformation, SymbolKind, TextEdit, Location};
use LanguageServer\NodeVisitor\{NodeAtPositionFinder, ReferencesAdder, DefinitionCollector, ColumnCalculator};
use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer, Parser};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeVisitor\NameResolver;
use Exception;
use function LanguageServer\uriToPath;

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
    private $statements;

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
     * @param string         $uri     The URI of the document
     * @param Project        $project The Project this document belongs to (to register definitions etc)
     * @param LanguageClient $client  The LanguageClient instance (to report errors etc)
     * @param Parser         $parser  The PHPParser instance
     */
    public function __construct(string $uri, Project $project, LanguageClient $client, Parser $parser)
    {
        $this->uri = $uri;
        $this->project = $project;
        $this->client = $client;
        $this->parser = $parser;
    }

    /**
     * Returns true if the content of this document is being held in memory
     *
     * @return bool
     */
    public function isLoaded()
    {
        return isset($this->content);
    }

    /**
     * Loads the content from disk and saves statements and definitions in memory
     *
     * @return void
     */
    public function load()
    {
        $this->updateContent(file_get_contents(uriToPath($this->getUri())), true);
    }

    /**
     * Unloads the content, statements and definitions from memory
     *
     * @return void
     */
    public function unload()
    {
        unset($this->content);
        unset($this->statements);
        unset($this->definitions);
        unset($this->references);
    }

    /**
     * Re-parses a source file, updates symbols and reports parsing errors
     * that may have occured as diagnostics.
     *
     * @param string $content
     * @param bool $keepInMemory Wether to keep content, statements and definitions in memory or only update project definitions
     * @return void
     */
    public function updateContent(string $content, bool $keepInMemory = true)
    {
        $keepInMemory = $keepInMemory || $this->isLoaded();
        if ($keepInMemory) {
            $this->content = $content;
        }
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
            $diagnostic = new Diagnostic();
            $diagnostic->range = new Range(
                new Position($error->getStartLine() - 1, $error->hasColumnInfo() ? $error->getStartColumn($content) - 1 : 0),
                new Position($error->getEndLine() - 1, $error->hasColumnInfo() ? $error->getEndColumn($content) : 0)
            );
            $diagnostic->severity = DiagnosticSeverity::ERROR;
            $diagnostic->source = 'php';
            // Do not include "on line ..." in the error message
            $diagnostic->message = $error->getRawMessage();
            $diagnostics[] = $diagnostic;
        }
        $this->client->textDocument->publishDiagnostics($this->uri, $diagnostics);

        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;

            // Resolve aliased names to FQNs
            $traverser->addVisitor(new NameResolver);

            // Add parentNode, previousSibling, nextSibling attributes
            $traverser->addVisitor(new ReferencesAdder($this));

            // Add column attributes to nodes
            $traverser->addVisitor(new ColumnCalculator($content));

            // Collect all definitions
            $definitionCollector = new DefinitionCollector;
            $traverser->addVisitor($definitionCollector);

            $traverser->traverse($stmts);

            // Register this document on the project for all the symbols defined in it
            foreach ($definitionCollector->definitions as $fqn => $node) {
                $this->project->addDefinitionDocument($fqn, $this);
            }

            if ($keepInMemory) {
                $this->statements = $stmts;
                $this->definitions = $definitionCollector->definitions;
            }
        }
    }

    /**
     * Returns array of TextEdit changes to format this document.
     *
     * @return \LanguageServer\Protocol\TextEdit[]
     */
    public function getFormattedText()
    {
        if (empty($this->getContent())) {
            return [];
        }
        return Formatter::format($this->content, $this->uri);
    }

    /**
     * Returns this document's text content.
     *
     * @return string
     * @throws Exception If the content was not loaded
     */
    public function getContent()
    {
        if (!isset($this->content)) {
            throw new Exception('Content is not loaded');
        }
        return $this->content;
    }

    /**
     * Returns this document's AST.
     *
     * @return Node[]
     */
    public function &getStatements()
    {
        if (!isset($this->statements)) {
            $this->parse($this->getContent());
        }
        return $this->statements;
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
        $traverser->traverse($this->getStatements());
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
        return $this->getDefinitions()[$fqn] ?? null;
    }

    /**
     * Returns a map from fully qualified name (FQN) to Nodes defined in this document
     *
     * @return Node[]
     * @throws Exception If the definitions are not loaded
     */
    public function &getDefinitions()
    {
        if (!isset($this->definitions)) {
            throw new Exception('Definitions of this document are not loaded');
        }
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
        return isset($this->getDefinitions()[$fqn]);
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
        if ($node instanceof Node\Name) {
            $nameNode = $node;
            $node = $node->getAttribute('parentNode');
        }
        // Only the class node should count as the definition, not the name node
        // Anonymous classes don't count as a definition
        if ($node instanceof Node\Stmt\ClassLike && !isset($nameNode) && isset($node->name)) {
            // Class, interface or trait declaration
            return (string)$node->namespacedName;
        } else if ($node instanceof Node\Stmt\Function_ && !isset($nameNode)) {
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
        if ($node instanceof Node\Name) {
            $nameNode = $node;
            $node = $node->getAttribute('parentNode');
        }

        if (
            ($node instanceof Node\Stmt\ClassLike
            || $node instanceof Node\Param
            || $node instanceof Node\Stmt\Function_)
            && isset($nameNode)
        ) {
            // For extends, implements and type hints use the name directly
            $name = (string)$nameNode;
        // Only the name node should be considered a reference, not the UseUse node itself
        } else if ($node instanceof Node\Stmt\UseUse && isset($nameNode)) {
            $name = (string)$node->name;
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Stmt\GroupUse) {
                $name = $parent->prefix . '\\' . $name;
            }
        // Only the name node should be considered a reference, not the New_ node itself
        } else if ($node instanceof Node\Expr\New_ && isset($nameNode)) {
            if (!($node->class instanceof Node\Name)) {
                // Cannot get definition of dynamic calls
                return null;
            }
            $name = (string)$node->class;
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
        } else if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Expr) {
                return null;
            }
            $name = (string)$node->name;
        } else if ($node instanceof Node\Expr\ConstFetch) {
            $name = (string)$node->name;
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
        }
        if (
            $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\FuncCall
            || $node instanceof Node\Expr\StaticCall
        ) {
            $name .= '()';
        }
        if (!isset($name)) {
            return null;
        }
        // If the node is a function or constant, it could be namespaced, but PHP falls back to global
        // The NameResolver therefor does not resolve these to namespaced names
        // http://php.net/manual/en/language.namespaces.fallback.php
        if ($node instanceof Node\Expr\FuncCall || $node instanceof Node\Expr\ConstFetch) {
            // Find and try with namespace
            $n = $node;
            while (isset($n)) {
                $n = $n->getAttribute('parentNode');
                if ($n instanceof Node\Stmt\Namespace_) {
                    $namespacedName = (string)$n->name . '\\' . $name;
                    // If the namespaced version is defined, return that
                    // Otherwise fall back to global
                    if ($this->project->isDefined($namespacedName)) {
                        return $namespacedName;
                    }
                }
            }
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
        if (!$document->isLoaded()) {
            $document->load();
        }
        if (!isset($document)) {
            return null;
        }
        return $document->getDefinitionByFqn($fqn);
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
        while (isset($n) && $n = $n->getAttribute('parentNode')) {
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
        }
        // Return null if nothing was found
        return null;
    }
}
