<?php
if (!defined('_INDEX_')) define('_INDEX_', true);
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 모바일 체크를 비활성화 (PC 버전으로 통일)
// if (G5_IS_MOBILE) {
//     include_once(G5_THEME_MOBILE_PATH.'/index.php');
//     return;
// }

if(G5_COMMUNITY_USE === false) {
    include_once(G5_THEME_SHOP_PATH.'/index.php');
    return;
}

include_once(G5_THEME_PATH.'/head.php');

// YouTube 랭킹 위젯 로드
include_once(G5_THEME_PATH.'/ybcontents/youtube_main_widget_clean.php');

?>

<!-- 페이지 헤더 -->
<div class="main-content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fab fa-youtube" style="color: #ff4444; margin-right: 15px;"></i>
            YouTube 채널 랭킹
        </h1>
        <p class="page-subtitle">구독자 수 기준 최고의 한국 개발자 채널들을 만나보세요</p>
    </div>

    <!-- YouTube 랭킹 위젯 영역 -->
    <?php 
    // 새로운 깔끔한 YouTube 위젯 출력
    if (function_exists('youtube_ranking_widget')) {
        echo youtube_ranking_widget();
    } else {
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; color: #666;">YouTube 위젯을 불러올 수 없습니다.</div>';
    }
    ?>
    
    <!-- 추가 정보 섹션 -->
    <div class="info-section">
        <div class="info-card">
            <h3><i class="fas fa-chart-line"></i> 실시간 데이터</h3>
            <p>매시간 업데이트되는 최신 채널 정보와 영상 데이터를 확인하세요.</p>
        </div>
        <div class="info-card">
            <h3><i class="fas fa-users"></i> 커뮤니티</h3>
            <p>한국 최고의 개발자들이 만든 양질의 콘텐츠를 만나보세요.</p>
        </div>
        <div class="info-card">
            <h3><i class="fas fa-graduation-cap"></i> 학습</h3>
            <p>초보부터 전문가까지, 모든 레벨에 맞는 학습 콘텐츠를 제공합니다.</p>
        </div>
    </div>
</div>

<?php /*?><h2 class="sound_only">최신글</h2>

<!-- 상단 최신글 영역 (반응형 그리드) -->
<div class="latest_top_wr">
    <?php
    // 최신글을 반응형 그리드로 배치
    // 사용방법 : latest(스킨, 게시판아이디, 출력라인, 글자수);
    // 테마의 스킨을 사용하려면 theme/basic 과 같이 지정
    ?>
    <div class="latest_grid_item">
        <?php echo latest('theme/pic_list', 'free', 4, 23); // 자유게시판 ?>
    </div>
    <div class="latest_grid_item">
        <?php echo latest('theme/pic_list', 'qa', 4, 23); // 질문답변게시판 ?>
    </div>
    <div class="latest_grid_item">
        <?php echo latest('theme/pic_list', 'notice', 4, 23); // 공지사항게시판 ?>
    </div>
</div>

<!-- 갤러리 최신글 영역 -->
<div class="latest_wr gallery_latest">
    <?php
    // 갤러리 최신글
    echo latest('theme/pic_block', 'gallery', 4, 23); // 갤러리게시판
    ?>
</div>

<!-- 게시판별 최신글 목록 -->
<div class="latest_wr board_latest">
    <!-- 최신글 시작 { -->
    <?php
    //  최신글
    $sql = " select bo_table
                from `{$g5['board_table']}` a left join `{$g5['group_table']}` b on (a.gr_id=b.gr_id)
                where a.bo_device <> 'mobile' ";
    if(!$is_admin)
	$sql .= " and a.bo_use_cert = '' ";
    $sql .= " and a.bo_table not in ('notice', 'gallery') ";     //공지사항과 갤러리 게시판은 제외
    $sql .= " order by b.gr_order, a.bo_order ";
    $result = sql_query($sql);
    
    $board_count = 0;
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $board_count++;
    }
    
    // 다시 쿼리 실행
    $result = sql_query($sql);
    for ($i=0; $row=sql_fetch_array($result); $i++) {
    ?>
    <div class="lt_wr">
        <?php
        // 이 함수가 바로 최신글을 추출하는 역할을 합니다.
        // 사용방법 : latest(스킨, 게시판아이디, 출력라인, 글자수);
        // 테마의 스킨을 사용하려면 theme/basic 과 같이 지정
        echo latest('theme/basic', $row['bo_table'], 6, 24);
        ?>
    </div>
    <?php
    }
    ?>
    <!-- } 최신글 끝 -->
</div>
<?php */?>
<style>
/* YouTube 위젯 메인페이지 스타일 */
.main-content-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.youtube-section {
    margin-bottom: 40px;
}

.youtube-section:last-child {
    margin-bottom: 20px;
}

/* 페이지 헤더 */
.page-header {
    text-align: center;
    margin-bottom: 50px;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 10px 0;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    font-weight: 300;
}

/* 정보 섹션 스타일 */
.info-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 60px;
    padding: 40px 20px;
}

.info-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
}

.info-card h3 {
    color: #2c3e50;
    margin: 0 0 15px 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.info-card h3 i {
    color: #667eea;
    margin-right: 10px;
    font-size: 1.4rem;
}

.info-card p {
    color: #7f8c8d;
    line-height: 1.6;
    margin: 0;
    font-size: 0.95rem;
}

/* 모바일 반응형 추가 */
@media (max-width: 768px) {
    .main-content-wrapper {
        padding: 15px;
    }
    
    .page-header {
        padding: 30px 20px;
        margin-bottom: 30px;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .page-subtitle {
        font-size: 1rem;
    }
    
    .youtube-section {
        margin-bottom: 30px;
    }
    
    .info-section {
        grid-template-columns: 1fr;
        gap: 20px;
        margin-top: 40px;
        padding: 20px 0;
    }
    
    .info-card {
        padding: 25px 20px;
    }
}

/* 메인페이지 반응형 스타일 */
.latest_top_wr {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.latest_grid_item {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gallery_latest {
    margin-bottom: 30px;
}

.board_latest {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.board_latest .lt_wr {
    width: 100%;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* 태블릿 */
@media screen and (max-width: 1024px) {
    .latest_top_wr {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .board_latest {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
}

/* 모바일 */
@media screen and (max-width: 768px) {
    .latest_top_wr {
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .board_latest {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .latest_grid_item,
    .board_latest .lt_wr {
        border-radius: 6px;
    }
}

/* 작은 모바일 */
@media screen and (max-width: 480px) {
    .latest_top_wr,
    .board_latest {
        gap: 10px;
    }
    
    .latest_top_wr {
        margin-bottom: 15px;
    }
    
    .latest_grid_item,
    .board_latest .lt_wr {
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
}
</style>

<?php
include_once(G5_THEME_PATH.'/tail.php');
?>