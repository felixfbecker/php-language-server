<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Diagnostics;

use PHPUnit\Framework\TestCase;
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\{
    DefinitionResolver, TreeAnalyzer
};
use LanguageServer\Index\{Index};
use LanguageServer\Protocol\{
    Diagnostic, DiagnosticSeverity, Position, Range
};
use function LanguageServer\pathToUri;
use Microsoft\PhpParser\Parser;

class InvalidThisUsageTest extends TestCase
{
    /**
     * Parse the given file and return diagnostics.
     *
     * @param string $path
     * @return Diagnostic[]
     */
    private function collectDiagnostics(string $path): array
    {
        $uri = pathToUri($path);
        $parser = new Parser();

        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $content = file_get_contents($path);

        $treeAnalyzer = new TreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        return $treeAnalyzer->getDiagnostics();
    }

    /**
     * Assertions about a diagnostic.
     *
     * @param Diagnostic|null $diagnostic
     * @param int $message
     * @param string $severity
     * @param Range $range
     */
    private function assertDiagnostic($diagnostic, $message, $severity, $range)
    {
        $this->assertInstanceOf(Diagnostic::class, $diagnostic);
        $this->assertEquals($message, $diagnostic->message);
        $this->assertEquals($severity, $diagnostic->severity);
        $this->assertEquals($range, $diagnostic->range);
    }

    public function testThisInStaticMethodProducesError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/errors/this_in_static_method.php'
        );

        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this can not be used in static methods.',
            DiagnosticSeverity::ERROR,
            new Range(
                new Position(6, 15),
                new Position(6, 20)
            )
        );
    }

    public function testThisInFunctionProducesError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/errors/this_in_function.php'
        );
        
        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this can only be used in an object context or non-static anonymous functions.',
            DiagnosticSeverity::ERROR,
            new Range(
                new Position(4, 11),
                new Position(4, 16)
            )
        );
    }

    public function testThisInRoot()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/errors/this_in_root.php'
        );

        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this can only be used in an object context or non-static anonymous functions.',
            DiagnosticSeverity::ERROR,
            new Range(
                new Position(2, 5),
                new Position(2, 10)
            )
        );
    }

    public function testThisInMethodProducesNoError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/baselines/this_in_method.php'
        );

        $this->assertCount(0, $diagnostics);
    }

    public function testThisInMethodInAnonymousFunctionWithNoCheckProducesNoError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/baselines/this_in_method_in_anonymous_function.php'
        );

        $this->assertCount(0, $diagnostics);
    }

    public function testThisInMethodInAnonymousFunctionWithCheckProducesNoError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/baselines/this_in_method_in_anonymous_function_check.php'
        );

        $this->assertCount(0, $diagnostics);
    }

    public function testThisInAnonymousFunctionWithCheckProducesNoError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/baselines/this_in_anonymous_function_check.php'
        );

        $this->assertCount(0, $diagnostics);
    }

    public function testThisInStaticMethodInAnonymousFunctionWithCheckProducesWarning()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/baselines/this_in_static_method_in_anonymous_function_check.php'
        );

        $this->assertCount(0, $diagnostics);
    }

    public function testThisInStaticAnonymousFunctionProducesError()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/errors/this_in_static_anonymous_function.php'
        );

        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this can not be used in static anonymous functions.',
            DiagnosticSeverity::ERROR,
            new Range(
                new Position(3, 11),
                new Position(3, 16)
            )
        );
    }

    public function testThisInAnonymousFunctionWithNoCheckProducesWarning()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/warnings/this_in_anonymous_function.php'
        );

        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this might not be bound by invoker of callable or might be bound to object of any class. Consider adding instance class check.',
            DiagnosticSeverity::WARNING,
            new Range(
                new Position(3, 11),
                new Position(3, 16)
            )
        );
    }

    public function testThisInStaticMethodInAnonymousFunctionWithNoCheckProducesWarning()
    {
        $diagnostics = $this->collectDiagnostics(
            __DIR__ . '/../../fixtures/diagnostics/warnings/this_in_static_method_in_anonymous_function.php'
        );

        $this->assertCount(1, $diagnostics);
        $this->assertDiagnostic(
            $diagnostics[0],
            '$this might not be bound by invoker of callable or might be bound to object of any class. Consider adding instance class check.',
            DiagnosticSeverity::WARNING,
            new Range(
                new Position(7, 19),
                new Position(7, 24)
            )
        );
    }
}
