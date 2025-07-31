<?php

require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../model/Check.php';
require_once __DIR__ . '/../model/CheckItem.php';
require_once __DIR__ . '/MarkCheckRegistry.php';

/**
 * Исключение при ошибке сопоставления маркировок
 */
class MarkMatchingException extends Exception
{
    private ?MarkingCode $markingCode;
    
    public function __construct(string $message, ?MarkingCode $markingCode = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->markingCode = $markingCode;
    }
    
    public function getMarkingCode(): ?MarkingCode
    {
        return $this->markingCode;
    }
}

/**
 * Сервис для сопоставления маркировок в чеке с результатами проверок
 * Реализует алгоритм поиска по cleanCode согласно ТЗ
 */
class MarkMatchingService
{
    private MarkCheckRegistry $registry;

    public function __construct(MarkCheckRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Сопоставляет все маркированные позиции в чеке с результатами проверок
     * Возвращает массив: cleanCode => данные проверки
     * 
     * @param Check $check
     * @param bool $requireFullChecks Требовать ли наличие проверок permit+ecr для всех марок
     * @return array<string, array>
     * @throws MarkMatchingException
     */
    public function matchCheckItems(Check $check, bool $requireFullChecks = false): array
    {
        $matches = [];
        $unmarkedItems = [];
        
        foreach ($check->getItems() as $item) {
            if (!$item->hasMarkingCode()) {
                continue; // Пропускаем немаркированные товары
            }
            
            $markingCode = $item->getMarkingCode();
            $cleanCode = $markingCode->getCleanCode();
            
            // Получаем все доступные проверки для марки
            $checkResults = $this->registry->getAllCheckResults($markingCode);
            
            // Проверяем требования к полноте проверок
            if ($requireFullChecks && !$this->registry->isFullyChecked($markingCode)) {
                $missingChecks = [];
                if (!$checkResults['permit']) {
                    $missingChecks[] = 'разрешительный режим';
                }
                if (!$checkResults['ecr']) {
                    $missingChecks[] = 'ККТ';
                }
                
                throw new MarkMatchingException(
                    sprintf(
                        'Марка "%s" в товаре "%s" не прошла все необходимые проверки. Отсутствуют: %s',
                        $markingCode->getRawCode(),
                        $item->getName(),
                        implode(', ', $missingChecks)
                    ),
                    $markingCode
                );
            }
            
            // Если нет никаких проверок вообще
            if (!$checkResults['permit'] && !$checkResults['ecr']) {
                $unmarkedItems[] = [
                    'item' => $item,
                    'marking_code' => $markingCode,
                    'clean_code' => $cleanCode
                ];
                continue;
            }
            
            $matches[$cleanCode] = [
                'item' => $item,
                'marking_code' => $markingCode,
                'permit_check' => $checkResults['permit'],
                'ecr_check' => $checkResults['ecr'],
                'is_fully_checked' => $this->registry->isFullyChecked($markingCode)
            ];
        }
        
        // Если есть непроверенные марки и режим строгий
        if (!empty($unmarkedItems) && $requireFullChecks) {
            $firstUnmarked = $unmarkedItems[0];
            throw new MarkMatchingException(
                sprintf(
                    'Марка "%s" в товаре "%s" не была проверена ранее',
                    $firstUnmarked['marking_code']->getRawCode(),
                    $firstUnmarked['item']->getName()
                ),
                $firstUnmarked['marking_code']
            );
        }
        
        return $matches;
    }

    /**
     * Получает результаты проверок для конкретной марки из чека
     * 
     * @param MarkingCode $markingCode
     * @return array|null Данные проверки или null если не найдено
     */
    public function getCheckResultsForMark(MarkingCode $markingCode): ?array
    {
        $checkResults = $this->registry->getAllCheckResults($markingCode);
        
        if (!$checkResults['permit'] && !$checkResults['ecr']) {
            return null;
        }
        
        return [
            'marking_code' => $markingCode,
            'permit_check' => $checkResults['permit'],
            'ecr_check' => $checkResults['ecr'],
            'is_fully_checked' => $this->registry->isFullyChecked($markingCode)
        ];
    }

    /**
     * Проверяет, все ли маркированные товары в чеке имеют результаты проверок
     */
    public function areAllMarksChecked(Check $check): bool
    {
        foreach ($check->getItems() as $item) {
            if (!$item->hasMarkingCode()) {
                continue;
            }
            
            $markingCode = $item->getMarkingCode();
            $checkResults = $this->registry->getAllCheckResults($markingCode);
            
            // Требуем хотя бы одну проверку (permit или ecr)
            if (!$checkResults['permit'] && !$checkResults['ecr']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Возвращает статистику сопоставления для чека
     */
    public function getMatchingStats(Check $check): array
    {
        $totalItems = count($check->getItems());
        $markedItems = 0;
        $checkedMarks = 0;
        $fullyCheckedMarks = 0;
        $uncheckMsrks = [];
        
        foreach ($check->getItems() as $item) {
            if (!$item->hasMarkingCode()) {
                continue;
            }
            
            $markedItems++;
            $markingCode = $item->getMarkingCode();
            $checkResults = $this->registry->getAllCheckResults($markingCode);
            
            if ($checkResults['permit'] || $checkResults['ecr']) {
                $checkedMarks++;
                
                if ($this->registry->isFullyChecked($markingCode)) {
                    $fullyCheckedMarks++;
                }
            } else {
                $uncheckMsrks[] = [
                    'item_name' => $item->getName(),
                    'raw_code' => $markingCode->getRawCode(),
                    'clean_code' => $markingCode->getCleanCode()
                ];
            }
        }
        
        return [
            'total_items' => $totalItems,
            'marked_items' => $markedItems,
            'checked_marks' => $checkedMarks,
            'fully_checked_marks' => $fullyCheckedMarks,
            'unchecked_marks' => $uncheckMsrks,
            'check_coverage_percent' => $markedItems > 0 ? round(($checkedMarks / $markedItems) * 100, 1) : 100
        ];
    }

    /**
     * Поиск марки в чеке по разным форматам
     * Позволяет найти марку даже если формат в чеке отличается от проверенного
     */
    public function findMarkInCheck(Check $check, string $rawMarkCode): ?array
    {
        $searchCode = new MarkingCode($rawMarkCode);
        $searchCleanCode = $searchCode->getCleanCode();
        
        foreach ($check->getItems() as $item) {
            if (!$item->hasMarkingCode()) {
                continue;
            }
            
            $itemMark = $item->getMarkingCode();
            
            // Сравниваем по cleanCode
            if ($itemMark->getCleanCode() === $searchCleanCode) {
                return [
                    'item' => $item,
                    'marking_code' => $itemMark,
                    'check_results' => $this->getCheckResultsForMark($itemMark)
                ];
            }
        }
        
        return null;
    }
}