# Форматы DataMatrix для категорий маркированной продукции

## Молочные продукты

**Структура кода DataMatrix:**

**Формат:** 01 + 14 цифр (GTIN) + 21 + 6 символов (серийный номер) + 93 + 4 символа (код проверки)

**Компоненты:**
- **AI=01:** 14 цифр — код товара (GTIN)
- **AI=21:** 6 символов (цифры, латинские буквы, спецсимволы) — индивидуальный серийный номер упаковки + разделитель FNC1 (ASCII 29)
- **AI=93:** 4 символа — код проверки
- **Опционально AI=3103:** 6 символов — вес продукции в килограммах (при различающемся весе)

## Газированные напитки, соки, компоты, упакованная вода

**Структура кода DataMatrix:**[7][8][9][10]

**Формат:** 01 + 14 цифр (GTIN) + 21 + 1 цифра (код страны) + 12 символов (серийный номер) + 93 + 4 символа (код проверки)

**Компоненты:**[9]
- **FNC1** (ASCII 232) — признак символики GS1 DataMatrix
- **AI=01:** 14 цифр — код товара (GTIN)
- **AI=21:** 13 символов (1 цифра кода страны + 12 символов индивидуального серийного номера)
- **AI=93:** 4 символа — код проверки

## Консервы (рыбные, мясные, овощные)

**Структура кода DataMatrix:**

**Полная структура (4 блока):**[13]
- **14 знаков** — код товара (GTIN)
- **6 знаков** — идентификатор государства и индивидуальный серийный номер упаковки
- **4 знака** — идентификатор ключа проверки
- **44 знака** — код проверки (криптографический «хвост»)
**Формат:** 01 + 14 цифр (GTIN) + 21 + 1 цифра (код страны) + 5 символов (серийный номер) + 91 + 4 символа (код проверки) 92 + 44 символа (код проверки (криптографический «хвост»))

**Упрощенная структура (3 блока):**[13]
- **14 знаков** — код товара (GTIN)  
- **6 знаков** — идентификатор государства и индивидуальный серийный номер упаковки
- **4 знака** — код проверки

**Формат:** 01 + 14 цифр (GTIN) + 21 + 1 цифра (код страны) + 5 символов (серийный номер) + 91 + 4 символа (код проверки)[14]

**Правило применения:** Полный код используется при нанесении на банку или крышку, упрощенный — при ограниченном пространстве[13]

## Растительные масла

**Структура кода DataMatrix:**[15][16][17][18]

**Компоненты:**
- **GTIN** — 14 цифр (код товара)
- **Серийный номер** — 13 символов 
- **Код проверки** — обеспечивает проверку оригинальности и защиту от копирования

**Особенности:** Код включает код товара (GTIN), серийный номер и проверочный код, отличается от QR-кодов и обычных штрих-кодов[16]

**Ограничение:** На один GTIN — не более 150 000 кодов DataMatrix[19]

## Морепродукты (икра)

**Структура кода DataMatrix:**[20][21][22][23]

**Формат:** 01 + 14 цифр (GTIN) + 21 + 1 цифра (код страны) + 5 символов (серийный номер) + 91 + 4 символа (код проверки)[23]

**Компоненты:**
- **GTIN** — 14 цифр (идентификатор позиции в международном справочнике GS1)
- **Серийный номер** — включает код ТН ВЭД ЕАЭС
- **Ключ и код проверки** — для проверки подлинности и защиты от копирования

**Применение:** Коды наносятся на жестяные, стеклянные, пластиковые банки и контейнеры, коробки[21]

**Общие требования для всех категорий:**
- Использование символики ECC 200 с FNC1 как признаком символики
- Соответствие стандарту ГОСТ Р ИСО/МЭК 16022-2008
- Печать черным цветом на белом фоне для оптимального считывания
- Применение идентификаторов GS1 (AI) для структурирования данных

[1](https://getmark.ru/blog/o-markirovke/kod-markirovki-molochnoj-produkcii-chto-soderzhit-rasshifrovka/)
[2](http://irbitskoemo.ru/novosti/zagolovok_/obyazatelnaya-markirovka-molochnoy-produktsii-s-2021-goda_20211014/)
[3](https://scanport.ru/blog/markirovka-molochnoj-produkczii-uchastniki-sroki-pravila-oborudovanie/)
[4](https://selkhozcentr65.ru/faq/markirovka-molochnoj-produkcii/struktura-koda-markirovki/)
[5](https://markirovka.ru/knowledge/tovarnye-gruppy/molochnaya-produkciya/sostav-koda-markirovki-moloko)
[6](https://www.cleverence.ru/files/54539/whitepaper-track-&-trace-for-dairy-gs1-datamatrix-possible-errors.pdf)
[7](https://kassaofd.ru/blog/obyazatelnaya-markirovka-sok-gazirovki-bezalk-napitkov)
[8](https://portkkm.ru/overview/markirovka-bezalkogolnykh-napitkov/)
[9](https://markirovka.ru/knowledge/tovarnye-gruppy/bezalcohol-napitki/sostav-koda-markirovki-ba-napitki)
[10](https://xn--80ajghhoc2aj1c8b.xn--p1ai/business/projects/beverages/)
[11](https://www.moysklad.ru/poleznoe/markirovka-tovarov/markirovka-konservov/)
[12](https://znak.store/markirovka/pishhevaya-promyshlennost/markirovka-konservov-2)
[13](https://rbs-id.ru/markirovka-konservov)
[14](https://markirovka.ru/knowledge/tovarnye-gruppy/otdelnye-vidy-konservirovannyx-produktov/sostav-koda-markirovki-konservy)
[15](https://kassaofd.ru/blog/markirovka-rastitelnyh-masel)
[16](https://fsrar.su/knowledge-base/chestnyy-znak/770/)
[17](https://www.moysklad.ru/poleznoe/markirovka-tovarov/markirovka-rastitelnyh-masel/)
[18](https://kontur.ru/markirovka/spravka/51513-markirovka_rastitelnogo_masla)
[19](https://scanport.ru/blog/markirovka-pishhevyh-rastitelnyh-masel/)
[20](https://26.rospotrebnadzor.ru/press-center/pr/12230/)
[21](https://www.moysklad.ru/poleznoe/markirovka-tovarov/markirovka-ikry/)
[22](https://online-kassa.ru/blog/markirovka-ikry-lososevyh-i-osetrovyh-kak-rabotat-po-chestnomu/)
[23](https://markirovka.ru/knowledge/tovarnye-gruppy/ikra-lasosevyh-ryb/sostav-koda-markirovki-ikra)
[24](https://taxcom-kassa.ru/blog/info/markirovka/markirovka-bezalkogolnyh-napitkov-v-rossii-2025/)
[25](https://markirovka.ru/knowledge/fast_start/start/gs1-datamatrix-shtrikhkod-markirovki-kak-vyglyadit-i-rabotaet-na-chto-obratit-vnimanie)
[26](https://getmark.ru/blog/o-markirovke/instrukciya-po-sozdaniyu-ehtiketki-datamatrix-po-svoemu-shablonu/)
[27](https://markirovka.ru/knowledge/tovarnye-gruppy/otdelnye-vidy-konservirovannyx-produktov/kakim-dolzhen-byt-datamatrix-trebovaniya-k-preobrazovaniyu-i-kachestvu-naneseniya-konservy)
[28](https://markirovka.ru/knowledge/fast_start/start/sostav-koda-markirovki-molochnoy-produktsii)
[29](https://markirovka.ru/knowledge/tovarnye-gruppy/bezalkogolnoe-pivo/kakim-dolzhen-byt-datamatrix-trebovaniya-k-preobrazovaniyu-i-kachestvu-naneseniya-ba-pivo)
[30](https://fs.rbsoft.ru/ServerKKM/doc/%D0%A1%D1%82%D1%80%D1%83%D0%BA%D1%82%D1%83%D1%80%D0%B0%20DataMatrix.pdf)
[31](https://xn--80ajghhoc2aj1c8b.xn--p1ai/business/projects/dairy/registration/)
[32](https://www.moysklad.ru/poleznoe/markirovka-tovarov/markirovka-bezalkogolnykh-napitkov/)
[33](https://taxcom.ru/baza-znaniy/markirovka-tovarov/novosti/markirovka-krasnoy-i-chyernoy-ikry-s-maya-obyazatelna/)
[34](https://customs.gov.ru/techdoc/markirovka-gismt/molochnaya-produkcziya/document/243434)
[35](https://markirovka.ru/community/markirovka-pishchevykh-rastitelnykh-masel/instruktsiya-dlya-nachinayushchikh-rabota-s-markirovkoy-rastitelnykh-masel)
[36](https://xn--80ajghhoc2aj1c8b.xn--p1ai/business/projects/caviar/faq/)
[37](https://flexo.ru/data-matrix-kody/246-markirovka-molochnoj-produktsii)
[38](https://prom-mact.ru/services/markirovka/rastitelnogo-masla/)
[39](https://smart-vision.pro/otrasli/markirovka-ikry-osetrovyh-i-lososevyh-ryb/)