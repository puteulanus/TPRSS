<?php
// 载入配置文件
include 'config.inc.php';
// 定义获取图片真实地址的函数
function get_pic_link($twitter_url){
    if (preg_match('/(^http:\/\/)|(^https:\/\/)/',$twitter_url) != 1){
        $twitter_url = 'http://'.$twitter_url;
    }
    $twitter_page = file_get_contents($twitter_url);
    preg_match('/data-url=\"([^\"]+)/',$twitter_page,$pic_url);
    return $pic_url[1];
}
// 定义数据库查询更新函数
function check_database($twitter_urls_array){
    global $user;
    $db_link = new mysqli(db_host,db_user,db_passwd,db_name,db_port);
    $stmt_read = $db_link -> prepare("SELECT urls FROM ".db_prefix."cache WHERE user=?");
    $stmt_write = $db_link -> prepare("INSERT INTO ".db_prefix."cache (user,urls) VALUES (?,?) ON DUPLICATE KEY UPDATE urls=?");
    $stmt_read -> bind_param("s",$user);
    $stmt_read -> execute();
    $stmt_read -> bind_result($json_result);
    $stmt_read -> fetch();
    $stmt_read -> close();
    $array_result = json_decode($json_result,true);
    $new_user_data = array();
    foreach ($twitter_urls_array as $twitter_url) {
        if ($array_result[$twitter_url]){
            $new_user_data += array($twitter_url => $array_result[$twitter_url]);
        }else{
            $new_user_data += array($twitter_url => get_pic_link($twitter_url));
        }
    }
    $stmt_write -> bind_param("sss",$user,json_encode($new_user_data),json_encode($new_user_data));
    $stmt_write -> execute();
    $stmt_write -> close();
    $db_link -> close();
    return $new_user_data;
}
// 定义检查用户存在函数
function check_user($user){
    global $user;
    $curl = curl_init();  
    curl_setopt($curl,CURLOPT_URL,"https://twitter.com/{$user}");// 获取内容url  
    curl_setopt($curl,CURLOPT_HEADER,1);// 获取http头信息  
    curl_setopt($curl,CURLOPT_NOBODY,1);// 不返回html的body信息  
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);// 返回数据流，不直接输出  
    curl_setopt($curl,CURLOPT_TIMEOUT,10); // 超时时长，单位秒  
    curl_exec($curl);  
    if (curl_getinfo($curl,CURLINFO_HTTP_CODE) == '404'){
        $user_status = false;
    }else{
        $user_status = true;
    }
    curl_close($curl);  
    return  $user_status;  
}
// 获取用户名或图片地址
$user = $_GET['user'];
$pic_url = $_GET['pic'];
if ($pic_url){
    Header("Content-type: image/jpg");
    echo file_get_contents($pic_url);
    exit;
}elseif (!$user){
    header("Content-Type: text/html; charset=utf-8");
    echo '请输入要订阅的推特用户名！';
    exit;
}
// 检查是否存在用户
if (!check_user($user)){
    header("Content-Type: text/html; charset=utf-8");
    echo '用户不存在，请核对后输入正确的用户名';
    exit;
}
// 从后端获取RSS信息
$rss_page = file_get_contents(perl_api_url."?user={$user}");
// 判断是否使用CDN
if ($_GET['cdn'] == 'on'){
    // 判断CDN是否正常工作
    if (file_get_contents(cdn_api_url."?check=on") == 'OK'){
        $pic_out_url = cdn_api_url;
    }else{// CDN故障时切到本体输出
        $pic_out_url = tprss_api_url;
    }
}else{
    $pic_out_url = tprss_api_url;
}
// 替换推文中的地址为图片
preg_match_all('/<description><!\[CDATA\[.*(pic.twitter.com\/\w+).*\]\]><\/description>/',$rss_page,$twitter_urls_array);
$twitter_urls_array = $twitter_urls_array[1];
$pic_urls_array = check_database($twitter_urls_array);
foreach ($pic_urls_array as $twitter_url => $pic_url) {
    $rss_page = preg_replace('/(<description><!\[CDATA\[.*)'.str_replace('/','\/',addslashes($twitter_url)).'(.*\]\]><\/description>)/',"$1<img src='{$pic_out_url}?pic={$pic_url}'/>$2",$rss_page);
}
// 输出RSS
echo $rss_page;