<?php

use App\Watch\Fingerprinter;

it('produces a stable fingerprint from class, file and line', function () {
    $fp = new Fingerprinter;

    $a = $fp->forException(['class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 10]);
    $b = $fp->forException(['class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 10]);

    expect($a)->toBe($b)->and(strlen($a))->toBe(64);
});

it('differs when class, file, or line changes', function () {
    $fp = new Fingerprinter;
    $base = $fp->forException(['class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 10]);

    expect($fp->forException(['class' => 'LogicException', 'file' => 'app/Foo.php', 'line' => 10]))->not->toBe($base);
    expect($fp->forException(['class' => 'RuntimeException', 'file' => 'app/Bar.php', 'line' => 10]))->not->toBe($base);
    expect($fp->forException(['class' => 'RuntimeException', 'file' => 'app/Foo.php', 'line' => 11]))->not->toBe($base);
});

it('falls back to the first stack frame when file/line missing', function () {
    $fp = new Fingerprinter;

    $fingerprint = $fp->forException([
        'class' => 'RuntimeException',
        'stacktrace' => [
            ['file' => 'app/Frame.php', 'line' => 7, 'function' => 'handle'],
        ],
    ]);

    $expected = hash('sha256', 'RuntimeException|app/Frame.php|7');
    expect($fingerprint)->toBe($expected);
});
