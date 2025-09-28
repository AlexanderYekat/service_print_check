/**
 * BarcodeScanner - Класс для работы со сканерами штрих-кодов через Web Serial API
 * 
 * @author Your Name
 * @version 1.0.0
 * @license MIT
 */

/**
 * Класс для работы со сканерами штрих-кодов
 * Поддерживает подключение к USB-сканерам через Web Serial API
 */
class BarcodeScanner {
  /**
   * Создает экземпляр BarcodeScanner
   * @param {Object} options - Настройки сканера
   * @param {Function} options.onScan - Callback при сканировании кода
   * @param {Function} options.onConnect - Callback при подключении
   * @param {Function} options.onDisconnect - Callback при отключении
   * @param {Function} options.onError - Callback при ошибке
   * @param {string} options.terminator - Терминатор для разделения кодов (по умолчанию '\t')
   * @param {number} options.baudRate - Скорость передачи данных (по умолчанию 9600)
   */
  constructor(options = {}) {
    this.port = null;
    this.reader = null;
    this.keepReading = false;
    this.isConnected = false;
    
    // Callbacks
    this.onScan = options.onScan || console.log;
    this.onConnect = options.onConnect || (() => {});
    this.onDisconnect = options.onDisconnect || (() => {});
    this.onError = options.onError || console.error;
    
    // Настройки
    this.terminator = this.parseTerminator(options.terminator || '\t');
    this.baudRate = options.baudRate || 9600;
  }

  /**
   * Подключается к сканеру
   * @returns {Promise<void>}
   * @throws {Error} Если Web Serial API не поддерживается или произошла ошибка подключения
   */
  async connect() {
    try {
      // Проверяем поддержку Web Serial API
      if (!('serial' in navigator)) {
        throw new Error('Web Serial API не поддерживается в этом браузере. Используйте Chrome/Edge/Opera версии 89+');
      }
      
      // Запрашиваем порт у пользователя
      this.port = await navigator.serial.requestPort();
      
      // Открываем порт с настройками
      await this.port.open({ 
        baudRate: this.baudRate, 
        dataBits: 8, 
        stopBits: 1, 
        parity: 'none' 
      });
      
      // Устанавливаем флаги
      this.keepReading = true;
      this.isConnected = true;
      
      // Вызываем callback подключения
      this.onConnect();
      
      // Запускаем чтение данных
      this.startReading();
      
    } catch (error) {
      this.onError('Ошибка подключения:', error.message);
      throw error;
    }
  }

  /**
   * Отключается от сканера
   * @returns {Promise<void>}
   * @throws {Error} Если произошла ошибка отключения
   */
  async disconnect() {
    try {
      // Останавливаем чтение
      this.keepReading = false;
      
      // Отменяем reader если он существует
      if (this.reader) {
        await this.reader.cancel().catch(e => 
          this.onError('Ошибка отмены reader:', e.message)
        );
      }
      
      // Небольшая пауза для завершения цикла чтения
      await new Promise(resolve => setTimeout(resolve, 100));
      
      // Закрываем порт
      if (this.port) {
        await this.port.close();
        this.port = null;
      }
      
      // Сбрасываем флаги
      this.isConnected = false;
      
      // Вызываем callback отключения
      this.onDisconnect();
      
    } catch (error) {
      this.onError('Ошибка отключения:', error.message);
      throw error;
    }
  }

  /**
   * Запускает чтение данных от сканера
   * @private
   */
  async startReading() {
    // Создаем TextDecoder для преобразования байтов в текст
    const textDecoder = new TextDecoderStream();
    this.port.readable.pipeTo(textDecoder.writable);
    
    // Создаем TransformStream для разделения данных по терминатору
    this.reader = textDecoder.readable.pipeThrough(
      new TransformStream(new SuffixTransformer(this.terminator))
    ).getReader();

    try {
      // Основной цикл чтения
      while (this.keepReading) {
        const { value, done } = await this.reader.read();
        
        // Если поток завершен
        if (done) {
          this.log('Цикл чтения завершён');
          break;
        }
        
        // Если получены данные - обрабатываем их
        if (value && value.trim()) {
          this.onScan(value.trim());
        }
      }
    } catch (error) {
      this.onError('Ошибка чтения:', error.message);
    } finally {
      // Освобождаем reader в любом случае
      if (this.reader) {
        this.reader.releaseLock();
        this.reader = null;
      }
    }
  }

  /**
   * Проверяет, подключен ли сканер
   * @returns {boolean}
   */
  isScannerConnected() {
    return this.isConnected;
  }

  /**
   * Получает информацию о порте
   * @returns {Object|null} Информация о порте или null если не подключен
   */
  getPortInfo() {
    return this.port ? this.port.getInfo() : null;
  }

  /**
   * Парсит терминатор из строки с escape-последовательностями
   * @param {string} terminator - Терминатор в виде строки
   * @returns {string} - Обработанный терминатор
   * @private
   */
  parseTerminator(terminator) {
    if (typeof terminator !== 'string') {
      return '\t';
    }
    
    // Обрабатываем escape-последовательности
    return terminator
      .replace(/\\t/g, '\t')      // табуляция
      .replace(/\\r\\n/g, '\r\n') // Windows CRLF
      .replace(/\\n/g, '\n')      // Unix LF
      .replace(/\\r/g, '\r')      // Mac CR
      .replace(/\\0/g, '\0')      // Null символ
      .replace(/\\\\/g, '\\');    // обратный слеш
  }

  /**
   * Логирование (можно переопределить)
   * @param {string} message - Сообщение для логирования
   * @private
   */
  log(message) {
    console.log(`[BarcodeScanner] ${message}`);
  }
}

/**
 * TransformStream для разделения данных по терминатору
 * Используется для корректной обработки данных от сканера
 */
class SuffixTransformer {
  /**
   * Создает экземпляр SuffixTransformer
   * @param {string} suffix - Терминатор для разделения данных
   */
  constructor(suffix = '\t') {
    this.suffix = suffix;
    this.buffer = '';
  }

  /**
   * Обрабатывает входящие данные
   * @param {string} chunk - Часть данных
   * @param {TransformStreamDefaultController} controller - Контроллер потока
   */
  transform(chunk, controller) {
    this.buffer += chunk;
    let index;
    
    // Ищем терминатор в буфере
    while ((index = this.buffer.indexOf(this.suffix)) !== -1) {
      // Извлекаем полные данные до терминатора
      const fullData = this.buffer.slice(0, index);
      controller.enqueue(fullData);
      
      // Удаляем обработанные данные из буфера
      this.buffer = this.buffer.slice(index + this.suffix.length);
    }
  }

  /**
   * Обрабатывает оставшиеся данные при закрытии потока
   * @param {TransformStreamDefaultController} controller - Контроллер потока
   */
  flush(controller) {
    if (this.buffer) {
      controller.enqueue(this.buffer);
    }
  }
}

// Экспорт для использования в модулях
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { BarcodeScanner, SuffixTransformer };
}

// Глобальный экспорт для браузера
if (typeof window !== 'undefined') {
  window.BarcodeScanner = BarcodeScanner;
  window.SuffixTransformer = SuffixTransformer;
}
