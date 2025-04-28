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
            document.getElementById('clearlogs').checked = settings.clearlogs;
            document.getElementById('debug').value = settings.debug;
            document.getElementById('comkkt').value = settings.comkkt;
            document.getElementById('cassir').value = settings.cassir;
            document.getElementById('ipkkt').value = settings.ipkkt;
            document.getElementById('portipkkt').value = settings.portipkkt;
            document.getElementById('ipservkkt').value = settings.ipservkkt;
            document.getElementById('emul').checked = settings.emul;
            document.getElementById('allowedOrigin').value = settings.allowedOrigin;
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
        settings.clearlogs = settings.clearlogs === 'on';
        settings.emul = settings.emul === 'on';

        // Преобразование числовых полей
        if ('debug' in settings) settings.debug = Number(settings.debug);
        if ('comkkt' in settings) settings.comkkt = Number(settings.comkkt);
        if ('portipkkt' in settings) settings.portipkkt = Number(settings.portipkkt);        

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
