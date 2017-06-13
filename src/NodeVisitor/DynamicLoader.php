<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};

use LanguageServer\{Definition, DefinitionResolver};
use LanguageServer\Protocol\{SymbolInformation, SymbolKind};

/**
 * Collects definitions of classes, interfaces, traits, methods, properties and constants
 * Depends on ReferencesAdder and NameResolver
 */
class DynamicLoader extends NodeVisitorAbstract
{
    public $definitionCollector;
    public $definitionResolver;

    public function __construct(DefinitionCollector $definitionCollector,
																DefinitionResolver $definitionResolver)
    {
        $this->definitionCollector = $definitionCollector;
				$this->definitionResolver = $definitionResolver;
    }

		// using the lowercase $modelName to find the FQN
		public function findModelDef(String $filepath) {
			$filename = $filepath . ".php";
			foreach ($this->definitionCollector->definitions as $def) {
				$fqn = $def->fqn;
				$si = $def->symbolInformation;

				if (!isset($si->location->uri)) continue;
				$uri = strtolower($si->location->uri);

				$endsWith = substr_compare($uri, $filename, strlen($uri) - strlen($filename)) === 0;

				// if the file matches, find the first class
				if ($endsWith && $si->kind === SymbolKind::CLASS_) {
					return $def;
				}
			}
			return NULL;
		}

    public function enterNode(Node $node)
    {
      // check its name is 'model'
      if (!($node instanceof Node\Expr\MethodCall && $node->name === 'model')) {
        return;
      }
        // check its caller is a 'load'
      $caller = $node->getAttribute('previousSibling'); 
      if (!($caller instanceof Node\Expr\PropertyFetch && $caller->name !== 'load')) {
        return;
      }
		
			// make sure the first argument is a string.
			if (!(isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_)) {
				return;
			}
			//if(!($node instanceof Node\Expr\MethodCall && isset($node->args[0]))) return;
			
			$argstr = $node->args[0]->value->value;

			// $node's FQN is "className->memberSuffix()". But we need to construct loaded name.
			$def = $this->findModelDef($argstr);	

			//var_dump($def);
			
			// if we cannot find definition, just return.
			if ($def === NULL) {
				return;	
			}

			$fqn = $def->fqn;
      $resolver = $this->definitionResolver;

      // add fqn to nodes and definitions.
      $this->definitionCollector->nodes[$fqn] = $node;

			// Definition is created based on types and context of $node. we should construct by ourselves.
			$definition = $resolver->createDefinitionFromNode($node, $fqn);
			// Have to override type:
			$definition->type = new Types\Object_;
			// TODO: check if setting document path is correct.

      $this->definitionCollector->definitions[$fqn] = $definition;
    }
}
