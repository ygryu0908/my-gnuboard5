<?php
$sub_menu = "650100";
include_once('./_common.php');
include_once(G5_PATH.'/lib/m3cron.lib.php');

// 권한 체크 (그누보드 버전 호환성 유지)
if(function_exists('auth_check_menu')) {
    auth_check_menu($auth, $sub_menu, 'w');
} else {
    auth_check($auth[$sub_menu], 'w');
}

// CSRF 토큰 확인
check_admin_token();

// 입력값 필터링 (기본적인 처리만 유지)
$name = trim($_POST['name']);
$descript = htmlspecialchars(trim($_POST['descript']));
$type = trim($_POST['type']);
$d = preg_replace('/[^0-9]/', '', $_POST['d']);
$w = trim($_POST['w']);
$at_date = trim($_POST['at']);
$h = preg_replace('/[^0-9]/', '', $_POST['h']);
$i = preg_replace('/[^0-9]/', '', $_POST['i']);
$status = isset($_POST['status']) ? $_POST['status'] : 0;
$robot = isset($_POST['robot']) ? $_POST['robot'] : 0;
$vr_id = trim($_POST['vr_id']);
$vr_only = isset($_POST['vr_only']) ? $_POST['vr_only'] : 0;

// 주기 타입이 once인 경우 h값과 결합 등록하고, 아니면 null값 등록
$at = '';
if ($type === 'once' && $at_date) {
    $at = date('Y-m-d '.sprintf('%02d', $h).':'.sprintf('%02d', $i).':00', strtotime($at_date));
}

// SQL 인젝션 방지를 위한 이스케이프 처리
$escaped_name = sql_real_escape_string($name);
$escaped_descript = sql_real_escape_string($descript);
$escaped_type = sql_real_escape_string($type);
$escaped_at = $at ? sql_real_escape_string($at) : null;
$escaped_vr_id = sql_real_escape_string($vr_id);

// 저장하기
$sql = "UPDATE `{$m3cron['config_table']}` SET 
            descript='{$escaped_descript}', 
            type='{$escaped_type}', 
            d='{$d}', 
            w='{$w}', 
            at=".($at ? "'{$escaped_at}'" : "NULL").", 
            h='{$h}', 
            i='{$i}', 
            robot='{$robot}', 
            status='{$status}' 
        WHERE name='{$escaped_name}' 
        LIMIT 1";

sql_query($sql);

// 가상로봇 설정 일괄 저장
sql_query("UPDATE `{$m3cron['config_table']}` SET vr_id='{$escaped_vr_id}', vr_only='{$vr_only}'");

// 수정페이지 이동(유지)
goto_url('./m3cron_edit.php?name='.$name);