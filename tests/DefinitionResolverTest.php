<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\Index\Index;
use LanguageServer\{DefinitionResolver, Parser};

class DefinitionResolverTest extends TestCase
{
    public function testCreateDefinitionFromNode()
    {
        $parser = new Parser;
        $stmts = $parser->parse("<?php\ndefine('TEST_DEFINE', true);");
        $stmts[0]->setAttribute('ownerDocument', new MockPhpDocument);

        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $def = $definitionResolver->createDefinitionFromNode($stmts[0], '\TEST_DEFINE');

        $this->assertInstanceOf(\phpDocumentor\Reflection\Types\Boolean::class, $def->type);
    }

    public function testGetTypeFromNode()
    {
        $parser = new Parser;
        $stmts = $parser->parse("<?php\ndefine('TEST_DEFINE', true);");
        $stmts[0]->setAttribute('ownerDocument', new MockPhpDocument);

        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $type = $definitionResolver->getTypeFromNode($stmts[0]);

        $this->assertInstanceOf(\phpDocumentor\Reflection\Types\Boolean::class, $type);
    }

    public function testGetDefinedFqnForIncompleteDefine()
    {
        // define('XXX') (only one argument) must not introduce a new symbol
        $parser = new Parser;
        $stmts = $parser->parse("<?php\ndefine('TEST_DEFINE');");
        $stmts[0]->setAttribute('ownerDocument', new MockPhpDocument);

        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $fqn = $definitionResolver->getDefinedFqn($stmts[0]);

        $this->assertNull($fqn);
    }

    public function testGetDefinedFqnForDefine()
    {
        $parser = new Parser;
        $stmts = $parser->parse("<?php\ndefine('TEST_DEFINE', true);");
        $stmts[0]->setAttribute('ownerDocument', new MockPhpDocument);

        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $fqn = $definitionResolver->getDefinedFqn($stmts[0]);

        $this->assertEquals('TEST_DEFINE', $fqn);
    }
}
