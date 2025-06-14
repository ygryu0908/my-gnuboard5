<?php
$sub_menu = "650300";
include_once('./_common.php');
include_once(G5_PATH.'/lib/m3cron.lib.php');

// 권한 체크 (그누보드 버전 호환성 유지)
if(function_exists('auth_check_menu')) {
    auth_check_menu($auth, $sub_menu, 'w');
} else {
    auth_check($auth[$sub_menu], 'w');
}

// 토큰 검증
check_admin_token();

// 입력값 필터링 및 검증
$year = isset($_POST['year']) ? (int)preg_replace('/[^0-9]/', '', $_POST['year']) : 0;
$month = isset($_POST['month']) ? (int)preg_replace('/[^0-9]/', '', $_POST['month']) : 0;
$method = isset($_POST['method']) ? clean_xss_tags(trim($_POST['method'])) : '';
$pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';

// 유효성 검사
if (empty($pass)) {
    alert('관리자 비밀번호를 입력해 주십시오.');
}

// 관리자 비밀번호 비교
$admin = get_admin('super');
if (!check_password($pass, $admin['mb_password'])) {
    alert('관리자 비밀번호가 일치하지 않습니다.');
}

// 연도 및 월 유효성 검사
if ($year < 1000 || $year > 9999) {
    alert('올바른 연도를 선택해 주십시오.');
}

if ($month < 1 || $month > 12) {
    alert('올바른 월을 선택해 주십시오.');
}

// 로그삭제 조건 구성
$del_date = sprintf('%04d-%02d', $year, $month);

// 메소드 유효성 검사
if (!in_array($method, array('before', 'specific'))) {
    alert('올바른 방법으로 이용해 주십시오.');
}

// SQL 조건 구성
$sql_common = ($method === 'before') 
    ? " where substring(datetime, 1, 7) < '" . sql_real_escape_string($del_date) . "' "
    : " where substring(datetime, 1, 7) = '" . sql_real_escape_string($del_date) . "' ";

// 삭제될 로그 개수 미리 계산
$sql = " SELECT COUNT(*) as cnt FROM `{$m3cron['log_table']}` {$sql_common} ";
$row = sql_fetch($sql);
$delete_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

// 총 로그수
$sql = " SELECT COUNT(*) as cnt FROM `{$m3cron['log_table']}` ";
$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

// 삭제 실행
if ($delete_count > 0) {
    // 로그 삭제 실행
    $sql = " DELETE FROM `{$m3cron['log_table']}` {$sql_common} ";
    $result = sql_query($sql);
    
    if ($result === false) {
        alert('로그 삭제 중 오류가 발생했습니다.', './m3cron_log.php');
    }
    
    // 성공 메시지 구성
    $message = sprintf(
        '총 %s건 중 %s건 삭제 완료', 
        number_format($total_count), 
        number_format($delete_count)
    );
    
    alert($message, './m3cron_log.php');
} else {
    alert('삭제할 로그가 없습니다.', './m3cron_log.php');
}