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
use Commune\Chatbot\Contracts\CacheAdapter;
use Commune\Chatbot\Framework\Conversation\MessageRequestHelper;
use Commune\Chatbot\Wechat\Messages\WechatEvent;
use Commune\Support\Uuid\HasIdGenerator;
use Commune\Support\Uuid\IdGeneratorHelper;
use EasyWeChat\OfficialAccount\Application as Wechat;
use Illuminate\Support\Collection;
use EasyWeChat\Kernel\Messages\Text as WechatText;
use EasyWeChat\Kernel\Messages\Message as WechatMessage;
use Illuminate\Support\Facades\Redis;
use Predis\ClientInterface;

class OfficialAccountRequest implements MessageRequest, HasIdGenerator
{
    use IdGeneratorHelper, MessageRequestHelper;

    /**
     * @var Wechat
     */
    protected $wechat;

    /**
     * @var array
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
     * @param array|Collection|Message $message
     */
    public function __construct(Wechat $wechat, $message)
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
        if ($this->message instanceof Message) {
            return $this->message;
        }

        switch ($this->message['MsgType']) {
            case 'text' :
                return new Text($this->message['Content'] ?? '');
            case 'event' :
                return new WechatEvent($this->message['Event'] ?? '');
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
        return '未知用户';
    }

    public function getOpenId() : string
    {
        return $this->fetchUserId();
    }

    /**
     * @return array
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function fetchUserData(): array
    {
        try {

//            /**
//             * @var CacheAdapter $cache
//             */
//            $cache = $this->conversation->make(CacheAdapter::class);
//
//            $id = $this->fetchUserId();
//            $key = "commune:chatbot:wechat:user:$id";
//
//            if ($cache->has($key)) {
//                $data = $cache->get($key);
//                if (is_string($data)) {
//                    $array = unserialize($data);
//                    if (is_array($array)) {
//                        return $array;
//                    }
//                }
//            }

            $openId = $this->getOpenId();

            if (empty($openId)) {
                $this->logger->error("empty wechat openId: ". json_encode($this->message));
                return [];
            }

            $user = $this->wechat->user->get($this->getOpenId());
            $this->logger->info("wechat user : ". json_encode($user));

            return [];
        } catch (\Exception $e) {
            $this->logger->error($e);
            return [];
        }
    }

    public function bufferConversationMessage(ConversationMessage $message): void
    {
        $this->buffer[] = $message;
    }

    protected function userMessageKey(string $userId) : string
    {
        return "commune:chatbot:messageTo:$userId";
    }

    public function flushChatMessages(): void
    {
        $this->bufferToCache($this->buffer);
        $this->buffer = [];

        $cached = $this->fetchCachedMessages();

        // 微信貌似目前只能回复一条消息. 干脆只允许回复文本好了.
        $text = implode("\n", array_map(function(ConversationMessage $message) {
            return $message->getMessage()->getText();
        }, $cached));

        $this->output = new WechatText($text);
    }

    /**
     * @param ConversationMessage[] $messages
     */
    protected function bufferToCache(array $messages) : void
    {
        // 先把消息压到队列里.
        Redis::pipeline(function($pipe) use ($messages){
            /**
             * @var ClientInterface $pipe
             */
            $push = [];
            foreach ($messages as $message) {
                $key = $this->userMessageKey($message->getUserId());
                $push[$key] = serialize($message);
            }

            foreach ($push as $key => $messages) {
                $pipe->lpush($key, $messages);
            }
        });
    }

    /**
     * @return ConversationMessage[]
     */
    protected function fetchCachedMessages() : array
    {
        $key = $this->userMessageKey($this->fetchUserId());
        $list = Redis::connection()->lrange($key, 0, -1);

        $buffer = [];
        $now = time();

        $delay = [];
        foreach ($list as $serialized) {
            /**
             * @var ConversationMessage $unserialized
             */
            $unserialized = unserialize($serialized);
            if (!$unserialized instanceof ConversationMessage) {
                continue;
            }

            if ($unserialized->message->getDeliverAt()->timestamp > $now) {
                array_unshift($buffer, $unserialized);
            } else {
                $delay[] = $serialized;
            }
        }

        $this->bufferToCache($delay);
        return $buffer;
    }

    /**
     * @return WechatMessage
     */
    public function getOutput(): WechatMessage
    {
        return $this->output;
    }


}