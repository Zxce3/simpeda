<?php
/**
 * Build script for minifying and serving PHP projects
 * 
 * @sourceFiles List of source files to be minified and combined
 * @outputFile Output file to save the minified content
 * @iswatching Flag to indicate if the script is watching for changes
 * 
 * Usage: php build.php <command>
 * 
 * Commands:
 *  build     - Build the project once
 *  watch     - Watch for changes and rebuild automatically
 *  serve     - Build, watch for changes, and start PHP server
 * 
 * Example: php build.php serve 8000
 */
class Builder
{
    private $sourceFiles;
    private $outputFile;
    private $isWatching = false;

    public function __construct(array $sourceFiles, string $outputFile)
    {
        $this->sourceFiles = $sourceFiles;
        $this->outputFile = $outputFile;
    }

    private function minifyContent(string $content): string
    {
        $phpBlocks = [];
        $content = preg_replace_callback('/(<\?php.*?\?>)/s', function ($matches) use (&$phpBlocks) {
            $placeholder = '___PHP_BLOCK_' . count($phpBlocks) . '___';
            $phpBlocks[$placeholder] = $matches[1];
            return $placeholder;
        }, $content);

        $jsBlocks = [];
        $content = preg_replace_callback('/(<script[^>]*>.*?<\/script>)/is', function ($matches) use (&$jsBlocks) {
            $placeholder = '___JS_BLOCK_' . count($jsBlocks) . '___';
            $jsBlocks[$placeholder] = $matches[1];
            return $placeholder;
        }, $content);

        $cssBlocks = [];
        $content = preg_replace_callback('/(<style[^>]*>.*?<\/style>)/is', function ($matches) use (&$cssBlocks) {
            $placeholder = '___CSS_BLOCK_' . count($cssBlocks) . '___';
            $cssBlocks[$placeholder] = $matches[1];
            return $placeholder;
        }, $content);

        $content = $this->minifyHTML($content);

        foreach ($phpBlocks as $placeholder => $code) {
            $code = $this->minifyPHP($code);
            $content = str_replace($placeholder, $code, $content);
        }

        foreach ($jsBlocks as $placeholder => $code) {
            $code = $this->minifyJS($code);
            $content = str_replace($placeholder, $code, $content);
        }

        foreach ($cssBlocks as $placeholder => $code) {
            $code = $this->minifyCSS($code);
            $content = str_replace($placeholder, $code, $content);
        }

        return $content;
    }

    private function minifyPHP(string $content): string
    {
        return $this->minifyCode($content, ['<?php ', '?>']);
    }

    private function minifyJS(string $content): string
    {
        return $this->minifyCode($content);
    }

    private function minifyCSS(string $content): string
    {
        return $this->minifyCode($content);
    }

    private function minifyCode(string $content, array $phpTags = []): string
    {
        // Preserve strings
        $strings = [];
        $content = preg_replace_callback('/([\'"])((?:\\\\.|(?!\1).)*)\1/s', function ($matches) use (&$strings) {
            $placeholder = '___STRING_' . count($strings) . '___';
            $strings[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);

        // Remove comments
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);

        // Remove single-line comments and empty lines
        $lines = explode("\n", $content);
        $result = [];
        foreach ($lines as $line) {
            $line = preg_replace('/(?<!:)\/\/.*$/', '', $line);
            if (trim($line) !== '') {
                $result[] = $line;
            }
        }
        $content = implode("\n", $result);

        // Minify whitespace
        $content = preg_replace('/\s+/s', ' ', $content);
        $content = preg_replace('/\s*([;{}(),:])\s*/', '$1', $content);

        // Restore strings
        foreach ($strings as $placeholder => $string) {
            $content = str_replace($placeholder, $string, $content);
        }

        // Additional PHP-specific minification
        if (!empty($phpTags)) {
            $content = preg_replace('/\s*' . preg_quote($phpTags[0], '/') . '/', $phpTags[0], $content);
            $content = preg_replace('/' . preg_quote($phpTags[1], '/') . '\s*/', $phpTags[1], $content);
            $content = preg_replace('/([^}])}/', '$1 }', $content);
        }

        return trim($content);
    }

    private function minifyHTML(string $content): string
    {
        $content = preg_replace('/<!--(?!\[if).*?-->/s', '', $content);
        $content = preg_replace('/>\s+</s', '><', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    public function build(): void
    {
        $output = '';
        foreach ($this->sourceFiles as $file) {
            if (!file_exists($file)) {
                echo "Warning: Source file '$file' not found!\n";
                continue;
            }
            $content = file_get_contents($file);
            $content = $this->minifyContent($content);
            $output .= $content . "\n";
        }

        $buildDir = dirname($this->outputFile);
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $output = "<?php /* Built: {$timestamp} */ session_start(); ?>\n" . $output;

        $output = preg_replace('/\?>\s*<\?php/', '', $output);
        if (strpos($output, '?>') === false) {
            $output = rtrim($output);
        }

        $output = preg_replace('/\?>\s*\/\*/', '?> /*', $output);
        $output = preg_replace('/\*\/\s*<\?php/', '*/ <?php', $output);

        file_put_contents($this->outputFile, $output);
        echo "Build completed: {$this->outputFile}\n";
    }

    public function watch(): void
    {
        $this->isWatching = true;
        $lastModifiedTimes = $this->getLastModifiedTimes();

        echo "Watching for file changes... Press Ctrl+C to stop.\n";

        while ($this->isWatching) {
            $currentModifiedTimes = $this->getLastModifiedTimes();
            if ($currentModifiedTimes !== $lastModifiedTimes) {
                echo "\nChange detected! Rebuilding...\n";
                $this->build();
                $lastModifiedTimes = $currentModifiedTimes;
            }
            usleep(1000000);
        }
    }

    private function getLastModifiedTimes(): array
    {
        $times = [];
        foreach ($this->sourceFiles as $file) {
            if (file_exists($file)) {
                $times[$file] = filemtime($file);
            }
        }
        return $times;
    }

    public function serve(int $port = 8000): void
    {
        $this->build();
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen(sprintf('start /B php "%s" watch', __FILE__), 'r'));
        } else {
            exec(sprintf('php "%s" watch > /dev/null 2>&1 &', __FILE__));
        }
        $command = sprintf('php -S localhost:%d -t %s', $port, dirname($this->outputFile));
        echo "Starting server at http://localhost:$port\n";
        shell_exec($command);
    }
}

$sourceFiles = [
    'src/error_page.php',
    'src/api.php',
    'src/dashboard.php',
    'src/SystemInformation.php',
    'src/install.php',
    'src/home.php',
    'src/js_script.php',
    'src/css_style.php',
    'src/auth.php',
    'src/lib/pocketbase.php',
];
$outputFile = 'build/index.php';

$builder = new Builder($sourceFiles, $outputFile);

if ($argc > 1) {
    switch ($argv[1]) {
        case 'build':
            $builder->build();
            break;
        case 'watch':
            $builder->watch();
            break;
        case 'serve':
            $port = isset($argv[2]) ? (int) $argv[2] : 8000;
            $builder->serve($port);
            break;
        default:
            echo "Unknown command. Available commands: build, watch, serve [port]\n";
            exit(1);
    }
} else {
    echo "Usage: php build.php <command>\n";
    echo "Commands:\n";
    echo "  build     - Build the project once\n";
    echo "  watch     - Watch for changes and rebuild automatically\n";
    echo "  serve     - Build, watch for changes, and start PHP server\n";
    exit(1);
}
?>