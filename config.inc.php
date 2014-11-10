<?php
// 设定Perl后端地址
define(perl_api_url,'http://twitrss.me/fcgi/twitter_user_to_rss.pl');
// 设定本体层地址
define(tprss_api_url,'http://tprss.puteulanus.com/index.php');
// 设定CDN地址
define(cdn_api_url,'http://rss-cdn-def0d.coding.io/index.php');
// 设定数据库
define(db_host, getenv('OPENSHIFT_MYSQL_DB_HOST'));// 数据库地址
define(db_port, getenv('OPENSHIFT_MYSQL_DB_PORT'));// 数据库端口
define(db_user, getenv('OPENSHIFT_MYSQL_DB_USERNAME'));// 数据库用户名
define(db_name, getenv('OPENSHIFT_APP_NAME'));// 数据库名
define(db_passwd, getenv('OPENSHIFT_MYSQL_DB_PASSWORD'));// 数据库密码
define(db_prefix, 'tr_');// 表前缀
