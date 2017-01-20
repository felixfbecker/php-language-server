<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\{
    Range,
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
     * @var Parser
     */
    private $parser;

    /**
     * @var Parser
     */
    private $parserErrorHandler;

    /**
     * @param DefinitionResolver $definitionResolver
     * @param ReadableIndex      $index
     */
    public function __construct(DefinitionResolver $definitionResolver, ReadableIndex $index)
    {
        $this->definitionResolver = $definitionResolver;
        $this->index = $index;
        $this->parser = new Parser;
        $this->parserErrorHandler = new Collecting;
    }

    /**
     * Returns signature help for a specific cursor position in a document
     *
     * @param PhpDocument $doc The opened document
     * @param Position $pos The cursor position
     * @return SignatureHelp
     */
    public function provideSignature(PhpDocument $doc, Position $pos) : SignatureHelp
    {
        $help = new SignatureHelp;
        $help->signatures = [];

        $handle = fopen($doc->getUri(), 'r');
        $lines = [];
        for ($i = 0; $i < $pos->line; $i++) {
            $lines[] = strlen(fgets($handle));
        }
        $filePos = ftell($handle) + $pos->character;
        $line = substr(fgets($handle), 0, $pos->character);
        fseek($handle, 0);
        
        do {
            $node = $doc->getNodeAtPosition($pos);
            $pos->character--;
            if ($pos->character < 0) {
                $pos->line --;
                if ($pos->line < 0) {
                    break;
                }
                $pos->character = $lines[$pos->line];
            }
        } while ($node === null);

        if ($node === null) {
            fclose($handle);
            return $help;
        }
        $i = 0;
        while (!(
            $node instanceof Node\Expr\PropertyFetch ||
            $node instanceof Node\Expr\MethodCall ||
            $node instanceof Node\Expr\FuncCall ||
            $node instanceof Node\Expr\ClassConstFetch ||
            $node instanceof Node\Expr\StaticCall
        ) && ++$i < 5 && $node !== null) {
            $node = $node->getAttribute('parentNode');
        }
        $params = '';
        if ($node instanceof Node\Expr\PropertyFetch) {
            fseek($handle, $node->name->getAttribute('startFilePos'));
            $method = fread($handle, ($node->name->getAttribute('endFilePos') + 1) - $node->name->getAttribute('startFilePos'));
            fseek($handle, $node->name->getAttribute('endFilePos') + 1);
            $params = fread($handle, ($filePos - 1) - $node->name->getAttribute('endFilePos'));
            if ($def = $this->definitionResolver->resolveReferenceNodeToDefinition($node->var)) {
                $fqn = $def->fqn;
                if (!$fqn) {
                    $fqns = DefinitionResolver::getFqnsFromType(
                        $this->definitionResolver->resolveExpressionNodeToType($node->var)
                    );
                    if (count($fqns)) {
                        $fqn = $fqns[0];
                    }
                }
                if ($fqn) {
                    $fqn = $fqn . '->' . $method . '()';
                    $def = $this->index->getDefinition($fqn);
                }
            }
        } else if ($node instanceof Node\Expr\MethodCall) {
            fseek($handle, $node->getAttribute('startFilePos'));
            $params = explode('(', fread($handle, $filePos - $node->getAttribute('startFilePos')), 2)[1];
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node);
        } else if ($node instanceof Node\Expr\FuncCall) {
            fseek($handle, $node->getAttribute('startFilePos'));
            $params = explode('(', fread($handle, $filePos - $node->getAttribute('startFilePos')), 2)[1];
            $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node->name);
            $def = $this->index->getDefinition($fqn);
        } else if ($node instanceof Node\Expr\StaticCall) {
            fseek($handle, $node->getAttribute('startFilePos'));
            $params = explode('(', fread($handle, $filePos - $node->getAttribute('startFilePos')), 2)[1];
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node);
        } else if ($node instanceof Node\Expr\ClassConstFetch) {
            fseek($handle, $node->name->getAttribute('endFilePos') + 2);
            $params = fread($handle, ($filePos - 1) - $node->name->getAttribute('endFilePos'));
            fseek($handle, $node->name->getAttribute('startFilePos'));
            $method = fread($handle, ($node->name->getAttribute('endFilePos') + 1) - $node->name->getAttribute('startFilePos'));
            $method = explode('::', str_replace('()', '', $method), 2);
            $method = $method[1] ?? $method[0];
            $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node->class);
            $def = $this->index->getDefinition($fqn.'::'.$method.'()');
        } else {
            if (!preg_match('(([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\((.*)$)', $line, $method)) {
                fclose($handle);
                return $help;
            }
            $def = $this->index->getDefinition($method[1] . '()');
            $params = $method[2];
        }
        fclose($handle);

        if ($def) {
            $method = preg_split('(::|->)', str_replace('()', '', $def->fqn), 2);
            $method = $method[1] ?? $method[0];
            $signature = new SignatureInformation;
            $signature->label = $method . '('.implode(', ', $def->parameters).')';
            $signature->documentation = $def->documentation;
            $signature->parameters = [];
            foreach ($def->parameters as $param) {
                $p = new ParameterInformation;
                $p->label = $param;
                $signature->parameters[] = $p;
            }
            $help->activeSignature = 0;
            $help->activeParameter = 0;
            $params = ltrim($params, "( ");
            if (strlen(trim($params))) {
                try {
                    $lex = new \PhpParser\Lexer();
                    $lex->startLexing('<?php $a = [ ' . $params, $this->parserErrorHandler);
                    $value = null;
                    $lex->getNextToken($value);
                    $lex->getNextToken($value);
                    $lex->getNextToken($value);
                    $params = 0;
                    $stack = [];
                    while ($value !== "\0") {
                        $lex->getNextToken($value);
                        if ($value === ',' && !count($stack)) {
                            $help->activeParameter++;
                        }
                        if ($value === '(') {
                            $stack[] = ')';
                        } else if ($value === '[') {
                            $stack[] = ']';
                        } else if (count($stack) && $value === $stack[count($stack)-1]) {
                            array_pop($stack);
                        }
                    }
                } catch (\Exception $ignore) { }
            }
            $help->signatures[] = $signature;
        }

        return $help;
    }
}
