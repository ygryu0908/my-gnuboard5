<?php // m3cron ver 1.10
$sub_menu = "650100";
include_once("./_common.php");

if(function_exists('auth_check_menu')) {
	auth_check_menu($auth, $sub_menu, 'r');
} else {
	auth_check($auth[$sub_menu], 'r');
}

include_once(G5_PATH.'/lib/m3cron.lib.php');

$g5['title'] = "m3cron 설정";
include_once('./admin.head.php');

// CONFIG 테이블 생성
sql_query( "CREATE TABLE IF NOT EXISTS `{$m3cron['config_table']}` (
	`name` VARCHAR(50) NOT NULL,
	`descript` VARCHAR(255) NOT NULL,
	`type` VARCHAR(10) NOT NULL,
	`prog_order` INT(11) NOT NULL DEFAULT '0',
	`run_now` TINYINT NOT NULL DEFAULT '0',
	`d` TINYINT NOT NULL,
	`w` TINYINT NOT NULL,
	`at` datetime NOT NULL,
	`h` TINYINT NOT NULL,
	`i` TINYINT NOT NULL,
	`lastrun` datetime NOT NULL,
	`lastruntime` FLOAT NOT NULL,
	`status` TINYINT NOT NULL,
	`robot` TINYINT NOT NULL,
	`vr_id` VARCHAR(50) NOT NULL,
	`vr_only` TINYINT NOT NULL,
	UNIQUE (`name`)
)");

// 정렬 순서 지정 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'prog_order' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `prog_order` INT(11) NOT NULL DEFAULT '0' AFTER `type` ", false);

// 지금 실행 지정 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'run_now' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `run_now` TINYINT NOT NULL DEFAULT '0' AFTER `prog_order` ", false);

// 한 번 실행용 날짜 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'at' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `at` datetime NOT NULL AFTER `w` ", false);

// 분 단위 지정 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'i' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `i` TINYINT NOT NULL AFTER `h` ", false);

// 가상로봇 계정 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'vr_id' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `vr_id` VARCHAR(50) NOT NULL AFTER `robot` ", false);

// 가상로봇 실행 전용 필드 추가
$sql = " SHOW COLUMNS FROM {$m3cron['config_table']} LIKE 'vr_only' ";
$row = sql_fetch($sql);
if(!$row['Field']) sql_query(" ALTER TABLE `{$m3cron['config_table']}` ADD `vr_only` TINYINT NOT NULL AFTER `vr_id` ", false);

// LOG 테이블 생성
sql_query( "CREATE TABLE IF NOT EXISTS `{$m3cron['log_table']}` (
	`name` VARCHAR(50) NOT NULL,
	`datetime` DATETIME NOT NULL,
	`runtime` FLOAT NOT NULL,
	`ip` CHAR(15) NOT NULL,
	`robot` TINYINT NOT NULL,
	`mb_id` VARCHAR(50) NOT NULL,
	INDEX (`name`,`datetime`)
)");

// 존재하는 파일 목록 가져오기
if(is_dir($m3cron['path'])) {
	// php 파일 include
	$dir = opendir($m3cron['path']);
	
	while ($entry = readdir($dir)) {
		if(preg_match('/\.php$/i', $entry))
			$m3cron['list'][] = $entry;
	}
	
	// 하위 폴더 파일 추가 병합
	$gr_files = array();

    $gr_dirs = get_gr_dir('m3cron');
    
	if(!empty($gr_dirs)) {
        foreach($gr_dirs as $gr_dir) {
			$file_list = get_file_list($gr_dir);
			foreach($file_list as $file) {
				$gr_files[] = $gr_dir.'/'.$file;
			}
        }
    }
	
    $cron_files = array_merge($gr_files, $m3cron['list']);
	
    sort($cron_files);
	
}

// 이하는 m3cron 폴더에 파일이 있을 경우에만 실행
if($cron_files) {

	// m3cron_config에 있지만 파일이 없는 놈들은 삭제
	$query = sql_query(" select name from `{$m3cron['config_table']}` ");
	while($row = sql_fetch_array($query)) {
		if(!in_array($row['name'], $cron_files)) sql_query(" delete from `{$m3cron['config_table']}` where name='{$row['name']}' limit 1 ");
	}

	// m3cron_config에 입력하기. 에러 무시하면 unique가 걸려있으므로 새로운 녀석들만 들어감
	foreach($cron_files as $name) {
		sql_query(" insert into `{$m3cron['config_table']}` set name='{$name}' ", false);
	}
	
	// 가상로봇 계정이 지정되어 있고, 신규 파일이 추가되면 계정 통일 - 별도 테이블을 안쓰므로 행마다 넣어줘야 함.
	$vr_set = sql_fetch(" select vr_id from `{$m3cron['config_table']}` where vr_id <> '' "); // 지정 된 가상로봇 계정 얻기
	$result = sql_query(" select * from `{$m3cron['config_table']}` where vr_id = '' ", false); // 빈 컬럼만 찾아 넣기
	if($result) {
		while($row = sql_fetch_array($result)) {
			sql_query(" update `{$m3cron['config_table']}` set vr_id = '{$vr_set['vr_id']}' where name='{$row['name']}' ");
		}
	}
	
	// 가상로봇 계정 접속 여부
	$is_vr = sql_fetch(" select count(*) as cnt from {$g5['login_table']} where mb_id <> '' and mb_id = '{$vr_set['vr_id']}' ");
	$is_vr_online = $is_vr['cnt'];

	// 목록 보이기 시작
	$query = sql_query(" select * from `{$m3cron['config_table']}` order by prog_order asc, name asc ");
	$cnt = sql_num_rows($query);
	$temp = sql_fetch(" select count(*) as cnt2 from `{$m3cron['config_table']}` where status='1' and type <> '' ");
	$cnt2 = $temp['cnt2'];
	$temp = sql_fetch(" select count(*) as cnt3 from `{$m3cron['config_table']}` where status!='1' or type='' ");
	$cnt3 = $temp['cnt3'];
	
	// 목록에서 순서 변경
	if($mode == 'prog_order') {
	
		$cnt = count($_POST['prog_order']);
		
		for($i=0; $i < $cnt; $i++) {
			
			$prog_name = trim(str_replace("foldergubun", "/", $_POST['prog_name'][$i]));
			$prog_order = intval($_POST['prog_order'][$i]);
	
			$sql = " update {$m3cron['config_table']} set prog_order = '{$prog_order}' where name = '{$prog_name}' ";
			sql_query($sql);
		}
	
		goto_url($_SERVER['SCRIPT_NAME']);
	}
	
	// 지금 실행
	if($mode == 'run_order') {
	
		$prog_name = trim($_POST['prog_name']);
	
		$sql = " update {$m3cron['config_table']} set run_now = '1' where name = '{$prog_name}' ";
		sql_query($sql);
	}
?>

<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">전체</span><span class="ov_num"><b class="font-14"><?php echo number_format($cnt);?></b></span></span>&nbsp;
    <span class="btn_ov01"><span class="ov_txt">실행중</span><span class="ov_num"><b class="font-14"><?php echo number_format($cnt2);?></b></span></span>&nbsp;
    <span class="btn_ov01"><span class="ov_txt">비활성</span><span class="ov_num"><b class="font-14"><?php echo number_format($cnt3);?></b></span></span>&nbsp;
    <span class="btn_ov01"><span class="ov_txt">가상로봇</span><span class="ov_num"> <?php echo $is_vr_online ? '접속중' : (!$vr_set ? '계정 미등록' : '신호없음');?></span></span>
</div>

<form id="m3cronlistform" name="m3cronlistform" method="post">
<input type="hidden" name="mode" value="prog_order">
<div class="tbl_head01 tbl_wrap">
    <table class="table-striped">
    <caption>m3cron 관리자 목록</caption>
    <thead>
    <tr>
        <th scope="col">No</th>
        <th scope="col">파일명</th>
        <th class="td_mng_xs">순서</th>
        <th scope="col">설명</th>
        <th scope="col">지금 실행</th>
        <th class="td_mng_s">주기</th>
        <th class="td_mng_min">일자</th>
        <th class="td_mng_xxs">요일</th>
        <th class="td_mng_xxs">시간</th>
        <th class="td_mng_xxs">분</th>
        <th class="td_mng_l">마지막 실행</th>
        <th class="td_mng_m">처리시간 (msec)</th>
        <th class="td_mng_xxs">상태</th>
        <th class="td_mng_xxs">로봇</th>
        <th scope="col">관리</th>
	</tr>	
    </thead>
    <tbody>

	<?php // 스케줄 시작
		for($i=0; $prog = sql_fetch_array($query); $i++) {
            
			// 처리시간 계산
			$prog['lastruntime'] = sprintf("%.3f", $prog['lastruntime']*1000);
			
			// 폴더 아이콘
			$icon_f = strpos($prog['name'],'/') !== false ? '<i class="fa fa-folder-open gray"></i> ':'';
			
			// 타입별 관련없는 설정값은 미표기
            switch($prog['type']) {
                case 'monthly'	: $prog['type'] = '매 월'; $prog['d'] = $prog['d']; $prog['w'] = '-'; break;
                case 'weekly'	: $prog['type'] = '매 주'; $prog['d'] = '-'; $prog['w'] = $day_arr[$prog['w']]; break;
                case 'daily'	: $prog['type'] = '매 일'; $prog['d'] = '-'; $prog['w'] = '-'; break;
                case 'hourly'	: $prog['type'] = ($prog['h'] == 0) ? '매 번' : '매 시간'; $prog['d'] = '-'; $prog['w'] = '-'; $prog['h'] = ($prog['h'] == 0) ? '-' : $prog['h']; $prog['i'] = '-'; break;
				case 'minutely'	: $prog['type'] = ($prog['i'] == 0) ? '매 번' : '매 분'; $prog['d'] = '-'; $prog['w'] = '-'; $prog['h'] = '-'; $prog['i'] = ($prog['i'] == 0) ? '-' : $prog['i']; break;
				case 'once'		: $prog['type'] = '한 번'; $prog['d'] = date('Y-m-d', strtotime($prog['at'])); $prog['w'] = $day_arr[date('w', strtotime(date('Y-m-d', strtotime($prog['at']))))]; break;
                default			: $prog['type'] = ''; $prog['d'] = ''; $prog['w'] = ''; $prog['h'] = ''; $prog['i'] = ''; break;
            }
			
            // 상태 아이콘
			$icon_on = '<i class="fa fa-circle-o blue"></i>';
			$icon_off = '<i class="fa fa-times"></i>';
			
            // 로봇 설정 상태
            $robot = $prog['robot'] ? ($prog['vr_id'] && $prog['vr_only'] ? 'VR' : $icon_on) : ($prog['vr_id'] && $prog['vr_only'] ? 'VR' : $icon_off);
			
            // 실행 상태(주기와 사용에 체크되어야 작동)
            $status = $prog['type'] && $prog['status'] ? $icon_on : $icon_off;
    ?>
    <tr>
        <td align="center"><?php echo $i+1;?></td>
        <td class="left"><?php echo $icon_f;?><?php echo substr($prog['name'],0,-4);?></td>
        <td align="center">
            <input type="text" name="prog_order[]" value="<?php echo $prog['prog_order'];?>" class="frm_input" size="2" autocomplete='off'>
            <input type="hidden" name="prog_name[]" value="<?php echo str_replace("/", "foldergubun", $prog['name']);?>">
        </td>
        <td class="left"><?php echo $prog['descript'];?></td>
        <td align="center" class="td_mng_s"><a href="#" onclick="run_it('<?php echo get_text($prog['name']);?>'); return false;" class="btn btn_02">실행</a></td>
        <td align="center"><?php echo $prog['type'];?></td>
        <td align="center"><?php echo $prog['d'];?></td>
        <td align="center"><?php echo $prog['w'];?></td>
        <td align="center"><?php echo $prog['h'];?></td>
        <td align="center"><?php echo $prog['i'];?></td>
        <td align="center"><?php echo $prog['lastrun'];?></td>
        <td align="center"><?php echo $prog['lastruntime'];?></td>
        <td align="center"><?php echo $status;?></td>
        <td align="center"><?php echo $robot;?></td>
        <td class="td_mng td_mng_m">
        	<a href="./m3cron_edit.php?name=<?php echo urlencode($prog['name']);?>" class="btn btn_01">수정</a>
            <a href="./m3cron_log.php?stx=<?php echo strpos($prog['name'],'/') !== false ? urlencode(str_replace("/", "foldergubun", $prog['name'])) : urlencode($prog['name']);?>" class="btn btn_02">로그</a>
        </td>
    </tr>
	<?php } ?>
    </tbody>
    </table>
</div>
<div class="btn_fixed_bottom" style="padding-top:50px;">
	<input type="submit" value="순서변경" class="btn_submit btn" accesskey="s">
</div>
</form>

<script>
function run_it(prog_name) {
	if (confirm("다음의 파일을 실행합니다.\n[ " + prog_name + " ]")) {
		$.ajax({
			type: "POST",
			url: document.location.href,
			data: {
                'mode': 'run_order',
				'table_name': '<?php echo $m3cron['config_table'];?>',
                'prog_name': prog_name,
            },
			success: function () {
				window.location.reload();
			}
		});
	} else {
		return false;
	}
}
</script>

<?php } // end if cron_files
include_once('./admin.tail.php');