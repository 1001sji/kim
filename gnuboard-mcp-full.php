<?php
// gnuboard-mcp-full.php
// MCP tools for Gnuboard5 skin and plugin development (완전 통합 버전)

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Attributes\{McpTool, McpPrompt};

class GnuboardMcp
{
    // 게시판 스킨 목록 조회
    #[McpTool(name: 'list_board_skins')]
    public function listBoardSkins(): array
    {
        $skinPath = G5_SKIN_PATH . '/board';
        if (!is_dir($skinPath)) {
            return ['error' => '스킨 디렉토리를 찾을 수 없습니다.'];
        }
        $skins = [];
        foreach (scandir($skinPath) as $file) {
            if (in_array($file, ['.', '..'], true)) continue;
            $path = "$skinPath/$file";
            if (is_dir($path)) {
                $skins[] = ['name' => $file, 'path' => $path, 'files' => $this->getFiles($path)];
            }
        }
        return ['skins' => $skins];
    }

    // 스킨 파일 읽기
    #[McpTool(name: 'read_skin_file')]
    public function readSkinFile(string $skinType, string $skinName, string $fileName): array
    {
        $basePath = $skinType === 'board' ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $filePath = $basePath . '/' . basename($skinName) . '/' . basename($fileName);
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['error' => '파일을 찾을 수 없거나 읽을 수 없습니다.'];
        }
        return [
            'content' => file_get_contents($filePath),
            'path' => $filePath,
            'type' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }

    // 스킨 파일 저장 (백업 포함)
    #[McpTool(name: 'save_skin_file')]
    public function saveSkinFile(string $skinType, string $skinName, string $fileName, string $content): array
    {
        $basePath = $skinType === 'board' ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $filePath = $basePath . '/' . basename($skinName) . '/' . basename($fileName);
        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['error' => '디렉토리를 생성할 수 없습니다.'];
        }
        $backup = null;
        if (file_exists($filePath)) {
            $backup = $filePath . '.backup.' . date('YmdHis');
            if (!copy($filePath, $backup)) {
                error_log('백업 실패: ' . $backup);
            }
        }
        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            return ['error' => '파일 저장에 실패했습니다.'];
        }
        return ['success' => true, 'path' => $filePath, 'backup' => $backup];
    }

    // 신규 게시판 스킨 생성
    #[McpTool(name: 'create_board_skin')]
    public function createBoardSkin(string $skinName, string $baseSkin = 'basic'): array
    {
        $skinName = basename($skinName);
        $newSkinPath = G5_SKIN_PATH . '/board/' . $skinName;
        if (is_dir($newSkinPath)) {
            return ['error' => '스킨이 이미 존재합니다.'];
        }
        if (!mkdir($newSkinPath, 0755, true)) {
            return ['error' => '스킨 디렉토리를 생성할 수 없습니다.'];
        }
        $defaultFiles = [
            'list.skin.php' => $this->getListTemplate($skinName),
            'view.skin.php' => $this->getViewTemplate($skinName),
            'write.skin.php' => $this->getWriteTemplate($skinName),
            'style.css' => $this->getDefaultCSS($skinName),
            'img/' => null,
        ];
        foreach ($defaultFiles as $file => $content) {
            $target = $newSkinPath . '/' . $file;
            if (substr($file, -1) === '/') {
                if (!mkdir($target, 0755, true)) {
                    return ['error' => '디렉토리 생성 실패: ' . $file];
                }
            } else {
                if (file_put_contents($target, $content) === false) {
                    return ['error' => '파일 생성 실패: ' . $file];
                }
            }
        }
        return ['success' => true, 'path' => $newSkinPath, 'files' => array_keys($defaultFiles)];
    }

    // 플러그인 목록 조회
    #[McpTool(name: 'list_plugins')]
    public function listPlugins(): array
    {
        $pluginPath = G5_PLUGIN_PATH;
        if (!is_dir($pluginPath)) {
            return ['error' => '플러그인 디렉토리를 찾을 수 없습니다.'];
        }
        $plugins = [];
        foreach (scandir($pluginPath) as $file) {
            if (in_array($file, ['.', '..'], true)) continue;
            $path = "$pluginPath/$file";
            if (is_dir($path)) {
                $plugins[] = ['name' => $file, 'path' => $path, 'files' => $this->getFiles($path)];
            }
        }
        return ['plugins' => $plugins];
    }

    // 플러그인 활성화/비활성화
    #[McpTool(name: 'toggle_plugin')]
    public function togglePlugin(string $pluginName, bool $activate): array
    {
        $configPath = G5_DATA_PATH . '/plugin_config.php';
        $config = file_exists($configPath) ? include $configPath : [];
        $config[$pluginName] = $activate;
        file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
        return ['success' => true, 'plugin' => $pluginName, 'activated' => $activate];
    }

    // 그누보드 주요 훅 목록
    #[McpTool(name: 'list_hooks')]
    public function listHooks(): array
    {
        return [
            'pre_login' => '로그인 시도 전',
            'post_login' => '로그인 완료 후',
            'pre_write_update' => '글 작성/수정 전',
            'post_write_update' => '글 작성/수정 후',
        ];
    }

    #[McpTool(name: 'get_gnuboard_hooks')]
    public function getGnuboardHooks(string $hookName = ''): array
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

        if ($hookName && isset($hooks[$hookName])) {
            return $hooks[$hookName];
        }

        return $hooks;
    }

    // 기본 스킨 파일 내 특정 섹션 찾기
    #[McpTool(name: 'locate_skin_section')]
    public function locateSkinSection(string $filePath, string $section): array
    {
        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }
        $content = file_get_contents($filePath);
        $patterns = [
            'title' => '/<h[1-6][^>]*>.*?<\\/h[1-6]>/',
            'author' => '/wr_name|\$list\[[^\]]+\]\[\'name\']/',
            'date' => '/wr_datetime|datetime2/',
        ];
        $matches = [];
        if (isset($patterns[$section])) {
            preg_match($patterns[$section], $content, $matches);
        }
        return ['section' => $section, 'code' => $matches[0] ?? ''];
    }

    // 기본 스킨 커스터마이징 스니펫
    #[McpTool(name: 'basic_skin_snippet')]
    public function basicSkinSnippet(string $type): array
    {
        $snippets = [
            'icon' => [
                'code' => "<?php if (strpos(\$list[\$i]['wr_option'], 'hot')) echo '<span class=\"icon-hot\"></span>'; ?>",
                'hint' => '게시글 제목 앞에 인기 아이콘 추가',
                'insert_after' => '$list[$i][\'subject\']',
            ],
            'grade_color' => [
                'code' => "<span class=\"grade-{\$member['mb_level']}\"></span>",
                'hint' => '회원 등급별 스타일 적용',
                'insert_after' => '$list[$i][\'name\']',
            ],
            'new_popular' => [
                'code' => "<?php if (\$list[\$i]['is_new']) echo '<span class=\"new\"></span>'; ?>",
                'hint' => '새 글 표시',
                'insert_after' => '$list[$i][\'subject\']',
            ],
        ];
        return $snippets[$type] ?? ['error' => 'Unknown snippet'];
    }

    // 스킨에서 사용 가능한 변수 목록
    #[McpTool(name: 'get_skin_variables')]
    public function getSkinVariables(string $skinType, string $fileType): array
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
                    '$total_page' => '전체 페이지 수',
                ],
                'view' => [
                    '$view' => '게시글 정보',
                    '$board' => '게시판 설정',
                    '$is_admin' => '관리자 여부',
                    '$member' => '현재 로그인 회원 정보',
                    '$prev_href' => '이전글 링크',
                    '$next_href' => '다음글 링크',
                ],
                'write' => [
                    '$board' => '게시판 설정',
                    '$write' => '수정시 기존 게시글 정보',
                    '$w' => '작업 구분 (w: 쓰기, u: 수정)',
                    '$is_member' => '회원 여부',
                    '$is_admin' => '관리자 여부',
                ],
            ],
        ];

        return $variables[$skinType][$fileType] ?? [];
    }

    // 베이직 스킨 구조 분석 및 안내
    #[McpTool(name: 'get_basic_skin_structure')]
    public function getBasicSkinStructure(string $fileName = ''): array
    {
        $structures = [
            'list.skin.php' => [
                'description' => '게시글 목록을 출력하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '게시글 루프', 'code_block' => '<?php for ($i=0; $i<count($list); $i++) { ?> ... <?php } ?>', 'purpose' => '게시글 디자인을 제어합니다.'],
                    ['name' => '페이징', 'code_block' => '<?php echo $write_pages; ?>', 'purpose' => '페이지 번호 출력'],
                    ['name' => '카테고리', 'code_block' => '<?php if ($is_category) { ?> ... <?php } ?>', 'purpose' => '게시판 카테고리를 출력'],
                ],
            ],
            'view.skin.php' => [
                'description' => '게시글 본문을 출력하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '본문 내용', 'code_block' => '<?php echo get_view_thumbnail($view); ?> <?php echo $view[\'content\']; ?>', 'purpose' => '게시글 내용 및 썸네일 출력'],
                    ['name' => '게시글 정보', 'code_block' => '<header class="view-header"> ... </header>', 'purpose' => '제목, 작성자, 날짜 등의 메타 정보'],
                    ['name' => '버튼 영역', 'code_block' => '<div class="view-buttons"> ... </div>', 'purpose' => '수정, 삭제, 목록 버튼 제어'],
                ],
            ],
            'write.skin.php' => [
                'description' => '글쓰기 및 수정 폼을 제공하는 파일입니다.',
                'editable_sections' => [
                    ['name' => '입력 폼', 'code_block' => '<form name="fwrite" ...>', 'purpose' => '사용자 입력 폼'],
                    ['name' => '에디터', 'code_block' => '<?php echo $editor_html; ?>', 'purpose' => '웹에디터 영역'],
                    ['name' => '파일 첨부', 'code_block' => '<?php if ($is_file) { ?> ... <?php } ?>', 'purpose' => '파일 첨부 제어'],
                ],
            ],
            'style.css' => [
                'description' => '스킨의 전체적인 디자인을 담당하는 CSS 파일입니다.',
                'editable_sections' => [
                    ['name' => '게시글 목록 스타일', 'code_block' => '.board-item { ... }', 'purpose' => '목록 디자인 조정'],
                    ['name' => '반응형 스타일', 'code_block' => '@media (max-width: 768px) { ... }', 'purpose' => '모바일 대응'],
                    ['name' => '다크모드 스타일', 'code_block' => '@media (prefers-color-scheme: dark) { ... }', 'purpose' => '다크모드 대응'],
                ],
            ],
        ];

        if ($fileName && isset($structures[$fileName])) {
            return $structures[$fileName];
        }

        return $structures;
    }

    // 커스터마이징 스니펫 제공
    #[McpTool(name: 'get_customizing_snippets')]
    public function getCustomizingSnippets(string $type = ''): array
    {
        $snippets = [
            'new_icon' => [
                'name' => '새 글 아이콘 추가',
                'description' => '게시글 목록에서 새 글(24시간 이내 작성) 옆에 아이콘을 표시합니다.',
                'target_file' => 'list.skin.php',
                'insertion_point' => '게시글 제목을 출력하는 `<a>` 태그 안에 추가',
                'code' => '<?php if ($list[$i][\'new\']) { ?><span class="new_icon">N</span><?php } ?>',
                'css' => '.new_icon { display: inline-block; margin-left: 5px; padding: 2px 5px; background-color: #ff4d4d; color: #fff; font-size: 10px; border-radius: 3px; }',
            ],
            'level_color' => [
                'name' => '회원 등급별 게시글 색상 변경',
                'description' => '특정 회원 등급이 작성한 게시글의 제목 색상을 변경합니다.',
                'target_file' => 'list.skin.php',
                'insertion_point' => '게시글 `<li>` 또는 `<article>` 태그에 클래스 추가',
                'code' => "<?php\n$level_class = \"\";\nif ($list[$i]['mb_id']) {\n    $mb = get_member($list[$i]['mb_id']);\n    if ($mb['mb_level'] >= 5) {\n        $level_class = \"level-vip\";\n    }\n}\n?>\n<article class=\"board-item <?php echo $level_class; ?>\">",
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

        if ($type && isset($snippets[$type])) {
            return $snippets[$type];
        }
        return $snippets;
    }

    // 반응형 스킨 템플릿 생성 프롬프트
    #[McpPrompt(name: 'responsive_skin_template')]
    public function responsiveSkinPrompt(string $skinName, string $designStyle): array
    {
        return [
            [
                'role' => 'user',
                'content' => "그누보드 게시판 스킨 '{$skinName}'을 위한 {$designStyle} 스타일의 반응형 list.skin.php 템플릿을 만들어주세요.\n- Bootstrap 5 또는 Tailwind CSS 활용\n- 모바일 우선 설계\n- 다크모드 지원\n- 애니메이션 효과\n- 접근성 고려 (ARIA)\n- SEO 최적화",
            ],
        ];
    }

    // CSS 프레임워크별 스킨 변환
    #[McpTool(name: 'convert_skin_framework')]
    public function convertSkinFramework(string $skinType, string $skinName, string $fileName, string $from, string $to): array
    {
        $read = $this->readSkinFile($skinType, $skinName, $fileName);
        if (isset($read['error'])) {
            return $read;
        }
        $maps = [
            'bootstrap_to_tailwind' => [
                'btn btn-primary' => 'bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded',
                'table table-striped' => 'min-w-full divide-y divide-gray-200',
                'form-control' => 'shadow appearance-none border rounded w-full py-2 px-3',
            ],
        ];
        $key = strtolower($from . '_to_' . $to);
        if (!isset($maps[$key])) {
            return ['error' => '변환 매핑을 찾을 수 없습니다.'];
        }
        $converted = strtr($read['content'], $maps[$key]);
        $newFileName = pathinfo($fileName, PATHINFO_FILENAME) . "_{$to}." . pathinfo($fileName, PATHINFO_EXTENSION);
        return $this->saveSkinFile($skinType, $skinName, $newFileName, $converted);
    }

    // 새 플러그인 템플릿 생성
    #[McpTool(name: 'create_plugin_template')]
    public function createPluginTemplate(string $pluginName): array
    {
        $pluginName = basename($pluginName);
        $pluginPath = G5_PLUGIN_PATH . '/' . $pluginName;
        if (is_dir($pluginPath)) {
            return ['error' => '이미 존재하는 플러그인입니다.'];
        }
        $dirs = [$pluginPath, $pluginPath . '/admin', $pluginPath . '/js', $pluginPath . '/css'];
        foreach ($dirs as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['error' => '디렉토리 생성 실패: ' . $dir];
            }
        }
        $defaultFiles = [
            'plugin.php' => $this->getPluginTemplate($pluginName),
            'admin/admin.php' => $this->getPluginAdminTemplate($pluginName),
            'setup.php' => $this->getPluginSetupTemplate($pluginName),
            'readme.txt' => "## {$pluginName} 플러그인\n\n플러그인에 대한 설명을 작성하세요.",
        ];
        foreach ($defaultFiles as $file => $content) {
            if (file_put_contents($pluginPath . '/' . $file, $content) === false) {
                return ['error' => '파일 생성 실패: ' . $file];
            }
        }
        return ['success' => true, 'path' => $pluginPath, 'files' => array_keys($defaultFiles)];
    }

    // 심플한 플러그인 생성 예제
    #[McpTool(name: 'create_plugin')]
    public function createPlugin(string $pluginName): array
    {
        $base = G5_PLUGIN_PATH . '/' . $pluginName;
        if (is_dir($base)) {
            return ['error' => 'Plugin already exists'];
        }
        if (!mkdir($base, 0755, true) && !is_dir($base)) {
            return ['error' => 'Failed to create plugin directory'];
        }
        $files = [
            'plugin.php' => "<?php\n/**\n * Plugin: {$pluginName}\n * Version: 1.0.0\n * Author: Generated by MCP\n */\nif (!defined('_GNUBOARD_')) exit;\n\nfunction {$pluginName}_pre_example() {\n    // pre_login hook example\n}\n",
            'admin.php' => "<?php if (!defined('_GNUBOARD_')) exit; \n// admin page for {$pluginName}\n",
        ];
        foreach ($files as $file => $content) {
            if (file_put_contents($base . '/' . $file, $content) === false) {
                return ['error' => 'Failed to write ' . $file];
            }
        }
        return ['success' => true, 'path' => $base, 'files' => array_keys($files)];
    }

    // 훅 사용 예제 프롬프트
    #[McpPrompt(name: 'hook_example')]
    public function hookExamplePrompt(string $hook): array
    {
        $code = "<?php\nadd_hook('{$hook}', function(\$data) {\n    // TODO: implement\n});\n";
        return [['role' => 'assistant', 'content' => $code]];
    }

    // DB 헬퍼 함수 모음
    #[McpTool(name: 'get_db_helper_functions')]
    public function getDbHelperFunctions(): array
    {
        return [
            'create_table' => [
                'name' => '커스텀 테이블 생성',
                'description' => '플러그인에서 사용할 새로운 DB 테이블을 생성하는 예제입니다.',
                'code' => "function create_my_plugin_table() {\n    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';\n    if(!sql_query(\" DESC `{\$table_name}` \", false)) {\n        sql_query(\" CREATE TABLE `{\$table_name}` (\n            `id` int(11) NOT NULL auto_increment,\n            `user_id` varchar(255) NOT NULL,\n            `some_value` text NOT NULL,\n            `created_at` datetime NOT NULL,\n            PRIMARY KEY (`id`)\n        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 \", true);\n        return '`{\$table_name}` 테이블이 생성되었습니다.';\n    }\n    return '`{\$table_name}` 테이블이 이미 존재합니다.';\n}",
            ],
            'insert_data' => [
                'name' => '데이터 추가 (INSERT)',
                'description' => '생성된 커스텀 테이블에 데이터를 추가하는 예제입니다.',
                'code' => "function insert_my_plugin_data(\$user_id, \$some_value) {\n    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';\n    \$sql = \" INSERT INTO `{\$table_name}` SET\n                user_id = '{\$user_id}',\n                some_value = '{\$some_value}',\n                created_at = now() \";\n    sql_query(\$sql);\n}",
            ],
            'select_data' => [
                'name' => '데이터 조회 (SELECT)',
                'description' => '커스텀 테이블에서 데이터를 조회하는 예제입니다.',
                'code' => "function get_my_plugin_data(\$user_id) {\n    \$table_name = G5_TABLE_PREFIX . 'my_plugin_data';\n    \$sql = \" SELECT * FROM `{\$table_name}` WHERE user_id = '{\$user_id}' ORDER BY created_at DESC \";\n    return sql_fetch(\$sql);\n}",
            ]
        ];
    }

    // 간단한 DB 테이블 생성 스니펫
    #[McpTool(name: 'db_table_helper')]
    public function dbTableHelper(string $table): array
    {
        $code = "<?php\n\$sql = \"CREATE TABLE IF NOT EXISTS {$table} ( id INT NOT NULL AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\";\nsql_query(\$sql);\n";
        return ['code' => $code];
    }

    // 관리자 메뉴 통합 안내 프롬프트
    #[McpPrompt(name: 'admin_menu_guide')]
    public function adminMenuGuide(string $pluginName): array
    {
        $content = "관리자 메뉴에 {$pluginName} 설정 페이지를 추가하려면 admin.menu.php에 다음 코드를 삽입하세요:\n\n<?php\nadd_admin_menu('{$pluginName}', G5_ADMIN_URL . '/?dir={$pluginName}');\n?>";
        return [['role' => 'assistant', 'content' => $content]];
    }

    // PHP 코드 Lint 검사
    #[McpTool(name: 'lint_php_file')]
    public function lintPhpFile(string $path): array
    {
        $output = shell_exec('php -l ' . escapeshellarg($path));
        return ['result' => $output];
    }

    // 공통 파일 목록 함수
    private function getFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) as $f) {
            if (in_array($f, ['.', '..'], true)) continue;
            if (is_file("$dir/$f")) $files[] = $f;
        }
        return $files;
    }

    private function getListTemplate(string $skinName): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$board_skin_url = get_board_skin_url($bo_table);

add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);
?>

<div class="{$skinName}-board-list">
    <?php if ($is_category) { ?>
    <nav class="board-category">
        <?php echo $category_option ?>
    </nav>
    <?php } ?>

    <div class="board-list-wrap">
        <?php for ($i=0; $i<count($list); $i++) { ?>
        <article class="board-item">
            <h3 class="board-title">
                <a href="<?php echo $list[$i]['href'] ?>">
                    <?php echo $list[$i]['subject'] ?>
                </a>
            </h3>
            <div class="board-meta">
                <span class="author"><?php echo $list[$i]['name'] ?></span>
                <span class="date"><?php echo $list[$i]['datetime2'] ?></span>
                <span class="hit">조회 <?php echo $list[$i]['wr_hit'] ?></span>
            </div>
        </article>
        <?php } ?>
    </div>

    <?php echo $write_pages; ?>
</div>
PHP;
    }

    private function getViewTemplate(string $skinName): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>
<article class="{$skinName}-board-view">
    <header class="view-header">
        <h1><?php echo cut_str(get_text($view['wr_subject']), 70); ?></h1>
        <div class="view-info">
            <span class="author"><?php echo $view['name'] ?></span>
            <span class="date"><?php echo date("Y-m-d H:i", strtotime($view['wr_datetime'])) ?></span>
            <span class="hit">조회 <?php echo number_format($view['wr_hit']) ?></span>
        </div>
    </header>

    <div class="view-content">
        <?php echo get_view_thumbnail($view); ?>
        <?php echo $view['content']; ?>
    </div>

    <footer class="view-footer">
        <div class="view-buttons">
            <?php if ($update_href) { ?><a href="<?php echo $update_href ?>" class="btn btn-modify">수정</a><?php } ?>
            <?php if ($delete_href) { ?><a href="<?php echo $delete_href ?>" class="btn btn-delete">삭제</a><?php } ?>
            <a href="<?php echo $list_href ?>" class="btn btn-list">목록</a>
        </div>
    </footer>
</article>
PHP;
    }

    private function getWriteTemplate(string $skinName): string
    {
        return <<<PHP
<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$board_skin_url = get_board_skin_url($bo_table);

add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);
?>

<section class="{$skinName}-board-write">
    <h2 class="sound_only"><?php echo $g5['title'] ?></h2>

    <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
    <input type="hidden" name="w" value="<?php echo $w ?>">
    <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
    <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">

    <div class="write-form">
        <div class="form-group">
            <label for="wr_subject">제목</label>
            <input type="text" name="wr_subject" value="<?php echo $subject ?>" id="wr_subject" required class="form-control" maxlength="255">
        </div>

        <div class="form-group">
            <label for="wr_content">내용</label>
            <?php echo $editor_html; ?>
        </div>
    </div>

    <div class="write-buttons">
        <button type="submit" class="btn btn-primary">작성완료</button>
        <a href="./board.php?bo_table=<?php echo $bo_table ?>" class="btn btn-cancel">취소</a>
    </div>
    </form>
</section>
PHP;
    }

    private function getDefaultCSS(string $skinName): string
    {
        return <<<CSS
/* {$skinName} 스킨 스타일 */

.{$skinName}-board-list {
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
    .{$skinName}-board-list {
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

    private function getPluginTemplate(string $pluginName): string
    {
        return <<<PHP
<?php
if (!defined('G5_USE_HOOK') || !G5_USE_HOOK) return;

// 플러그인 정보
$plugin_info = [
    'name' => '{$pluginName}',
    'version' => '1.0.0',
    'author' => 'Your Name',
    'description' => '{$pluginName} 플러그인입니다.',
    'license' => 'GPLv2 or later',
];

// 훅(Hook) 등록 예시
// add_hook('post_login', 'plugin_post_login_example');
// function plugin_post_login_example($mb) {
//     error_log("{$mb['mb_id']}님이 로그인했습니다.");
// }

// 관리자 메뉴 추가 예시
// add_admin_menu('{$pluginName}_admin', '{$pluginName} 설정', G5_PLUGIN_URL . '/{$pluginName}/admin/admin.php');
PHP;
    }

    private function getPluginAdminTemplate(string $pluginName): string
    {
        return <<<PHP
<?php
$sub_menu = '100800'; // 관리자 메뉴 코드에 맞게 수정
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '{$pluginName} 플러그인 설정';
include_once(G5_ADMIN_PATH.'/admin.head.php');
?>

<section id="anc_cf_{$pluginName}">
    <h2 class="h2_frm">{$pluginName} 설정</h2>

    <form name="fconfig" action="./plugin_update.php" method="post">
    <input type="hidden" name="plugin" value="{$pluginName}">

    <div class="tbl_frm01 tbl_wrap">
        <table>
        <caption>{$pluginName} 설정</caption>
        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row"><label for="option1">옵션 1</label></th>
            <td>
                <input type="text" name="option1" value="<?php echo $config['cf_{$pluginName}_option1'] ?? '' ?>" id="option1" class="frm_input" size="40">
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

    private function getPluginSetupTemplate(string $pluginName): string
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
        ->withServerInfo('그누보드 MCP 풀버전', '1.0.0')
        ->build();

    $server->discover(__DIR__, ['src']);

    $transport = new StdioServerTransport();
    $server->listen($transport);

} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}