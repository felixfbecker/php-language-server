<?php
declare(strict_types = 1);

namespace LanguageServer\Client;

use LanguageServer\ClientHandler;
use Sabre\Event\Promise;

class WorkDoneProgress
{
    /**
     * @var ClientHandler
     */
    private $handler;

    /**
     * ProgressToken
     *
     * @var string
     */
    private $token;

    public function __construct(ClientHandler $handler, string $token)
    {
        $this->handler = $handler;
        $this->token = $token;
    }

    public function beginProgress(string $title, string $message = null, int $percentage = null): Promise
    {
        return $this->handler->notify(
            '$/progress',
            [
                'token' => $this->token,
                'value' => [
                    'kind' => 'begin',
                    'title' => $title,
                    // 'cancellable'
                    'message' => $message,
                    'percentage' => $percentage,
                ]
            ]
        );
    }

    public function reportProgress(string $message = null, int $percentage = null): Promise
    {
        return $this->handler->notify(
            '$/progress',
            [
                'token' => $this->token,
                'value' => [
                    'kind' => 'report',
                    // 'cancellable'
                    'message' => $message,
                    'percentage' => $percentage,
                ]
            ]
        );
    }

    public function endProgress(string $message = null): Promise
    {
        return $this->handler->notify(
            '$/progress',
            [
                'token' => $this->token,
                'value' => [
                    'kind' => 'end',
                    'message' => $message,
                ]
            ]
        );
    }
}
