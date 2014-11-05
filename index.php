<?php
// 设定表前缀
$db_prefix = 'tr_';
// 设定后端地址与图片CDN地址
$api_url = 'http://twitrss.me/fcgi/twitter_user_to_rss.pl';
$cdn_api_url = 'http://rss-cdn-def0d.coding.io/index.php';
// 定义获取图片真实地址的函数
function get_pic_link($pic_url){
    if (preg_match('/(^http:\/\/)|(^https:\/\/)/',$pic_url) != 1){
        $pic_url = 'http://'.$pic_url;
    }
    $twitter_page = file_get_contents($pic_url);
    preg_match('/data-url=\"([^\"]+)/',$twitter_page,$img_url);
    return $img_url[1];
}
// 定义数据库查询更新函数
function check_database($user,$pic_urls){
    global $db_prefix;
    $mysql_link = mysql_connect(getenv('OPENSHIFT_MYSQL_DB_HOST').':'.getenv('OPENSHIFT_MYSQL_DB_PORT'),getenv('OPENSHIFT_MYSQL_DB_USERNAME'),getenv('OPENSHIFT_MYSQL_DB_PASSWORD'));
    if (!$mysql_link){
    die('Could not connect: '.mysql_error());
    }
    mysql_select_db(getenv('OPENSHIFT_APP_NAME'), $mysql_link);
    $json_result = mysql_fetch_array(mysql_query("SELECT urls FROM ".$db_prefix."cache where user='".addslashes($user)."'"));
    // 更新数据库
    $json_result = $json_result['urls'];
    $array_result = json_decode($json_result,true);
    $new_user = array();
    foreach ($pic_urls as $pic_url) {
        if ($array_result[$pic_url]){
            $new_user += array($pic_url => $array_result[$pic_url]);
        }else{
            $new_user += array($pic_url => get_pic_link($pic_url));
        }
    }
    mysql_query("INSERT INTO ".$db_prefix."cache (user,urls) VALUES ('".addslashes($user)."','".addslashes(json_encode($new_user))."') ON DUPLICATE KEY UPDATE urls = '".addslashes(json_encode($new_user))."'");
    mysql_close($mysql_link);
    return $new_user;
}
// 获取用户名或图片地址
$user = $_GET['user'];
$pic = $_GET['pic'];
if ($pic){
    Header("Content-type: image/jpg");
    echo file_get_contents($pic);
    exit;
}elseif (!$user){
    header("Content-Type: text/html; charset=utf-8");
    echo '请输入要订阅的推特用户名！';
    exit;
}
// 从后端获取RSS信息
$rss_page = file_get_contents($api_url.'?user='.$user);
// 判断是否使用CDN
if ($_GET['cdn'] != 'on'){
    $cdn_api_url = 'http://tprss.puteulanus.com/index.php'
}
// 替换图片地址为CDN地址
preg_match_all('/<description><!\[CDATA\[.*(pic.twitter.com\/\w+).*\]\]><\/description>/',$rss_page,$pic_urls);
$pic_urls = $pic_urls[1];
$pic_urls = check_database($user,$pic_urls);
foreach ($pic_urls as $pic_url => $real_url) {
    $rss_page = preg_replace('/(<description><!\[CDATA\[.*)'.str_replace('/','\/',addslashes($pic_url)).'(.*\]\]><\/description>)/','$1<img src="'.$cdn_api_url.'?pic='.$real_url.'"/>$2',$rss_page);
}
// 输出RSS
echo $rss_page;