<?php

/**
 * Class WechatComponent
 * @package Commune\Chatbot\Wechat\Component
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat\Component;


use Commune\Chatbot\Framework\Component\ComponentOption;

/**
 * @property-read string $serverIp
 * @property-read int $serverPort
 * @property-read array $wechat
 */
class WechatComponent extends ComponentOption
{
    protected function doBootstrap(): void
    {
    }

    public static function stub(): array
    {
        return [
            'serverIp' => '127.0.0.1',
            'serverPort' => 80,
            'wechat' => [



            ]
        ];
    }


}