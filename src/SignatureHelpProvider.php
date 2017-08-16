<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\{
    Position,
    SignatureHelp,
    SignatureInformation,
    ParameterInformation
};
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;

class SignatureHelpProvider
{
    /** @var DefinitionResolver */
    private $definitionResolver;

    /** @var ReadableIndex */
    private $index;

    private $documentLoader;

    /**
     * @param DefinitionResolver $definitionResolver
     * @param ReadableIndex      $index
     */
    public function __construct(DefinitionResolver $definitionResolver, ReadableIndex $index, PhpDocumentLoader $documentLoader)
    {
        $this->definitionResolver = $definitionResolver;
        $this->index = $index;
        $this->documentLoader = $documentLoader;
    }

    public function getSignatureHelp(PhpDocument $doc, Position $position): SignatureHelp
    {
        // Find the node under the cursor
        $node = $doc->getNodeAtPosition($position);

        $fqn = null;

        // First find the node that the call belongs to
        if ($node instanceof Node\DelimitedList\ArgumentExpressionList) {
            $argumentExpressionList = $node;
            if ($node->parent instanceof Node\Expression\ObjectCreationExpression) {
                $node = $node->parent->classTypeDesignator;
                if (!$node instanceof Node\QualifiedName) {
                    return new SignatureHelp();
                }
                $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node);
                $fqn = "{$fqn}->__construct()";
            } else {
                $node = $node->parent->getFirstChildNode(
                    Node\Expression\MemberAccessExpression::class,
                    Node\Expression\ScopedPropertyAccessExpression::class,
                    Node\QualifiedName::class
                );
            }
        } elseif ($node instanceof Node\Expression\CallExpression) {
            $argumentExpressionList = $node->getFirstChildNode(Node\DelimitedList\ArgumentExpressionList::class);
            $node = $node->getFirstChildNode(
                Node\Expression\MemberAccessExpression::class,
                Node\Expression\ScopedPropertyAccessExpression::class,
                Node\QualifiedName::class
            );
        } elseif ($node instanceof Node\Expression\ObjectCreationExpression) {
            $argumentExpressionList = $node->getFirstChildNode(Node\DelimitedList\ArgumentExpressionList::class);
            //$node = $node->getFirstChildNode(Node\QualifiedName::class);
            $node = $node->classTypeDesignator;
            if (!$node instanceof Node\QualifiedName) {
                return new SignatureHelp();
            }
            $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node);
            $fqn = "{$fqn}->__construct()";
        } else {
            $node = null;
        }

        if (!$node) {
            return new SignatureHelp();
        }

        // Now find the definition of the call
        $fqn = $fqn ?: DefinitionResolver::getDefinedFqn($node);
        if ($fqn) {
            $def = $this->index->getDefinition($fqn);
        } else {
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node);
        }

        if (!$def) {
            return new SignatureHelp();
        }

        $activeParam = $argumentExpressionList
            ? $this->findActiveParameter($argumentExpressionList, $position, $doc)
            : 0;

        $doc = $this->documentLoader->get($def->symbolInformation->location->uri);
        if (!$doc) {
            return new SignatureHelp();
        }
        $node = $doc->getNodeAtPosition($def->symbolInformation->location->range->start);
        $params = $this->getParameters($node, $doc);
        $label = $this->getLabel($node, $params, $doc);
        $signatureInformation = new SignatureInformation();
        $signatureInformation->label = $label;
        $signatureInformation->parameters = $params;
        $signatureInformation->documentation = $this->definitionResolver->getDocumentationFromNode($node);
        $signatureHelp = new SignatureHelp();
        $signatureHelp->signatures = [$signatureInformation];
        $signatureHelp->activeSignature = 0;
        $signatureHelp->activeParameter = $activeParam;
        return $signatureHelp;
    }

    /**
     * @param Node\MethodDeclaration|Node\Statement\FunctionDeclaration $node
     */
    private function getLabel($node, array $params, PhpDocument $doc): string
    {
        //$label = $node->getName() . '(';
        $label = '(';
        if ($params) {
            foreach ($params as $param) {
                $label .= $param->label . ', ';
            }
            $label = substr($label, 0, -2);
        }
        $label .= ')';
        /*
        if ($node->returnType) {
            $label .= ': ';
            if ($node->returnType instanceof QualifiedName) {
                $label .= $node->returnType->getResolvedName();
            } else {
                $label .= $node->returnType->getText($doc->getContent());
            }
        }
        */
        return $label;
    }

    /**
     * @param Node\MethodDeclaration|Node\Statement\FunctionDeclaration $node
     */
    private function getParameters($node, PhpDocument $doc): array
    {
        $params = [];
        if ($node->parameters) {
            foreach ($node->parameters->getElements() as $element) {
                $param = (string) $this->definitionResolver->getTypeFromNode($element);
                $param .= ' ' . $element->variableName->getText($doc->getContent());
                if ($element->default) {
                    $param .= ' = ' . $element->default->getText($doc->getContent());
                }
                $info = new ParameterInformation();
                $info->label = $param;
                $info->documentation = $this->definitionResolver->getDocumentationFromNode($element);
                $params[] = $info;
            }
        }
        return $params;
    }

    private function findActiveParameter(
        Node\DelimitedList\ArgumentExpressionList $argumentExpressionList,
        Position $position,
        PhpDocument $doc
    ): int {
        $args = $argumentExpressionList->children;
        $i = 0;
        $found = null;
        foreach ($args as $arg) {
            if ($arg instanceof Node) {
                $start = $arg->getFullStart();
                $end = $arg->getEndPosition();
                ++$i;
            } else {
                $start = $arg->fullStart;
                $end = $start + $arg->length;
            }
            $offset = $position->toOffset($doc->getContent());
            if ($offset >= $start && $offset <= $end) {
                $found = $i;
                break;
            }
        }
        if (is_null($found)) {
            $found = $i;
        }
        return $found;
    }
}
