<?php
// common.php 파일을 포함시켜 기본 환경 구성을 로드합니다.
include_once('./_common.php');

$password = get_encrypt_string('password');

// 게시글 작성 함수
function write_board($bo_table, $subject, $content, $link, $media, $pub_date, $mb_id, $nickname, $password) {
    global $g5;

    // 게시판 테이블
    $write_table = $g5['write_prefix'] . $bo_table;

    // SQL 인젝션 방지를 위해 escape 처리를 합니다.
    $subject = sql_real_escape_string($subject);
    $content = sql_real_escape_string($content);
    $link = sql_real_escape_string($link);
    $media = sql_real_escape_string($media);
    $pub_date = sql_real_escape_string($pub_date);
    $mb_id = sql_real_escape_string($mb_id);
    $nickname = sql_real_escape_string($nickname);

    // wr_num 최대값 조회
    $sql = " SELECT MAX(wr_num) as max_wr_num FROM `$write_table` ";
    $row = sql_fetch($sql);
    $wr_num = ($row && $row['max_wr_num']) ? $row['max_wr_num'] - 1 : 0;

    //시글 DB에 추가
    $sql = " INSERT INTO `$write_table`
                SET wr_num = '{$wr_num}',
                    wr_reply = '',
                    wr_comment = 0,
                    ca_name = '',
                    wr_option = '2', //html2 옵션으로 저장해야 이미지 등록 가능, 오류 발생 시 이 주석 삭제
                    wr_subject = '{$subject}',
                    wr_content = '{$content}',
                    wr_link1 = '{$link}',
                    wr_link2 = '{$media}',
                    wr_hit = 0,
                    wr_good = 0,
                    wr_nogood = 0,
                    mb_id = '{$mb_id}',
                    wr_password = '{$password}',
                    wr_name = '{$nickname}',
                    wr_email = '',
                    wr_datetime = '".G5_TIME_YMDHIS."',
                    wr_last = '".G5_TIME_YMDHIS."',
                    wr_ip = '".$_SERVER['REMOTE_ADDR']."',
                    wr_1 = '',
                    wr_2 = '',
                    wr_3 = '',
                    wr_4 = '',
                    wr_5 = '';
            ";
    sql_query($sql);
    $wr_id = sql_insert_id();

    $sql = " UPDATE `$write_table` SET wr_parent = '{$wr_id}' WHERE wr_id = '{$wr_id}' ";
    sql_query($sql);

    $sql = " INSERT INTO `{$g5['board_new_table']}`
                SET bo_table = '{$bo_table}',
                    wr_id = '{$wr_id}',
                    wr_parent = '{$wr_id}',
                    bn_datetime = '".G5_TIME_YMDHIS."',
                    mb_id = '{$mb_id}' ";
    sql_query($sql);
    
    $sql = " UPDATE `{$g5['board_table']}`
                SET bo_count_write = bo_count_write + 1
                WHERE bo_table = '{$bo_table}' ";
    sql_query($sql);

    return $wr_id;
}

// HTML 태그와 CSS 스타일 요소 제거하는 함수
function strip_html_css($content) {
    // HTML 태그와 CSS 스타일 요소 제거
    $content = preg_replace('/<[^>]+>/', '', $content);
    return $content;
}

// 게시글 작성 실행
$bo_table = 'dsclub'; // 게시판 테이블명
$mb_id = 'tak2'; // 게시글 작성자 ID
$nickname = 'tak2'; // 게시글 작성자 닉네임

// RSS 주소에서 데이터 가져오기
$url = '';
$data = file_get_contents($url);
$data = simplexml_load_string($data);

// 게시글 작성
foreach ($data->channel->item as $item) {
    $subject = $item->title;
    $content = $item->description;
    $link = $item->link;
    $media = ''; // 미디어 URL 초기화
    $pub_date = $item->pubDate; // pubDate 가져오기

    // 미디어 콘텐츠 가져오기
    if ($item->children('media', true)->content) {
        $media = (string)$item->children('media', true)->content->attributes()['url'];
    }

    // HTML 태그와 CSS 스타일 요소 제거
    $subject = strip_html_css($subject);
    $content = strip_html_css($content);

    // 미디어 요소를 img 태그로 변환
    $media_html = '';
    if ($media) {
        $media_html = "<img src=\"{$media}\" title=\"{$subject}\">";
    }

    // 콘텐츠 안의 텍스트 가져오기 (10줄로 제한)
    $content_text = strip_tags($content);
    $content_lines = explode("\n", $content_text);
    $content_lines = array_slice($content_lines, 0, 10);
    $content_text = implode("\n", $content_lines);

    // pubDate를 최상단에 위치하도록 수정
    $content_with_media = $pub_date . "<br>" . $media_html . "<br>" . $content_text . "<br>" . "<center><a href=\"{$link}\">[출처]</a></center>";

    $wr_id = write_board($bo_table, $subject, $content_with_media, $link, $media, $pub_date, $mb_id, $nickname, $password);
    if ($wr_id > 0) {
        echo "게시글이 정상적으로 등록되었습니다. 👍<br>";
        echo "제목: {$subject}<br>";
        echo "링크: <a href=\"{$link}\">{$link}</a><br>";
        if ($media) {
            echo "미디어 첨부: {$media_html}<br>";
        }
        echo "작성일: {$pub_date}<br>";
    } else {
        echo "게시글 등록에 실패하였습니다. 😥<br>";
    }
}

?>