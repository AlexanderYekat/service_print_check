<?php

require_once __DIR__ . '/../../src/infrastructure/bank/GoBankTerminalAdapter.php';

class GoBankTerminalAdapterTest
{
    public function testPaymentEmulation()
    {
        $adapter = new GoBankTerminalAdapter('/fake/path', true); // emulation mode
        $result = $adapter->pay(100.0);
        
        assert($result->success === true, "Payment should succeed in emulation mode");
        assert(str_contains($result->message, "100"), "Message should contain amount");
        assert(is_array($result->slipLines), "Slip lines should be an array");
        
        echo "✅ testPaymentEmulation passed\n";
    }

    public function testRefundEmulation()
    {
        $adapter = new GoBankTerminalAdapter('/fake/path', true);
        $result = $adapter->refund(50.0);
        
        assert($result->success === true, "Refund should succeed in emulation mode");
        assert(str_contains($result->message, "50"), "Message should contain amount");
        
        echo "✅ testRefundEmulation passed\n";
    }

    public function testCloseShiftEmulation()
    {
        $adapter = new GoBankTerminalAdapter('/fake/path', true);
        $result = $adapter->closeShift();
        
        assert($result->success === true, "Close shift should succeed in emulation mode");
        
        echo "✅ testCloseShiftEmulation passed\n";
    }

    public function runAllTests()
    {
        echo "Running GoBankTerminalAdapter tests...\n";
        $this->testPaymentEmulation();
        $this->testRefundEmulation();
        $this->testCloseShiftEmulation();
        echo "All GoBankTerminalAdapter tests passed! ✅\n\n";
    }
}