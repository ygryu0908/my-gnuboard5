<?php
add_stylesheet('<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/m3cron.css">', 0);

$menu['menu650'] = array (
    array('650000', 'm3cron 관리', G5_ADMIN_URL.'/m3cron_list.php'),
    array('650100', 'm3cron 설정', G5_ADMIN_URL.'/m3cron_list.php'),
    array('650200', 'm3cron 로그', G5_ADMIN_URL.'/m3cron_log.php')
);