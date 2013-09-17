<?php
$container->loadFromExtension('swiftmailer', array(
    'transport'  => "smtp",
    'username'   =>"user",
    'password'   => "pass",
    'host'       => "example.org",
    'port'       => "12345",
    'encryption' => "tls",
    'auth-mode'  => "login",
    'timeout'    => "1000",
    'source_ip'  => "127.0.0.1",
    'logging'    => true,
    'spool' => array('type' => 'memory'),
    'delivery_address'   => 'single@host.com',
    'delivery_whitelist' => array('/foo@.*/', '/.*@bar.com$/'),
));
