<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, SymbolInformation, SymbolKind, TextEdit, Location};
use LanguageServer\NodeVisitors\{NodeAtPositionFinder, ReferencesAdder, DefinitionCollector, ColumnCalculator};
use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer, Parser};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeVisitor\NameResolver;

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
    private $stmts = [];

    /**
     * Map from fully qualified name (FQN) to Node
     * Examples of fully qualified names:
     *  - testFunction()
     *  - TestNamespace\TestClass
     *  - TestNamespace\TestClass::TEST_CONSTANT
     *  - TestNamespace\TestClass::staticTestProperty
     *  - TestNamespace\TestClass::testProperty
     *  - TestNamespace\TestClass::staticTestMethod()
     *  - TestNamespace\TestClass::testMethod()
     *
     * @var Node[]
     */
    private $definitions = [];

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
     * Returns all symbols in this document.
     *
     * @return SymbolInformation[]|null
     */
    public function getSymbols()
    {
        if (!isset($this->definitions)) {
            return null;
        }
        $nodeSymbolKindMap = [
            Node\Stmt\Class_::class           => SymbolKind::CLASS_,
            Node\Stmt\Trait_::class           => SymbolKind::CLASS_,
            Node\Stmt\Interface_::class       => SymbolKind::INTERFACE,
            Node\Stmt\Namespace_::class       => SymbolKind::NAMESPACE,
            Node\Stmt\Function_::class        => SymbolKind::FUNCTION,
            Node\Stmt\ClassMethod::class      => SymbolKind::METHOD,
            Node\Stmt\PropertyProperty::class => SymbolKind::PROPERTY,
            Node\Const_::class                => SymbolKind::CONSTANT
        ];
        $symbols = [];
        foreach ($this->definitions as $fqn => $node) {
            $symbol = new SymbolInformation();
            $symbol->kind = $nodeSymbolKindMap[get_class($node)];
            $symbol->name = (string)$node->name;
            $symbol->location = new Location(
                $this->getUri(),
                new Range(
                    new Position($node->getAttribute('startLine') - 1, $node->getAttribute('startColumn') - 1),
                    new Position($node->getAttribute('endLine') - 1, $node->getAttribute('endColumn'))
                )
            );
            $parts = preg_split('/(::|\\\\)/', $fqn);
            array_pop($parts);
            $symbol->containerName = implode('\\', $parts);
            $symbols[] = $symbol;
        }
        return $symbols;
    }

    /**
     * Returns symbols in this document filtered by query string.
     *
     * @param string $query The search query
     * @return SymbolInformation[]|null
     */
    public function findSymbols(string $query)
    {
        $symbols = $this->getSymbols();
        if ($symbols === null) {
            return null;
        }
        return array_filter($symbols, function($symbol) use ($query) {
            return stripos($symbol->name, $query) !== false;
        });
    }

    /**
     * Updates the content on this document.
     *
     * @param string $content
     * @return void
     */
    public function updateContent(string $content)
    {
        $this->content = $content;
        $this->parse();
    }

    /**
     * Re-parses a source file, updates symbols, reports parsing errors
     * that may have occured as diagnostics and returns parsed nodes.
     *
     * @return void
     */
    public function parse()
    {
        $stmts = null;
        $errors = [];
        try {
            $stmts = $this->parser->parse($this->content);
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
                new Position($error->getStartLine() - 1, $error->hasColumnInfo() ? $error->getStartColumn($this->content) - 1 : 0),
                new Position($error->getEndLine() - 1, $error->hasColumnInfo() ? $error->getEndColumn($this->content) : 0)
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
            $traverser->addVisitor(new ColumnCalculator($this->content));

            // Collect all definitions
            $definitionCollector = new DefinitionCollector;
            $traverser->addVisitor($definitionCollector);

            $traverser->traverse($stmts);

            $this->definitions = $definitionCollector->definitions;
            // Register this document on the project for all the symbols defined in it
            foreach ($definitionCollector->definitions as $fqn => $node) {
                $this->project->addDefinitionDocument($fqn, $this);
            }

            $this->stmts = $stmts;
        }
    }

    /**
     * Returns this document as formatted text.
     *
     * @return string
     */
    public function getFormattedText()
    {
        if (empty($this->stmts)) {
            return [];
        }
        $prettyPrinter = new PrettyPrinter();
        $edit = new TextEdit();
        $edit->range = new Range(new Position(0, 0), new Position(PHP_INT_MAX, PHP_INT_MAX));
        $edit->newText = $prettyPrinter->prettyPrintFile($this->stmts);
        return [$edit];
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
     * Returns the node at a specified position
     *
     * @param Position $position
     * @return Node|null
     */
    public function getNodeAtPosition(Position $position)
    {
        if ($this->stmts === null) {
            return null;
        }
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
     * Returns the definition node for any node
     * The definition node MAY be in another document, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Node|null
     */
    public function getDefinitionByNode(Node $node)
    {
        if ($node instanceof Node\Name) {
            $nameNode = $node;
            $node = $node->getAttribute('parentNode');
        }
        // Variables always stay in the boundary of the file and need to be searched inside their function scope
        // by traversing the AST
        if ($node instanceof Node\Expr\Variable) {
            return $this->getVariableDefinition($node);
        }

        if (
            ($node instanceof Node\Stmt\ClassLike
            || $node instanceof Node\Param
            || $node instanceof Node\Stmt\Function_)
            && isset($nameNode)
        ) {
            // For extends, implements and type hints use the name directly
            $name = (string)$nameNode;
        } else if ($node instanceof Node\Stmt\UseUse) {
            $name = (string)$node->name;
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Stmt\GroupUse) {
                $name = $parent->prefix . '\\' . $name;
            }
        } else if ($node instanceof Node\Expr\New_) {
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
        // Search for the document where the class, interface, trait, function, method or property is defined
        $document = $this->project->getDefinitionDocument($name);
        if (!$document && $node instanceof Node\Expr\FuncCall) {
            // Find and try with namespace
            // Namespaces aren't added automatically by NameResolver because PHP falls back to global functions
            $n = $node;
            while (isset($n)) {
                $n = $n->getAttribute('parentNode');
                if ($n instanceof Node\Stmt\Namespace_) {
                    $name = (string)$n->name . '\\' . $name;
                    $document = $this->project->getDefinitionDocument($name);
                    break;
                }
            }
        }
        if (!isset($document)) {
            return null;
        }
        return $document->getDefinitionByFqn($name);
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
                if ($n instanceof Node\Expr\Assign && $n->var->name === $var->name) {
                    return $n;
                }
            }
        }
        // Return null if nothing was found
        return null;
    }
}
