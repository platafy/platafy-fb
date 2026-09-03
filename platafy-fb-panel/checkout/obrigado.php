<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFY FB - Obrigado!</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --neon: #ffaa00;
            --primary: #4d5b9a;
            --bg: #0a0d1a;
            --glass: rgba(10, 13, 30, 0.75);
            --border: rgba(255, 170, 0, 0.3);
            --text: #ffffff;
            --muted: #b0b8cc;
        }
        body {
            background: radial-gradient(ellipse at top, #1a2040, #0a0d1a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 20px;
        }
        .card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            box-shadow: 0 0 40px rgba(0,0,0,0.8);
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 10px rgba(0, 230, 118, 0.5));
        }
        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            color: var(--neon);
            margin-bottom: 15px;
            letter-spacing: 2px;
        }
        p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .info-box {
            background: rgba(255, 170, 0, 0.05);
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
        }
        .info-box ul {
            list-style-type: none;
        }
        .info-box li {
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--muted);
        }
        .info-box li:last-child { margin-bottom: 0; }
        .info-box strong { color: var(--text); }
        .btn-home {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #4d5b9a, #ffaa00);
            color: #0a0d1a;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            border-radius: 10px;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-home:hover {
            box-shadow: 0 0 25px rgba(255, 170, 0, 0.4);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🎉</div>
        <h1>PAGAMENTO CONFIRMADO!</h1>
        <p>Obrigado por assinar o <b>PLATAFY FB</b>! Sua assinatura foi processada com sucesso.</p>
        
        <div class="info-box">
            <ul>
                <li>📌 <b>Ativação Automática:</b> O Mercado Pago enviou a confirmação para nosso sistema.</li>
                <li>🔑 <b>Sua Chave:</b> Sua chave de licença foi ativada. Se comprou via checkout online, abra a extensão no WhatsApp Web e sua licença estará pronta para autenticar.</li>
                <li>📧 <b>Dúvidas?</b> Caso precise de suporte, entre em contato com nosso atendimento.</li>
            </ul>
        </div>
        
        <a href="https://web.whatsapp.com" class="btn-home" target="_blank">IR PARA O WHATSAPP WEB</a>
    </div>
</body>
</html>
