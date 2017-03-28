<?php
declare(strict_types = 1);

namespace LanguageServer;

class LoggedDefinitionResolver extends DefinitionResolver
{
    use LoggedDefinitionResolverTrait;
}
