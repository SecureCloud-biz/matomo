<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
use Piwik\Error;
use Piwik\Config;
use Piwik\Log;
use Piwik\Common;
use Piwik\Db;
use Piwik\ExceptionHandler;

require_once PIWIK_INCLUDE_PATH . '/tests/resources/TestPluginLogClass.php';
use Piwik\Plugins\TestPlugin\TestLoggingUtility;

class LogTest extends DatabaseTestCase
{
    const TESTMESSAGE = 'test%smessage';
    const STRING_MESSAGE_FORMAT = '[%pluginName%] %message%';
    const STRING_MESSAGE_FORMAT_SPRINTF = "[%s] %s";

    public static $expectedExceptionOutput = array(
        'screen' => 'dummy error message<br />
 <br />
 --&gt; To temporarily debug this error further, set const DISPLAY_BACKTRACE_DEBUG=true; in ResponseBuilder.php',
        'file' => '[] LogTest.php(178): dummy error message
#0 [internal function]: LogTest->testLoggingWorksWhenMessageIsException(\'file\')
#1 TestCase.php(976): ReflectionMethod->invokeArgs(Object(LogTest), Array)
#2 TestCase.php(831): PHPUnit_Framework_TestCase->runTest()
#3 TestResult.php(648): PHPUnit_Framework_TestCase->runBare()
#4 TestCase.php(776): PHPUnit_Framework_TestResult->run(Object(LogTest))
#5 TestSuite.php(775): PHPUnit_Framework_TestCase->run(Object(PHPUnit_Framework_TestResult))
#6 TestSuite.php(745): PHPUnit_Framework_TestSuite->runTest(Object(LogTest), Object(PHPUnit_Framework_TestResult))
#7 TestSuite.php(705): PHPUnit_Framework_TestSuite->run(Object(PHPUnit_Framework_TestResult), false, Array, Array, false)
#8 TestRunner.php(349): PHPUnit_Framework_TestSuite->run(Object(PHPUnit_Framework_TestResult), false, Array, Array, false)
#9 Command.php(176): PHPUnit_TextUI_TestRunner->doRun(Object(PHPUnit_Framework_TestSuite), Array)
#10 Command.php(129): PHPUnit_TextUI_Command->run(Array, true)
#11 phpunit(46): PHPUnit_TextUI_Command::main()
#12 {main}',
        'database' => '[] LogTest.php(178): dummy error message
#0 [internal function]: LogTest->testLoggingWorksWhenMessageIsException(\'database\')
#1 TestCase.php(976): ReflectionMethod->invokeArgs(Object(LogTest), Array)
#2 TestCase.php(831): PHPUnit_Framework_TestCase->runTest()
#3 TestResult.php(648): PHPUnit_Framework_TestCase->runBare()
#4 TestCase.php(776): PHPUnit_Framework_TestResult->run(Object(LogTest))
#5 TestSuite.php(775): PHPUnit_Framework_TestCase->run(Object(PHPUnit_Framework_TestResult))
#6 TestSuite.php(745): PHPUnit_Framework_TestSuite->runTest(Object(LogTest), Object(PHPUnit_Framework_TestResult))
#7 TestSuite.php(705): PHPUnit_Framework_TestSuite->run(Object(PHPUnit_Framework_TestResult), false, Array, Array, false)
#8 TestRunner.php(349): PHPUnit_Framework_TestSuite->run(Object(PHPUnit_Framework_TestResult), false, Array, Array, false)
#9 Command.php(176): PHPUnit_TextUI_TestRunner->doRun(Object(PHPUnit_Framework_TestSuite), Array)
#10 Command.php(129): PHPUnit_TextUI_Command->run(Array, true)
#11 phpunit(46): PHPUnit_TextUI_Command::main()
#12 {main}'
    );

    public static $expectedErrorOutput = array(
        'screen' => '
<div style=\'word-wrap: break-word; border: 3px solid red; padding:4px; width:70%; background-color:#FFFF96;\'>
        <strong>There is an error. Please report the message (Piwik 2.0-a7)
        and full backtrace in the <a href=\'?module=Proxy&action=redirect&url=http://forum.piwik.org\' target=\'_blank\'>Piwik forums</a> (please do a Search first as it might have been reported already!).<br /><br/>
        Unknown error (102):</strong> <em>dummy error string</em> in <strong>dummyerrorfile.php</strong> on line <strong>145</strong>
<br /><br />Backtrace --&gt;<div style="font-family:Courier;font-size:10pt"><br />
dummy backtrace</div><br />
 </pre></div><br />',
        'file' => '[] dummyerrorfile.php(145): Unknown error (102) - dummy error string
dummy backtrace',
        'database' => '[] dummyerrorfile.php(145): Unknown error (102) - dummy error string
dummy backtrace'
    );

    private $screenOutput;

    public static function setUpBeforeClass()
    {
        Error::setErrorHandler();
        ExceptionHandler::setUp();
    }

    public static function tearDownAfterClass()
    {
        restore_error_handler();
        restore_exception_handler();
    }

    public function setUp()
    {
        parent::setUp();

        Config::getInstance()->log['string_message_format'] = self::STRING_MESSAGE_FORMAT;
        Config::getInstance()->log['logger_file_path'] = self::getLogFileLocation();
        @unlink(self::getLogFileLocation());
        Log::clearInstance();
    }

    public function tearDown()
    {
        parent::tearDown();

        Log::clearInstance();
        @unlink(self::getLogFileLocation());
    }

    /**
     * Data provider for every test.
     */
    public function getBackendsToTest()
    {
        return array(array('screen'),
                     array('file'),
                     array('database'));
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLoggingWorksWhenMessageIsString($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        Log::warning(self::TESTMESSAGE);
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkBackend($backend, self::TESTMESSAGE, $formatMessage = true);
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLoggingWorksWhenMessageIsSprintfString($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        Log::warning(self::TESTMESSAGE, " subst ");
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkBackend($backend, sprintf(self::TESTMESSAGE, " subst "), $formatMessage = true);
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLoggingWorksWhenMessageIsError($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        $error = new Error(102, "dummy error string", "dummyerrorfile.php", 145, "dummy backtrace");
        Log::error($error);
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkBackend($backend, self::$expectedErrorOutput[$backend]);
        $this->checkBackend('screen', self::$expectedErrorOutput['screen']); // errors should always written to the screen
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLoggingWorksWhenMessageIsException($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        $exception = new Exception("dummy error message");
        Log::error($exception);
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkBackend($backend, self::$expectedExceptionOutput[$backend]);
        $this->checkBackend('screen', self::$expectedExceptionOutput['screen']); // errors should always written to the screen
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLoggingCorrectlyIdentifiesPlugin($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        TestLoggingUtility::doLog(self::TESTMESSAGE);
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkBackend($backend, self::TESTMESSAGE, $formatMessage = true, $plugin = 'TestPlugin');
    }

    /**
     * @group Core
     * @group Access
     * @dataProvider getBackendsToTest
     */
    public function testLogMessagesIgnoredWhenNotWithinLevel($backend)
    {
        Config::getInstance()->log['log_writers'] = array($backend);

        ob_start();
        Log::info(self::TESTMESSAGE);
        $this->screenOutput = ob_get_contents();
        ob_end_clean();

        $this->checkNoMessagesLogged($backend);
    }

    private function checkBackend($backend, $expectedMessage, $formatMessage = false, $plugin = false)
    {
        if ($formatMessage) {
            $expectedMessage = sprintf(self::STRING_MESSAGE_FORMAT_SPRINTF, $plugin, $expectedMessage);
        }

        if ($backend == 'screen') {
            if ($formatMessage) {
                $expectedMessage = '<pre>' . $expectedMessage . '</pre>';
            }

            $this->screenOutput = $this->removePathsFromBacktrace($this->screenOutput);

            $this->assertEquals($expectedMessage . "\n", $this->screenOutput);
        } else if ($backend == 'file') {
            $this->assertTrue(file_exists(self::getLogFileLocation()));

            $fileContents = file_get_contents(self::getLogFileLocation());
            $fileContents = $this->removePathsFromBacktrace($fileContents);

            $this->assertEquals($expectedMessage . "\n", $fileContents);
        } else if ($backend == 'database') {
            $count = Db::fetchOne("SELECT COUNT(*) FROM " . Common::prefixTable('logger_message'));
            $this->assertEquals(1, $count);

            $message = Db::fetchOne("SELECT message FROM " . Common::prefixTable('logger_message') . " LIMIT 1");
            $message = $this->removePathsFromBacktrace($message);
            $this->assertEquals($expectedMessage, $message);

            $pluginInDb = Db::fetchOne("SELECT plugin FROM " . Common::prefixTable('logger_message') . " LIMIT 1");
            if ($plugin === false) {
                $this->assertEmpty($pluginInDb);
            } else {
                $this->assertEquals($plugin, $pluginInDb);
            }
        }
    }

    private function checkNoMessagesLogged($backend)
    {
        if ($backend == 'screen') {
            $this->assertEmpty($this->screenOutput);
        } else if ($backend == 'file') {
            $this->assertFalse(file_exists(self::getLogFileLocation()));
        } else if ($backend == 'database') {
            $this->assertEquals(0, Db::fetchOne("SELECT COUNT(*) FROM " . Common::prefixTable('logger_message')));
        }
    }

    private function removePathsFromBacktrace($content)
    {
        return preg_replace_callback("/(?:\/[^\s(<>]+)*\//", function ($matches) {
            if ($matches[0] == '/') {
                return '/';
            } else {
                return '';
            }
        }, $content);
    }

    public static function getLogFileLocation()
    {
        return PIWIK_INCLUDE_PATH . '/tmp/logs/piwik.test.log';
    }
}