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
    this.dataBuffer = ''; // Буфер для накопления данных от сканера
    
    // Callbacks
    this.onScan = options.onScan || console.log;
    this.onConnect = options.onConnect || (() => {});
    this.onDisconnect = options.onDisconnect || (() => {});
    this.onError = options.onError || console.error;
    
    // Настройки
    this.terminator = this.parseTerminator(options.terminator || '\t');
    this.baudRate = options.baudRate || 9600;
    
    // Настройки логирования
    this.debug = options.debug || false;
    
    // Настройки управления буфером
    this.maxBufferSize = options.maxBufferSize || 10240; // 10KB по умолчанию
    this.bufferTimeout = options.bufferTimeout || 300000; // 5 минут по умолчанию
    this.lastDataTime = Date.now(); // Время последнего получения данных
    this.bufferTimer = null; // Таймер для очистки буфера по таймауту
  }

  /**
   * Подключается к сканеру
   * @param {Object} options - Опции подключения
   * @param {Object} options.savedPortInfo - Информация о сохраненном порте {vendorId, productId}
   * @param {boolean} options.trySavedPortFirst - Пытаться ли сначала подключиться к сохраненному порту
   * @returns {Promise<void>}
   * @throws {Error} Если Web Serial API не поддерживается или произошла ошибка подключения
   */
  async connect(options = {}) {
    try {
      // Проверяем поддержку Web Serial API
      if (!('serial' in navigator)) {
        throw new Error('Web Serial API не поддерживается в этом браузере. Используйте Chrome/Edge/Opera версии 89+');
      }
      
      // Пытаемся подключиться к сохраненному порту
      if (options.trySavedPortFirst && options.savedPortInfo) {
        const savedPort = await this.tryConnectToSavedPort(options.savedPortInfo);
        if (savedPort) {
          this.log('Подключение к сохраненному порту успешно');
          this.setupPort(savedPort);
          return;
        }
        this.log('Сохраненный порт не найден, используем диалог выбора');
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
      
      // Настраиваем подключенный порт
      this.setupPort(this.port);
      
    } catch (error) {
      this.onError('Ошибка подключения:', error.message);
      throw error;
    }
  }

  /**
   * Пытается подключиться к сохраненному порту
   * @param {Object} savedPortInfo - Информация о сохраненном порте
   * @returns {Promise<SerialPort|null>} Найденный порт или null
   * @private
   */
  async tryConnectToSavedPort(savedPortInfo) {
    try {
      // Получаем список разрешенных портов
      const ports = await navigator.serial.getPorts();
      
      // Ищем порт по USB ID
      const savedPort = ports.find(port => {
        const info = port.getInfo();
        return info.usbVendorId === savedPortInfo.vendorId && 
               info.usbProductId === savedPortInfo.productId;
      });

      if (savedPort) {
        // Открываем найденный порт
        await savedPort.open({ 
          baudRate: this.baudRate, 
          dataBits: 8, 
          stopBits: 1, 
          parity: 'none' 
        });
        return savedPort;
      }
    } catch (error) {
      this.log(`Ошибка при подключении к сохраненному порту: ${error.message}`);
    }
    return null;
  }

  /**
   * Настраивает подключенный порт
   * @param {SerialPort} port - Подключенный порт
   * @private
   */
  setupPort(port) {
    this.port = port;
    
    // Устанавливаем флаги
    this.keepReading = true;
    this.isConnected = true;
    
    // Очищаем буфер данных при новом подключении
    this.dataBuffer = '';
    this.lastDataTime = Date.now();
    this.log('Буфер данных очищен при подключении');
    
    // Запускаем таймер для очистки буфера по таймауту
    this.startBufferTimer();
    
    // Вызываем callback подключения
    this.onConnect();
    
    // Запускаем чтение данных
    this.startReading();
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
      
      // Останавливаем таймер очистки буфера
      this.stopBufferTimer();
      
      // Очищаем буфер данных при отключении
      this.dataBuffer = '';
      this.log('Буфер данных очищен при отключении');
      
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
    
    // Получаем reader для чтения декодированных данных
    this.reader = textDecoder.readable.getReader();

    try {
      // Основной цикл чтения
      while (this.keepReading) {
        const { value, done } = await this.reader.read();
        
        // Если поток завершен
        if (done) {
          this.log('Цикл чтения завершён');
          break;
        }
        
        // Если получены данные - обрабатываем их через буфер
        if (value) {
          this.processIncomingData(value);
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
   * Обрабатывает входящие данные с буферизацией
   * @param {string} data - Входящие данные
   * @private
   */
  processIncomingData(data) {
    // Обновляем время последнего получения данных
    this.lastDataTime = Date.now();
    
    if (this.debug) {
      this.log(`Получены данные: "${data}" (размер: ${data.length})`);
      this.log(`Коды символов: [${Array.from(data).map(c => c.charCodeAt(0)).join(', ')}]`);
    }
    
    // Проверяем размер буфера перед добавлением новых данных
    if (this.dataBuffer.length + data.length > this.maxBufferSize) {
      this.log(`Буфер превышает максимальный размер (${this.maxBufferSize} байт), очищаем его`);
      this.dataBuffer = '';
    }
    
    // Добавляем новые данные в буфер
    this.dataBuffer += data;
    
    if (this.debug) {
      this.log(`Текущий буфер: "${this.dataBuffer}" (размер: ${this.dataBuffer.length}/${this.maxBufferSize})`);
      this.log(`Используемый терминатор: "${this.terminator}" (коды: [${Array.from(this.terminator).map(c => c.charCodeAt(0)).join(', ')}])`);
    }
    
    // Ищем полные коды в буфере
    let index;
    while ((index = this.dataBuffer.indexOf(this.terminator)) !== -1) {
      // Извлекаем полный код до терминатора
      const fullCode = this.dataBuffer.slice(0, index);
      
      if (this.debug) {
        this.log(`Найден полный код: "${fullCode}" (размер: ${fullCode.length})`);
      }
      
      // Обрабатываем код (убираем лишние пробелы)
      const trimmedCode = fullCode.trim();
      if (trimmedCode) {
        if (this.debug) {
          this.log(`Обрабатываем код: "${trimmedCode}"`);
        }
        this.onScan(trimmedCode);
      }
      
      // Удаляем обработанные данные из буфера
      this.dataBuffer = this.dataBuffer.slice(index + this.terminator.length);
      
      if (this.debug) {
        this.log(`Обновленный буфер: "${this.dataBuffer}" (размер: ${this.dataBuffer.length})`);
      }
    }
    
    // Перезапускаем таймер очистки буфера
    this.restartBufferTimer();
  }

  /**
   * Проверяет, подключен ли сканер
   * @returns {boolean}
   */
  isScannerConnected() {
    return this.isConnected;
  }

  /**
   * Очищает буфер данных сканера
   */
  clearBuffer() {
    this.dataBuffer = '';
    this.lastDataTime = Date.now();
    this.log('Буфер данных очищен вручную');
  }

  /**
   * Запускает таймер для автоматической очистки буфера по таймауту
   * @private
   */
  startBufferTimer() {
    this.stopBufferTimer(); // Останавливаем предыдущий таймер если есть
    
    // Устанавливаем таймер на время таймаута + небольшая задержка
    const timeoutMs = this.bufferTimeout + 1000; // +1 секунда для надежности
    
    this.bufferTimer = setTimeout(() => {
      const now = Date.now();
      const timeSinceLastData = now - this.lastDataTime;
      
      if (timeSinceLastData > this.bufferTimeout && this.dataBuffer.length > 0) {
        this.log(`Буфер очищен по таймауту (${Math.round(timeSinceLastData / 1000)} сек без данных)`);
        this.dataBuffer = '';
        this.lastDataTime = now;
      }
      
      // Перезапускаем таймер только если сканер подключен
      if (this.isConnected) {
        this.startBufferTimer();
      }
    }, timeoutMs);
    
    this.log(`Таймер очистки буфера запущен (очистка через ${timeoutMs / 1000} сек)`);
  }

  /**
   * Останавливает таймер очистки буфера
   * @private
   */
  stopBufferTimer() {
    if (this.bufferTimer) {
      clearTimeout(this.bufferTimer);
      this.bufferTimer = null;
      this.log('Таймер очистки буфера остановлен');
    }
  }

  /**
   * Перезапускает таймер очистки буфера
   * @private
   */
  restartBufferTimer() {
    // Перезапускаем таймер только если сканер подключен
    if (this.isConnected) {
      this.startBufferTimer();
    }
  }

  /**
   * Получает информацию о порте
   * @returns {Object|null} Информация о порте или null если не подключен
   */
  getPortInfo() {
    return this.port ? this.port.getInfo() : null;
  }

  /**
   * Получает статистику буфера данных
   * @returns {Object} Информация о состоянии буфера
   */
  getBufferStats() {
    const now = Date.now();
    const timeSinceLastData = now - this.lastDataTime;
    
    return {
      bufferSize: this.dataBuffer.length,
      maxBufferSize: this.maxBufferSize,
      bufferUsagePercent: Math.round((this.dataBuffer.length / this.maxBufferSize) * 100),
      timeSinceLastData: timeSinceLastData,
      timeSinceLastDataSeconds: Math.round(timeSinceLastData / 1000),
      bufferTimeout: this.bufferTimeout,
      isTimerActive: this.bufferTimer !== null,
      isConnected: this.isConnected
    };
  }

  /**
   * Получает информацию о текущем порте для сохранения
   * @returns {Object|null} Информация о порте или null если не подключен
   */
  getPortInfoForSaving() {
    if (!this.port) {
      return null;
    }
    
    const info = this.port.getInfo();
    return {
      vendorId: info.usbVendorId,
      productId: info.usbProductId
    };
  }

  /**
   * Сохраняет информацию о порте на сервере
   * @param {number} comNumber - Номер COM-порта
   * @returns {Promise<boolean>} Успех операции
   */
  async savePortInfo(comNumber) {
    const portInfo = this.getPortInfoForSaving();
    if (!portInfo) {
      this.log('Нет информации о порте для сохранения');
      return false;
    }

    const portData = {
      scannerUsbVendorId: portInfo.vendorId,
      scannerUsbProductId: portInfo.productId,
      comScanner: comNumber
    };
    
    try {
      const response = await fetch('/api/settings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(portData)
      });
      
      if (response.ok) {
        this.log('Информация о порте сохранена на сервере');
        return true;
      } else {
        this.log('Ошибка при сохранении информации о порте на сервере');
        return false;
      }
    } catch (error) {
      this.log(`Ошибка при сохранении информации о порте: ${error.message}`);
      return false;
    }
  }

  /**
   * Загружает информацию о сохраненном порте с сервера
   * @returns {Promise<Object|null>} Информация о сохраненном порте или null
   */
  async loadSavedPortInfo() {
    try {
      const response = await fetch('/api/settings');
      if (response.ok) {
        const settings = await response.json();
        if (settings.scannerUsbVendorId && settings.scannerUsbProductId) {
          return {
            vendorId: settings.scannerUsbVendorId,
            productId: settings.scannerUsbProductId,
            comNumber: settings.comScanner || 0
          };
        }
      }
    } catch (error) {
      this.log(`Ошибка при загрузке настроек с сервера: ${error.message}`);
    }
    return null;
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


// Экспорт для использования в модулях
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { BarcodeScanner };
}

// Глобальный экспорт для браузера
if (typeof window !== 'undefined') {
  window.BarcodeScanner = BarcodeScanner;
}
