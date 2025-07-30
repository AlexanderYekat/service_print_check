<?php
class PrintCheckUseCase {
    private PrinterInterface $printer;
    private SettingsStorageInterface $settings;
    public function __construct(PrinterInterface $printer, SettingsStorageInterface $settings) {
        $this->printer = $printer;
        $this->settings = $settings;
    }
    public function execute(Check $check): PrintResult {
        // 1. Можно взять настройки, если нужно
        $cfg = $this->settings->load();
        // 2. Вызываем принтер (настоящий, эмулятор, любой)
        return $this->printer->printCheck($check);
    }
}