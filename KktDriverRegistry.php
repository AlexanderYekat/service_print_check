<?php

require_once 'kktutils.php';

final class KktDriverRegistry {
    private static $driver = null; // TFptr10Driver | null

    public static function get($settings, $logger) {
        $logger->info("KktDriverRegistry was called with settings: " . json_encode($settings, JSON_UNESCAPED_UNICODE));
        if (self::$driver === null) {
            self::$driver = new TFptr10Driver(
                $settings->comKkt,
                $settings->ipKkt,
                $settings->portIpKkt,
                $settings->ipServKkt,
                $logger,
                $settings->emulation
            );
        } else {
            self::$driver->setComport($settings->comKkt);
            self::$driver->setIpKkt($settings->ipKkt);
            self::$driver->setPortIpKkt($settings->portIpKkt);
            self::$driver->setIpServKkt($settings->ipServKkt);
            self::$driver->setEmulation($settings->emulation);
            self::$driver->setEmulationwait($settings->emulationwait);
        }
        return self::$driver;
    }
}


