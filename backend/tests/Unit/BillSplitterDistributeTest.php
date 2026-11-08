<?php

use App\Services\BillSplitter;

it('razdeli znesek sorazmerno z dnevi bivanja (primer iz US-06)', function () {
    // 90,00 € za 30, 30 in 15 dni → 36,00 €, 36,00 € in 18,00 €.
    expect(app(BillSplitter::class)->distribute(9000, [30, 30, 15]))->toBe([3600, 3600, 1800]);
});

it('ostanek centov pri enakih dnevih dobi tisti, ki je bil dodan prvi', function () {
    // 100,00 € med tri enake sostanovalce → 33,34 €, 33,33 €, 33,33 €.
    expect(app(BillSplitter::class)->distribute(10000, [30, 30, 30]))->toBe([3334, 3333, 3333]);
});

it('ostanek centov dobijo sostanovalci z največ dnevi', function () {
    // 1,00 € za 10 in 20 dni: osnovi 33 in 66 centov, ostanek 1 cent dobi daljše bivanje.
    expect(app(BillSplitter::class)->distribute(100, [10, 20]))->toBe([33, 67])
        ->and(app(BillSplitter::class)->distribute(1000, [7, 7, 7]))->toBe([334, 333, 333]);
});

it('vsota je vedno enaka znesku', function (int $amount, array $weights) {
    expect(array_sum(app(BillSplitter::class)->distribute($amount, $weights)))->toBe($amount);
})->with([
    [12345, [31, 17, 3]],
    [1, [1, 1, 1]],
    [99999, [5000, 3000, 2000, 1]],
    [126000, [5200, 3850, 4500, 6800]],
]);

it('pri ničelnih utežeh ne razdeli ničesar', function () {
    expect(app(BillSplitter::class)->distribute(500, [0, 0]))->toBe([0, 0]);
});
