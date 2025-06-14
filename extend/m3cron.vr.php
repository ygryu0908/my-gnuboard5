<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/* ※ 가상로봇 사용자 환경 설정 조건※
1. 모바일 브라우저 이용시 디바이스 스크린이 OFF 상태면 스크립트가 중지되므로 공기계 등에 와이파이와 충전기 연결 후 항상 켜둬야 함(화면 꺼짐방지 앱 활용 및 화면밝기 조절 등 베터리 소모량 최소화 설정 후 사용 권장).
2. 일반 데스크탑 PC를 항상 켜두고 사용하는 경우 PC 이용

*** 가상로봇 계정과 관리자 계정이 동일한 아이피 환경에 있으면 접속 상태는 '신호없음' 으로 표시 됨 ***
*/

// 스크립트를 직접 연결하지 않고 나중에 처리할 수 있게 글로벌 변수로 설정
$GLOBALS['m3cron_vr_script'] = '';

if($is_member) { // 가상로봇은 임의의 회원 아이디를 사용함
	
	// 가상로봇 계정 정보
	$vr_info = sql_fetch("select vr_id from m3cron_config where vr_Id <> '' ", false);
	
	if($member['mb_id'] == $vr_info['vr_id']) {
		
		// * 스케줄 실행과 별개로 로그아웃 방지를 위해 주기적으로 새로고침 시킴
		// * 현재접속 처리 시간(관리자 페이지에서 가상로봇 접속여부 확인 겸)을 자동 새로고침 텀으로 사용하고, 최대 3시간(세션 유지시간)을 넘지 않도록 함
		// * 현재접속 처리 시간이 180분 이상이면 세션만료 10초 전에 새로고침 시킴
		
		$reload_term = ($config['cf_login_minutes'] < 180) ? ($config['cf_login_minutes'] * 60 * 1000) : (3 * 60 * 60 * 1000) - 10000; // 단위 : msec,  1초 = 1000 msec
		
		// 버퍼에 스크립트 저장 (직접 출력하지 않음)
		ob_start();
		?>
<script type="text/javascript">
/* <![CDATA[ */
(function() {
	// 가상로봇 자동 새로고침 스크립트
	setTimeout(function() { window.location.reload(); }, <?php echo $reload_term; ?>);
})();
/* ]]> */
</script>
		<?php
		$reload_script = ob_get_clean();
		$GLOBALS['m3cron_vr_script'] .= $reload_script;
			
		// 실행중인 파일 목록 가져오기
		$result = sql_query("select * from m3cron_config where type <> '' and status = '1' ", false);
		
		if($result) {
		
			// 요일 string
			$en_day_arr = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
			$time_list = array();
			$remain_time = array();
			
			for($i=0; $row = sql_fetch_array($result); $i++) {
					
				// 실행시 까지 남은시간
				switch($row['type']) {
					case 'monthly'	: $remain_time[$i] = strtotime(date('Y-m-'.$row['d'].' '.$row['h'].':'.$row['i'].':00')) - G5_SERVER_TIME; break;
					case 'weekly'	: $remain_time[$i] = strtotime(date('Y-m-d '.$row['h'].':'.$row['i'].':00', strtotime($en_day_arr[$row['w']]))) - G5_SERVER_TIME; break;
					case 'daily'	: $remain_time[$i] = strtotime(G5_TIME_YMD.' '.$row['h'].':'.$row['i'].':00') - G5_SERVER_TIME; break;
					case 'hourly'	: $remain_time[$i] = (strtotime($row['lastrun']) + ($row['h'] * 60 * 60)) - G5_SERVER_TIME; break;
					case 'minutely'	: $remain_time[$i] = (strtotime($row['lastrun']) + ($row['i'] * 60)) - G5_SERVER_TIME; break;
					case 'once'		: $remain_time[$i] = strtotime($row['at']) - G5_SERVER_TIME; break;
				}
				
				if($remain_time[$i] < 0) continue; // 남은 시간이 있는 스케줄만 체크
				
				$time_list[] = $remain_time[$i]; // 실행 목록 중 남은시간이 가장 적은 것만 사용
			}
            
            if($time_list) {
				ob_start();
?>
<script type="text/javascript">
/* <![CDATA[ */
(function() {
	// 가상로봇 실행시간 체크 스크립트
	var remain_time = <?php echo min($time_list);?>;
						
	function remain_timer() {
		remain_time--;
							
		if (remain_time <= 0) { // 실행시간 초과시 새로고침
			window.location.reload();
		}
	}
						
	remain_timer();
	setInterval(remain_timer, 1000);
})();
/* ]]> */
</script>
<?php
				$timer_script = ob_get_clean();
				$GLOBALS['m3cron_vr_script'] .= $timer_script;
			}
        }
	}
}

// tail.php나 tail.sub.php 파일의 맨 마지막에 스크립트 추가
// 파일에 추가하기 어려우면 아래 방법으로 직접 실행 직전에 출력

// 스크립트 호출 함수 정의
function m3cron_output_script() {
	if (!empty($GLOBALS['m3cron_vr_script'])) {
		echo $GLOBALS['m3cron_vr_script'];
	}
}

// PHP 종료 시 자동으로 함수를 호출하도록 등록
register_shutdown_function('m3cron_output_script');