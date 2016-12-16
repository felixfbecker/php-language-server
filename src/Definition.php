<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};
use LanguageServer\Protocol\SymbolInformation;
use Exception;

/**
 * Class used to represent symbols
 */
class Definition
{
    /**
     * The fully qualified name of the symbol, if it has one
     *
     * Examples of FQNs:
     *  - testFunction()
     *  - TestNamespace
     *  - TestNamespace\TestClass
     *  - TestNamespace\TestClass::TEST_CONSTANT
     *  - TestNamespace\TestClass::$staticTestProperty
     *  - TestNamespace\TestClass->testProperty
     *  - TestNamespace\TestClass::staticTestMethod()
     *  - TestNamespace\TestClass->testMethod()
     *
     * @var string|null
     */
    public $fqn;

    /**
     * For class or interfaces, the FQNs of extended classes and implemented interfaces
     *
     * @var string[]
     */
    public $extends;

    /**
     * Only true for classes, interfaces, traits, functions and non-class constants
     * This is so methods and properties are not suggested in the global scope
     *
     * @var bool
     */
    public $isGlobal;

    /**
     * False for instance methods and properties
     *
     * @var bool
     */
    public $isStatic;

    /**
     * True if the Definition is a class
     *
     * @var bool
     */
    public $canBeInstantiated;

    /**
     * @var Protocol\SymbolInformation
     */
    public $symbolInformation;

    /**
     * The type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For any other declaration it will be null.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     *
     * @var \phpDocumentor\Type|null
     */
    public $type;

    /**
     * The first line of the declaration, for use in textDocument/hover
     *
     * @var string
     */
    public $declarationLine;

    /**
     * A documentation string, for use in textDocument/hover
     *
     * @var string
     */
    public $documentation;
}
