<?php

namespace LanguageServer\ProtocolBridge;

use AdvancedJsonRpc\Message as AdvancedJsonRpcMessage;
use LanguageServer\ProtocolBridge\Message;

class MessageFactory
{
    /**
     * Returns a LSP Message from a raw requets string.
     *
     * NOTE: This is only used in the MockProtocolStream. Consider moving it
     *       somewhere else.
     *
     * @param string $msg
     * @return Message
     */
    public static function fromRawMessage(string $msg): Message
    {
        $obj = new Message();
        $parts = explode("\r\n", $msg);
        $obj->body = AdvancedJsonRpcMessage::parse(array_pop($parts))->__toString();
        foreach ($parts as $line) {
            if ($line) {
                $pair = explode(': ', $line);
                $obj->headers[$pair[0]] = $pair[1];
            }
        }
        return $obj;
    }
}
