<?php

/**
 * Доменная модель кода маркировки
 * Содержит только основную информацию согласно ТЗ
 * ИНН и GTIN передаются через контекст, не хранятся в модели
 */
class MarkingCode
{
    private string $rawCode;

    // Кэш для разных форматов
    private ?string $cleanCode = null;     // Латиница + GS
    private ?string $shortCode = null;
    private ?string $base64Code = null;    

    public function __construct(string $rawCode)
    {
        $this->rawCode = trim($rawCode);
    }
    public function getRawCode(): string
    {
        return $this->rawCode;
    }

    public function getCleanCode(): string
    {
        if ($this->cleanCode === null) {
            $this->cleanCode = $this->normalizeCode($this->rawCode);
        }
        return $this->cleanCode;
    }

    public function getBase64Code(): string
    {
        if ($this->base64Code === null) {
            $this->base64Code = base64_encode($this->getCleanCode());
        }
        return $this->base64Code;
    }

    public function getShortCode(): string
    {
        if ($this->shortCode === null) {
            $this->shortCode = $this->extractShortCode($this->getCleanCode());
        }
        return $this->shortCode;
    }    

    /**
     * Преобразует rawCode:
     * - если есть кириллица — переводит в ANSI латиницу (по маппингу)
     * - добавляет символы GS (\x1D), если нужно
     */
    private function normalizeCode(string $code): string
    {
        $code = $this->convertCyrillicToLatin($code);

        // Проверить, есть ли GS (\x1D), если нет — добавить по правилам
        if (strpos($code, "\x1D") === false) {
            // ТУТ ТВОЯ ЛОГИКА ДОБАВЛЕНИЯ GS (пример — после 14-го символа):
            $code = substr($code, 0, 14) . "\x1D" . substr($code, 14);
        }

        return $code;
    }

    /**
     * Преобразует код: кириллицу -> латиницу (ANSI mapping)
     */
    public function convertCyrillicToLatin(string $code): string
    {
        $map = [
            'а' => 'f', 'б' => 'g', 'в' => 'h', 'г' => 'i', 'д' => 'j', 'е' => 'k', 'ё' => 'l', 'ж' => 'm', 'з' => 'n', 'и' => 'o', 'й' => 'p', 'к' => 'q', 'л' => 'r', 'м' => 's', 'н' => 't', 'о' => 'u', 'п' => 'v', 'р' => 'w', 'с' => 'x', 'т' => 'y', 'у' => 'z', 'ф' => 'a', 'х' => 'b', 'ц' => 'c', 'ч' => 'd', 'ш' => 'e', 'щ' => 'f', 'ъ' => 'g', 'ы' => 'h', 'ь' => 'i', 'э' => 'j', 'ю' => 'k', 'я' => 'l',
            'А' => 'F', 'Б' => 'G', 'В' => 'H', 'Г' => 'I', 'Д' => 'J', 'Е' => 'K', 'Ё' => 'L', 'Ж' => 'M', 'З' => 'N', 'И' => 'O', 'Й' => 'P', 'К' => 'Q', 'Л' => 'R', 'М' => 'S', 'Н' => 'T', 'О' => 'U', 'П' => 'V', 'Р' => 'W', 'С' => 'X', 'Т' => 'Y', 'У' => 'Z', 'Ф' => 'A', 'Х' => 'B', 'Ц' => 'C', 'Ч' => 'D', 'Ш' => 'E', 'Щ' => 'F', 'Ъ' => 'G', 'Ы' => 'H', 'Ь' => 'I', 'Э' => 'J', 'Ю' => 'K', 'Я' => 'L',
            '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9',
            ' ' => ' ',
            '!' => '!', '@' => '@', '#' => '#', '$' => '$', '%' => '%', '^' => '^', '&' => '&', '*' => '*', '(' => '(', ')' => ')', '_' => '_', '+' => '+', '=' => '=', '`' => '`', '~' => '~', '|' => '|', '\\' => '\\', '/' => '/', '?' => '?', ':' => ':', ';' => ';', '.' => '.', ',' => ',', '<' => '<', '>' => '>', '"' => '"', "'" => "'",
            '№' => '№',
            '–' => '-',
            '—' => '-',
            '«' => '"', '»' => '"',
        ];
        return strtr($code, $map);
    }    

    /**
     * Вырезает криптохвост (оставляет GTIN+серийник, пример: первые 22 символа cleanCode)
     * Подкорректируй под свой реальный формат!
     */
    private function extractShortCode(string $code): string
    {
        // Пример: для DataMatrix GS1 это первые 22 символа (GTIN+Serial)
        // Если твой формат другой — измени
        return substr($code, 0, 22);
    }
}