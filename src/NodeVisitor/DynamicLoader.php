<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\{NodeVisitorAbstract, Node};
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};

use LanguageServer\{Definition, DefinitionResolver};
use LanguageServer\Protocol\{SymbolInformation, SymbolKind, Location};

/**
 * Collects definitions created dynamically by framework such as CodeIgniter
 */
class DynamicLoader extends NodeVisitorAbstract
{
    public $definitionCollector;
    public $prettyPrinter;

    public function __construct(DefinitionCollector $definitionCollector)
    {
        $this->definitionCollector = $definitionCollector;
        $this->prettyPrinter = new PrettyPrinter;
    }

    public function enterNode(Node $node)
    {
        // check its name is 'model'
        if (!($node instanceof Node\Expr\MethodCall)) {
            return;
        }

        if ($node->name !== 'model' && $node->name !== 'library') {
            return;
        }

        // check its caller is a 'load'
        if (!isset($node->var) || !isset($node->var->name) || $node->var->name !== 'load') {
            return;
        }

        $argSize = count($node->args);
        if ($argSize == 0 || $argSize == 3) { // when argSize = 3 it's loading from db
            return;
        }

        // make sure the first argument is a string.
        if (!($node->args[0]->value instanceof Node\Scalar\String_)) {
            return;
        }

        $argNode = $node->args[0];
        $argstr = $argNode->value->value;
        $argparts = explode('\\', $argstr);
        $modelName = array_pop($argparts);
        $fieldName = $modelName;

        // deal with case like: 	$this->_CI->load->model('users_mdl', 'hahaha');
        if ($argSize == 2) {
            if (!($node->args[1]->value instanceof Node\Scalar\String_)) {
                return;
            }
            $fieldName = $node->args[1]->value->value;
        }

        $enclosedClass = $node;
        $fqn = NULL;
        $classFqn = NULL;
        while ($enclosedClass !== NULL) {
            $enclosedClass = $enclosedClass->getAttribute('parentNode');
            if ($enclosedClass instanceof Node\Stmt\ClassLike && isset($enclosedClass->name)) {
                $classFqn = $enclosedClass->namespacedName->toString();
                $fqn = $classFqn . '->' . $fieldName;
                break;
            }
        }

        // if we cannot find definition, just return.
        if ($fqn === NULL) {
            return;
        }

      // add fqn to nodes and definitions.
        $this->definitionCollector->nodes[$fqn] = $argNode;

        // Create symbol
//        $classFqnParts = preg_split('/(::|->|\\\\)/', $fqn);
//        array_pop($classFqnParts);
//        $classFqn = implode('\\', $classFqnParts);
        $sym = new SymbolInformation($fieldName, SymbolKind::PROPERTY, Location::fromNode($argNode), $classFqn);

        // Create type
        array_push($argparts, ucwords($modelName));
        $typeName = implode('\\', $argparts);
        $type = new Types\Object_(new Fqsen('\\' . $typeName));

        // Create defintion from symbol, type and all others
        $def = new Definition;
        $def->canBeInstantiated = false;
        $def->isGlobal = false; // TODO check the meaning of this, why public field has this set to false?
        $def->isStatic = false; // it should not be a static field
        $def->fqn = $fqn;
        $def->symbolInformation = $sym;
        $def->type = $type;
        // Maybe this is not the best
        $def->declarationLine = $fieldName; // $this->prettyPrinter->prettyPrint([$argNode]);
        $def->documentation = "Dynamically Generated Field: " . $fieldName;

        $this->definitionCollector->definitions[$fqn] = $def;
    }
}
