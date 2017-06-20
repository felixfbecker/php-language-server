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
    public $definitionResolver;
    
    private $collectAutoload;

    public function __construct(DefinitionCollector $definitionCollector, DefinitionResolver $definitionResolver, bool $collectAutoload)
    {
        $this->definitionCollector = $definitionCollector;
        $this->definitionResolver = $definitionResolver;
        $this->collectAutoload = $collectAutoload;
        $this->prettyPrinter = new PrettyPrinter;
    }

    public function visitAutoloadClassDeclaration(Node $node) {
      if (!($node instanceof Node\Stmt\Class_)) {
        return;
      }

      $extends = $node->extends;
      if (!isset($extends->parts)) {
          return;
      }
      $shouldAutoload = false;
      foreach ($extends->parts as $part) {
        // TODO: add more criteria here?
        if ($part === "CI_Controller" || $part === "ST_Controller" ||
            $part === "ST_Auth_Controller") {
          $shouldAutoload = true;
          break;
        }
      }

      if (!$shouldAutoload) {
        return;
      }

      if (isset($this->definitionResolver->autoloadLibraries)) {
        foreach ($this->definitionResolver->autoloadLibraries as $key => $value) {
          $this->createAutoloadDefinition($node, $value);
        }
      }

      if (isset($this->definitionResolver->autoloadModels)) {
        foreach ($this->definitionResolver->autoloadModels as $key => $value) {
          $this->createAutoloadDefinition($node, $value);
        }
      }

      if (isset($this->definitionResolver->autoloadHelpers)) {
        foreach ($this->definitionResolver->autoloadHelpers as $key => $value) {
          $this->createAutoloadDefinition($node, $value);
        }
      }

      if (isset($this->definitionResolver->autoloadConfig)) {
        foreach ($this->definitionResolver->autoloadConfig as $key => $value) {
          $this->createAutoloadDefinition($node, $value);
        }
      }

      if (isset($this->definitionResolver->autoloadLanguage)) {
        foreach ($this->definitionResolver->autoloadLanguage as $key => $value) {
          $this->createAutoloadDefinition($node, $value);
        }
      }
    }

    public function visitAutoloadNode(Node $node) {
      // looking at array assignments.
      if (!($node instanceof Node\Expr\Assign)) {
        return;
      }

      // check left hand side.
      $lhs = $node->var;
      if (!($lhs instanceof Node\Expr\ArrayDimFetch)) {
        return;
      }

      $dimFetchVar = $lhs->var;
      if (!($dimFetchVar instanceof Node\Expr\Variable)) {
        return;
      }

      if ($dimFetchVar->name !== "autoload") {
        return;
      }
      // end of checking left hand side.

      $dim = $lhs->dim;
      if (!($dim instanceof Node\Scalar\String_)) {
        return;
      }
      // TODO: support more than libraries
      $target = $dim->value;

      // extract right hand side.
      $rhs = $node->expr;
      if (!($rhs instanceof Node\Expr\Array_)) {
        return;
      }
    
      // $target -> $node reference
      $arrayOfLibs = $rhs->items;
      foreach ($arrayOfLibs as $lib) {
        $libName = $lib->value->value;
        switch ($target) {
          case "libraries":
            $this->definitionResolver->autoloadLibraries[$libName] = $lib;
            break;
          case "helper":
            $this->definitionResolver->autoloadHelpers[$libName] = $lib;
            break;
          case "config":
            $this->definitionResolver->autoloadConfig[$libName] = $lib;
            break;
          case "model":
            $this->definitionResolver->autoloadModels[$libName] = $lib;
            break;
          case "language":
            $this->definitionResolver->autoloadLanguage[$libName] = $lib;
            break;
        }
      }

    }

    public function enterNode(Node $node)
    {
        // handling autoloading.
        if ($this->collectAutoload) {
          // records autoloading fields into definition resolver.
          $this->visitAutoloadNode($node);
        }

        // spits autoloading fields to a class that is derived from controller classes.
        $this->visitAutoloadClassDeclaration($node);

        // The follwoing is for handling dynamic loading. (Finished)

        // check its name is 'model', 'library' or 'helper'.
        if (!($node instanceof Node\Expr\MethodCall)) {
            return;
        }

        if ($node->name !== 'model' && $node->name !== 'library' &&  $node->name !== 'helper') {
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

        $nameNode = NULL;
        if ($node->args[0]->value instanceof Node\Scalar\String_) {
            // make sure the first argument is a string.

            if ($argSize == 2) {
                $nameNode = $node->args[1]->value;
            }
            $this->createDefinition($node, $node->args[0]->value, $nameNode);
        } else if ($node->args[0]->value instanceof Node\Expr\Array_) {
            $elems = $node->args[0]->value->items;
            foreach ($elems as $item) {
                if ($item->value instanceof Node\Scalar\String_) {
                    $this->createDefinition($node, $item->value, $nameNode);
                }
            }
        }
    }

    // copied from createDefinition and tailored.
    public function createAutoloadDefinition(Node $classNode, Node $entityNode)
    {
        $fieldName = $entityNode->value->value;

        $enclosedClass = $classNode;
        $classFqn = $enclosedClass->namespacedName->toString();
        $fqn = $classFqn . "->" . $fieldName;

        // if we cannot find definition, just return.
        if ($fqn === NULL) {
            return;
        }

        // add fqn to nodes and definitions.
        $this->definitionCollector->nodes[$fqn] = $entityNode;

        // Create symbol
//        $classFqnParts = preg_split('/(::|->|\\\\)/', $fqn);
//        array_pop($classFqnParts);
//        $classFqn = implode('\\', $classFqnParts);
        $sym = new SymbolInformation($fieldName, SymbolKind::PROPERTY, Location::fromNode($entityNode), $classFqn);

        // Create type
        // array_push($entityParts, ucwords($enityName));
        // $typeName = implode('\\', $entityParts);
        $typeName = ucwords($fieldName);
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

    public function createDefinition($callNode, $entityNode, $nameNode)
    {
        $entityString = $entityNode->value;
        $entityParts = explode('/', $entityString);
        $enityName = array_pop($entityParts);
        $fieldName = $enityName;

        // deal with case like:   $this->_CI->load->model('users_mdl', 'hahaha');
        if ($callNode->name = "model" && $nameNode !== NULL) {
            if (!($nameNode instanceof Node\Scalar\String_)) {
                return;
            }
            $fieldName = $nameNode->value;
        }

        $enclosedClass = $callNode;
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
        $this->definitionCollector->nodes[$fqn] = $entityNode;

        // Create symbol
//        $classFqnParts = preg_split('/(::|->|\\\\)/', $fqn);
//        array_pop($classFqnParts);
//        $classFqn = implode('\\', $classFqnParts);
        $sym = new SymbolInformation($fieldName, SymbolKind::PROPERTY, Location::fromNode($entityNode), $classFqn);

        // Create type
        // array_push($entityParts, ucwords($enityName));
        // $typeName = implode('\\', $entityParts);
        $typeName = ucwords($enityName);
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
