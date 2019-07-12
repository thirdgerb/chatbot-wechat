
开发和测试中... 尚未正式发布



[commune/chatbot](https://github.com/thirdgerb/chatbot) 项目的微信 adapter.

具体使用可参考
[commune/chatbot-studio](https://github.com/thirdgerb/chatbot-studio)

web服务基于 swoole, wechat 功能使用了 [easywechat项目](https://www.easywechat.com/docs) . 因为开发时间有限, 包括数据读写, 都用比较粗糙的方式实现.


## 使用方法:


### 在laravel 中安装

在 laravel 项目中, 运行加载包.

    composer require commune/chatbot-wechat:dev-develop


在 laravel 的 config/app.php 中, 为 providers 添加


       \App\Providers\CommuneStudioServiceProvider::class,
       \Commune\Chatbot\Wechat\CommuneWechatServiceProvider::class,

### 加载配置文件

    php artisan vendor:publish

### 安装数据库

    php artisan migrate

### 确认依赖

-   swoole ~4.8
-   laravel ~7.2
-   mysql
-   redis

### 运行

命令行测试

    php artisan commune:tinker

tcp 端口测试

    php artisan commune:tcp

微信服务端测试

    php artisan commune:wechat-server



