<?php

namespace Tests\Unit;

use App\Services\OptionOrder;
use PHPUnit\Framework\TestCase;

class OptionOrderTest extends TestCase
{
    public function test_order_is_a_deterministic_permutation(): void
    {
        $a = OptionOrder::for('session-1|A-01');

        $this->assertSame($a, OptionOrder::for('session-1|A-01'));
        $chars = str_split($a);
        sort($chars);
        $this->assertSame(['A', 'B', 'C', 'D'], $chars);
    }

    public function test_orders_vary_across_seeds(): void
    {
        $orders = [];
        for ($i = 0; $i < 200; $i++) {
            $orders[OptionOrder::for("s{$i}|X")] = true;
        }

        $this->assertGreaterThan(12, count($orders)); // of 24 possible
    }
}
