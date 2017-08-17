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

    /** @var PhpDocumentLoader */
    private $documentLoader;

    /**
     * Constructor
     *
     * @param DefinitionResolver $definitionResolver
     * @param ReadableIndex      $index
     * @param PhpDocumentLoader  $documentLoader
     */
    public function __construct(DefinitionResolver $definitionResolver, ReadableIndex $index, PhpDocumentLoader $documentLoader)
    {
        $this->definitionResolver = $definitionResolver;
        $this->index = $index;
        $this->documentLoader = $documentLoader;
    }

    /**
     * Finds signature help for a callable position
     *
     * @param PhpDocument $doc      The document the position belongs to
     * @param Position    $position The position to detect a call from
     *
     * @return SignatureHelp
     */
    public function getSignatureHelp(PhpDocument $doc, Position $position): SignatureHelp
    {
        // Find the node under the cursor
        $node = $doc->getNodeAtPosition($position);

        // Find the definition of the item being called
        list($def, $argumentExpressionList) = $this->getCallingInfo($node);

        if (!$def) {
            return new SignatureHelp();
        }

        // Find the active parameter
        $activeParam = $argumentExpressionList
            ? $this->findActiveParameter($argumentExpressionList, $position, $doc)
            : 0;

        // Get information from the item being called to build the signature information
        $calledDoc = $this->documentLoader->get($def->symbolInformation->location->uri);
        if (!$calledDoc) {
            return new SignatureHelp();
        }
        $calledNode = $calledDoc->getNodeAtPosition($def->symbolInformation->location->range->start);
        $params = $this->getParameters($calledNode, $calledDoc);
        $label = $this->getLabel($calledNode, $params, $calledDoc);

        $signatureInformation = new SignatureInformation();
        $signatureInformation->label = $label;
        $signatureInformation->parameters = $params;
        $signatureInformation->documentation = $this->definitionResolver->getDocumentationFromNode($calledNode);
        $signatureHelp = new SignatureHelp();
        $signatureHelp->signatures = [$signatureInformation];
        $signatureHelp->activeSignature = 0;
        $signatureHelp->activeParameter = $activeParam;
        return $signatureHelp;
    }

    /**
     * Given a node that could be a callable, finds the definition of the call and the argument expression list of
     * the node
     *
     * @param Node $node The node to find calling information from
     *
     * @return array|null
     */
    private function getCallingInfo(Node $node)
    {
        $fqn = null;
        $callingNode = null;
        if ($node instanceof Node\DelimitedList\ArgumentExpressionList) {
            // Cursor is already inside a (
            $argumentExpressionList = $node;
            if ($node->parent instanceof Node\Expression\ObjectCreationExpression) {
                // Constructing something
                $callingNode = $node->parent->classTypeDesignator;
                if (!$callingNode instanceof Node\QualifiedName) {
                    // We only support constructing from a QualifiedName
                    return null;
                }
                $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($callingNode);
                $fqn = "{$fqn}->__construct()";
            } else {
                $callingNode = $node->parent->getFirstChildNode(
                    Node\Expression\MemberAccessExpression::class,
                    Node\Expression\ScopedPropertyAccessExpression::class,
                    Node\QualifiedName::class
                );
            }
        } elseif ($node instanceof Node\Expression\CallExpression) {
            $argumentExpressionList = $node->getFirstChildNode(Node\DelimitedList\ArgumentExpressionList::class);
            $callingNode = $node->getFirstChildNode(
                Node\Expression\MemberAccessExpression::class,
                Node\Expression\ScopedPropertyAccessExpression::class,
                Node\QualifiedName::class
            );
        } elseif ($node instanceof Node\Expression\ObjectCreationExpression) {
            $argumentExpressionList = $node->getFirstChildNode(Node\DelimitedList\ArgumentExpressionList::class);
            $callingNode = $node->classTypeDesignator;
            if (!$callingNode instanceof Node\QualifiedName) {
                // We only support constructing from a QualifiedName
                return null;
            }
            // Manually build the __construct fqn
            $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($callingNode);
            $fqn = "{$fqn}->__construct()";
        }

        if (!$callingNode) {
            return null;
        }

        // Now find the definition of the call
        $fqn = $fqn ?: DefinitionResolver::getDefinedFqn($callingNode);
        if ($fqn) {
            $def = $this->index->getDefinition($fqn);
        } else {
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($callingNode);
        }

        if (!$def) {
            return null;
        }
        return [$def, $argumentExpressionList];
    }

    /**
     * Creates a label for SignatureInformation
     *
     * @param Node\MethodDeclaration|Node\Statement\FunctionDeclaration $node   The method/function declaration node
     *                                                                          we are building the label for
     * @param ParameterInformation[]                                    $params Parameters that belong to the node
     *
     * @return string
     */
    private function getLabel($node, array $params): string
    {
        $label = '(';
        if ($params) {
            foreach ($params as $param) {
                $label .= $param->label . ', ';
            }
            $label = substr($label, 0, -2);
        }
        $label .= ')';
        return $label;
    }

    /**
     * Builds ParameterInformation from a node
     *
     * @param Node\MethodDeclaration|Node\Statement\FunctionDeclaration $node The node to build parameters from
     * @param PhpDocument                                               $doc  The document the node belongs to
     *
     * @return ParameterInformation[]
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

    /**
     * Given a position and arguments, finds the "active" argument at the position
     *
     * @param Node\DelimitedList\ArgumentExpressionList $argumentExpressionList The argument expression list
     * @param Position                                  $position               The position to detect the active argument from
     * @param PhpDocument                               $doc                    The document that contains the expression
     *
     * @return int
     */
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
            } else {
                $start = $arg->fullStart;
                $end = $start + $arg->length;
            }
            $offset = $position->toOffset($doc->getContent());
            if ($offset >= $start && $offset <= $end) {
                $found = $i;
                break;
            }
            if ($arg instanceof Node) {
                ++$i;
            }
        }
        if (is_null($found)) {
            $found = $i;
        }
        return $found;
    }
}
