♟️ Seega Online - Jogo Multiplayer em Tempo Real com JSON-RPC

Este é um jogo Seega Clássico, feito com PHP + JSON RPC + HTML/CSS/JS (Bootstrap), rodando em tempo real entre dois jogadores (no navegador).
🎮 Funcionalidades

    Tabuleiro 5x5 com fases de posicionamento e movimentação
    Regras do Seega (incluindo captura, centro, bloqueios)
    Jogadores se alternam colocando 1 peça por turno
    Chat em tempo real
    Detecção de vitória automática por:
        Captura total
        Peças bloqueadas
        Desistência
        Desconexão
    Totalmente sincronizado entre as abas
    Sistema RPC com JSON

⚙️ Instalação
1. Clonar o repositório

git clone https://github.com/AnthonyJeff/ppd2025-rpc
cd ppd2025-rpc


2. Abrir o jogo em dois navegadores ou abas

Abra o arquivo:

public/index.html

    Em uma aba: Jogador 1
    Em outra aba: Jogador 2
    O sistema já detecta as conexões automaticamente

💬 Controles no jogo

    Posicione peças clicando nas casas
    Cada jogador coloca 2 peças por vez
    Após 12 peças, começa a movimentação
    Capture peças flanqueando o inimigo
    Use o chat para conversar em tempo real

📦 Tecnologias utilizadas

    🧠 PHP + JSON/RPC 
    🎨 HTML5, CSS3, Bootstrap
    ⚡ JavaScript (sem frameworks)
    💬 Comunicação bidirecional em tempo real

🛠️ Em desenvolvimento

    Sistema de salas com IDs
    Reconexão de jogadores

🧑‍💻 Autor

Desenvolvido por Anthony Jefferson
