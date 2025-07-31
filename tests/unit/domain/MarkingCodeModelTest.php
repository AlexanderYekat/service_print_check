<?php
/**
 * Unit-тесты для доменной модели MarkingCode
 * 
 * Тестирует бизнес-логику обработки кодов маркировки:
 * - Нормализация кодов (латиница, GS символы)
 * - Преобразование кириллицы в латиницу
 * - Извлечение коротких кодов (без криптохвоста)
 * - Сравнение кодов
 * - Кэширование форматов
 */

require_once __DIR__ . '/../../../src/domain/model/MarkingCode.php';

class MarkingCodeModelTest
{
    public function testBasicCodeCreation(): bool
    {
        try {
            $rawCode = "01046356523123451521ABCDEF123456789";
            $markingCode = new MarkingCode($rawCode);
            
            if ($markingCode->getRawCode() !== $rawCode) {
                throw new Exception('Raw код должен сохраняться без изменений');
            }
            
            if ($markingCode->__toString() !== $rawCode) {
                throw new Exception('toString должен возвращать raw код');
            }
            
            echo "✅ Создание базового кода: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание базового кода: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCodeWithSpacesNormalization(): bool
    {
        try {
            // Код с пробелами должен очищаться
            $rawCode = "01 04635652312345 15 21 ABC DEF 123456789";
            $markingCode = new MarkingCode($rawCode);
            
            $cleanCode = $markingCode->getCleanCode();
            if (strpos($cleanCode, ' ') !== false) {
                throw new Exception('Очищенный код не должен содержать пробелы');
            }
            
            // Проверяем, что пробелы удалены (не проверяем точное содержание, так как могут добавляться GS символы)
            $withoutSpaces = str_replace(' ', '', $rawCode);
            if (strlen($cleanCode) < strlen($withoutSpaces)) {
                throw new Exception("Очищенный код короче ожидаемого: {$cleanCode}");
            }
            
            echo "✅ Нормализация пробелов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Нормализация пробелов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCyrillicToLatinConversion(): bool
    {
        try {
            // Код с кириллицей
            $rawCode = "01046356523123451521АБВГДЕабвгде123456";
            $markingCode = new MarkingCode($rawCode);
            
            $cleanCode = $markingCode->getCleanCode();
            
            // Проверяем, что кириллица конвертирована
            if (preg_match('/[а-яё]/iu', $cleanCode)) {
                throw new Exception("Очищенный код не должен содержать кириллицу: {$cleanCode}");
            }
            
            // Проверяем конкретные символы
            $converted = $markingCode->convertCyrillicToLatin("АБВГДЕ");
            if ($converted !== "FGHIJK") {
                throw new Exception("Неправильная конвертация: {$converted}");
            }
            
            echo "✅ Конвертация кириллицы: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Конвертация кириллицы: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGsSymbolsAddition(): bool
    {
        try {
            // Стандартный код без GS символов
            $rawCode = "0104635652312345152123456789ABCDEF";
            $markingCode = new MarkingCode($rawCode);
            
            $cleanCode = $markingCode->getCleanCode();
            
            // Должны быть добавлены GS символы
            if (strpos($cleanCode, "\x1D") === false) {
                throw new Exception("GS символы не добавлены");
            }
            
            // Проверяем позицию первого GS (после GTIN)
            $firstGsPos = strpos($cleanCode, "\x1D");
            if ($firstGsPos !== 16) {
                throw new Exception("Первый GS символ должен быть на позиции 16, найден на: {$firstGsPos}");
            }
            
            echo "✅ Добавление GS символов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Добавление GS символов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCodeWithExistingGsSymbols(): bool
    {
        try {
            // Код уже с GS символами не должен изменяться
            $rawCode = "0104635652312345\x1D152123456789\x1DABCDEF";
            $markingCode = new MarkingCode($rawCode);
            
            $cleanCode = $markingCode->getCleanCode();
            
            // Количество GS символов не должно увеличиться
            $gsCount = substr_count($cleanCode, "\x1D");
            $originalGsCount = substr_count($rawCode, "\x1D");
            
            if ($gsCount !== $originalGsCount) {
                throw new Exception("Количество GS символов изменилось: {$originalGsCount} -> {$gsCount}");
            }
            
            echo "✅ Сохранение существующих GS: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сохранение существующих GS: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testShortCodeExtraction(): bool
    {
        try {
            // Код с криптохвостом
            $rawCode = "0104635652312345\x1D152123456789\x1D93ABCDEF123456789";
            $markingCode = new MarkingCode($rawCode);
            
            $shortCode = $markingCode->getShortCode();
            
            // Короткий код не должен содержать криптохвост (после последнего GS)
            if (strpos($shortCode, "93ABCDEF") !== false) {
                throw new Exception("Короткий код содержит криптохвост: {$shortCode}");
            }
            
            // Должен содержать GTIN и серийный номер
            if (strpos($shortCode, "0104635652312345") === false || 
                strpos($shortCode, "152123456789") === false) {
                throw new Exception("Короткий код должен содержать GTIN и серийный номер: {$shortCode}");
            }
            
            echo "✅ Извлечение короткого кода: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Извлечение короткого кода: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testBase64Encoding(): bool
    {
        try {
            $rawCode = "0104635652312345152123456789";
            $markingCode = new MarkingCode($rawCode);
            
            $base64Code = $markingCode->getBase64Code();
            
            // Проверяем, что это валидный base64
            $decoded = base64_decode($base64Code, true);
            if ($decoded === false) {
                throw new Exception("Некорректная base64 кодировка: {$base64Code}");
            }
            
            // Проверяем, что декодирование дает cleanCode
            $expectedClean = $markingCode->getCleanCode();
            if ($decoded !== $expectedClean) {
                throw new Exception("Base64 декодирование не совпадает с cleanCode");
            }
            
            echo "✅ Base64 кодирование: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Base64 кодирование: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCodeComparison(): bool
    {
        try {
            // Два одинаковых кода в разных форматах
            $code1 = new MarkingCode("01 04635652312345 15 21 ABCDEF123");
            $code2 = new MarkingCode("0104635652312345\x1D1521ABCDEF123"); // С GS
            
            // equals должен сравнивать по cleanCode
            if (!$code1->equals($code2)) {
                throw new Exception("Коды должны быть равны после нормализации");
            }
            
            // Разные коды не должны быть равны
            $differentCode = new MarkingCode("0104635652312345152199999999");
            if ($code1->equals($differentCode)) {
                throw new Exception("Разные коды не должны быть равны");
            }
            
            echo "✅ Сравнение кодов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сравнение кодов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testShortCodeComparison(): bool
    {
        try {
            // Коды с одинаковым началом, но разными криптохвостами
            $code1 = new MarkingCode("0104635652312345\x1D152123456789\x1D93ABC123");
            $code2 = new MarkingCode("0104635652312345\x1D152123456789\x1D93XYZ789");
            
            // equalsShort должен игнорировать криптохвост
            if (!$code1->equalsShort($code2)) {
                throw new Exception("Коды должны быть равны по shortCode (без криптохвоста)");
            }
            
            // Но полное сравнение должно показать различие
            if ($code1->equals($code2)) {
                throw new Exception("Полные коды должны различаться из-за криптохвоста");
            }
            
            echo "✅ Сравнение коротких кодов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сравнение коротких кодов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCodeCaching(): bool
    {
        try {
            $rawCode = "01 04635652312345 15 21 АБВГДЕабвгде123456";
            $markingCode = new MarkingCode($rawCode);
            
            // Первый вызов должен создать кэш
            $cleanCode1 = $markingCode->getCleanCode();
            $shortCode1 = $markingCode->getShortCode();
            $base64Code1 = $markingCode->getBase64Code();
            
            // Повторные вызовы должны возвращать те же результаты (из кэша)
            $cleanCode2 = $markingCode->getCleanCode();
            $shortCode2 = $markingCode->getShortCode();
            $base64Code2 = $markingCode->getBase64Code();
            
            if ($cleanCode1 !== $cleanCode2 || 
                $shortCode1 !== $shortCode2 || 
                $base64Code1 !== $base64Code2) {
                throw new Exception("Кэширование работает некорректно");
            }
            
            echo "✅ Кэширование форматов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Кэширование форматов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testInvalidCodeHandling(): bool
    {
        try {
            // Слишком короткий код
            $shortCode = new MarkingCode("123");
            $cleanCode = $shortCode->getCleanCode();
            
            // Не должно вызывать ошибок, просто возвращает как есть
            if ($cleanCode !== "123") {
                throw new Exception("Короткий код должен возвращаться без изменений");
            }
            
            // Пустой код
            $emptyCode = new MarkingCode("");
            $emptyClean = $emptyCode->getCleanCode();
            
            if ($emptyClean !== "") {
                throw new Exception("Пустой код должен остаться пустым");
            }
            
            echo "✅ Обработка некорректных кодов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка некорректных кодов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов MarkingCode...\n\n";
        
        $tests = [
            'testBasicCodeCreation',
            'testCodeWithSpacesNormalization',
            'testCyrillicToLatinConversion', 
            'testGsSymbolsAddition',
            'testCodeWithExistingGsSymbols',
            'testShortCodeExtraction',
            'testBase64Encoding',
            'testCodeComparison',
            'testShortCodeComparison',
            'testCodeCaching',
            'testInvalidCodeHandling'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n📊 Результат: {$passed}/{$total} тестов пройдено\n";
        
        return $passed === $total;
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new MarkingCodeModelTest();
    $result = $test->run();
    echo $result ? "✅ MarkingCodeModelTest PASSED\n" : "❌ MarkingCodeModelTest FAILED\n";
    exit($result ? 0 : 1);
}