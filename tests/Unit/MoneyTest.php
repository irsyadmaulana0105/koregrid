<?php

use App\Domain\Shared\Money;

it('multiplies a unit price by a quantity exactly', function (string $price, int $quantity, string $expected) {
    expect(Money::multiply($price, $quantity))->toBe($expected);
})->with([
    'whole price' => ['85000.00', 50, '4250000.00'],
    'cents' => ['12.34', 3, '37.02'],
    'one' => ['99999999.99', 1, '99999999.99'],
]);

it('adds amounts without float errors', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::add())->toBe('0.00')
        ->and(Money::add('4250000.00', '467500.00'))->toBe('4717500.00');
});

it('calculates percentages rounded half up', function (string $amount, string $rate, string $expected) {
    expect(Money::percentage($amount, $rate))->toBe($expected);
})->with([
    'exact' => ['4250000.00', '0.11', '467500.00'],
    'rounds up' => ['3333.33', '0.11', '366.67'],
    'rounds down' => ['10.01', '0.11', '1.10'],
    'half goes up' => ['0.50', '0.01', '0.01'],
    'zero' => ['0.00', '0.11', '0.00'],
    'three decimal rate' => ['1000.00', '0.115', '115.00'],
]);
