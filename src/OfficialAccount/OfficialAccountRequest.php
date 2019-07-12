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
use Commune\Chatbot\Framework\Messages\Events\ConnectionEvt;
use Commune\Chatbot\Laravel\Drivers\LaravelMessageRequest;
use Commune\Chatbot\Wechat\Messages\WechatEvent;
use Commune\Support\Uuid\HasIdGenerator;
use Commune\Support\Uuid\IdGeneratorHelper;
use EasyWeChat\OfficialAccount\Application as Wechat;
use Illuminate\Support\Collection;
use EasyWeChat\Kernel\Messages\Text as WechatText;
use EasyWeChat\Kernel\Messages\Message as WechatMessage;
use Illuminate\Support\Facades\Redis;
use Predis\ClientInterface;

class OfficialAccountRequest extends LaravelMessageRequest 
{

    /**
     * @var Wechat
     */
    protected $wechat;

    /*------ cached ------*/

    /**
     * @var string
     */
    protected $userWechatId;

    /**
     * @var WechatMessage
     */
    protected $output;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $input;

    /**
     * @var string
     */
    protected $id;

    /**
     * OfficialAccountRequest constructor.
     * @param Wechat $wechat
     * @param array $message
     */
    public function __construct(Wechat $wechat, array $message)
    {
        $this->wechat = $wechat;
        $this->config = $wechat->getConfig();
        parent::__construct($message);
    }

    public function getChatbotUserId(): string
    {
        return $this->input['ToUserName'];
    }

    public function getPlatformId(): string
    {
        return SwooleOfficialAccountServer::class;
    }

    protected function makeInputMessage() : Message
    {
        switch ($this->input['MsgType']) {
            case 'text' :
                return new Text($this->input['Content'] ?? '');
            case 'event' :
                $event = $this->input['Event'] ?? '';
                if ( $event === 'subscribe') {
                    return new ConnectionEvt();
                }
                return new WechatEvent($this->input['Event'] ?? '');
            default :
                // todo
                return new Text('暂时不支持的消息');
        }

    }

    public function fetchMessageId(): string
    {
        return $this->messageId
            ?? $this->messageId = $this->input['MsgId'] ?? $this->createUuId();
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

        $value = $this->input['FromUserName'];
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
        return $this->fetchUserData()['nickname'] 
            ?? $this->config['defaults']['nickname']
            ?? 'guest';
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

            /**
             * @var CacheAdapter $cache
             */
            $cache = $this->conversation->make(CacheAdapter::class);

            $id = $this->fetchUserId();
            $key = "commune:chatbot:wechat:user:$id";

            if ($cache->has($key)) {
                $data = $cache->get($key);
                if (is_string($data)) {
                    $array = unserialize($data);
                    if (is_array($array)) {
                        return $array;
                    }
                }
            }

            $openId = $this->getOpenId();

            if (empty($openId)) {
                $this->logger->error("empty wechat openId: ". json_encode($this->input));
                return [];
            }

            $user = $this->wechat->user->get($this->getOpenId());
            $serialized = serialize($user);
            $cache->set($key, $serialized, 3600);

            return $user;

        } catch (\Exception $e) {
            $this->logger->error($e);
            return [];

        }
    }

    /**
     * @param ConversationMessage[] $messages
     */
    protected function renderChatMessages(array $messages): void
    {
        // 微信貌似目前只能回复一条消息. 干脆只允许回复文本好了.
        $text = implode("\n", array_map(function(ConversationMessage $message) {
            return $message->getMessage()->getText();
        }, $messages));

        $this->output = new WechatText($text);
    }

    /**
     * @return WechatMessage
     */
    public function getOutput(): ? WechatMessage
    {
        return $this->output;
    }


}