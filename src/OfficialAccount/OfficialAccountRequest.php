<?php

/**
 * Class WechatRequest
 * @package Commune\Chatbot\Wechat\Drivers
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\OfficialAccount;



use Commune\Chatbot\App\Messages\Text;
use Commune\Chatbot\Blueprint\Message\Message;
use Commune\Chatbot\Blueprint\Conversation\ConversationMessage;
use Commune\Chatbot\Blueprint\Conversation\MessageRequest;
use Commune\Support\Uuid\HasIdGenerator;
use Commune\Support\Uuid\IdGeneratorHelper;
use EasyWeChat\OfficialAccount\Application as Wechat;
use Illuminate\Support\Collection;
use EasyWeChat\Kernel\Messages\Text as WechatText;
use EasyWeChat\Kernel\Messages\Message as WechatMessage;

class OfficialAccountRequest implements MessageRequest, HasIdGenerator
{
    use IdGeneratorHelper;

    /**
     * @var Wechat
     */
    protected $wechat;

    /**
     * @var Collection
     */
    protected $message;

    /*------ cached ------*/

    /**
     * @var Message
     */
    protected $inputMessage;

    /**
     * @var string
     */
    protected $userWechatId;

    /**
     * @var ConversationMessage[]
     */
    protected $buffer = [];

    /**
     * @var WechatMessage
     */
    protected $output;

    /**
     * OfficialAccountRequest constructor.
     * @param Wechat $wechat
     * @param Collection $message
     */
    public function __construct(Wechat $wechat, Collection $message)
    {
        $this->wechat = $wechat;
        $this->message = $message;
    }


    public function generateMessageId(): string
    {
        return $this->createUuId();
    }

    public function getChatbotUserId(): string
    {
        return $this->message['ToUserName'];
    }

    public function getPlatformId(): string
    {
        return SwooleOfficialAccountServer::class;
    }

    public function fetchMessage(): Message
    {
        return $this->inputMessage ?? $this->inputMessage = $this->parseWechatMessage();

    }

    protected function parseWechatMessage() : Message
    {
        switch ($this->message['MsgType']) {
            case 'text' :
                return new Text($this->message['Content']);
            default :
                // todo
                return new Text('暂时不支持的消息');
        }

    }

    public function fetchMessageId(): string
    {
        return $this->message['MsgId'];
    }

    public function fetchTraceId(): string
    {
        return $this->fetchMessageId();
    }

    public function fetchUserId(): string
    {
        if (isset($this->userWechatId)) {
            return $this->userWechatId;
        }

        $value = $this->message['FromUserName'];
        if (empty($value)) {
            throw new \InvalidArgumentException(
                __METHOD__
                .' message field fromUserName not valid'
            );
        }
        return $this->userWechatId = strval($value);
    }

    public function fetchUserName(): string
    {
        // todo
        return '';
    }

    public function fetchUserData(): array
    {
        // todo
        return [];
    }

    public function bufferMessageToChat(ConversationMessage $message): void
    {
        //todo 暂时不做延时回复和发送模板消息. 太麻烦了.
        $this->buffer[] = $message;
    }

    public function flushChatMessages(): void
    {
        // 微信貌似目前只能回复一条消息. 干脆只允许回复文本好了.
        $text = implode("\n", array_map(function(ConversationMessage $message) {
            return $message->getMessage()->getText();
        }, $this->buffer));

        $this->output = new WechatText($text);
    }

    public function finishRequest(): void
    {
    }

    /**
     * @return WechatMessage
     */
    public function getOutput(): WechatMessage
    {
        return $this->output;
    }


}