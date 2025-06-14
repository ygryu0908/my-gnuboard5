<?php
// m3cron ver 1.11 (2009-06-02)
// a plugin for GNU board 4.31.02
// 리눅스의 크론처럼 매월, 매주, 매일, 설정한 간격마다 원하는 파일을 실행시키고 기록을 남깁니다.
// 1) /extend 에 이 파일(m3cron.extend.php)를 복사합니다.
// 2) /lib 에 m3cron.lib.php 를 복사합니다.
// 3) /adm 에 m3cron_list.php, m3cron_edit.php, admin.menu400.php 를 복사합니다.
// 4) /adm/img 에 menu400.gif 를 복사합니다.
// 5) /m3cron 폴더를 생성시키고 실행시킬 파일을 복사합니다.
// please give feedbacks to http://bomool.net

include_once(G5_PATH.'/lib/m3cron.lib.php');

// 현재 시간 관련 변수들 미리 계산
$current_time = G5_SERVER_TIME;
$current_date = G5_TIME_YMD;
$current_datetime = G5_TIME_YMDHIS;
$current_hour_min = G5_TIME_HIS;
$current_day_of_week = date("w");

// 파일 목록 가져오기
$query = sql_query("SELECT * FROM `{$m3cron['config_table']}`", false);

while($prog = sql_fetch_array($query)) {
    $should_run = false; // 실행 여부
    $program_name = sql_real_escape_string($prog['name']); // SQL 인젝션 방지
    
    // 지금 실행 체크
    if($prog['run_now']) {
        // 지금 실행 플래그 초기화
        sql_query("UPDATE `{$m3cron['config_table']}` SET run_now='0' WHERE name='{$program_name}' LIMIT 1");
        $should_run = true;
    } 
    // 일반 실행 조건 체크
    else if($prog['status'] && $prog['type']) {
        // 가상로봇 실행 조건 체크
        if($prog['vr_id'] && $prog['vr_only']) {
            // 가상로봇 전용인 경우
            if(!$is_member || $member['mb_id'] != $prog['vr_id']) {
                continue; // 조건에 맞지 않으면 다음 항목으로
            }
        } else {
            // 일반 실행 또는 봇 실행인 경우
            if($prog['robot'] && !$is_robot) {
                continue; // 봇 실행 조건이지만 봇이 아니면 스킵
            }
        }
        
        // 타입별 실행 조건 체크
        switch($prog['type']) {
            case 'monthly':
                if($current_date <= $prog['lastrun'] || intval(date("d")) != intval($prog['d']) || 
                   $current_hour_min < date('H:i:s', strtotime($prog['h'].':'.$prog['i'].':00'))) {
                    continue 2; // 조건에 맞지 않으면 다음 항목으로 (switch와 while 모두 continue)
                }
                break;
                
            case 'weekly':
                if($current_date <= $prog['lastrun'] || $current_day_of_week != $prog['w'] || 
                   $current_hour_min < date('H:i:s', strtotime($prog['h'].':'.$prog['i'].':00'))) {
                    continue 2;
                }
                break;
                
            case 'daily':
                if($current_date <= $prog['lastrun'] || 
                   $current_hour_min < date('H:i:s', strtotime($prog['h'].':'.$prog['i'].':00'))) {
                    continue 2;
                }
                break;
                
            case 'hourly':
                if($current_time - strtotime($prog['lastrun']) < $prog['h'] * 60 * 60) {
                    continue 2;
                }
                break;
                
            case 'minutely':
                if($current_time - strtotime($prog['lastrun']) < $prog['i'] * 60) {
                    continue 2;
                }
                break;
                
            case 'once':
                if($current_time < strtotime($prog['at'])) {
                    continue 2;
                }
                break;
        }
        
        $should_run = true;
    }
    
    // 실행 조건이 맞지 않으면 다음 항목으로
    if(!$should_run) {
        continue;
    }
    
    // 한 번 실행 타입이면 실행 후 비활성화
    if($prog['type'] == 'once') {
        sql_query("UPDATE `{$m3cron['config_table']}` SET status='0' WHERE name='{$program_name}' LIMIT 1");
    }

    // 마지막 실행 시각 기록
    sql_query("UPDATE `{$m3cron['config_table']}` SET lastrun='{$current_datetime}' WHERE name='{$program_name}' LIMIT 1");

    // 실행 시작 시간 구함
    $starttime = get_microtime();
    
    // 파일 경로 검증
    $file_path = $m3cron['path'].'/'.$prog['name'];
    if(!file_exists($file_path) || !is_file($file_path)) {
        // 파일이 없거나 디렉토리인 경우 로그 기록 후 스킵
        sql_query("INSERT INTO `{$m3cron['log_table']}` SET 
                 name='{$program_name}', 
                 datetime='{$current_datetime}', 
                 runtime='0', 
                 ip='".$_SERVER['REMOTE_ADDR']."', 
                 robot='{$is_robot}', 
                 mb_id='".sql_real_escape_string($member['mb_id'])."',
                 error='File not found or is a directory'");
        continue;
    }
    
    // 파일 확장자 검증 (PHP 파일만 실행)
    if(!preg_match('/\.php$/i', $prog['name'])) {
        // PHP 파일이 아닌 경우 로그 기록 후 스킵
        sql_query("INSERT INTO `{$m3cron['log_table']}` SET 
                 name='{$program_name}', 
                 datetime='{$current_datetime}', 
                 runtime='0', 
                 ip='".$_SERVER['REMOTE_ADDR']."', 
                 robot='{$is_robot}', 
                 mb_id='".sql_real_escape_string($member['mb_id'])."',
                 error='Not a PHP file'");
        continue;
    }

    // 에러 캡처 시작
    ob_start();
    $error_occurred = false;
    
    try {
        // 실행!!!
        include_once($file_path);
    } catch (Exception $e) {
        $error_occurred = true;
        $error_message = $e->getMessage();
    }
    
    // 출력 버퍼 비우기
    ob_end_clean();

    // 실행 시간 구함
    $runtime = get_microtime() - $starttime;

    // 마지막 실행 시간 기록
    sql_query("UPDATE `{$m3cron['config_table']}` SET lastruntime='{$runtime}' WHERE name='{$program_name}' LIMIT 1");

    // 로그 남김 (에러 발생 여부 포함)
    $error_sql = $error_occurred ? ", error='".sql_real_escape_string($error_message)."'" : "";
    sql_query("INSERT INTO `{$m3cron['log_table']}` SET 
             name='{$program_name}', 
             datetime='{$current_datetime}', 
             runtime='{$runtime}', 
             ip='".$_SERVER['REMOTE_ADDR']."', 
             robot='{$is_robot}', 
             mb_id='".sql_real_escape_string($member['mb_id'])."' 
             {$error_sql}");
}