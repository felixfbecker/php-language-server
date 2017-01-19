<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\{
    Position,
    SignatureHelp,
    SignatureInformation,
    ParameterInformation
};

class SignatureHelpProvider
{
    /**
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * @var ReadableIndex
     */
    private $index;

    /**
     * @param DefinitionResolver $definitionResolver
     * @param ReadableIndex      $index
     */
    public function __construct(DefinitionResolver $definitionResolver, ReadableIndex $index)
    {
        $this->definitionResolver = $definitionResolver;
        $this->index = $index;
    }

    /**
     * Returns signature help for a specific cursor position in a document
     *
     * @param PhpDocument $doc The opened document
     * @param Position $pos The cursor position
     * @return SignatureHelp
     */
    public function provideSignature(PhpDocument $doc, Position $pos): SignatureHelp
    {
        $help = new SignatureHelp;
        $help->signatures = [];

        $newPos = clone $pos;
        $line = explode("\n", $doc->getContent())[$newPos->line];
        do {
            $newPos->character --;
        } while ($newPos->character > 0 && $line[$newPos->character] !== "(");

        if (!$newPos->character) {
            return $help;
        }
        $line = substr($line, 0, $newPos->character);
        
        //echo $line . "\n";
        //die();
        $newPos->character --;

        $node = $doc->getNodeAtPosition($newPos);

        if ($node instanceof Node\Expr\Error) {
            $node = $node->getAttribute('parentNode');
        }
        
        //echo get_class($node);
        //die();
        //$def = $this->definitionResolver->resolveReferenceNodeToDefinition($node->var);
        //var_dump($def);
        //echo $def->fqn;

        //echo $node->name;

        
        //die();

        if ($node instanceof Node\Expr\Error) {
            $node = $node->getAttribute('parentNode');
        }
        if ($node instanceof Node\Expr\FuncCall) {
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node)) {
                $signature = new SignatureInformation;
                $signature->label = str_replace('()', '', $def->fqn);
                $signature->documentation = $def->documentation;
                $signature->parameters = [];
                foreach ($def->parameters as $param) {
                    $p = new ParameterInformation;
                    $p->label = $param;
                    $signature->parameters[] = $p;
                }
                $help->signatures[] = $signature;
            }
        } else if ($node instanceof Node\Name\FullyQualified || $node === null) {
            if (preg_match('([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$)', $line, $method)) {
                $fqn = $method[0] . '()';
                if ($def = $this->index->getDefinition($fqn)) {
                    $signature = new SignatureInformation;
                    $signature->label = $method[0];
                    $signature->documentation = $def->documentation;
                    $signature->parameters = [];
                    foreach ($def->parameters as $param) {
                        $p = new ParameterInformation;
                        $p->label = $param;
                        $signature->parameters[] = $p;
                    }
                    $help->signatures[] = $signature;
                }
            }
        } else if ($node instanceof Node\Expr\MethodCall) {
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node)) {
                $signature = new SignatureInformation;
                $signature->label = str_replace('()', '', explode('->', $def->fqn)[1]);
                $signature->documentation = $def->documentation;
                $signature->parameters = [];
                foreach ($def->parameters as $param) {
                    $p = new ParameterInformation;
                    $p->label = $param;
                    $signature->parameters[] = $p;
                }
                $help->signatures[] = $signature;
            }
        } else if ($node instanceof Node\Expr\PropertyFetch) {
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node->var)) {
                $method = trim(substr($line, strrpos($line, ">") + 1));
                if ($method) {
                    $fqn = $def->fqn . '->' . $method . '()';
                    if ($def = $this->index->getDefinition($fqn)) {
                        $signature = new SignatureInformation;
                        $signature->label = str_replace('()', '', explode('->', $def->fqn)[1]);
                        $signature->documentation = $def->documentation;
                        $signature->parameters = [];
                        foreach ($def->parameters as $param) {
                            $p = new ParameterInformation;
                            $p->label = $param;
                            $signature->parameters[] = $p;
                        }
                        $help->signatures[] = $signature;
                    }
                }
            }
        } else if ($node instanceof Node\Expr\StaticCall) {
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node)) {
                $signature = new SignatureInformation;
                $signature->label = str_replace('()', '', explode('::', $def->fqn)[1]);
                $signature->documentation = $def->documentation;
                $signature->parameters = [];
                foreach ($def->parameters as $param) {
                    $p = new ParameterInformation;
                    $p->label = $param;
                    $signature->parameters[] = $p;
                }
                $help->signatures[] = $signature;
            }
        } else if ($node instanceof Node\Expr\ClassConstFetch) {
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node->class)) {
                $method = trim(substr($line, strrpos($line, ":") + 1));
                if ($method) {
                    $fqn = $def->fqn . '::' . $method . '()';
                    if ($def = $this->index->getDefinition($fqn)) {
                        $signature = new SignatureInformation;
                        $signature->label = str_replace('()', '', explode('::', $def->fqn)[1]);
                        $signature->documentation = $def->documentation;
                        $signature->parameters = [];
                        foreach ($def->parameters as $param) {
                            $p = new ParameterInformation;
                            $p->label = $param;
                            $signature->parameters[] = $p;
                        }
                        $help->signatures[] = $signature;
                    }
                }
            }
        }

        return $help;
    }
}
