document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    const restartButton = document.getElementById('restartService');
    const openLogsButton = document.getElementById('openLogs');
    const logPathElement = document.getElementById('logPath');
    const settingsPathElement = document.getElementById('settingsPath');
    const versionInfoElement = document.getElementById('versionInfo');
    const resetDefaultsButton = document.getElementById('resetDefaults');

    // Загрузка пути к файлу настроек
    fetch('/api/settingspath')
        .then(response => response.text())
        .then(path => {
            settingsPathElement.textContent = path;
        })
        .catch(error => console.error('Ошибка при загрузке пути к файлу настроек:', error));

    // Загрузка версии программы
    fetch('/api/version')
        .then(response => response.text())
        .then(version => {
            versionInfoElement.textContent = version;
        })
        .catch(error => console.error('Ошибка при загрузке версии программы:', error));

    // Загрузка текущих настроек
    fetch('/api/settings')
        .then(response => response.json())
        .then(settings => {
            console.log("Ответ сервера:", settings);
            document.getElementById('clearLogs').checked = settings.clearLogs;
            document.getElementById('debug').value = settings.debug;
            document.getElementById('comKkt').value = settings.comKkt;
            document.getElementById('cassir').value = settings.cassir;
            document.getElementById('ipKkt').value = settings.ipKkt;
            document.getElementById('portIpKkt').value = settings.portIpKkt;
            document.getElementById('ipServKkt').value = settings.ipServKkt;
            document.getElementById('emulation').checked = settings.emulation;
            document.getElementById('allowedOrigin').value = settings.allowedOrigin;
            // Новые настройки для весов
            document.getElementById('comScale').value = settings.comScale;
            document.getElementById('baudRateScale').value = settings.baudRateScale;
            document.getElementById('modelScale').value = settings.modelScale;
            document.getElementById('emulationScale').checked = settings.emulationScale;
            // Новые настройки для банковского терминала
            document.getElementById('bankEmulation').checked = settings.bankEmulation;
            // Новая настройка для отключения логирования
            document.getElementById('disableLogging').checked = settings.disableLogging;
        })
        .catch(error => console.error('Ошибка при загрузке настроек:', error));

    // Загрузка пути к логам
    fetch('/api/logpath')
        .then(response => response.text())
        .then(path => {
            logPathElement.textContent = path;
        })
        .catch(error => console.error('Ошибка при загрузке пути к логам:', error));

    // Обработка отправки формы
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        const settings = Object.fromEntries(formData.entries());
        
        // Преобразование checkbox значений в boolean
        settings.clearLogs = settings.clearLogs === 'on';
        settings.emulation = settings.emulation === 'on';
        settings.emulationScale = settings.emulationScale === 'on';
        settings.bankEmulation = settings.bankEmulation === 'on';
        // Новая настройка для отключения логирования
        settings.disableLogging = settings.disableLogging === 'on';

        // Преобразование числовых полей
        if ('debug' in settings) settings.debug = Number(settings.debug);
        if ('comKkt' in settings) settings.comKkt = Number(settings.comKkt);
        if ('portIpKkt' in settings) settings.portIpKkt = Number(settings.portIpKkt);
        // Новые числовые поля для весов
        if ('comScale' in settings) settings.comScale = Number(settings.comScale);
        if ('baudRateScale' in settings) settings.baudRateScale = Number(settings.baudRateScale);
        if ('modelScale' in settings) settings.modelScale = Number(settings.modelScale);

        const settingsJson = JSON.stringify(settings);

        fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: settingsJson,
        })
        .then(response => response.json())
        .then(data => {
            console.log('Ответ сервера на сохранение:', data);
            alert('Настройки успешно сохранены');
        })
        .catch((error) => {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при сохранении настроек');
        });
    });

    // Обработчик для кнопки перезапуска службы
    restartButton.addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите перезапустить службу?')) {
            fetch('/api/restart', {
                method: 'POST',
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Служба успешно перезапущена');
                } else {
                    alert('Ошибка при перезапуске службы: ' + data.message);
                }
            })
            .catch((error) => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при перезапуске службы');
            });
        }
    });

    // Обработчик для кнопки открытия логов
    openLogsButton.addEventListener('click', function() {
        fetch('/api/openlogs', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Папка с логами открыта');
                } else {
                    alert('Ошибка при открытии папки с логами: ' + data.message);
                }
            })
            .catch((error) => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при открытии папки с логами');
            });
    });

    // Обработка сброса настроек по умолчанию
    resetDefaultsButton.addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите сбросить настройки по умолчанию?')) {
            fetch('/api/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({resetDefaults: true})
            })
            .then(response => response.json())
            .then(data => {
                alert('Настройки сброшены по умолчанию. Перезагрузите страницу.');
                location.reload();
            })
            .catch((error) => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при сбросе настроек');
            });
        }
    });
});
