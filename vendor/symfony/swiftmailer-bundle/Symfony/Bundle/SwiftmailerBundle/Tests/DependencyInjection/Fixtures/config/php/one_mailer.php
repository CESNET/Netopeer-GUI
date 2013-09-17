<?php
$container->loadFromExtension('swiftmailer', array(
    'default_mailer' => 'main_mailer',
    'mailers' => array(
        'main_mailer' => array(
            'transport'  => "smtp",
            'username'   => "user",
            'password'   => "pass",
            'host'       => "example.org",
            'port'       => "12345",
            'encryption' => "tls",
            'auth-mode'  => "login",
            'timeout'    => "1000",
            'source_ip'  => "127.0.0.1",
        ),
    ),
));
