<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__).'/vendor/autoload.php';

/** Input PHP refuses (nested too deep, or past max_input_vars) is a 400 and is not reported as a server error. */
final class InputLimitTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_input_limit_warnings_are_a_bad_request_and_unreported(): void
    {
        $messages = ['parse_str(): Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.', 'Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.', 'Input variables exceeded 1000. To increase the limit change max_input_vars in php.ini.'];
        $handler = app(ExceptionHandler::class);
        foreach ($messages as $index => $message) {
            // PHP raises these as warnings, which Laravel turns into ErrorExceptions wherever the input is parsed; the first is the exact
            // production trace (Symfony's HeaderUtils::parseQuery(), reached through Request::fullUrl()).
            $this->assertFalse($handler->shouldReport(new ErrorException($message)), 'never reported, so no issue is opened');
            Route::get('/_input-limit-'.$index, fn () => throw new ErrorException($message));
            $this->get('/_input-limit-'.$index)->assertStatus(400);
            $this->getJson('/_input-limit-'.$index)->assertStatus(400)->assertJsonPath('error', 'bad_request');
        }
        // Any other warning is still a server error, and is reported.
        $this->assertTrue($handler->shouldReport(new ErrorException('Undefined variable $x')));
        Route::get('/_other-warning', fn () => throw new ErrorException('Undefined variable $x'));
        $this->withoutExceptionHandling()->expectException(ErrorException::class);
        $this->get('/_other-warning');
    }

    public function test_a_deeply_nested_query_string_is_not_a_server_error(): void
    {
        Exceptions::fake();
        $query = 'a'.implode('', array_map(fn (int $i): string => "[k$i]", range(0, 69))).'=1';
        $this->get('/login?'.$query)->assertStatus(200);
        $this->call('GET', '/login', [], [], [], ['QUERY_STRING' => $query])->assertStatus(200);
        Exceptions::assertNothingReported();
    }
}
