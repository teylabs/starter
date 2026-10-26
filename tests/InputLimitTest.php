<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

test('the input limit warnings are a bad request and unreported', function () {
    $messages = ['parse_str(): Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.', 'Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.', 'Input variables exceeded 1000. To increase the limit change max_input_vars in php.ini.'];
    $handler = app(ExceptionHandler::class);
    foreach ($messages as $index => $message) {
        // PHP raises these as warnings, which Laravel turns into ErrorExceptions wherever the input is parsed; the first is the exact
        // production trace (Symfony's HeaderUtils::parseQuery(), reached through Request::fullUrl()).
        expect($handler->shouldReport(new ErrorException($message)))->toBeFalse('never reported, so no issue is opened');
        Route::get('/_input-limit-'.$index, fn () => throw new ErrorException($message));
        $this->get('/_input-limit-'.$index)->assertStatus(400);
        $this->getJson('/_input-limit-'.$index)->assertStatus(400)->assertJsonPath('error', 'bad_request');
    }

    // Any other warning is still a server error, and is reported.
    expect($handler->shouldReport(new ErrorException('Undefined variable $x')))->toBeTrue();
    Route::get('/_other-warning', fn () => throw new ErrorException('Undefined variable $x'));
    $this->withoutExceptionHandling()->expectException(ErrorException::class);
    $this->get('/_other-warning');
});
test('a deeply nested query string is not a server error', function () {
    Exceptions::fake();
    $query = 'a'.implode('', array_map(fn (int $i): string => "[k$i]", range(0, 69))).'=1';
    $this->get('/login?'.$query)->assertStatus(200);
    $this->call('GET', '/login', [], [], [], ['QUERY_STRING' => $query])->assertStatus(200);
    Exceptions::assertNothingReported();
});
