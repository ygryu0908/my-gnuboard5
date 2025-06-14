<?php 
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// m3cron 설정
$m3cron['config_table'] = "m3cron_config";
$m3cron['log_table'] = "m3cron_log";
$m3cron['path'] = G5_PATH.'/m3cron';
define('G5_M3CRON_PATH',  $m3cron['path']);
// 상용 변수들
$type_arr = array("monthly", "weekly", "daily", "hourly", "minutely", "once");
$day_arr = array("일", "월", "화", "수", "목", "금", "토");


// 로봇인 경우
$bot_patterns = 'bot|slurp|crawler|spider|archiver|validator|facebook|ia_archiver|googlebot|bingbot|yandex';
if(preg_match("/($bot_patterns)/i", $_SERVER['HTTP_USER_AGENT'])) {
    $is_robot = 1;
} else {
    $is_robot = 0;
}

// 폴더명을 얻는다
function get_gr_dir($skin, $len='') {
    static $dir_cache = array(); // 정적 캐시 변수
    
    // 이미 캐시된 결과가 있으면 반환
    if (isset($dir_cache[$skin])) {
        return $dir_cache[$skin];
    }

    $result_array = array();
    $dirname = G5_PATH.'/'.$skin.'/';

    if(!is_dir($dirname)) return;

    $handle = opendir($dirname);
    while ($file = readdir($handle)) {
        if($file == "."||$file == "..") continue;

        if (is_dir($dirname.$file)) $result_array[] = $file;
    }
    closedir($handle);
    sort($result_array);
    
    // 결과를 캐시에 저장
    $dir_cache[$skin] = $result_array;
    
    return $result_array;
}

// 폴더내 파일을 얻는다
function get_file_list($dir) {
    static $file_cache = array(); // 정적 캐시 변수
    
    // 이미 캐시된 결과가 있으면 반환
    if (isset($file_cache[$dir])) {
        return $file_cache[$dir];
    }

    $arr = array();
    // 디렉토리 경로 검증 (상위 디렉토리 접근 방지)
    $dir = str_replace(array('..', '/', '\\'), '', $dir);
    $file_path = G5_PATH.'/m3cron/'.$dir.'/';

    if(!is_dir($file_path)) return;

    $handle = opendir($file_path);
    
    while ($file = readdir($handle)) {
        if($file == "."||$file == "..") continue;

        if(preg_match('/\.php$/i', $file)) {
            $arr[] = $file;
        }
    }
    closedir($handle);
    
    sort($arr);
    
    // 결과를 캐시에 저장
    $file_cache[$dir] = $arr;
    
    return $arr;
}

// 실행 주기 셀렉트
function m3_cycle($name, $value) {
	global $prog, $day_arr, $type_arr;
	
	// 실행 주기 셀렉트
	if($name == 'type') {
		$str = '<select name="'.$name.'" style="text-align-last:center">'.PHP_EOL;
		$str .= '<option value="">- 선택 -</option>'.PHP_EOL;
		$str .= '<option value="monthly"'.get_selected('monthly', $value).'>* 월/1회 (monthly)</option>'.PHP_EOL;
		$str .= '<option value="weekly"'.get_selected('weekly', $value).'>* 주/1회 (weekly)</option>'.PHP_EOL;
		$str .= '<option value="daily"'.get_selected('daily', $value).'>* 일/1회 (daily)</option>'.PHP_EOL;
		$str .= '<option value="hourly"'.get_selected('hourly', $value).'>* n시간/1회 (hourly)</option>'.PHP_EOL;
		$str .= '<option value="minutely"'.get_selected('minutely', $value).'>* n분/1회 (minutely)</option>'.PHP_EOL;
		$str .= '<option value="once"'.get_selected('once', $value).'>* 한 번 (once)</option>'.PHP_EOL;
		$str .= '</select>'.PHP_EOL;
	} else {
		switch($name) {
			case 'd'	:	$display = ($prog['type'] == 'monthly') ? '':'none'; break;
			case 'w'	:	$display = ($prog['type'] == 'weekly') ? '':'none'; break;
			case 'h'	:	$display = in_array($prog['type'], $type_arr) && $prog['type'] != 'minutely' ? '':'none'; break;
			case 'i'	:	$display = in_array($prog['type'], $type_arr) && $prog['type'] != 'hourly' ? '':'none'; break;
			case 'at'	:	$display = ($prog['type'] == 'once') ? '':'none'; break;
		}
		// 실행 일자 셀렉트
		if($name == 'd') {
			$str = '<select name="'.$name.'" id="'.$name.'" style="display:'.$display.';text-align-last:center">'.PHP_EOL;
			for ($i=1; $i < 29; $i++) {
				$d = sprintf('%02d',$i); //2자리 표기
				$str .= '<option value="'.$i.'"'.get_selected($value, $i).'>'.$d.' 일</option>'.PHP_EOL;
			}
			$str .= '</select>'.PHP_EOL;
		}
		// 실행 요일 셀렉트
		if($name == 'w') {
			$str = '<select name="'.$name.'" id="'.$name.'" style="display:'.$display.';text-align-last:center">'.PHP_EOL;
			for ($i=0; $i < 7; $i++) {
				$str .= '<option value="'.$i.'"'.get_selected($value, $i).'>'.$day_arr[$i].'요일</option>'.PHP_EOL;
			}
			$str .= '</select>'.PHP_EOL;
		}
		// 실행 시간 셀렉트
		if($name == 'h') {
			$str = '<select name="'.$name.'" id="'.$name.'" style="display:'.$display.';text-align-last:center">'.PHP_EOL;
			for ($i=0; $i < 24; $i++) {
				$h = sprintf('%02d',$i); //2자리 표기
				$str .= '<option value="'.$i.'"'.get_selected($value, $i).'>'.$h.' 시</option>'.PHP_EOL;
			}
			$str .= '</select>'.PHP_EOL;
		}
		// 실행 분 셀렉트
		if($name == 'i') {
			$str = '<select name="'.$name.'" id="'.$name.'" style="display:'.$display.';text-align-last:center">'.PHP_EOL;
			for ($i=0; $i < 60; $i++) {
				$m = sprintf('%02d',$i); //2자리 표기
				$str .= '<option value="'.$i.'"'.get_selected($value, $i).'>'.$m.' 분</option>'.PHP_EOL;
			}
			$str .= '</select>'.PHP_EOL;
		}
		// 한 번 실행 날짜입력
		if($name == 'at') {
			$value = strtotime($value) > 0 ? date('Y-m-d', strtotime($value)) : '';
			$str = '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'" class="frm_input" size="11" maxlength="10" autocomplete="off" style="display:'.$display.';text-align-last:center">'.PHP_EOL;
		}
	}
	
	return $str;
	
}

// 로그삭제 연도 셀렉트
function m3_delete_year($name) {
    global $m3cron;
    static $min_year = null; // 정적 변수로 SQL 쿼리 결과 저장
    
    // 최소 연도가 아직 조회되지 않았을 때만 SQL 실행
    if ($min_year === null) {
        // 최소 연도 구함
        $sql = " select min(datetime) as min_date from `{$m3cron['log_table']}` ";
        $row = sql_fetch($sql);
        $min_year = $row['min_date'] ? (int)substr($row['min_date'], 0, 4) : 0;
    }
    
    $now_year = (int)substr(G5_TIME_YMD, 0, 4);
    
    $str = '<select name="'.$name.'" id="'.$name.'">'.PHP_EOL;
    $str .= '<option value="">&nbsp;연도 선택&nbsp;</option>'.PHP_EOL;
    
    if($min_year) {
        for($i=$min_year; $i<=$now_year; $i++) {
            $str .= '<option value="'.$i.'">'.$i.'</option>'.PHP_EOL;
        }
    }
    $str .= '</select>'.PHP_EOL;
    
    return $str;
}

// 로그삭제 월 셀렉트
function m3_delete_month($name) {
	
	$str = '<select name="'.$name.'" id="'.$name.'">'.PHP_EOL;
	$str .= '<option value="">&nbsp;월 선택&nbsp;</option>'.PHP_EOL;
	for($i=1; $i<=12; $i++) {
		$str .= '<option value="'.$i.'">'.$i.'</option>'.PHP_EOL;
	}
	$str .= '</select>'.PHP_EOL;
	
	return $str;
	
}

// 로그삭제 최종범위 설정 셀렉트
function m3_delete_method($name) {
	
	$str = '<select name="'.$name.'" id="'.$name.'">'.PHP_EOL;
	$str .= '<option value="specific">선택연월 자료삭제</option>'.PHP_EOL;
	$str .= '<option value="before">선택연월 이전 자료삭제</option>'.PHP_EOL;
	$str .= '</select>'.PHP_EOL;
	
	return $str;
	
}