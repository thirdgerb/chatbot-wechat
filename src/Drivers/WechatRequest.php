<?php

/**
 * Class WechatRequest
 * @package Commune\Chatbot\Wechat\Drivers
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\Drivers;



use Commune\Chatbot\Blueprint\Conversation\ConversationMessage;
use Commune\Chatbot\Blueprint\Conversation\MessageRequest;
use Commune\Chatbot\Blueprint\Message\Message;
use Commune\Chatbot\Wechat\EasyWechat\Wechat;
use Swoole\Http\Request;
use Swoole\Http\Response;

class WechatRequest implements MessageRequest
{

    /**
     * @var Request
     */
    protected $swooleRequest;

    /**
     * @var Response
     */
    protected $swooleResponse;

    /**
     * @var Wechat
     */
    protected $wechat;

    /**
     * WechatRequest constructor.
     * @param Request $swooleRequest
     * @param Response $swooleResponse
     * @param Wechat $wechat
     */
    public function __construct(Request $swooleRequest, Response $swooleResponse, Wechat $wechat)
    {
        $this->swooleRequest = $swooleRequest;
        $this->swooleResponse = $swooleResponse;
        $this->wechat = $wechat;
    }


    public function generateMessageId(): string
    {
        // TODO: Implement generateMessageId() method.
    }

    public function getChatbotUserId(): string
    {
        // TODO: Implement getChatbotUserId() method.
    }

    public function getPlatformId(): string
    {
        // TODO: Implement getPlatformId() method.
    }

    public function fetchMessage(): Message
    {
        // TODO: Implement fetchMessage() method.
    }

    public function fetchMessageId(): string
    {
        // TODO: Implement fetchMessageId() method.
    }

    public function fetchTraceId(): string
    {
        // TODO: Implement fetchTraceId() method.
    }

    public function fetchUserId(): string
    {
        // TODO: Implement fetchUserId() method.
    }

    public function fetchUserName(): string
    {
        // TODO: Implement fetchUserName() method.
    }

    public function fetchUserData(): array
    {
        // TODO: Implement fetchUserData() method.
    }

    public function bufferMessageToChat(ConversationMessage $message): void
    {
        // TODO: Implement bufferMessageToChat() method.
    }

    public function flushChatMessages(): void
    {
        // TODO: Implement flushChatMessages() method.
    }

    public function finishRequest(): void
    {
        // TODO: Implement finishRequest() method.
    }


}