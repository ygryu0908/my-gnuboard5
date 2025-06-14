<?php // m3cron ver 1.10
$sub_menu = "650200";
include_once("./_common.php");

// 권한 체크
if(function_exists('auth_check_menu')) {
    auth_check_menu($auth, $sub_menu, 'r');
} else {
    auth_check($auth[$sub_menu], 'r');
}

include_once(G5_PATH.'/lib/m3cron.lib.php');

$g5['title'] = "m3cron 로그";
include_once('./admin.head.php');

$colspan = 6;

// 로그 가져오기
$sql_common = " from `{$m3cron['log_table']}` ";

// 파일명 지정시 조건 추가 (XSS 방지 및 SQL 인젝션 방지)
if($stx) { 
    $search_term = '';
    if(strpos($stx, 'foldergubun') !== false) {
        $search_term = str_replace("foldergubun", "/", $stx);
    } else {
        $search_term = $stx;
    }
    $search_term = sql_real_escape_string($search_term);
    $sql_common .= " where name = '{$search_term}' ";
}

// 테이블의 전체 레코드수 조회
$sql = " select count(*) as cnt " . $sql_common;
$row = sql_fetch($sql);
$total_count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

// 페이지네이션 변수 설정
$rows = $config['cf_page_rows'];
$total_page = ceil($total_count / $rows);  // 전체 페이지 계산
$page = isset($page) ? (int)$page : 1;
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

$sql_order = " order by datetime DESC ";

// 출력할 레코드를 얻음
$sql = " select * 
          $sql_common
          $sql_order
          limit $from_record, $rows ";
$result = sql_query($sql);
?>

<div class="local_ov01 local_ov">
    <h4>* m3cron 로그 삭제</h4>
</div>

<div class="tbl_head01 tbl_wrap m3">
    <form name="fm3crondelete" class="m3cron_del" method="post" action="./m3cron_delete_update.php" onsubmit="return form_submit(this);">
    
    <div class="log_delete">
        <?php echo m3_delete_year('year'); ?>&nbsp;
        <?php echo m3_delete_month('month'); ?>&nbsp;
        <?php echo m3_delete_method('method'); ?>&nbsp;
        <label for="pass">관리자 비밀번호<strong class="sound_only"> 필수</strong></label>
        <input type="password" name="pass" id="pass" class="frm_input required">
        <input type="submit" value="확인" class="btn_submit btn" style="padding:0 10px;">
    </div>
    </form>
</div>

<div class="tbl_head01 tbl_wrap">
    <div class="local_ov01 local_ov">
        <span class="btn_ov01">
            <span class="ov_txt"><?php echo $stx ? '검색결과' : '전체'; ?></span>
            <span class="ov_num"><?php echo number_format($total_count); ?> 건</span>
        </span>
        <?php if($stx) { ?>
            &nbsp;<a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="btn btn_03 btn_total">전체보기</a>
        <?php } ?>
    </div>
    <table>
    <caption>m3cron 실행 기록 목록</caption>
    <thead>
    <tr>
        <th scope="col">파일명</th>
        <th scope="col">실행시각</th>
        <th scope="col">처리시간</th>
        <th scope="col">IP</th>
        <th scope="col">로봇</th>
        <th scope="col">mb_id 실행</th>
    </tr>    
    </thead>
    <tbody>

    <?php
    $list_count = 0;
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        // 폴더 아이콘
        $is_folder = strpos($row['name'], '/') !== false;
        $icon_f = $is_folder ? '<i class="fa fa-folder-open gray"></i> ' : '';
        $file_name = substr($row['name'], 0, -4); // .php 확장자 제거
        
        // 검색 링크 URL 생성
        $search_url = '';
        if (!$stx) {
            $encoded_name = $is_folder ? 
                urlencode(str_replace("/", "foldergubun", $row['name'])) : 
                urlencode($row['name']);
            $search_url = './m3cron_log.php?stx=' . $encoded_name;
        }
        
        // 실행시간 (밀리초)
        $runtime = sprintf("%.3f", $row['runtime'] * 1000);
    ?>
    <tr>
        <td style="text-align:left;padding-left:10px;">
            <?php 
            if (!$stx) {
                echo '<a href="' . $search_url . '" class="each_log">' . $icon_f . $file_name . '</a>';
            } else {
                echo $icon_f . $file_name;
            }
            ?>
        </td>
        <td align="center"><?php echo $row['datetime']; ?></td>
        <td align="center"><?php echo $runtime; ?> msec</td>
        <td align="center"><?php echo $row['ip']; ?></td>
        <td align="center"><?php echo $row['robot']; ?></td>
        <td align="center"><?php echo $row['mb_id']; ?></td>
    </tr>
    <?php 
        $list_count++;
    } 
    
    if($list_count == 0) {
        echo '<tr><td colspan="' . $colspan . '" class="empty_table">자료가 없습니다.</td></tr>';
    }
    ?>
    </tbody>
    </table>
</div>

<script>
function form_submit(f)
{
    var year = $("#year").val();
    var month = $("#month").val();
    var method = $("#method").val();
    var pass = $("#pass").val();

    if(!year) {
        alert("연도를 선택해 주십시오.");
        return false;
    }

    if(!month) {
        alert("월을 선택해 주십시오.");
        return false;
    }

    if(!pass) {
        alert("관리자 비밀번호를 입력해 주십시오.");
        return false;
    }

    var msg = year+"년 "+month+"월";
    if(method == "before")
        msg += " 이전";
    else
        msg += "의";
    msg += " 자료를 삭제하시겠습니까?";

    return confirm(msg);
}
</script>

<?php
$pagelist = get_paging($config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page=');
if ($pagelist) {
    echo $pagelist;
}

include_once('./admin.tail.php');