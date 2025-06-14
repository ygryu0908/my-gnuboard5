<?php // m3cron ver 1.11
$sub_menu = "650100";
include_once("./_common.php");

// 권한 체크 (그누보드 버전 호환성 유지)
if(function_exists('auth_check_menu')) {
    auth_check_menu($auth, $sub_menu, 'r');
} else {
    auth_check($auth[$sub_menu], 'r');
}

include_once(G5_PATH.'/lib/m3cron.lib.php');

// 관리자 권한 확인
if(!$is_admin) {
    alert("권한이 없습니다.");
}

// 수정 파일명 변수 확인
$name = isset($_GET['name']) ? clean_xss_tags(trim($_GET['name'])) : '';
if(empty($name)) {
    die("파일명이 지정되지 않았습니다.");
}

// 불러오기 (SQL 인젝션 방지)
$escaped_name = sql_real_escape_string($name);
$sql = "SELECT * FROM `{$m3cron['config_table']}` WHERE name='{$escaped_name}'";
$prog = sql_fetch($sql);

if(!$prog) {
    alert("내용이 존재하지 않는 파일입니다.");
}

$g5['title'] = "m3cron 수정";

include_once('./admin.head.php');
include_once(G5_PLUGIN_PATH.'/jquery-ui/datepicker.php');

// XSS 방지를 위한 출력 변수 처리
$prog_name = substr($prog['name'], 0, -4); // .php 확장자 제거
$prog_descript = isset($prog['descript']) ? htmlspecialchars($prog['descript']) : '';
$prog_vr_id = isset($prog['vr_id']) ? htmlspecialchars($prog['vr_id']) : '';
$prog_vr_only = isset($prog['vr_only']) ? (int)$prog['vr_only'] : 0;
$prog_robot = isset($prog['robot']) ? (int)$prog['robot'] : 0;
$prog_status = isset($prog['status']) ? (int)$prog['status'] : 0;
?>

<form name="fm3cronedit" action="./m3cron_edit_update.php" method="post">

<input type="hidden" name="name" value="<?php echo htmlspecialchars($prog['name']); ?>">

<section id="anc_cf_basic">
    <div class="tbl_frm01 tbl_wrap m3">
        <table>
        <caption>m3cron 관리자 설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="cf_title">파일명<strong class="sound_only">필수</strong></label></th>
            <td><?php echo $prog_name; ?></td>
        </tr>
        <tr>
            <th scope="row">파일 설명</th>
            <td><input type="text" name="descript" value="<?php echo $prog_descript; ?>" class="frm_input" size="70"></td>
        </tr>
        <tr>
            <th scope="row">실행주기</th>
            <td>
                <?php echo m3_cycle('type', $prog['type']); ?>
                <?php echo m3_cycle('d', $prog['d']); ?>
                <?php echo m3_cycle('w', $prog['w']); ?>
                <?php echo m3_cycle('at', $prog['at']); ?>
                <?php echo m3_cycle('h', $prog['h']); ?>
                <?php echo m3_cycle('i', $prog['i']); ?>
                <span id="h_info" style="display:<?php echo ($prog['type'] == 'hourly') ? '' : 'none'; ?>">간 마다 실행 ( 00 시 = 매 번 실행 )</span>
                <span id="i_info" style="display:<?php echo ($prog['type'] == 'minutely') ? '' : 'none'; ?>"> 마다 실행 ( 00 분 = 매 번 실행 )</span>
                <span id="at_info" style="display:<?php echo ($prog['type'] == 'once') ? '' : 'none'; ?>; padding-left:10px;">*오늘(<?php echo G5_TIME_YMD; ?>) 실행 설정시, 00시~<?php echo date('H'); ?>시로 지정하면 즉시 실행되므로 현재시간 이후로 설정해야 합니다.</span>
            </td>
        </tr>
        <tr>
            <th scope="row">가상로봇 계정</th>
            <td>
                <input type="text" name="vr_id" value="<?php echo $prog_vr_id; ?>" class="frm_input" size="25">&nbsp;
                <label><input type="checkbox" name="vr_only" value="1" <?php echo get_checked('1', $prog_vr_only); ?>> <b>가상로봇만 실행 가능</b> ('검색로봇 실행' 설정보다 우선하며, 계정 미등록시 적용 안됨)</label>
            </td>
        </tr>
        <tr>
            <th scope="row">검색로봇 실행</th>
            <td><label><input type="checkbox" name="robot" value="1" <?php echo get_checked('1', $prog_robot); ?>> <b>검색로봇이 접속한 경우만 실행</b> (실행 소요시간이 길면 사람은 중간에 창을 닫을 수 있음)</label></td>
        </tr>
        <tr>
            <th scope="row">실행 여부</th>
            <td><label><input type="checkbox" name="status" value="1" <?php echo get_checked('1', $prog_status); ?>> 실행시 체크</label></td>
        </tr>
        <tr>
            <td colspan="2">
                <input type="submit" class="btn btn_01" value="저장하기" accesskey="s" title="alt+s">
                <input type="button" class="btn btn_03" value="목록" onclick="location.href='./m3cron_list.php'" accesskey="l" title="alt+l">
            </td>
        </tr>
        </tbody>
        </table>
    </div>
</section>
</form>

<script>
$(document).ready(function(){

    //show & hide
    $("select[name='type']").change(function(){
        // 모든 필드 초기 숨김
        var allFields = ['d', 'w', 'at', 'h', 'i'];
        var allInfos = ['at_info', 'h_info', 'i_info'];
        
        // 모든 필드 숨김 처리
        allFields.forEach(function(field) {
            document.getElementById(field).style.display = 'none';
        });
        
        // 모든 안내 텍스트 숨김 처리
        allInfos.forEach(function(info) {
            document.getElementById(info).style.display = 'none';
        });
        
        // 필요한 필드만 표시
        var type = $(this).val();
        
        if (type === 'monthly') { // 월
            document.getElementById('d').style.display = '';
            document.getElementById('h').style.display = '';
            document.getElementById('i').style.display = '';
            document.getElementById("at").required = false;
        } else if (type === 'weekly') { // 주
            document.getElementById('w').style.display = '';
            document.getElementById('h').style.display = '';
            document.getElementById('i').style.display = '';
            document.getElementById("at").required = false;
        } else if (type === 'daily') { // 일
            document.getElementById('h').style.display = '';
            document.getElementById('i').style.display = '';
            document.getElementById("at").required = false;
        } else if (type === 'hourly') { // 시간
            document.getElementById('h').style.display = '';
            document.getElementById('h_info').style.display = '';
            document.getElementById("at").required = false;
        } else if (type === 'minutely') { // 분
            document.getElementById('i').style.display = '';
            document.getElementById('i_info').style.display = '';
            document.getElementById("at").required = false;
        } else if (type === 'once') { // 한 번
            document.getElementById('at').style.display = '';
            document.getElementById('at_info').style.display = '';
            document.getElementById('h').style.display = '';
            document.getElementById('i').style.display = '';
            document.getElementById("at").required = true;
        }
    });
    
    <?php if($prog['type'] == 'once' && isset($prog['at']) && $prog['at'] <= G5_TIME_YMDHIS) { ?>
        // 한 번 실행 설정시 지정일이 지나면 폼을 비우고 필수입력으로
        document.getElementById("at").value = '';
        document.getElementById("at").required = true;
    <?php } ?>
});

$(function(){
    $("#at").datepicker({ 
        changeMonth: true, 
        changeYear: true, 
        dateFormat: "yy-mm-dd", 
        showButtonPanel: true, 
        yearRange: "c-99:c+99", 
        minDate: "+0d;", 
        maxDate: "+365d" 
    });
});
</script>

<?php
include_once('./admin.tail.php');