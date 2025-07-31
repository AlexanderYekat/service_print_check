<?php

namespace App\Domain\Model;

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
     * - добавляет символы GS (\x1D), если нужно по стандарту GS1
     */
    private function normalizeCode(string $code): string
    {
        // 1. Сначала переводим кириллицу в латиницу если есть
        $code = $this->convertCyrillicToLatin($code);
        
        // 2. Удаляем пробелы и лишние символы
        $code = preg_replace('/\s+/', '', $code);
        
        // 3. Проверяем и добавляем GS символы согласно стандарту GS1
        $code = $this->addGsSymbolsIfNeeded($code);

        return $code;
    }
    
    /**
     * Добавляет GS символы (\x1D) согласно стандарту GS1 DataMatrix
     * GS обычно ставится после GTIN (14 символов) и после серийного номера
     */
    private function addGsSymbolsIfNeeded(string $code): string
    {
        // Если уже есть GS символы, возвращаем как есть
        if (strpos($code, "\x1D") !== false) {
            return $code;
        }
        
        // Для стандартного DataMatrix кода маркировки:
        // Позиции 1-2: Идентификатор применения (01)
        // Позиции 3-16: GTIN (14 цифр)
        // Позиции 17-18: Идентификатор применения (21)
        // Остальное: Серийный номер + криптохвост
        
        if (strlen($code) > 16) {
            // Проверяем, что начинается с 01 (GTIN)
            if (substr($code, 0, 2) === '01') {
                // Добавляем GS после GTIN (позиция 16)
                $result = substr($code, 0, 16) . "\x1D" . substr($code, 16);
                
                // Если есть серийный номер (21), добавляем GS после него
                if (strlen($code) > 20 && substr($code, 16, 2) === '21') {
                    // Ищем конец серийного номера (обычно фиксированная длина или до следующего AI)
                    // Для простоты добавляем после позиции 29 (AI 21 + до 13 символов серийника)
                    if (strlen($result) > 29) {
                        $result = substr($result, 0, 29) . "\x1D" . substr($result, 29);
                    }
                }
                
                return $result;
            }
        }
        
        // Если не подходит под стандартный формат, возвращаем без изменений
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
     * Извлекает короткий код без криптохвоста
     * Оставляет только GTIN + серийный номер (до последнего GS символа)
     */
    private function extractShortCode(string $code): string
    {
        // Находим последний GS символ - после него обычно идет криптохвост
        $lastGsPos = strrpos($code, "\x1D");
        
        if ($lastGsPos !== false && $lastGsPos > 16) {
            // Возвращаем все до последнего GS (включая GTIN и серийный номер)
            return substr($code, 0, $lastGsPos);
        }
        
        // Если GS нет или он в начале, используем фиксированную длину
        // GTIN (14 символов после AI "01") + AI "21" + серийник (до 13 символов)
        // Итого максимум: 2 + 14 + 2 + 13 = 31 символ
        return substr($code, 0, min(31, strlen($code)));
    }
    
    /**
     * Сравнивает два кода маркировки по cleanCode (нормализованному виду)
     */
    public function equals(MarkingCode $other): bool
    {
        return $this->getCleanCode() === $other->getCleanCode();
    }
    
    /**
     * Сравнивает два кода маркировки по shortCode (без криптохвоста)
     */
    public function equalsShort(MarkingCode $other): bool
    {
        return $this->getShortCode() === $other->getShortCode();
    }
    
    /**
     * Возвращает строковое представление для отладки
     */
    public function __toString(): string
    {
        return $this->getRawCode();
    }
}