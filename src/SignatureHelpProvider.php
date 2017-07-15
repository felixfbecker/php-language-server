<?php
declare(strict_types = 1);

namespace LanguageServer;

use Microsoft\PhpParser\Node\DelimitedList\ArgumentExpressionList;
use Microsoft\PhpParser\Node\Expression\CallExpression;
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
    public function provideSignature(PhpDocument $doc, Position $pos) : SignatureHelp
    {
        $node = $doc->getNodeAtPosition($pos);
        $nodes = [$node];
        while ($node &&
            !($node instanceof ArgumentExpressionList) &&
            !($node instanceof CallExpression) &&
            $node->parent
        ) {
            $node = $node->parent;
            $nodes[] = $node;
        }
        if (!($node instanceof ArgumentExpressionList) &&
            !($node instanceof CallExpression)
        ) {
            return new SignatureHelp;
        }
        $count = null;
        if ($node instanceof ArgumentExpressionList) {
            $count = 0;
            foreach ($node->getElements() as $param) {
                if (in_array($param, $nodes)) {
                    break;
                }
                $count ++;
            }
            while ($node && !($node instanceof CallExpression) && $node->parent) {
                $node = $node->parent;
            }
            if (!($node instanceof CallExpression)) {
                return new SignatureHelp;
            }
        }
        $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node->callableExpression);
        if (!$def) {
            return new SignatureHelp;
        }
        return new SignatureHelp(
            [
                new SignatureInformation(
                    trim(str_replace(['public', 'protected', 'private', 'function', 'static'], '', $def->declarationLine)),
                    $def->documentation,
                    $def->parameters
                )
            ],
            0,
            $count !== null && $def->parameters !== null && $count < count($def->parameters) ? $count : null
        );
    }
}
