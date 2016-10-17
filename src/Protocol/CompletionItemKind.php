<?php

namespace LanguageServer\Protocol;

/**
 * The kind of a completion entry.
 */
abstract class CompletionItemKind {
    const TEXT = 1;
    const METHOD = 2;
    const FUNCTION = 3;
    const CONSTRUCTOR = 4;
    const FIELD = 5;
    const VARIABLE = 6;
    const _CLASS = 7;
    const INTERFACE = 8;
    const MODULE = 9;
    const PROPERTY = 10;
    const UNIT = 11;
    const VALUE = 12;
    const ENUM = 13;
    const KEYWORD = 14;
    const SNIPPET = 15;
    const COLOR = 16;
    const FILE = 17;
    const REFERENCE = 18;
}
