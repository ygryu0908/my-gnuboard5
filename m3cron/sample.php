<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 예제
// 관리자에게 1포인트 지급
if($is_admin) {
	insert_point('admin', '1', '크론 테스트 포인트', '@passive', 'admin', $member['mb_id'].'-'.uniqid(''), '');
}