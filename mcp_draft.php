<?php
// gnuboard-skin-mcp-server.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Attributes\{McpTool, McpResource, McpPrompt};

class GnuboardSkinDeveloper
{
    /**
     * 게시판 스킨 목록 조회
     */
    #[McpTool(name: 'list_board_skins')]
    public function listBoardSkins(): array
    {
        global $g5;
        $skin_path = G5_SKIN_PATH . '/board';
        $skins = [];

        $handle = opendir($skin_path);
        while ($file = readdir($handle)) {
            if ($file == "." || $file == "..") continue;
            if (is_dir($skin_path.'/'.$file)) {
                $skins[] = [
                    'name' => $file,
                    'path' => $skin_path.'/'.$file,
                    'files' => $this->getSkinFiles($skin_path.'/'.$file)
                ];
            }
        }
        closedir($handle);

        return ['skins' => $skins];
    }

    /**
     * 스킨 파일 읽기
     */
    #[McpTool(name: 'read_skin_file')]
    public function readSkinFile(string $skin_type, string $skin_name, string $file_name): array
    {
        global $g5;

        // Path Traversal 방지
        $skin_name = basename($skin_name);
        $file_name = basename($file_name);

        $base_path = ($skin_type === 'board') ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $file_path = $base_path . '/' . $skin_name . '/' . $file_name;

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return ['error' => '파일을 찾을 수 없거나 읽을 수 없습니다.'];
        }

        return [
            'content' => file_get_contents($file_path),
            'path' => $file_path,
            'type' => pathinfo($file_path, PATHINFO_EXTENSION)
        ];
    }

    /**
     * 스킨 파일 저장
     */
    #[McpTool(name: 'save_skin_file')]
    public function saveSkinFile(
        string $skin_type,
        string $skin_name,
        string $file_name,
        string $content
    ): array {
        global $g5;

        // Path Traversal 방지
        $skin_name = basename($skin_name);
        $file_name = basename($file_name);

        $base_path = ($skin_type === 'board') ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $file_path = $base_path . '/' . $skin_name . '/' . $file_name;

        // 디렉토리 존재 여부 확인 및 생성
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return ['error' => '디렉토리를 생성할 수 없습니다.'];
            }
        }

        try {
            // 백업 생성
            if (file_exists($file_path)) {
                $backup_path = $file_path . '.backup.' . date('YmdHis');
                if (!copy($file_path, $backup_path)) {
                    // 백업 실패 시에도 계속 진행할지 결정 (여기서는 경고만 로깅)
                    error_log("백업 파일 생성 실패: " . $backup_path);
                }
            }

            $result = file_put_contents($file_path, $content, LOCK_EX);

            if ($result === false) {
                throw new \Exception('파일 저장에 실패했습니다.');
            }

            return [
                'success' => true,
                'path' => $file_path,
                'backup' => isset($backup_path) ? $backup_path : null
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 새 스킨 생성 (기본 템플릿 포함)
     */
    #[McpTool(name: 'create_board_skin')]
    public function createBoardSkin(string $skin_name, string $base_skin = 'basic'): array
    {
        global $g5;

        $skin_name = basename($skin_name);
        $new_skin_path = G5_SKIN_PATH . '/board/' . $skin_name;

        if (is_dir($new_skin_path)) {
            return ['error' => '이미 존재하는 스킨입니다.'];
        }

        try {
            if (!mkdir($new_skin_path, 0755, true)) {
                throw new \Exception('스킨 디렉토리를 생성할 수 없습니다.');
            }

            $default_files = [
                'list.skin.php' => $this->getListTemplate($skin_name),
                'view.skin.php' => $this->getViewTemplate($skin_name),
                'write.skin.php' => $this->getWriteTemplate($skin_name),
                'style.css' => $this->getDefaultCSS($skin_name),
                'img/' => null
            ];

            foreach ($default_files as $file => $content) {
                $target_path = $new_skin_path . '/' . $file;
                if (substr($file, -1) === '/') {
                    if (!mkdir($target_path, 0755)) {
                        throw new \Exception("디렉토리 생성 실패: {$target_path}");
                    }
                } else {
                    if (file_put_contents($target_path, $content) === false) {
                        throw new \Exception("파일 생성 실패: {$target_path}");
                    }
                }
            }

            return [
                'success' => true,
                'skin_name' => $skin_name,
                'path' => $new_skin_path,
                'files' => array_keys($default_files)
            ];
        } catch (\Throwable $e) {
            // 롤백: 생성된 디렉토리/파일 삭제
            if (is_dir($new_skin_path)) {
                // 간단한 롤백. 실제로는 재귀적으로 삭제해야 함
                rmdir($new_skin_path);
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 스킨에서 사용 가능한 변수 목록
     */
    #[McpTool(name: 'get_skin_variables')]
    public function getSkinVariables(string $skin_type, string $file_type): array
    {
        $variables = [
            'board' => [
                'list' => [
                    '$board' => '게시판 설정 정보',
                    '$list' => '게시글 목록 배열',
                    '$write_href' => '글쓰기 링크',
                    '$is_checkbox' => '체크박스 표시 여부',
                    '$total_count' => '전체 게시글 수',
                    '$page' => '현재 페이지',
                    '$total_page' => '전체 페이지 수'
                ],
                'view' => [
                    '$view' => '게시글 정보',
                    '$board' => '게시판 설정',
                    '$is_admin' => '관리자 여부',
                    '$member' => '현재 로그인 회원 정보',
                    '$prev_href' => '이전글 링크',
                    '$next_href' => '다음글 링크'
                ],
                'write' => [
                    '$board' => '게시판 설정',
                    '$write' => '수정시 기존 게시글 정보',
                    '$w' => '작업 구분 (w: 쓰기, u: 수정)',
                    '$is_member' => '회원 여부',
                    '$is_admin' => '관리자 여부'
                ]
            ]
        ];

        return $variables[$skin_type][$file_type] ?? [];
    }

    /**
     * 베이직 스킨 구조 분석 및 안내
     */
    #[McpTool(name: 'get_basic_skin_structure')]
    public function getBasicSkinStructure(string $file_name = ''): array
    {
        $structures = [
            'list.skin.php' => [
                'description' => '게시글 목록을 출력하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '게시글 루프', 'code_block' => '<?php for ($i=0; $i<count($list); $i++) { ?> ... <?php } ?>', 'purpose' => '게시글 하나하나의 디자인을 제어합니다. 제목, 작성자, 날짜, 조회수 등을 출력하는 부분을 수정할 수 있습니다.'],
                    ['name' => '페이징', 'code_block' => '<?php echo $write_pages; ?>', 'purpose' => '페이지 번호를 표시하는 부분입니다.'],
                    ['name' => '카테고리', 'code_block' => '<?php if ($is_category) { ?> ... <?php } ?>', 'purpose' => '게시판 카테고리를 출력하는 부분입니다.'],
                ],
            ],
            'view.skin.php' => [
                'description' => '게시글 본문을 출력하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '본문 내용', 'code_block' => '<?php echo get_view_thumbnail($view); ?> <?php echo $view[\'content\']; ?>', 'purpose' => '게시글의 내용과 썸네일을 출력합니다.'],
                    ['name' => '게시글 정보', 'code_block' => '<header class="view-header"> ... </header>', 'purpose' => '제목, 작성자, 날짜 등 게시글의 메타 정보를 표시합니다.'],
                    ['name' => '버튼 영역', 'code_block' => '<div class="view-buttons"> ... </div>', 'purpose' => '수정, 삭제, 목록 버튼 등을 제어합니다.'],
                ],
            ],
            'write.skin.php' => [
                'description' => '글쓰기 및 수정 폼을 제공하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '입력 폼', 'code_block' => '<form name="fwrite" ...>', 'purpose' => '제목, 내용 등 사용자가 글을 입력하는 폼 전체를 감싸고 있습니다.'],
                    ['name' => '에디터', 'code_block' => '<?php echo $editor_html; ?>', 'purpose' => '그누보드의 웹에디터가 출력되는 부분입니다.'],
                    ['name' => '파일 첨부', 'code_block' => '<?php if ($is_file) { ?> ... <?php } ?>', 'purpose' => '파일 첨부 기능을 제어하는 부분입니다.'],
                ],
            ],
            'style.css' => [
                'description' => '스킨의 전체적인 디자인을 담당하는 CSS 파일입니다.',
                'editable_sections' => [
                    ['name' => '게시글 목록 스타일', 'code_block' => '.board-item { ... }', 'purpose' => 'list.skin.php의 게시글 목록 디자인을 제어합니다.'],
                    ['name' => '반응형 스타일', 'code_block' => '@media (max-width: 768px) { ... }', 'purpose' => '모바일 환경 등 화면 크기에 따른 디자인 변경을 담당합니다.'],
                    ['name' => '다크모드 스타일', 'code_block' => '@media (prefers-color-scheme: dark) { ... }', 'purpose' => '시스템의 다크모드 설정에 따른 디자인을 제어합니다.'],
                ],
            ],
        ];

        if (!empty($file_name) && isset($structures[$file_name])) {
            return $structures[$file_name];
        }

        return $structures;
    }

    /**
     * 커스터마이징 스니펫 제공
     */
    #[McpTool(name: 'get_customizing_snippets')]
    public function getCustomizingSnippets(string $type = ''): array
    {
        $snippets = [
            'new_icon' => [
                'name' => '새 글 아이콘 추가',
                'description' => '게시글 목록에서 새 글(24시간 이내 작성) 옆에 아이콘을 표시합니다.',
                'target_file' => 'list.skin.php',
                'insertion_point' => '게시글 제목을 출력하는 `<a>` 태그 안에 추가합니다. (예: `<?php echo $list[$i][\'subject\'] ?>` 다음)',
                'code' => '<?php if ($list[$i][\'new\']) { ?><span class="new_icon">N</span><?php } ?>',
                'css' => '.new_icon { display: inline-block; margin-left: 5px; padding: 2px 5px; background-color: #ff4d4d; color: #fff; font-size: 10px; border-radius: 3px; }',
            ],
            'level_color' => [
                'name' => '회원 등급별 게시글 색상 변경',
                'description' => '특정 회원 등급이 작성한 게시글의 제목 색상을 변경합니다.',
                'target_file' => 'list.skin.php',
                'insertion_point' => '게시글 `<li>` 또는 `<article>` 태그에 클래스를 추가하는 방식으로 구현합니다.',
                'code' => '<?php
$level_class = "";
if ($list[$i][\'mb_id\']) {
    $mb = get_member($list[$i][\'mb_id\']);
    if ($mb[\'mb_level\'] >= 5) { // 5레벨 이상
        $level_class = "level-vip";
    }
}
?>
<article class="board-item <?php echo $level_class; ?>">',
                'css' => '.level-vip .board-title a { color: #a60000; font-weight: bold; }',
            ],
            'popular_icon' => [
                'name' => '인기 글 아이콘 추가',
                'description' => '일정 조회수 이상인 게시글에 인기 아이콘을 표시합니다.',
                'target_file' => 'list.skin.php',
                'insertion_point' => '새 글 아이콘과 유사하게 제목 옆에 추가합니다.',
                'code' => '<?php if ($list[$i][\'wr_hit\'] > 100) { ?><span class="popular_icon">HOT</span><?php } ?>',
                'css' => '.popular_icon { display: inline-block; margin-left: 5px; padding: 2px 5px; background-color: #ff9800; color: #fff; font-size: 10px; border-radius: 3px; }',
            ],
        ];

        if (!empty($type) && isset($snippets[$type])) {
            return $snippets[$type];
        }

        return $snippets;
    }

    /**
     * 반응형 스킨 템플릿 생성
     */
    #[McpPrompt(name: 'responsive_skin_template')]
    public function responsiveSkinPrompt(string $skin_name, string $design_style): array
    {
        return [
            [
                'role' => 'user',
                'content' => "그누보드 게시판 스킨 '{$skin_name}'을 위한 {$design_style} 스타일의 반응형 list.skin.php 템플릿을 만들어주세요.
                다음 요구사항을 포함해주세요:
                - Bootstrap 5 또는 Tailwind CSS 활용
                - 모바일 우선 설계
                - 다크모드 지원
                - 애니메이션 효과
                - 접근성 고려 (ARIA 속성)
                - SEO 최적화"
            ]
        ];
    }

    /**
     * CSS 프레임워크별 스킨 변환
     */
    #[McpTool(name: 'convert_skin_framework')]
    public function convertSkinFramework(
        string $skin_type,
        string $skin_name,
        string $file_name,
        string $from_framework,
        string $to_framework
    ): array {
        $file_data = $this->readSkinFile($skin_type, $skin_name, $file_name);
        if (isset($file_data['error'])) {
            return $file_data;
        }

        $content = $file_data['content'];

        $conversions = [
            'bootstrap_to_tailwind' => [
                '/\bbtn btn-primary\b/' => 'bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded',
                '/\bbtn btn-secondary\b/' => 'bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded',
                '/\btable\b/' => 'min-w-full divide-y divide-gray-200',
                '/\btable-striped\b/' => '', // Tailwind는 a:nth-child(odd) 같은 선택자로 구현
                '/\bform-control\b/' => 'shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline',
                '/\brow\b/' => 'flex flex-wrap -mx-2',
                '/\bcol-md-6\b/' => 'w-full md:w-1/2 px-2',
                '/\bcontainer\b/' => 'container mx-auto',
                '/\bcontainer-fluid\b/' => 'w-full',
            ]
        ];

        $conversion_map = $conversions["{$from_framework}_to_{$to_framework}"] ?? null;

        if (!$conversion_map) {
            return ['error' => "{$from_framework}에서 {$to_framework}으로의 변환은 지원하지 않습니다."];
        }

        $converted_content = preg_replace(array_keys($conversion_map), array_values($conversion_map), $content);

        // 변환된 내용을 새 파일에 저장하거나, 원본 파일을 덮어쓰기
        $new_file_name = pathinfo($file_name, PATHINFO_FILENAME) . "_{$to_framework}." . pathinfo($file_name, PATHINFO_EXTENSION);
        $save_result = $this->saveSkinFile($skin_type, $skin_name, $new_file_name, $converted_content);

        return [
            'status' => 'conversion_complete',
            'from' => $from_framework,
            'to' => $to_framework,
            'original_file' => $file_name,
            'converted_file' => $new_file_name,
            'save_result' => $save_result,
        ];
    }

    /**
     * 새 플러그인 템플릿 생성
     */
    #[McpTool(name: 'create_plugin_template')]
    public function createPluginTemplate(string $plugin_name): array
    {
        $plugin_name = basename($plugin_name);
        $plugin_path = G5_PLUGIN_PATH . '/' . $plugin_name;

        if (is_dir($plugin_path)) {
            return ['error' => '이미 존재하는 플러그인입니다.'];
        }

        try {
            $dirs_to_create = [
                $plugin_path,
                $plugin_path . '/admin',
                $plugin_path . '/js',
                $plugin_path . '/css',
            ];

            foreach ($dirs_to_create as $dir) {
                if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new \Exception("디렉토리 생성 실패: {$dir}");
                }
            }

            $default_files = [
                'plugin.php' => $this->getPluginTemplate($plugin_name),
                'admin/admin.php' => $this->getPluginAdminTemplate($plugin_name),
                'setup.php' => $this->getPluginSetupTemplate($plugin_name),
                'readme.txt' => "## {$plugin_name} 플러그인\n\n플러그인에 대한 설명을 작성하세요.",
            ];

            foreach ($default_files as $file => $content) {
                $target_path = $plugin_path . '/' . $file;
                if (file_put_contents($target_path, $content) === false) {
                    throw new \Exception("파일 생성 실패: {$target_path}");
                }
            }

            return [
                'success' => true,
                'plugin_name' => $plugin_name,
                'path' => $plugin_path,
                'files' => array_keys($default_files)
            ];
        } catch (\Throwable $e) {
            // 롤백 로직 추가 가능
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 그누보드 훅(Hook) 정보 제공
     */
    #[McpTool(name: 'get_gnuboard_hooks')]
    public function getGnuboardHooks(string $hook_name = ''): array
    {
        $hooks = [
            'pre_login' => [
                'name' => 'pre_login',
                'description' => '로그인 처리 전에 실행됩니다.',
                'parameters' => [
                    ['name' => '$mb_id', 'type' => 'string', 'description' => '로그인 시도 아이디'],
                ],
                'example' => "add_hook('pre_login', 'my_pre_login_function');\nfunction my_pre_login_function(\$mb_id) {\n    // ...\n}",
            ],
            'post_login' => [
                'name' => 'post_login',
                'description' => '로그인 성공 후에 실행됩니다.',
                'parameters' => [
                    ['name' => '$member', 'type' => 'array', 'description' => '로그인한 회원 정보'],
                ],
                'example' => "add_hook('post_login', 'my_post_login_function');\nfunction my_post_login_function(\$member) {\n    // ...\n}",
            ],
            'pre_write_update' => [
                'name' => 'pre_write_update',
                'description' => '게시글/댓글 작성/수정 DB 업데이트 전에 실행됩니다.',
                'parameters' => [
                    ['name' => '$wr_id', 'type' => 'int', 'description' => '게시글 ID'],
                    ['name' => '$bo_table', 'type' => 'string', 'description' => '게시판 테이블명'],
                    ['name' => '$w', 'type' => 'string', 'description' => '작업 구분 (w, u, c, cu)'],
                ],
                'example' => "add_hook('pre_write_update', 'my_pre_write_function');\nfunction my_pre_write_function(\$wr_id, \$bo_table, \$w) {\n    // ...\n}",
            ],
        ];

        if (!empty($hook_name) && isset($hooks[$hook_name])) {
            return $hooks[$hook_name];
        }

        return $hooks;
    }

    /**
     * DB 테이블 생성/관리 헬퍼
     */
    #[McpTool(name: 'get_db_helper_functions')]
    public function getDbHelperFunctions(): array
    {
        return [
            'create_table' => [
                'name' => '커스텀 테이블 생성',
                'description' => '플러그인에서 사용할 새로운 DB 테이블을 생성하는 예제입니다.',
                'code' => "
function create_my_plugin_table() {
    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';
    if(!sql_query(\" DESC `{\$table_name}` \", false)) {
        sql_query(\" CREATE TABLE `{\$table_name}` (
            `id` int(11) NOT NULL auto_increment,
            `user_id` varchar(255) NOT NULL,
            `some_value` text NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 \", true);

        return '`{\$table_name}` 테이블이 생성되었습니다.';
    }
    return '`{\$table_name}` 테이블이 이미 존재합니다.';
}",
            ],
            'insert_data' => [
                'name' => '데이터 추가 (INSERT)',
                'description' => '생성된 커스텀 테이블에 데이터를 추가하는 예제입니다. SQL Injection에 안전한 `sql_password()` 함수 사용을 권장합니다.',
                'code' => "
function insert_my_plugin_data(\$user_id, \$some_value) {
    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';
    \$sql = \" INSERT INTO `{\$table_name}` SET
                user_id = '{\$user_id}',
                some_value = '{\$some_value}',
                created_at = now() \";
    sql_query(\$sql);
}",
            ],
            'select_data' => [
                'name' => '데이터 조회 (SELECT)',
                'description' => '커스텀 테이블에서 데이터를 조회하는 예제입니다.',
                'code' => "
function get_my_plugin_data(\$user_id) {
    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';
    \$sql = \" SELECT * FROM `{\$table_name}` WHERE user_id = '{\$user_id}' ORDER BY created_at DESC \";
    return sql_fetch(\$sql);
}",
            ]
        ];
    }

    // 헬퍼 메서드들
    private function getSkinFiles($path): array
    {
        $files = [];
        $handle = opendir($path);
        while ($file = readdir($handle)) {
            if ($file == "." || $file == "..") continue;
            if (is_file($path.'/'.$file)) {
                $files[] = $file;
            }
        }
        closedir($handle);
        return $files;
    }

    private function getListTemplate($skin_name): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 스킨 경로
\$board_skin_url = get_board_skin_url(\$bo_table);

// 추가 CSS
add_stylesheet('<link rel="stylesheet" href="'.\$board_skin_url.'/style.css">', 0);
?>

<div class="{$skin_name}-board-list">
    <!-- 카테고리 -->
    <?php if (\$is_category) { ?>
    <nav class="board-category">
        <?php echo \$category_option ?>
    </nav>
    <?php } ?>

    <!-- 게시글 목록 -->
    <div class="board-list-wrap">
        <?php for (\$i=0; \$i<count(\$list); \$i++) { ?>
        <article class="board-item">
            <h3 class="board-title">
                <a href="<?php echo \$list[\$i]['href'] ?>">
                    <?php echo \$list[\$i]['subject'] ?>
                </a>
            </h3>
            <div class="board-meta">
                <span class="author"><?php echo \$list[\$i]['name'] ?></span>
                <span class="date"><?php echo \$list[\$i]['datetime2'] ?></span>
                <span class="hit">조회 <?php echo \$list[\$i]['wr_hit'] ?></span>
            </div>
        </article>
        <?php } ?>
    </div>

    <!-- 페이징 -->
    <?php echo \$write_pages; ?>
</div>
PHP;
    }

    private function getViewTemplate($skin_name): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 스킨 경로
\$board_skin_url = get_board_skin_url(\$bo_table);

// 추가 CSS
add_stylesheet('<link rel="stylesheet" href="'.\$board_skin_url.'/style.css">', 0);
?>

<article class="{$skin_name}-board-view">
    <header class="view-header">
        <h1><?php echo cut_str(get_text(\$view['wr_subject']), 70); ?></h1>
        <div class="view-info">
            <span class="author"><?php echo \$view['name'] ?></span>
            <span class="date"><?php echo date("Y-m-d H:i", strtotime(\$view['wr_datetime'])) ?></span>
            <span class="hit">조회 <?php echo number_format(\$view['wr_hit']) ?></span>
        </div>
    </header>

    <div class="view-content">
        <?php echo get_view_thumbnail(\$view); ?>
        <?php echo \$view['content']; ?>
    </div>

    <footer class="view-footer">
        <div class="view-buttons">
            <?php if (\$update_href) { ?><a href="<?php echo \$update_href ?>" class="btn btn-modify">수정</a><?php } ?>
            <?php if (\$delete_href) { ?><a href="<?php echo \$delete_href ?>" class="btn btn-delete">삭제</a><?php } ?>
            <a href="<?php echo \$list_href ?>" class="btn btn-list">목록</a>
        </div>
    </footer>
</article>
PHP;
    }

    private function getWriteTemplate($skin_name): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 스킨 경로
\$board_skin_url = get_board_skin_url(\$bo_table);

// 추가 CSS
add_stylesheet('<link rel="stylesheet" href="'.\$board_skin_url.'/style.css">', 0);
?>

<section class="{$skin_name}-board-write">
    <h2 class="sound_only"><?php echo \$g5['title'] ?></h2>

    <form name="fwrite" id="fwrite" action="<?php echo \$action_url ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
    <input type="hidden" name="w" value="<?php echo \$w ?>">
    <input type="hidden" name="bo_table" value="<?php echo \$bo_table ?>">
    <input type="hidden" name="wr_id" value="<?php echo \$wr_id ?>">

    <div class="write-form">
        <div class="form-group">
            <label for="wr_subject">제목</label>
            <input type="text" name="wr_subject" value="<?php echo \$subject ?>" id="wr_subject" required class="form-control" maxlength="255">
        </div>

        <div class="form-group">
            <label for="wr_content">내용</label>
            <?php echo \$editor_html; ?>
        </div>
    </div>

    <div class="write-buttons">
        <button type="submit" class="btn btn-primary">작성완료</button>
        <a href="./board.php?bo_table=<?php echo \$bo_table ?>" class="btn btn-cancel">취소</a>
    </div>
    </form>
</section>
PHP;
    }

    private function getDefaultCSS($skin_name): string
    {
        return <<<CSS
/* {$skin_name} 스킨 스타일 */

.{$skin_name}-board-list {
    margin: 20px 0;
}

.board-item {
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
    transition: background-color 0.3s ease;
}

.board-item:hover {
    background-color: #f8f9fa;
}

.board-title {
    margin: 0 0 10px;
    font-size: 18px;
}

.board-title a {
    color: #333;
    text-decoration: none;
}

.board-meta {
    font-size: 14px;
    color: #666;
}

.board-meta span {
    margin-right: 15px;
}

/* 반응형 스타일 */
@media (max-width: 768px) {
    .board-item {
        padding: 15px;
    }

    .board-title {
        font-size: 16px;
    }

    .board-meta {
        font-size: 12px;
    }
}

/* 다크모드 지원 */
@media (prefers-color-scheme: dark) {
    .{$skin_name}-board-list {
        background-color: #1a1a1a;
        color: #e5e5e5;
    }

    .board-item {
        border-color: #333;
    }

    .board-item:hover {
        background-color: #2a2a2a;
    }

    .board-title a {
        color: #e5e5e5;
    }
}
CSS;
    }

    private function getPluginTemplate(string $plugin_name): string
    {
        return <<<PHP
<?php
if (!defined('G5_USE_HOOK') || !G5_USE_HOOK) return;

// 플러그인 정보
\$plugin_info = [
    'name' => '{$plugin_name}',
    'version' => '1.0.0',
    'author' => 'Your Name',
    'description' => '{$plugin_name} 플러그인입니다.',
    'license' => 'GPLv2 or later',
];

// 훅(Hook) 등록 예시
// add_hook('post_login', 'plugin_post_login_example');
// function plugin_post_login_example(\$mb) {
//     // 로그인 후 실행할 코드
//     error_log("{\$mb['mb_id']}님이 로그인했습니다.");
// }

// 관리자 메뉴 추가 예시
// add_admin_menu('{$plugin_name}_admin', '{$plugin_name} 설정', G5_PLUGIN_URL . '/{$plugin_name}/admin/admin.php');

PHP;
    }

    private function getPluginAdminTemplate(string $plugin_name): string
    {
        return <<<PHP
<?php
\$sub_menu = '100800'; // 그누보드 관리자 메뉴 코드에 맞게 수정
include_once('./_common.php');

auth_check_menu(\$auth, \$sub_menu, 'r');

\$g5['title'] = '{$plugin_name} 플러그인 설정';
include_once(G5_ADMIN_PATH.'/admin.head.php');
?>

<section id="anc_cf_{$plugin_name}">
    <h2 class="h2_frm">{$plugin_name} 설정</h2>

    <form name="fconfig" action="./plugin_update.php" method="post">
    <input type="hidden" name="plugin" value="{$plugin_name}">

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>{$plugin_name} 설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="option1">옵션 1</label></th>
            <td>
                <input type="text" name="option1" value="<?php echo \$config['cf_{$plugin_name}_option1'] ?? '' ?>" id="option1" class="frm_input" size="40">
            </td>
        </tr>
        </tbody>
        </table>
    </div>

    <div class="btn_confirm01 btn_confirm">
        <input type="submit" value="확인" class="btn_submit" accesskey="s">
    </div>

    </form>
</section>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
PHP;
    }

    private function getPluginSetupTemplate(string $plugin_name): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit;

// 플러그인 설치 시 실행되는 코드
// 예: DB 테이블 생성
// \$table_name = G5_TABLE_PREFIX . 'your_custom_table';
// if(!sql_query(" DESC `{\$table_name}` ", false)) {
//     sql_query(" CREATE TABLE `{\$table_name}` (
//         `id` int(11) NOT NULL auto_increment,
//         `data` varchar(255) NOT NULL,
//         PRIMARY KEY (`id`)
//     ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
// }

// 플러그인 삭제 시 실행되는 코드
// register_uninstall_hook(__FILE__, 'plugin_uninstall_example');
// function plugin_uninstall_example() {
//     // 예: DB 테이블 삭제
//     // \$table_name = G5_TABLE_PREFIX . 'your_custom_table';
//     // sql_query(" DROP TABLE IF EXISTS `{\$table_name}` ");
// }
PHP;
    }
}

// 서버 실행
try {
    $server = Server::make()
        ->withServerInfo('그누보드 스킨 개발 도우미', '1.0.0')
        ->build();

    $server->discover(__DIR__, ['src']);

    $transport = new StdioServerTransport();
    $server->listen($transport);

} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
