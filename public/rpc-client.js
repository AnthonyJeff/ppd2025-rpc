const boardEl = document.getElementById("game-board");
let selectedCell = null;
let localPlayer = parseInt(prompt("Você é o Jogador 1 ou 2?"));

let isInteracting = false;
let lastPlacingPhase = true;
let hasShownMovementAlert = false;

/* Chamadas de JSON - RCP */
async function fetchGameState() {
  const res = await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "getState",
      id: 1
    })
  });
  const data = await res.json();
  renderBoard(data.result);
  checkVictory(data.result);
}

async function placePiece(row, col) {
  isInteracting = true;
  const res = await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "placePiece",
      params: { row, col, player: localPlayer },
      id: 2
    })
  });

  const data = await res.json();
  if (data.result) {
    selectedCell = null;
    renderBoard(data.result);
    checkVictory(data.result);
  } else {
    alert(data.error.message);
  }
  isInteracting = false;
}

async function movePiece(from, to) {
  isInteracting = true;
  const res = await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "movePiece",
      params: { from, to, player: localPlayer },
      id: 3
    })
  });

  const data = await res.json();
  selectedCell = null;

  if (data.result) {
    renderBoard(data.result);
    checkVictory(data.result);
  } else {
    alert(data.error.message);
  }

  isInteracting = false;
}

async function resetGame() {
  if (!confirm("Tem certeza que deseja reiniciar a partida?")) return;

  const res = await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "resetGame",
      params: { player: localPlayer },
      id: 99
    })
  });

  const data = await res.json();
  selectedCell = null;
  lastPlacingPhase = true;
  hasShownMovementAlert = false; // ✅ Permitir alerta na próxima partida
  renderBoard(data.result);
  checkVictory(data.result);
}

async function desistir() {
  if (!confirm("Tem certeza que deseja desistir da partida?")) return;

  await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "giveUp",
      params: { player: localPlayer },
      id: Date.now()
    })
  });

  // Atualiza estado após desistência
  fetchGameState();
}

// Chat
async function sendMessage() {
  const input = document.getElementById("chat-input");
  const text = input.value.trim();
  if (text === "") return;

  await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "sendMessage",
      params: { player: localPlayer, text },
      id: Date.now()
    })
  });

  input.value = "";
  loadChat(); // atualizar após envio
}

async function loadChat() {
  const res = await fetch("../server/rpc.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      jsonrpc: "2.0",
      method: "getChat",
      id: Date.now()
    })
  });

  const data = await res.json();
  const chatBox = document.getElementById("chat-box");
  chatBox.innerHTML = "";

  data.result.forEach(msg => {
    const div = document.createElement("div");
    div.className = "d-flex flex-column chat-msg " + (msg.player === 1 ? "msg-p1 align-self-start" : "msg-p2 align-self-end");
    div.innerHTML = `<div>${msg.text}</div><div class="chat-time">${formatTime(msg.timestamp)}</div>`;
    chatBox.appendChild(div);
  });

  chatBox.scrollTop = chatBox.scrollHeight;
}

/* Funções JS */
function renderBoard(state) {
  boardEl.innerHTML = "";
  for (let i = 0; i < 5; i++) {
    for (let j = 0; j < 5; j++) {
      const cell = document.createElement("div");
      cell.className = "cell";
      cell.dataset.row = i;
      cell.dataset.col = j;

      const value = state.board[i][j];
      if (value === "P1") {
        cell.classList.add("player1");
        cell.textContent = "●";
      } else if (value === "P2") {
        cell.classList.add("player2");
        cell.textContent = "●";
      }

      if (state.placingPhase) {
        if (state.turn === localPlayer && !value && !(i === 2 && j === 2)) {
          cell.onclick = () => placePiece(i, j);
        }
      } else {
        if (value === `P${localPlayer}`) {
          cell.onclick = () => {
            selectedCell = { row: i, col: j };
            highlightSelected(i, j);
          };
        } else if (!value && selectedCell) {
          cell.onclick = () => movePiece(selectedCell, { row: i, col: j });
        }
      }

      // Alerta de transição para fase de movimentação
      if (lastPlacingPhase && state.placingPhase === false) {
        const message = localPlayer === 1
          ? "📢 A fase de movimentação começou. A casa central foi desbloqueada!\n🎮 É sua vez de iniciar!"
          : "📢 A fase de movimentação começou. A casa central foi desbloqueada!\n🎮 O jogador 1 inicia.";
        alert(message);
        hasShownMovementAlert = true; // ✅ Exibir apenas uma vez
      }
      lastPlacingPhase = state.placingPhase;

      boardEl.appendChild(cell);
    }
  }

  document.getElementById("turn-display").textContent = state.turn;
  document.getElementById("phase-display").textContent = state.placingPhase ? "Posicionamento" : "Movimentação";
  document.getElementById("score-p1").textContent = state.player1Count;
  document.getElementById("score-p2").textContent = state.player2Count;
}

function highlightSelected(row, col) {
  document.querySelectorAll(".cell").forEach(cell => {
    cell.style.outline = "none";
  });
  const cell = document.querySelector(`.cell[data-row="${row}"][data-col="${col}"]`);
  if (cell) cell.style.outline = "2px solid green";
}

function checkVictory(state) {
  if (!state.winner) return;

  const youWin = parseInt(state.winner) === localPlayer;

  if (youWin) {
    if (state.reason === "block") {
      alert("🏆 Você venceu por bloquear o adversário!");
    } else if (state.reason === "giveup") {
      alert("🏆 O adversário desistiu. Você venceu!");
    } else if (state.reason === "reset") {
      alert("🏆 Você venceu porque o adversário reiniciou a partida.");
    } else {
      alert("🏆 Você venceu!");
    }
  } else {
    if (state.reason === "block") {
      alert("❌ Você perdeu pois está bloqueado.");
    } else if (state.reason === "giveup") {
      alert("❌ Você desistiu da partida.");
    } else if (state.reason === "reset") {
      alert("❌ Você perdeu porque você reiniciou a partida.");
    } else {
      alert("❌ Você perdeu.");
    }
  }
}


function formatTime(iso) {
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Inicializa o jogo ao carregar
fetchGameState();

setInterval(loadChat, 2000); // Atualiza o chat automaticamente a cada 2 segundos  
setInterval(fetchGameState, 2000); // Atualiza o jogo em tempo real
