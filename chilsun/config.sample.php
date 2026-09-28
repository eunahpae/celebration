<?php
// 이 파일을 config.php 로 복사해 값을 채운다. config.php 는 git 에 올라가지 않는다.
return [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    // 관리자 비밀번호의 해시. 만드는 법: php -r "echo password_hash('비밀번호', PASSWORD_DEFAULT);"
    'admin_hash' => '',
];
