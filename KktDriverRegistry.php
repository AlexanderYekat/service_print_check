<?php

require_once 'kktutils.php';

final class KktDriverRegistry {
    private static $driver = null; // TFptr10Driver | null

    public static function get($settings, $logger) {
        if (self::$driver === null) {
            self::$driver = new TFptr10Driver(
                $settings->comKkt,
                $settings->ipKkt,
                $settings->portIpKkt,
                $settings->ipServKkt,
                $logger,
                $settings->emulation
            );
        }
        return self::$driver;
    }
}


