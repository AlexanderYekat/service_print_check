document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    const restartButton = document.getElementById('restartService');
    const openLogsButton = document.getElementById('openLogs');
    const logPathElement = document.getElementById('logPath');

    // Загрузка текущих настроек
    fetch('/api/settings')
        .then(response => response.json())
        .then(settings => {
            document.getElementById('clearlogs').checked = settings.clearlogs;
            document.getElementById('debug').value = settings.debug;
            document.getElementById('com').value = settings.com;
            document.getElementById('cassir').value = settings.cassir;
            document.getElementById('ipkkt').value = settings.ipkkt;
            document.getElementById('portipkkt').value = settings.portipkkt;
            document.getElementById('ipservkkt').value = settings.ipservkkt;
            document.getElementById('emul').checked = settings.emul;
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

        fetch('/api/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(settings),
        })
        .then(response => response.json())
        .then(data => {
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
});
