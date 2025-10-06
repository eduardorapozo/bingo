// Variáveis globais
let tempoRestante = gameConfig.tempoRestante;
let userBalance = gameConfig.userBalance;
let drawnNumbers = gameConfig.drawnNumbers;
let jogoEmAndamento = gameConfig.jogoEmAndamento;
let jogoTerminado = gameConfig.jogoTerminado;
let aguardandoProximo = gameConfig.aguardandoProximo;
let cartelasCompradas = gameConfig.cartelasCompradas;
let maxCartelas = gameConfig.maxCartelas;
let salaId = gameConfig.salaId;
let velocidadeSorteio = gameConfig.velocidadeSorteio;
let ativarBots = gameConfig.ativarBots;
let ativarNarracao = gameConfig.ativarNarracao;

// Sistema de bots/jogadores
let botPlayers = [];
let botInterval;
let sorteioInterval;

// Sistema de áudio
let isMuted = localStorage.getItem('isMuted') === 'true';
const synth = window.speechSynthesis;
let voices = [];

function getVoices() {
    voices = synth.getVoices().filter(voice => voice.lang.includes('pt-BR'));
}
window.speechSynthesis.onvoiceschanged = getVoices;
getVoices();

function playAudioFile(filename) {
    if (confirm(`Comprar ${qty} cartela${qty > 1 ? 's' : ''} por R$ ${total.toFixed(2)}?`)) {
        document.getElementById('quickPrice').value = price;
        document.getElementById('quickQty').value = qty;
        document.getElementById('quickBuyForm').submit();
    }
}

// Atualizar cartela 1-75
function updateBingoBoard(numero) {
    const boardNumber = document.querySelector(`.board-number[data-number="${numero}"]`);
    if (boardNumber) {
        boardNumber.classList.add('drawn');
    }
}

// Atualizar últimas 4 bolas
function updateLastFourBalls() {
    const lastFour = drawnNumbers.slice(-4);
    const ballPositions = document.querySelectorAll('.ball-position');
    
    for (let i = 0; i < 4; i++) {
        const position = ballPositions[i];
        if (position && lastFour[i]) {
            position.innerHTML = `<img src="assets/images/balls/${lastFour[i]}.png" alt="Bola ${lastFour[i]}" onerror="this.innerHTML='<div style=\\'color: #9ca3af; font-size: 10px;\\'>${lastFour[i]}</div>'">`;
        }
    }
}

// Sistema de sorteio
function getNextBallFromServer() {
    if (drawnNumbers.length >= 75 || !salaId) {
        clearInterval(sorteioInterval);
        return;
    }
    
    fetch('ajax/sorteio_server.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sala_id: salaId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.numero) {
            drawnNumbers.push(data.numero);
            
            // Atualizar última bola
            const lastBallContainer = document.getElementById('lastBall');
            if (lastBallContainer) {
                lastBallContainer.innerHTML = `<img src="assets/images/balls/${data.numero}.png" alt="Bola ${data.numero}" onerror="this.innerHTML='<div style=\\'color: #64748b; font-size: 14px;\\'>${data.numero}</div>'">`;
            }
            
            // Atualizar cartela 1-75
            updateBingoBoard(data.numero);
            
            // Atualizar últimas 4 bolas
            updateLastFourBalls();
            
            // Marcar cartelas do usuário
            const cartelaNumbers = document.querySelectorAll('.cartela-number');
            cartelaNumbers.forEach(cell => {
                if (parseInt(cell.dataset.number) === data.numero) {
                    cell.classList.add('marked');
                }
            });
            
            showToast(`Bola ${data.numero}`, 'info');
            narrarBola(data.numero);
            
            // Atualizar status dos bots
            updateBotStatus();
            
            // Verificar se jogo terminou
            if (data.jogo_finalizado || data.total_sorteados >= 75) {
                jogoTerminado = true;
                showToast('Jogo finalizado!', 'success');
                clearInterval(sorteioInterval);
                clearInterval(botInterval);
                
                setTimeout(() => window.location.reload(), 5000);
            }
        }
    })
    .catch(error => {
        console.error('Erro no sorteio:', error);
    });
}

function startSorteioPolling() {
    if (sorteioInterval) {
        clearInterval(sorteioInterval);
    }
    getNextBallFromServer();
    sorteioInterval = setInterval(getNextBallFromServer, velocidadeSorteio * 1000);
}

// Controle de áudio
const volumeToggleBtn = document.getElementById('volumeToggle');
if (volumeToggleBtn) {
    const volumeIcon = volumeToggleBtn.querySelector('i');
    
    if (isMuted) {
        volumeIcon.classList.remove('fa-volume-up');
        volumeIcon.classList.add('fa-volume-mute');
    }

    volumeToggleBtn.addEventListener('click', () => {
        isMuted = !isMuted;
        localStorage.setItem('isMuted', isMuted);
        
        if (isMuted) {
            volumeIcon.classList.remove('fa-volume-up');
            volumeIcon.classList.add('fa-volume-mute');
            showToast('Áudio desativado', 'info');
        } else {
            volumeIcon.classList.remove('fa-volume-mute');
            volumeIcon.classList.add('fa-volume-up');
            showToast('Áudio ativado', 'info');
        }
    });
}

function logout() {
    if (confirm('Tem certeza que deseja sair?')) {
        window.location.href = 'libs/logout.php';
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar bots
    initializeBotPlayers();
    
    // Iniciar countdown se necessário
    if (!jogoEmAndamento && !jogoTerminado && !aguardandoProximo && tempoRestante > 0) {
        setInterval(updateCountdown, 1000);
    }
    
    // Iniciar sorteio se jogo em andamento
    if (jogoEmAndamento && !jogoTerminado) {
        startSorteioPolling();
        if (ativarBots) {
            botInterval = setInterval(updateBotStatus, 3000);
        }
    }
    
    // Auto-reload se jogo terminado
    if (jogoTerminado || aguardandoProximo) {
        setTimeout(() => window.location.reload(), 6000);
    }
});isMuted) return;
    
    const audio = new Audio(`libs/audio/${filename}`);
    audio.volume = 0.8;
    audio.play().catch(() => {
        // Fallback para Text-to-Speech
        let text = '';
        switch(filename) {
            case '15s.mp3':
                text = 'Faltam 15 segundos';
                break;
            case '2min.mp3':
                text = 'Faltam 2 minutos';
                break;
            case 'compras_encerradas.mp3':
                text = 'Compras encerradas';
                break;
        }
        if (text && voices.length > 0) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.voice = voices[0];
            synth.speak(utterance);
        }
    });
}

function narrarBola(numero) {
    if (isMuted || !ativarNarracao || voices.length === 0) return;
    
    const utterance = new SpeechSynthesisUtterance(`Bola ${numero}`);
    utterance.voice = voices[0];
    synth.speak(utterance);
}

// Função para recarregar página
function reloadPage() {
    window.location.reload();
}

// Sistema de notificações
function showToast(message, type = 'success') {
    const existingToast = document.getElementById('toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = `toast show ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Auto-hide initial toast
const initialToast = document.getElementById('toast');
if (initialToast) {
    setTimeout(() => {
        initialToast.classList.remove('show');
    }, 4000);
}

// Menu Mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

if (menuToggle) {
    menuToggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    });
}

if (overlay) {
    overlay.addEventListener('click', (e) => {
        e.preventDefault();
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
}

// Fechar menu ao clicar em links
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
    });
});

// Sistema de Bots/Jogadores
function initializeBotPlayers() {
    botPlayers = [];
    for (let i = 0; i < 5; i++) {
        const playerId = Math.floor(Math.random() * 90000000) + 10000000;
        botPlayers.push({
            id: playerId,
            name: `Player ID: ${playerId}`,
            status: 'Jogando',
            ballsToWin: Math.floor(Math.random() * 26) + 5, // Entre 5 e 30 bolas
            lastUpdate: Date.now()
        });
    }
    updatePlayersDisplay();
}

function updateBotStatus() {
    if (!ativarBots || drawnNumbers.length === 0) return;
    
    const ballsDrawn = drawnNumbers.length;
    
    botPlayers.forEach(bot => {
        const timeSinceUpdate = Date.now() - bot.lastUpdate;
        
        if (ballsDrawn >= bot.ballsToWin && timeSinceUpdate > 4000) {
            const rand = Math.random();
            
            if (ballsDrawn >= bot.ballsToWin && ballsDrawn <= bot.ballsToWin + 5) {
                if (rand < 0.3) {
                    bot.status = 'quadra';
                    showToast(`${bot.name}: QUADRA!`, 'info');
                }
            } else if (ballsDrawn > bot.ballsToWin + 5 && ballsDrawn <= bot.ballsToWin + 15) {
                if (rand < 0.25) {
                    bot.status = 'quina';
                    showToast(`${bot.name}: QUINA!`, 'info');
                } else if (rand < 0.5 && bot.status === 'Jogando') {
                    bot.status = 'quadra';
                }
            } else if (ballsDrawn > bot.ballsToWin + 15) {
                if (rand < 0.15) {
                    bot.status = 'bingo';
                    showToast(`${bot.name}: BINGO!`, 'success');
                } else if (rand < 0.4 && bot.status !== 'bingo') {
                    bot.status = 'quina';
                } else if (rand < 0.7 && bot.status === 'Jogando') {
                    bot.status = 'quadra';
                }
            }
            
            bot.lastUpdate = Date.now();
        }
    });
    
    updatePlayersDisplay();
}

function updatePlayersDisplay() {
    const playersList = document.getElementById('playersList');
    if (!playersList) return;
    
    let html = '';
    botPlayers.forEach(bot => {
        let statusClass = '';
        let statusText = bot.status;
        
        switch(bot.status) {
            case 'quadra':
                statusClass = 'quadra';
                statusText = 'QUADRA';
                break;
            case 'quina':
                statusClass = 'quina';
                statusText = 'QUINA';
                break;
            case 'bingo':
                statusClass = 'bingo';
                statusText = 'BINGO';
                break;
            default:
                statusText = 'Jogando';
        }
        
        html += `
            <div class="player-item">
                <span class="player-id">${bot.name}</span>
                <span class="player-status ${statusClass}">${statusText}</span>
            </div>
        `;
    });
    
    playersList.innerHTML = html;
}

// Countdown
function updateCountdown() {
    if (tempoRestante > 0) {
        const minutes = Math.floor(tempoRestante / 60);
        const seconds = tempoRestante % 60;
        const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        const countdownDisplay = document.getElementById('countdownDisplay');
        if (countdownDisplay) {
            countdownDisplay.textContent = timeString;
        }
        
        // Avisos de áudio
        if (tempoRestante === 120) {
            playAudioFile('2min.mp3');
            showToast('2 minutos para início!', 'info');
        }
        
        if (tempoRestante === 15) {
            playAudioFile('15s.mp3');
            showToast('15 segundos!', 'info');
        }
        
        if (tempoRestante === 1) {
            playAudioFile('compras_encerradas.mp3');
        }
        
        tempoRestante--;
    } else {
        showToast('Jogo iniciado!', 'success');
        setTimeout(() => window.location.reload(), 2000);
    }
}

// Sistema de compra rápida
function quickBuy(price, qty) {
    const total = price * qty;
    const newTotal = cartelasCompradas + qty;
    
    if (jogoEmAndamento) {
        showToast('Jogo em andamento!', 'error');
        return;
    }
    
    if (newTotal > maxCartelas) {
        showToast(`Limite de ${maxCartelas} cartelas!`, 'error');
        return;
    }
    
    if (total > userBalance) {
        showToast('Saldo insuficiente!', 'error');
        return;
    }
    
    if (