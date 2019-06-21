<?php

/**
 * Class ChatbotWechatServiceProvider
 * @package Commune\Chatbot\Wechat\Laravel
 * @author BrightRed
 */

namespace Commune\Chatbot\Wechat;


use Commune\Chatbot\Wechat\Commands\WechatServerCommand;
use Illuminate\Support\ServiceProvider;

class CommuneWechatServiceProvider extends ServiceProvider
{

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WechatServerCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../configs/wechat.php' => config_path('wechat.php'),
        ], 'public');

    }

    public function register()
    {

    }


}