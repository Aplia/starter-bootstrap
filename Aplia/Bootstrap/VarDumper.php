<?php
namespace Aplia\Bootstrap;

/**
 * Helper class for Symfony VarDumper, takes care of passing result to
 * Symfony VarDumper. In addition it logs the result of the dump and
 * stores result in the error handler for display when errors occurs.
 *
 * Using the function dump() will eventually lead to VarDumper::dumpVar()
 * to be called.
 *
 * The dumper and cloner instances are automatically determined by how
 * PHP is run but can be overriden by setting VarDumper::$dumper and
 * VarDumper::$cloner to a new object, setting these to null will force
 * the default behaviour.
 */
class VarDumper
{
    /**
     * The current dump instance used for dumping a value.
     * If null it automatically determines the dump class to use, instantiates
     * it and stores it in this variable.
     *
     * @var \Symfony\Component\VarDumper\Dumper\DataDumperInterface
     */
    public static $dumper = null;

    /**
     * The current format used for the $dumper instance, should either be "html" or "cli".
     * If it is null then the dumper instance has not yet been created.
     *
     * @var str
     */
    protected static $format = null;

    /**
     * The current variable cloner.
     * If null it automatically determines the clone class to use, instantiates
     * it and stores it in this variable.
     *
     * @var \Symfony\Component\VarDumper\Cloner\ClonerInterface
     */
    public static $cloner = null;

    /**
     * The current dump instance used for dumping a value to the log.
     * If null it will use a CliDumper to dump the result.
     * Any custom dumper should disable any colors or special formatting.
     *
     * @var \Symfony\Component\VarDumper\Dumper\DataDumperInterface
     */
    public static $logDumper = null;

    /**
     * The variable name for the next dump call, will be reset to
     * null after each dump occurs.
     */
    public static $variableName = null;

    /**
     * Current Bootstrap application.
     */
    public $app;

    /**
     * Array of classes to skip in backtrace lookup.
     * If it is null it will use defaults from config `app.dump.skipClasses`.
     *
     * @var array
     */
    public static $skipClasses = null;

    /**
     * Array of functions to skip in backtrace lookup.
     * If it is null it will use defaults from config `app.dump.skipFunctions`.
     *
     * @var array
     */
    public static $skipFunctions = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Dumps the contents of $value to the output, sends a debug log message,
     * and stores the value in case of an error page.
     *
     * This function is not called directly but called as part of the dump()
     * function provided by symfony/var-dumper.
     *
     * This effectively replaces var_dump() for debugging variables
     * as it will display a better looking and easier to read
     * output using symfony/var-dumper.
     * Logging of the value also allows it to be examined in the debug
     * logs across multiple runs.
     * Finally if an error occurs any previous output will no longer be
     * displayed due to buffering in the error hander, to overcome this
     * the value is stored in memory and display on the error page
     * with other important information.
     * 
     * Examples:
     * @example dump($data)
     * @example dump(array_values($data))
     * @example dump($object)->call()
     */
    public function dumpVar($value)
    {
        // Counter for automatic variable names
        static $counter = 1;

        try {
            if (self::$dumper === null) {
                // Determine output handler, based on code in VarDumper
                if (self::$cloner === null) {
                    self::$cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
                }

                if (isset($_SERVER['VAR_DUMPER_FORMAT'])) {
                    $format = 'html' === $_SERVER['VAR_DUMPER_FORMAT'] ? 'html' : 'cli';
                } else {
                    $format = \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) ? 'cli' : 'html';
                }
                self::$format = $format;
                $level = $this->app->config->get('app.dump.level', 'terse');
                $useColors = $this->app->config->get('app.dump.colors', true);
                if ($level === 'verbose') {
                    $flags = \Symfony\Component\VarDumper\Dumper\AbstractDumper::DUMP_STRING_LENGTH | \Symfony\Component\VarDumper\Dumper\AbstractDumper::DUMP_TRAILING_COMMA;
                } else {
                    $flags = 0;
                }
                if ($format === 'html') {
                    $dumper = self::$dumper = new \Symfony\Component\VarDumper\Dumper\HtmlDumper(null, null, $flags);
                } else {
                    $dumper = self::$dumper = new \Symfony\Component\VarDumper\Dumper\CliDumper(null, null, $flags);
                    $dumper->setColors($useColors);
                    $dumper->setMaxStringWidth($useColors = $this->app->config->get('app.dump.maxStringWidth', 0));
                }
            }

            if (self::$logDumper === null) {
                $level = $this->app->config->get('app.dump.level', 'terse');
                if ($level === 'verbose') {
                    $flags = \Symfony\Component\VarDumper\Dumper\AbstractDumper::DUMP_STRING_LENGTH | \Symfony\Component\VarDumper\Dumper\AbstractDumper::DUMP_TRAILING_COMMA;
                } else {
                    $flags = 0;
                }
                self::$logDumper = new \Symfony\Component\VarDumper\Dumper\CliDumper(null, null, $flags);
                self::$logDumper->setColors(false);
            }

            if (self::$variableName !== null) {
                $nameText = $name = self::$variableName;
            } else {
                $name = 'var' . $counter++;
                $nameText = '';
            }

            $location = null;

            if (self::$skipClasses === null) {
                self::$skipClasses = array_keys(array_filter($this->app->config->get('app.dump.skipClasses')));
            }
            if (self::$skipFunctions === null) {
                self::$skipFunctions = array_keys(array_filter($this->app->config->get('app.dump.skipFunctions')));
            }
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $stack = new \Aplia\Bootstrap\CallStackAnalyzer($trace, 0);
            if (self::$skipClasses) {
                $stack->addSkipClassesPartials(self::$skipClasses);
            }
            if (self::$skipFunctions) {
                $stack->addSkipFunctions(self::$skipFunctions);
            }
            $stack->walk();
            if ($stack->file && $stack->line) {
                $location = $stack->file . ":" . $stack->line;
            }

            // Store debug variable for display if an error occurs
            $this->app->setDebugVariable($name, $value, $location);

            // Log the value using debug level, value must first dumped to memory
            if (function_exists('starter_log_name') && !$this->app->isLoggerInitializing(starter_log_name())) {
                $memOutput = fopen('php://memory', 'r+b');
                self::$logDumper->dump(self::$cloner->cloneVar($value), $memOutput);
                $logOutput = stream_get_contents($memOutput, -1, 0);
                fclose($memOutput);
                starter_debug("dump($nameText) result: " . $logOutput);
            }

            // Fill in file, line and code snippet if possible
            if ($stack->file && $stack->line && self::$format === 'html') {
                if ($stack->codeLine) {
                    self::$dumper->setDumpBoundaries(
                        "<pre class=sf-dump id=%s data-indent-pad=\"%s\">\n" .
                        "In {$stack->file}:{$stack->line}\n" .
                        "> {$stack->codeLine}",
                        '</pre><script>Sfdump(%s)</script>'
                    );
                } else {
                    self::$dumper->setDumpBoundaries(
                        "<pre class=sf-dump id=%s data-indent-pad=\"%s\">\n" .
                        "{$stack->file}:{$stack->line}\n",
                        '</pre><script>Sfdump(%s)</script>'
                    );
                }
            }
            self::$dumper->dump(self::$cloner->cloneVar($value));
        } catch (\Exception $exc) {
            starter_error("Failed to dump() variable, got exception: " . get_class($exc) . ": " . $exc->getMessage());
        }
        self::$variableName = null;
    }
}
