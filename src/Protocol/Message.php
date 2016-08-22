<?php

namespace LanguageServer\Protocol;

/**
 * A general message as defined by JSON-RPC. The language server protocol always uses "2.0" as the jsonrpc version.
 */
abstract class Message
{
    /**
     * The version (2.0)
     *
     * @var string
     */
    public $jsonrpc;

    /**
     * Parses a request body and returns the appropiate Message subclass
     *
     * @param string $body The raw request body
     * @param string $fallbackClass The class to fall back to if the body is not a Notification and the method is
     * unknown (Request::class or Response::class)
     */
    public static function parse(string $body, string $fallbackClass): self
    {
        $decoded = json_decode($body);

        // The appropiate Request/Notification subclasses are namespaced according to the method
        // example: textDocument/didOpen -> LanguageServer\Protocol\Methods\TextDocument\DidOpenNotification
        $class = __NAMESPACE__ . '\\Methods\\' . implode('\\', array_map('ucfirst', explode('/', $decoded->method))) . (isset($decoded->id) ? 'Request' : 'Notification');

        // If the Request/Notification type is unknown, instantiate a basic Request or Notification class
        // (this is the reason Request and Notification are not abstract)
        if (!class_exists($class)) {
            fwrite(STDERR, "Unknown method {$decoded->method}\n");
            if (!isset($decoded->id)) {
                $class = Notification::class;
            } else {
                $class = $fallbackClass;
            }
        }

        // JsonMapper will take care of recursively using the right classes for $params etc.
        $mapper = new JsonMapper();
        $message = $mapper->map($decoded, new $class);

        return $message;
    }
}
