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

    /**
     * Conservative minifier. PHP is tokenized (so strings and heredocs are never touched),
     * and inline HTML/JS/CSS only loses comments and redundant whitespace while keeping
     * line breaks, so `//` comments in JS can never swallow code.
     */
    private function minifyContent(string $content): string
    {
        $out = '';
        $inString = false;
        foreach (token_get_all($content) as $token) {
            if (!is_array($token)) {
                if ($token === '"') {
                    $inString = !$inString;
                } elseif (!$inString && strpos(';{},()', $token) !== false && substr($out, -6) !== '<?php ') {
                    $out = rtrim($out, ' ');
                }
                $out .= $token;
                continue;
            }
            [$id, $text] = $token;
            if ($inString && $id !== T_END_HEREDOC) {
                $out .= $text;
                continue;
            }
            switch ($id) {
                case T_OPEN_TAG:
                    $out .= '<?php ';
                    break;
                case T_CLOSE_TAG:
                    $out = rtrim($out, ' ') . '?>';
                    break;
                case T_COMMENT:
                case T_DOC_COMMENT:
                    break;
                case T_WHITESPACE:
                    if ($out !== '' && !in_array(substr($out, -1), [' ', ';', '{', '}', ',', '('], true)) {
                        $out .= ' ';
                    }
                    break;
                case T_INLINE_HTML:
                    $out .= $this->minifyHTML($text);
                    break;
                case T_START_HEREDOC:
                    $inString = true;
                    $out .= $text;
                    break;
                case T_END_HEREDOC:
                    $inString = false;
                    $out .= $text;
                    break;
                default:
                    $out .= $text;
            }
        }
        return trim($out);
    }

    private function minifyCSS(string $content): string
    {
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        $content = preg_replace('/\s+/s', ' ', $content);
        return trim(preg_replace('/\s*([;{}(),:])\s*/', '$1', $content));
    }

    private function minifyHTML(string $content): string
    {
        $content = preg_replace('/<!--(?!\[if).*?-->/s', '', $content);
        $content = preg_replace_callback('/(<style[^>]*>)(.*?)(<\/style>)/is', function ($m) {
            return $m[1] . $this->minifyCSS($m[2]) . $m[3];
        }, $content);
        // Collapse indentation and blank lines but keep one line break (safe for inline JS).
        return preg_replace('/[ \t]*\R\s*/', "\n", $content);
    }

    private function version(): string
    {
        $version = getenv('SIMPEDA_VERSION');
        if (!$version) {
            $version = trim((string) @shell_exec('git describe --tags --always --dirty 2>/dev/null'));
        }
        return preg_replace('/[^A-Za-z0-9._+-]/', '', $version) ?: 'dev';
    }

    public function build(): bool
    {
        $output = '';
        foreach ($this->sourceFiles as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                fwrite(STDERR, "Error: Could not read file '$file'\n");
                return false;
            }
            $output .= $this->minifyContent($content) . "\n";
        }

        $buildDir = dirname($this->outputFile);
        if (!is_dir($buildDir) && !mkdir($buildDir, 0755, true) && !is_dir($buildDir)) {
            fwrite(STDERR, "Error: Could not create directory '$buildDir'\n");
            return false;
        }

        // No timestamp: the same sources always produce the same file.
        $header = "<?php /* simpeda {$this->version()} */ "
            . "session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty(\$_SERVER['HTTPS'])]); "
            . "if (PHP_SAPI !== 'cli') { session_start(); } ?>\n";
        $output = $header . $output;

        // A PHP close tag implies a semicolon, so join each close/open tag pair with an explicit one.
        $output = preg_replace('/\?>\s*<\?php\s*/', ';', $output);
        if (strpos($output, '?>') === false) {
            $output = rtrim($output);
        }

        if (file_put_contents($this->outputFile, $output) === false) {
            fwrite(STDERR, "Error: Could not write to file '{$this->outputFile}'\n");
            return false;
        }
        echo "Build completed: {$this->outputFile}\n";
        return true;
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

    public function serve(int $port = 8000): bool
    {
        if (!$this->build()) {
            return false;
        }
        // index.php is the router script so /login, /install etc. reach the app.
        $server = proc_open(
            [PHP_BINARY, '-S', "localhost:$port", '-t', dirname($this->outputFile), $this->outputFile],
            [],
            $pipes
        );
        if (!is_resource($server)) {
            fwrite(STDERR, "Error: Could not start the PHP server\n");
            return false;
        }
        register_shutdown_function(static function () use ($server) {
            proc_terminate($server);
        });
        if (function_exists('pcntl_signal')) {
            // Exit normally on Ctrl+C / kill so the shutdown function stops the server.
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) {
                pcntl_signal($signal, static function () {
                    exit(0);
                });
            }
        }
        echo "Starting server at http://localhost:$port\n";
        $this->watch();
        return true;
    }
}
$sourceFiles = array_merge(
    glob('src/*.php') ?: [],
    glob('src/lib/*.php') ?: [],
    glob('src/components/*.php') ?: [],
    glob('src/pages/*.php') ?: []
);

$outputFile = 'build/index.php';

$builder = new Builder($sourceFiles, $outputFile);

if ($argc > 1) {
    switch ($argv[1]) {
        case 'build':
            exit($builder->build() ? 0 : 1);
        case 'watch':
            $builder->watch();
            break;
        case 'serve':
            $port = isset($argv[2]) ? (int) $argv[2] : 8000;
            exit($builder->serve($port) ? 0 : 1);
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
