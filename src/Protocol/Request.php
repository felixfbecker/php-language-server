<?php

namespace LanguageServer\Protocol;

use JsonMapper;

class Request extends Message
{
    /**
     * @var int|string
     */
    public $id;

    /**
     * @var string
     */
    public $method;

    /**
     * @var object
     */
    public $params;

    public static function parse(string $body)
    {
        $mapper = new JsonMapper();
        $request->id = $decoded->id;
        $request->method = $decoded->method;
        $pascalCasedMethod = ucfirst($decoded->method);
        $namespace = __NAMESPACE__ . '\\' . str_replace('/', '\\', $pascalCasedMethod);
        $className = end(explode('\\', $request->method)) . 'Params';
        $fullyQualifiedName = $namespace . $className;
        $mapper->classMap['RequestParams'] = $fullyQualifiedName;
        $request = $mapper->map(json_decode($body), new self());
        if (class_exists($fullyQualifiedName)) {
            $request->params = new $fullyQualifiedName();
        }
        foreach ($request->params as $key => $value) {
            $request->{$key} = $value;
        }
        return $request;
    }
}
