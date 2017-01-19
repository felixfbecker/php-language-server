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
        $node = $doc->getNodeAtPosition($pos);
        $help = new SignatureHelp;
        $help->signatures = [];

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
        } else if ($node instanceof Node\Expr\StaticCall) {
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

        return $help;
    }
}
