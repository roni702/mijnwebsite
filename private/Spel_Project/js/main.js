document.addEventListener('DOMContentLoaded', () => {
    // --- ELEMENT SELECTIES ---
    const mainAppContainer = document.getElementById('main-app-container');
    const gameSelectionScreen = document.getElementById('game-selection-screen');
    const orientationWarning = document.getElementById('orientation-warning');
    const gameContainers = document.querySelectorAll('.game-container');
    const playerEditModal = document.getElementById('player-edit-modal');
    const editPlayerNameInput = document.getElementById('editPlayerName');
    const editPlayerImageInput = document.getElementById('editPlayerImage');
    const editPlayerImagePreview = document.getElementById('editPlayerImagePreview');
    const savePlayerBtn = document.getElementById('save-player-btn');
    const cancelPlayerEditBtn = document.getElementById('cancel-player-edit-btn');
    const deletePlayerBtn = document.getElementById('delete-player-btn');
    const screenshotMessage = document.getElementById('screenshot-message');
    const emailFormContainer = document.getElementById('email-form-container');
    const emailForm = document.getElementById('email-form');
    const cancelFormBtn = document.getElementById('cancel-form-btn');
    const statusMessage = document.getElementById('status-message');
    const statusText = document.getElementById('status-text');

    // --- STATE VARIABELEN ---
    let activePlayerForEdit = null;
    let playerCounter = 1;
    let currentScreenshotData = null;
    let isLineupDirty = true; // Houdt bij of de opstelling is gewijzigd.

    // --- FUNCTIES ---

    /**
     * Markeert de opstelling als gewijzigd en activeert de verstuurknoppen.
     */
    function markLineupAsDirty() {
        if (!isLineupDirty) {
            isLineupDirty = true;
            document.querySelectorAll('.screenshot-btn').forEach(btn => {
                btn.disabled = false;
            });
            showStatusMessage('Opstelling gewijzigd, je kunt opnieuw versturen.', 'success');
        }
    }
    
    function showStatusMessage(message, type = 'success') {
        statusText.textContent = message;
        statusMessage.className = 'fixed bottom-4 right-4 text-white p-4 rounded-lg shadow-lg z-50 transition-transform duration-300 ease-out transform';
        statusMessage.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500');
        statusMessage.classList.remove('hidden', 'translate-y-full');
        setTimeout(() => {
            statusMessage.classList.add('translate-y-full');
            setTimeout(() => statusMessage.classList.add('hidden'), 350);
        }, 3000);
    }

    function checkOrientation() {
        if (window.innerHeight > window.innerWidth && window.innerWidth <= 1023) {
            orientationWarning.style.display = 'flex';
            mainAppContainer.style.display = 'none';
        } else {
            orientationWarning.style.display = 'none';
            mainAppContainer.style.display = 'block';
        }
    }

    function showGameScreen(gameId) {
        gameSelectionScreen.classList.add('hidden');
        gameContainers.forEach(container => container.classList.add('hidden'));
        document.getElementById(gameId)?.classList.remove('hidden');
    }

    function showGameSelectionScreen() {
        gameContainers.forEach(container => container.classList.add('hidden'));
        gameSelectionScreen.classList.remove('hidden');
    }

    function createPlayerOnField(x, y, field, initialName = 'Speler') {
        const player = document.createElement('div');
        player.className = 'player-on-field';
        player.style.left = `${x}px`;
        player.style.top = `${y}px`;
        player.dataset.x = x;
        player.dataset.y = y;
        player.innerHTML = `
            <div class="action-buttons">
                <button class="action-btn edit-btn" title="Bewerk speler"><i class="fas fa-pencil-alt"></i></button>
                <button class="action-btn delete-btn" title="Verwijder speler"><i class="fas fa-trash-alt"></i></button>
            </div>
            <img src="https://placehold.co/70x70/3b82f6/ffffff?text=${initialName.charAt(0)}" alt="Speler" class="player-image">
            <span class="player-name">${initialName}</span>`;
        field.appendChild(player);

        player.querySelector('.edit-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            openPlayerEditModal(player);
        });
        player.querySelector('.delete-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            player.remove();
            markLineupAsDirty();
        });
    }

    function openPlayerEditModal(playerElement) {
        activePlayerForEdit = playerElement;
        editPlayerNameInput.value = playerElement.querySelector('.player-name').textContent;
        editPlayerImagePreview.src = playerElement.querySelector('.player-image').src;
        editPlayerImageInput.value = '';
        playerEditModal.classList.remove('hidden');
    }

    function savePlayerChanges() {
        if (!activePlayerForEdit) return;
        const nameSpan = activePlayerForEdit.querySelector('.player-name');
        const image = activePlayerForEdit.querySelector('.player-image');
        const newName = editPlayerNameInput.value.trim();
        nameSpan.textContent = newName === '' ? 'Speler' : newName;
        const file = editPlayerImageInput.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => { image.src = e.target.result; };
            reader.readAsDataURL(file);
        }
        playerEditModal.classList.add('hidden');
        showStatusMessage('Speler bijgewerkt!', 'success');
        activePlayerForEdit = null;
        markLineupAsDirty();
    }

    function handleScreenshotClick(field, button) {
        screenshotMessage.classList.remove('hidden');
        field.classList.add('no-hover');
        const options = {
            scale: 2,
            useCORS: true,
            backgroundColor: window.getComputedStyle(field).backgroundColor,
            foreignObjectRendering: true
        };
        html2canvas(field, options).then(canvas => {
            currentScreenshotData = canvas.toDataURL('image/png');
            setTimeout(() => {
                screenshotMessage.classList.add('hidden');
                emailFormContainer.classList.remove('hidden');
            }, 500);
        }).catch(err => {
            console.error("Fout bij maken van screenshot:", err);
            showStatusMessage('Maken van screenshot mislukt.', 'error');
            field.classList.remove('no-hover');
            screenshotMessage.classList.add('hidden');
        });
    }

    interact('.draggable-template').draggable({
        inertia: true,
        listeners: {
            move: (event) => {
                const { target, dx, dy } = event;
                const x = (parseFloat(target.dataset.x) || 0) + dx;
                const y = (parseFloat(target.dataset.y) || 0) + dy;
                target.style.transform = `translate(${x}px, ${y}px)`;
                target.dataset.x = x;
                target.dataset.y = y;
            },
            end: (event) => {
                event.target.style.transform = '';
                event.target.removeAttribute('data-x');
                event.target.removeAttribute('data-y');
            }
        }
    });

    interact('.game-field').dropzone({
        accept: '.draggable-template',
        ondrop: (event) => {
            const field = event.target;
            const dropRect = field.getBoundingClientRect();
            const x = event.clientX - dropRect.left - 35;
            const y = event.clientY - dropRect.top - 35;
            createPlayerOnField(x, y, field, event.relatedTarget.textContent.trim());
            markLineupAsDirty();
        }
    });

    interact('.player-on-field').draggable({
        inertia: true,
        modifiers: [interact.modifiers.restrictRect({ restriction: 'parent' })],
        listeners: {
            move: (event) => {
                const { target, dx, dy } = event;
                const x = (parseFloat(target.dataset.x) || 0) + dx;
                const y = (parseFloat(target.dataset.y) || 0) + dy;
                target.style.left = `${x}px`;
                target.style.top = `${y}px`;
                target.dataset.x = x;
                target.dataset.y = y;
            },
            end: () => markLineupAsDirty()
        }
    });

    // --- Algemene Event Listeners ---
    window.addEventListener('resize', checkOrientation);
    window.addEventListener('orientationchange', checkOrientation);
    checkOrientation();

    document.getElementById('select-football').addEventListener('click', () => showGameScreen('football-game-container'));
    document.getElementById('select-basketball').addEventListener('click', () => showGameScreen('basketball-game-container'));
    document.getElementById('select-tennis').addEventListener('click', () => showGameScreen('tennis-game-container'));

    document.querySelectorAll('.game-container .btn-secondary').forEach(button => {
        button.addEventListener('click', showGameSelectionScreen);
    });

    savePlayerBtn.addEventListener('click', savePlayerChanges);
    cancelPlayerEditBtn.addEventListener('click', () => playerEditModal.classList.add('hidden'));
    deletePlayerBtn.addEventListener('click', () => {
        if (activePlayerForEdit) {
            activePlayerForEdit.remove();
            playerEditModal.classList.add('hidden');
            activePlayerForEdit = null;
            markLineupAsDirty();
        }
    });
    
    editPlayerImageInput.addEventListener('change', (e) => {
        if (e.target.files?.[0]) {
            const reader = new FileReader();
            reader.onload = (event) => { editPlayerImagePreview.src = event.target.result; };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    document.querySelectorAll('.screenshot-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            const gameContainer = e.currentTarget.closest('.game-container');
            const field = gameContainer.querySelector('.game-field');
            handleScreenshotClick(field, e.currentTarget);
        });
    });

    cancelFormBtn.addEventListener('click', () => {
        emailFormContainer.classList.add('hidden');
        emailForm.reset();
        currentScreenshotData = null;
        document.querySelector('.game-field:not(.hidden)')?.classList.remove('no-hover');
    });

    emailForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitButton = event.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Bezig met versturen...';

        const params = {
            firstName: document.getElementById('firstName').value,
            lastName: document.getElementById('lastName').value,
            email: document.getElementById('email').value,
            screenshot: currentScreenshotData
        };

        try {
            const response = await fetch('verstuur_mail.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(params)
            });
            const result = await response.json();
            if (result.success) {
                showStatusMessage('Opstelling succesvol verstuurd!', 'success');
                emailFormContainer.classList.add('hidden');
                emailForm.reset();
                currentScreenshotData = null;
                isLineupDirty = false;
                document.querySelectorAll('.screenshot-btn').forEach(btn => btn.disabled = true);
            } else {
                throw new Error(result.message || 'Onbekende fout op de server.');
            }
        } catch (error) {
            console.error("Fout bij versturen:", error);
            showStatusMessage(`Versturen mislukt: ${error.message}`, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Verstuur mijn opstelling';
            document.querySelector('.game-field:not(.hidden)')?.classList.remove('no-hover');
        }
    });

    function addNewPlayerTemplate(panelId, prefix) {
        playerCounter++;
        const panel = document.getElementById(panelId);
        const newPlayer = document.createElement('div');
        newPlayer.className = 'draggable-template';
        newPlayer.textContent = `${prefix}${playerCounter}`;
        panel.insertBefore(newPlayer, panel.lastElementChild);
    }
    document.getElementById('add-football-player-btn').addEventListener('click', () => addNewPlayerTemplate('football-player-panel', 'P'));
    document.getElementById('add-basketball-player-btn').addEventListener('click', () => addNewPlayerTemplate('basketball-player-panel', 'B'));
    document.getElementById('add-tennis-player-btn').addEventListener('click', () => addNewPlayerTemplate('tennis-player-panel', 'T'));
});