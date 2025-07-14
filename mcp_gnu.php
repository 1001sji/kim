<?php
// gnuboard-mcp.php
// MCP tools for Gnuboard5 skin and plugin development

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Attributes\{McpTool, McpPrompt};

class GnuboardMcp
{
    /**
     * List available board skins
     */
    #[McpTool(name: 'list_board_skins')]
    public function listBoardSkins(): array
    {
        global $g5;
        $skinPath = G5_SKIN_PATH . '/board';
        $skins = [];

        if (!is_dir($skinPath)) {
            return ['error' => 'Skin directory not found'];
        }

        $handle = opendir($skinPath);
        if ($handle === false) {
            return ['error' => 'Cannot open skin directory'];
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $skinPath . '/' . $file;
            if (is_dir($path)) {
                $skins[] = [
                    'name' => $file,
                    'path' => $path,
                    'files' => $this->getSkinFiles($path),
                ];
            }
        }
        closedir($handle);

        return ['skins' => $skins];
    }

    /**
     * Read a skin file
     */
    #[McpTool(name: 'read_skin_file')]
    public function readSkinFile(string $skinType, string $skinName, string $fileName): array
    {
        global $g5;

        $basePath = $skinType === 'board' ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $filePath = $basePath . '/' . $skinName . '/' . $fileName;

        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }

        return [
            'content' => file_get_contents($filePath),
            'path' => $filePath,
            'type' => pathinfo($filePath, PATHINFO_EXTENSION),
        ];
    }

    /**
     * Save a skin file with backup
     */
    #[McpTool(name: 'save_skin_file')]
    public function saveSkinFile(
        string $skinType,
        string $skinName,
        string $fileName,
        string $content
    ): array {
        global $g5;

        $basePath = $skinType === 'board' ? G5_SKIN_PATH . '/board' : G5_SKIN_PATH . '/member';
        $filePath = $basePath . '/' . $skinName . '/' . $fileName;

        if (!is_dir(dirname($filePath))) {
            return ['error' => 'Target directory does not exist'];
        }

        $backupPath = null;
        if (file_exists($filePath)) {
            $backupPath = $filePath . '.backup.' . date('YmdHis');
            if (!copy($filePath, $backupPath)) {
                return ['error' => 'Failed to create backup'];
            }
        }

        $result = file_put_contents($filePath, $content);

        return [
            'success' => $result !== false,
            'path' => $filePath,
            'backup' => $backupPath,
        ];
    }

    /**
     * Create a new board skin based on basic template
     */
    #[McpTool(name: 'create_board_skin')]
    public function createBoardSkin(string $skinName, string $baseSkin = 'basic'): array
    {
        global $g5;

        $newSkinPath = G5_SKIN_PATH . '/board/' . $skinName;
        if (is_dir($newSkinPath)) {
            return ['error' => 'Skin already exists'];
        }

        if (!mkdir($newSkinPath, 0755, true) && !is_dir($newSkinPath)) {
            return ['error' => 'Failed to create directory'];
        }

        $defaultFiles = [
            'list.skin.php' => $this->getListTemplate($skinName),
            'view.skin.php' => $this->getViewTemplate($skinName),
            'write.skin.php' => $this->getWriteTemplate($skinName),
            'style.css' => $this->getDefaultCss($skinName),
            'img/' => null,
        ];

        foreach ($defaultFiles as $file => $content) {
            $target = $newSkinPath . '/' . $file;
            if (str_ends_with($file, '/')) {
                if (!mkdir($target, 0755) && !is_dir($target)) {
                    return ['error' => 'Failed to create ' . $file];
                }
                continue;
            }

            if (file_put_contents($target, $content) === false) {
                return ['error' => 'Failed to write ' . $file];
            }
        }

        return [
            'success' => true,
            'skin_name' => $skinName,
            'path' => $newSkinPath,
            'files' => array_keys($defaultFiles),
        ];
    }

    /**
     * Get variables available in skin templates
     */
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

    /**
     * Analyze basic skin structure and describe key files
     */
    #[McpTool(name: 'analyze_basic_skin')]
    public function analyzeBasicSkin(): array
    {
        return [
            'list.skin.php' => [
                'role' => '게시글 목록 출력',
                'hotspots' => 'HTML 구조, 제목/작성자/날짜 영역',
            ],
            'view.skin.php' => [
                'role' => '게시글 상세 보기',
                'hotspots' => '본문 출력, 이전/다음 글 링크, 댓글 영역',
            ],
            'write.skin.php' => [
                'role' => '글 작성/수정 폼',
                'hotspots' => '입력 필드, 파일 업로드, 에디터 설정',
            ],
            'form.php' => [
                'role' => '회원 폼 처리 공통 코드',
                'hotspots' => '입력 검증, 토큰 처리',
            ],
            'style.css' => [
                'role' => '스킨 스타일 정의',
                'hotspots' => '레이아웃, 색상, 반응형 미디어쿼리',
            ],
        ];
    }

    /**
     * Locate common section in basic skin file
     */
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

        return [
            'section' => $section,
            'code' => $matches[0] ?? '',
        ];
    }

    /**
     * Provide commonly used customization snippets for basic skin
     */
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

    /**
     * Generate responsive skin template prompt
     */
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

    /**
     * Convert skin HTML classes between frameworks (simple replacement)
     */
    #[McpTool(name: 'convert_skin_framework')]
    public function convertSkinFramework(string $filePath, string $from, string $to): array
    {
        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }
        $content = file_get_contents($filePath);
        $maps = [
            'bootstrap_to_tailwind' => [
                'btn btn-primary' => 'bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded',
                'table table-striped' => 'min-w-full divide-y divide-gray-200',
                'form-control' => 'shadow appearance-none border rounded w-full py-2 px-3',
                'row' => 'flex flex-wrap -mx-4',
                'col-md-6' => 'w-full md:w-1/2 px-4',
            ],
        ];

        $key = strtolower($from . '_to_' . $to);
        if (!isset($maps[$key])) {
            return ['error' => 'Conversion map not found'];
        }

        $converted = strtr($content, $maps[$key]);
        $newPath = $filePath . '.' . $to;
        if (file_put_contents($newPath, $converted) === false) {
            return ['error' => 'Failed to write converted file'];
        }

        return [
            'success' => true,
            'converted_path' => $newPath,
        ];
    }

    /**
     * Create plugin skeleton
     */
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
            'setup.php' => "<?php if (!defined('_GNUBOARD_')) exit; \n// installation script for {$pluginName}\n",
        ];

        foreach ($files as $name => $content) {
            if (file_put_contents($base . '/' . $name, $content) === false) {
                return ['error' => 'Failed to write ' . $name];
            }
        }

        return [
            'success' => true,
            'path' => $base,
            'files' => array_keys($files),
        ];
    }

    /**
     * Provide list of main Gnuboard hooks
     */
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

    /**
     * Prompt example for using a hook
     */
    #[McpPrompt(name: 'hook_example')]
    public function hookExamplePrompt(string $hook): array
    {
        $code = "<?php\nadd_hook('{$hook}', function(\$data) {\n    // TODO: implement\n});\n";
        return [
            ['role' => 'assistant', 'content' => $code],
        ];
    }

    /**
     * DB table helper snippet
     */
    #[McpTool(name: 'db_table_helper')]
    public function dbTableHelper(string $table): array
    {
        $code = "<?php\n$sql = \"CREATE TABLE IF NOT EXISTS {$table} ( id INT NOT NULL AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\";\nsql_query(\$sql);\n";
        return ['code' => $code];
    }

    /**
     * Admin menu integration guide
     */
    #[McpPrompt(name: 'admin_menu_guide')]
    public function adminMenuGuide(string $pluginName): array
    {
        $content = "관리자 메뉴에 {$pluginName} 설정 페이지를 추가하려면 admin.menu.php에 다음 코드를 삽입하세요:\n\n<?php\nadd_admin_menu('{$pluginName}', G5_ADMIN_URL . '/?dir={$pluginName}');\n?>";
        return [
            ['role' => 'assistant', 'content' => $content],
        ];
    }

    // helper utilities ---------------------------------------------------
    private function getSkinFiles(string $path): array
    {
        $files = [];
        $handle = opendir($path);
        if ($handle === false) {
            return $files;
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_file($path . '/' . $file)) {
                $files[] = $file;
            }
        }
        closedir($handle);
        return $files;
    }

    private function getListTemplate(string $skinName): string
    {
        return "<?php\nif (!defined('_GNUBOARD_')) exit;\n?>\n<div class=\"{$skinName}-list\">\n    <?php foreach (\$list as \$item) { ?>\n    <div class=\"item\">\n        <a href=\"<?php echo \$item['href']; ?>\"><?php echo \$item['subject']; ?></a>\n    </div>\n    <?php } ?>\n</div>\n";
    }

    private function getViewTemplate(string $skinName): string
    {
        return "<?php\nif (!defined('_GNUBOARD_')) exit;\n?>\n<article class=\"{$skinName}-view\">\n    <h1><?php echo \$view['wr_subject']; ?></h1>\n    <div><?php echo \$view['content']; ?></div>\n</article>\n";
    }

    private function getWriteTemplate(string $skinName): string
    {
        return "<?php\nif (!defined('_GNUBOARD_')) exit;\n?>\n<form method=\"post\" enctype=\"multipart/form-data\" class=\"{$skinName}-write\">\n    <input type=\"text\" name=\"wr_subject\" required>\n    <textarea name=\"wr_content\"></textarea>\n    <button type=\"submit\">저장</button>\n</form>\n";
    }

    private function getDefaultCss(string $skinName): string
    {
        return ".{$skinName}-list{margin:20px 0}.{$skinName}-view{padding:20px}";
    }
}

// run server -------------------------------------------------------------
try {
    $server = Server::make()
        ->withServerInfo('Gnuboard MCP', '1.1.0')
        ->build();

    $server->discover(__DIR__, ['src']);

    $transport = new StdioServerTransport();
    $server->listen($transport);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
