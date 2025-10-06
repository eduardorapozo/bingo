<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['usuario_logado'])) {
	header('Location: login.php');
	exit;
}

require_once('conexao.php');
$usuario = $_SESSION['usuario_logado'];

// Buscar dados do usuário, incluindo a permissão de admin
$stmt = $pdo->prepare("SELECT id, nome_completo, saldo, is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
	session_destroy();
	header('Location: login.php');
	exit;
}

// --- LISTA GRANDE DE NOMES DE JOGADORES (50 NOMES) ---
$bot_names = [
 'Ana Clara', 'Bruno Henrique', 'Carla Dias', 'Daniel Lima', 'Erica Santos',
 'Felipe Mello', 'Gabriela Cruz', 'Hugo Alves', 'Isabela Rocha', 'Jonas Silva',
 'Karina Souza', 'Lucas Pereira', 'Marcela Gomes', 'Natan Costa', 'Olivia Ferreira',
 'Pedro Júnior', 'Quenia Ramos', 'Rafael Telles', 'Sofia Mendes', 'Thiago Vieira',
 'Ursula Nunes', 'Victor Hugo', 'Wanda Matos', 'Xavier Lopes', 'Yara Santos',
 'Zeca Silva', 'Alice Barros', 'Beto Cunha', 'Cecilia Rocha', 'Davi Luiz',
 'Elisa Neves', 'Fernando Paz', 'Gisele Diniz', 'Heitor Castro', 'Igor Freitas',
 'Júlia Antunes', 'Kauã Martins', 'Lívia Bastos', 'Márcio Souza', 'Nara Faria',
 'Otávio Lemos', 'Priscila Dias', 'Quiteria Luz', 'Renato Braga', 'Sabrina Neta',
 'Tadeu Lima', 'Valéria Reis', 'Wesley Cintra', 'Yasmin Melo', 'Zenon Garcia'
];
// Salva/Carrega os nomes dos jogadores na sessão para que sejam consistentes durante a rodada
if (!isset($_SESSION['bots_nomes_sorteados'])) {
 $_SESSION['bots_nomes_sorteados'] = $bot_names;
}
$bots_nomes_sorteados = $_SESSION['bots_nomes_sorteados'];


// --- SISTEMA DE PLAYERS ONLINE (133 a 350) ---
if (!isset($_SESSION['players_online_base'])) {
 $_SESSION['players_online_base'] = mt_rand(133, 350);
}
$players_online_base = $_SESSION['players_online_base'];

// Buscar configurações do jogo
$stmt_config = $pdo->prepare("SELECT * FROM configuracoes_jogo WHERE ativo = 1 LIMIT 1");
$stmt_config->execute();
$config = $stmt_config->fetch(PDO::FETCH_ASSOC);

$valores_cartela = json_decode($config['valores_cartela'] ?? '["0.10", "0.20", "0.30", "0.50", "1.00", "5.00", "10.00", "50.00"]', true);
$min_cartelas = $config['min_cartelas'] ?? 1;
$max_cartelas = $config['max_cartelas'] ?? 50;
$intervalo_jogos = $config['intervalo_jogos'] ?? 300;
$rtp = $config['rtp'] ?? 50;
$velocidade_sorteio = $config['velocidade_sorteio'] ?? 1;
$ativar_bots = $config['ativar_bots'] ?? true;
$url_suporte = $config['url_suporte'] ?? '#';
$ativar_narracao = $config['ativar_narracao'] ?? true;

// Buscar banners ativos
$banners = [];
try {
	$stmt_banners = $pdo->prepare("SELECT * FROM banners WHERE ativo = 1 ORDER BY ordem ASC");
	$stmt_banners->execute();
	$banners = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$banners = [];
}

// Lógica de gerenciamento de salas
$agora = time();
$jogo_em_andamento = false;
$jogo_terminado = false;
$aguardando_proximo = false;
$sala = null;
$bingo_fechado = false; // Estado de bingo fechado

// Tenta encontrar uma sala 'em_andamento'
$stmt = $pdo->prepare("SELECT * FROM salas_bingo WHERE status = 'em_andamento' LIMIT 1");
$stmt->execute();
$sala = $stmt->fetch(PDO::FETCH_ASSOC);

// NOVA LÓGICA: Verifica se o jogo foi marcado como encerrado por ter atingido o limite de bolas
if ($sala) {
	$numeros_sorteados = json_decode($sala['numeros_sorteados'], true) ?: [];
	
	// Verifica se o jogo atingiu entre 20-51 bolas (limite para encerramento automático)
	$limite_bolas_atingido = count($numeros_sorteados) >= 20 && count($numeros_sorteados) <= 51;
	
	// Verifica se já existe uma transação de bingo (jogo já finalizado)
	$stmt_bingo_status = $pdo->prepare("SELECT COUNT(*) FROM transacoes WHERE tipo = 'premio_bingo' AND sala_id = ? AND descricao LIKE '%Cartela cheia%'");
	$stmt_bingo_status->execute([$sala['id']]);
	$bingo_fechado = $stmt_bingo_status->fetchColumn() > 0;
	
	// NOVA CONDIÇÃO: Se atingiu o limite OU já foi processado o bingo, encerra o jogo
	if ($limite_bolas_atingido || $bingo_fechado || count($numeros_sorteados) >= 51) {
		$jogo_terminado = true;
		
		// APENAS ATUALIZA O STATUS DA SALA UMA ÚNICA VEZ
		if ($sala['status'] !== 'finalizado') {
			$stmt_update_sala = $pdo->prepare("UPDATE salas_bingo SET status = 'finalizado', fim_jogo = NOW() WHERE id = ?");
			$stmt_update_sala->execute([$sala['id']]);
		}
		
		$total_premio_ganho = 0;
		$premio_ja_pago = $pdo->prepare("SELECT COUNT(*) FROM transacoes WHERE usuario_id = ? AND tipo = 'premio_bingo' AND sala_id = ?");
		$premio_ja_pago->execute([$user_data['id'], $sala['id']]);
		
		// LÓGICA DE CÁLCULO E PAGAMENTO DE PRÊMIOS (DEVE SER FEITA NO PHP APENAS UMA VEZ)
		if ($premio_ja_pago->fetchColumn() == 0) {
			$stmt_cartelas = $pdo->prepare("SELECT * FROM cartelas_compradas WHERE usuario_id = ? AND sala_id = ?");
			$stmt_cartelas->execute([$user_data['id'], $sala['id']]);
			$cartelas_do_jogo_finalizado = $stmt_cartelas->fetchAll(PDO::FETCH_ASSOC);
			
			$quadras_ganhas = 0;
			$quinas_ganhas = 0;
			$cartelas_cheias_ganhas = 0;
			$total_gasto_jogo = 0;

			foreach ($cartelas_do_jogo_finalizado as $cartela) {
				$numeros_cartela = json_decode($cartela['numeros'], true);
				$vitorias = check_bingo_wins($numeros_cartela, $numeros_sorteados);

				$total_gasto_jogo += $cartela['valor_pago'];
				
				if (!empty($vitorias)) {
					foreach ($vitorias as $vitoria) {
						if ($vitoria === 'Quadra') { $total_premio_ganho += floatval($sala['premio_quadra']); $quadras_ganhas++; }
						if ($vitoria === 'Quina') { $total_premio_ganho += floatval($sala['premio_quina']); $quinas_ganhas++; }
						if ($vitoria === 'Cartela cheia') { $total_premio_ganho += floatval($sala['premio_cartela_cheia']); $cartelas_cheias_ganhas++; }
					}
				}
			}
			
			if ($total_premio_ganho > 0) {
				$novo_saldo_final = $user_data['saldo'] + $total_premio_ganho;
				$stmt_update_saldo = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
				$stmt_update_saldo->execute([$novo_saldo_final, $user_data['id']]);
				$descricao = "Prêmio do sorteio #" . $sala['numero_sorteio'] . ". Ganho total: R$ " . number_format($total_premio_ganho, 2, ',', '.');
				$stmt_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao, sala_id) VALUES (?, 'premio_bingo', ?, ?, ?, ?, ?)");
				$stmt_transacao->execute([$user_data['id'], $total_premio_ganho, $user_data['saldo'], $novo_saldo_final, $descricao, $sala['id']]);
				$user_data['saldo'] = $novo_saldo_final; // Atualiza o saldo para exibição
				$_SESSION['success'] = "Você ganhou R$ " . number_format($total_premio_ganho, 2, ',', '.') . " no sorteio!";
			} else {
				// Evita que a mensagem de sucesso de prêmio anterior seja exibida
				if (isset($_SESSION['success']) && strpos($_SESSION['success'], 'ganhou R$') !== false) {
					unset($_SESSION['success']);
				}
			}
			
			try {
				$stmt_historico = $pdo->prepare("INSERT INTO historico_jogos (usuario_id, sala_id, numero_sorteio, cartelas_compradas, valor_total_pago, premio_total_ganho, quadras_ganhas, quinas_ganhas, cartelas_cheias_ganhas, resultado, data_jogo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
				$resultado = ($total_premio_ganho > 0) ? 'vitoria' : 'derrota';
				$stmt_historico->execute([ $user_data['id'], $sala['id'], $sala['numero_sorteio'], count($cartelas_do_jogo_finalizado), $total_gasto_jogo, $total_premio_ganho, $quadras_ganhas, $quinas_ganhas, $cartelas_cheias_ganhas, $resultado ]);
			} catch (Exception $e) {}
		}
		
		// A sala já está marcada como 'finalizado' no DB.

	} else {
		$jogo_em_andamento = true;
	}
}

if (!$sala && !$aguardando_proximo) {
	$stmt = $pdo->prepare("SELECT * FROM salas_bingo WHERE status = 'aguardando' LIMIT 1");
	$stmt->execute();
	$sala = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$sala) {
		$numero_sorteio = rand(100000, 999999);
		$inicio = date('Y-m-d H:i:s', strtotime('+'.$intervalo_jogos.' seconds'));
		$premio_quadra = 20.00;
		$premio_quina = 30.00;
		$premio_cartela_cheia = 60.00;

		$stmt = $pdo->prepare("INSERT INTO salas_bingo (numero_sorteio, valor_cartela, premio_quadra, premio_quina, premio_cartela_cheia, inicio_previsto) VALUES (?, 0.10, ?, ?, ?, ?)");
		$stmt->execute([$numero_sorteio, $premio_quadra, $premio_quina, $premio_cartela_cheia, $inicio]);
		$sala_id = $pdo->lastInsertId();
		$stmt = $pdo->prepare("SELECT * FROM salas_bingo WHERE id = ?");
		$stmt->execute([$sala_id]);
		$sala = $stmt->fetch(PDO::FETCH_ASSOC);
		
		// Define a meta de bolas para o Bingo nesta nova sala (20-51)
		$_SESSION['meta_bingo_bolas_' . $sala_id] = mt_rand(20, 51);
	}
}

if ($sala && $sala['status'] === 'aguardando' && strtotime($sala['inicio_previsto']) <= $agora) {
	$pdo->prepare("UPDATE salas_bingo SET status = 'em_andamento', inicio_real = NOW() WHERE id = ?")->execute([$sala['id']]);
	$jogo_em_andamento = true;
	$sala['status'] = 'em_andamento';
}

$cartelas = [];
if ($sala) {
	$stmt = $pdo->prepare("SELECT * FROM cartelas_compradas WHERE usuario_id = ? AND sala_id = ? ORDER BY created_at ASC");
	$stmt->execute([$user_data['id'], $sala['id']]);
	$cartelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$cartelas_compradas_agora = count($cartelas);
$pode_comprar_mais = $cartelas_compradas_agora < $max_cartelas;

// LÓGICA DE COMPRA AGORA PROCESSADA PELO FORM TRADICIONAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprar'])) {
	$preco = floatval($_POST['preco']);
	$quantidade = intval($_POST['quantidade']);
	$total = $preco * $quantidade;
	
	$total_comprado_agora = $cartelas_compradas_agora + $quantidade;

	if ($total_comprado_agora > $max_cartelas) {
		$_SESSION['error'] = "Você não pode comprar mais que " . $max_cartelas . " cartelas. Faltam " . ($max_cartelas - $cartelas_compradas_agora) . " para o limite.";
	} elseif ($jogo_em_andamento) {
		$_SESSION['error'] = "Não é possível comprar cartelas durante o jogo em andamento.";
	} elseif ($user_data['saldo'] >= $total && $quantidade >= $min_cartelas) {
		try {
			$pdo->beginTransaction();
			$novo_saldo = $user_data['saldo'] - $total;
			$stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
			$stmt->execute([$novo_saldo, $user_data['id']]);
			$stmt = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao) VALUES (?, 'compra_cartela', ?, ?, ?, ?)");
			$stmt->execute([$user_data['id'], $total, $user_data['saldo'], $novo_saldo, "Compra de {$quantidade} cartelas"]);
			
			for ($i = 0; $i < $quantidade; $i++) {
				$numeros = [];
				$ranges = [[1,15], [16,30], [31,45], [46,60], [61,75]];
				foreach ($ranges as $range) {
					$coluna = [];
					while (count($coluna) < 5) {
						$num = rand($range[0], $range[1]);
						if (!in_array($num, $coluna)) {
							$coluna[] = $num;
						}
					}
					sort($coluna);
					$numeros = array_merge($numeros, $coluna);
				}
				$stmt = $pdo->prepare("INSERT INTO cartelas_compradas (usuario_id, sala_id, numeros, valor_pago) VALUES (?, ?, ?, ?)");
				$stmt->execute([$user_data['id'], $sala['id'], json_encode($numeros), $preco]);
			}
			$pdo->commit();
			$_SESSION['success'] = "Cartelas compradas com sucesso! Foram adicionadas {$quantidade} cartelas.";
			
			// REDIRECIONA APÓS A COMPRA PARA ATUALIZAR A TELA
			header('Location: main.php');
			exit;
		} catch (Exception $e) {
			$pdo->rollBack();
			$_SESSION['error'] = "Erro ao processar compra: " . $e->getMessage();
		}
	} else {
		$_SESSION['error'] = "Saldo insuficiente ou quantidade inválida.";
	}
}

/**
* REVISÃO DA FUNÇÃO check_bingo_wins:
* Verifica os padrões de Quadra, Quina e Cartela Cheia baseados no bingo 75.
* Assume cartela 5x5 com 25 números.
*/
function check_bingo_wins($cartela_numeros, $numeros_sorteados) {
	$matched_numbers = array_intersect($cartela_numeros, $numeros_sorteados);
	$numeros_acertados_set = array_flip($matched_numbers);
	$wins = [];
	
	// 1. VERIFICAÇÃO DE CARTELA CHEIA (BINGO) - 25 acertos (ou 24 se for free space)
	// Vamos assumir o padrão 24 acertos para ser consistente com o frontend (24 números)
	if (count($matched_numbers) >= 24) {
		$wins[] = 'Cartela cheia';
		return array_unique($wins);
	}

	// 2. VERIFICAÇÃO DE LINHAS (Quina) e Quadras
	for ($i = 0; $i < 5; $i++) {
		$acertos_linha = 0;
		for ($j = 0; $j < 5; $j++) {
			$num = $cartela_numeros[$i * 5 + $j];
			if (isset($numeros_acertados_set[$num])) {
				$acertos_linha++;
			}
		}
		
		if ($acertos_linha === 5) {
			$wins[] = 'Quina';
		} elseif ($acertos_linha === 4) {
			// Adiciona Quadra. A remoção de duplicados ocorrerá no final.
			$wins[] = 'Quadra';
		}
	}
	
	// 3. VERIFICAÇÃO DE COLUNAS (Quina) e Quadras
	for ($i = 0; $i < 5; $i++) {
		$acertos_coluna = 0;
		for ($j = 0; $j < 5; $j++) {
			$num = $cartela_numeros[$j * 5 + $i];
			if (isset($numeros_acertados_set[$num])) {
				$acertos_coluna++;
			}
		}
		
		if ($acertos_coluna === 5) {
			$wins[] = 'Quina';
		} elseif ($acertos_coluna === 4) {
			$wins[] = 'Quadra';
		}
	}
	
	// 4. VERIFICAÇÃO DE DIAGONAIS (Quina) e Quadras
	$diagonais = [
		[0, 6, 12, 18, 24], // Diagonal principal
		[4, 8, 12, 16, 20] // Diagonal secundária
	];
	
	foreach ($diagonais as $indices_diag) {
		$acertos_diag = 0;
		foreach ($indices_diag as $index) {
			$num = $cartela_numeros[$index];
			if (isset($numeros_acertados_set[$num])) {
				$acertos_diag++;
			}
		}
		
		if ($acertos_diag === 5) {
			$wins[] = 'Quina';
		} elseif ($acertos_diag === 4) {
			$wins[] = 'Quadra';
		}
	}
	
	return array_unique($wins);
}

$tempo_restante = 0;
if ($sala && $sala['status'] === 'aguardando') {
	$inicio = strtotime($sala['inicio_previsto']);
	$agora = time();
	$tempo_restante = max(0, $inicio - $agora);
}

$numeros_sorteados = [];
if ($sala && !empty($sala['numeros_sorteados'])) {
	$numeros_sorteados = json_decode($sala['numeros_sorteados'], true) ?: [];
}

$total_premio_ganho = 0;
if ($jogo_terminado) {
	if (count($cartelas) > 0) {
		// Busca o prêmio que foi pago (somente para exibição)
		$stmt_premio_final = $pdo->prepare("SELECT valor FROM transacoes WHERE usuario_id = ? AND tipo = 'premio_bingo' AND sala_id = ? ORDER BY id DESC LIMIT 1");
		$stmt_premio_final->execute([$user_data['id'], $sala['id']]);
		$total_premio_ganho = $stmt_premio_final->fetchColumn() ?: 0;
	}
}

$total_gasto = 0;
if (isset($cartelas) && !empty($cartelas)) {
	foreach ($cartelas as $cartela) {
		$total_gasto += $cartela['valor_pago'];
	}
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bingo Online TV</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
	/* Reset e Configurações Base */
	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
		-webkit-tap-highlight-color: transparent;
	}

	body {
		font-family: Arial, sans-serif;
		/* Tema Claro: Fundo principal */
		background-color: #f0f2f5; /* Um cinza muito claro, quase branco */
		min-height: 100vh;
		color: #333; /* Cor do texto principal escura */
		display: flex;
		-webkit-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
	}

	/* Cores de Destaque */
	:root {
		--primary-color: #3b82f6; /* Azul */
		--primary-hover: #2563eb;
		--secondary-color: #FFD700; /* Amarelo/Dourado do Bingo */
		--secondary-dark: #FFA500;
		--success-color: #10b981; /* Verde */
		--error-color: #ef4444; /* Vermelho */
		--neutral-bg-light: #ffffff; /* Fundo dos painéis claro */
		--neutral-bg-dark: #e9ecef; /* Um cinza um pouco mais escuro para elementos de fundo */
		--neutral-border: #dee2e6; /* Cor de borda para tema claro */
		--neutral-text: #495057; /* Texto neutro escuro */
	}

	/* SIDEBAR (Sem alterações, mantido o original para brevidade, mas está no código final) */
	.sidebar {
		width: 250px;
		background-color: var(--neutral-bg-light); /* Fundo branco/claro para sidebar */
		height: 100vh;
		position: fixed;
		left: 0;
		top: 0;
		z-index: 100;
		overflow-y: auto;
		box-shadow: 2px 0 5px rgba(0,0,0,0.1);
		border-right: 1px solid var(--neutral-border);
		transition: transform 0.3s ease;
	}

	.sidebar-header {
		padding: 20px;
		background-color: var(--neutral-bg-dark); /* Fundo um pouco mais escuro para o cabeçalho */
		border-bottom: 1px solid var(--neutral-border);
		text-align: center;
	}

	.logo {
		width: 100px;
		height: auto;
		margin-bottom: 10px;
		display: block;
		margin-left: auto;
		margin-right: auto;
	}

	.logo img {
		width: 100%;
		height: 100%;
		border-radius: 8px;
	}

	.sidebar-header h2 {
		color: var(--neutral-text); /* Título escuro */
		font-size: 20px;
		margin-bottom: 5px;
	}

	.user-info {
		color: var(--neutral-text); /* Info do usuário escuro */
		font-size: 12px;
		margin-bottom: 10px;
	}

	.user-balance {
		background-color: var(--primary-color); /* Manter destaque primário para o saldo */
		color: white;
		padding: 10px 15px;
		border-radius: 20px;
		font-size: 14px;
		font-weight: bold;
		margin-bottom: 15px;
	}

	.sidebar-menu {
		padding: 10px 0;
	}

	.menu-item {
		display: flex;
		align-items: center;
		padding: 12px 20px;
		color: var(--neutral-text); /* Texto do menu escuro */
		text-decoration: none;
		transition: background-color 0.3s ease, color 0.3s ease;
		border: none;
		background: none;
		width: 100%;
		cursor: pointer;
		font-size: 14px;
	}

	.menu-item:hover {
		background-color: var(--neutral-bg-dark); /* Fundo claro no hover */
		color: var(--primary-color);
	}

	.menu-item.active {
		background-color: var(--primary-color); /* Manter primário para ativo */
		color: white;
		border-left: 5px solid var(--secondary-color);
	}

	.menu-item img {
		width: 20px;
		height: 20px;
		margin-right: 10px;
		filter: none; /* Remover filtro para tema claro, se houver */
	}

	.deposit-btn {
		background-color: var(--success-color) !important;
		color: white !important;
		border: none !important;
		padding: 10px 15px !important;
		border-radius: 20px !important;
		font-weight: bold !important;
		cursor: pointer !important;
		transition: background-color 0.3s ease !important;
		text-decoration: none !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		gap: 8px !important;
		margin: 15px 20px !important;
		box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
	}

	.deposit-btn:hover {
		background-color: #059669 !important; /* Um verde um pouco mais escuro no hover */
	}

	/* BANNER SLIDER (Sem alterações, mantido o original) */
	.banner-slider {
		width: 100%;
		height: 180px;
		position: relative;
		overflow: hidden;
		background-color: var(--primary-color); /* Fundo do banner primário */
		margin-bottom: 20px;
		border-radius: 8px;
	}

	.banner-slide {
		position: absolute;
		width: 100%;
		height: 100%;
		opacity: 0;
		transition: opacity 1s ease-in-out;
		display: flex;
		align-items: center;
		justify-content: center;
		background-size: cover;
		background-position: center;
		background-repeat: no-repeat;
	}

	.banner-slide.active {
		opacity: 1;
	}

	.banner-content {
		text-align: center;
		color: white;
		text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
		z-index: 2;
		position: relative;
	}

	.banner-title {
		font-size: 2rem;
		font-weight: bold;
		margin-bottom: 5px;
	}

	.banner-subtitle {
		font-size: 1rem;
	}

	.banner-dots {
		position: absolute;
		bottom: 10px;
		left: 50%;
		transform: translateX(-50%);
		display: flex;
		gap: 8px;
		z-index: 3;
	}

	.banner-dot.active {
		background-color: var(--secondary-color); /* Dourado para dot ativo */
	}

	/* MAIN CONTENT AREA (Sem alterações, mantido o original) */
	.main-content {
		margin-left: 250px;
		flex: 1;
		padding: 20px;
		min-height: 100vh;
		background-color: #f0f2f5; /* Fundo do conteúdo principal claro */
		max-width: calc(100vw - 250px);
		overflow-x: hidden;
	}

	.mobile-header {
		display: none;
		align-items: center;
		justify-content: space-between;
		padding: 15px 20px;
		background-color: var(--neutral-bg-light); /* Fundo branco no mobile */
		border-bottom: 1px solid var(--neutral-border);
		position: sticky;
		top: 0;
		z-index: 10;
		box-shadow: 0 2px 4px rgba(0,0,0,0.05);
	}

	.menu-toggle {
		background: none;
		border: none;
		font-size: 24px;
		color: var(--primary-color);
		cursor: pointer;
	}

	/* GAME HEADER (Sem alterações, mantido o original) */
	.game-header {
		background-color: var(--neutral-bg-light); /* Fundo do cabeçalho do jogo claro */
		border-radius: 8px;
		padding: 20px;
		margin-bottom: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		border: 1px solid var(--neutral-border);
		display: flex;
		justify-content: space-around;
		align-items: center;
		flex-wrap: wrap; /* Adicionado para responsividade melhor */
	}
	
	/* Players Online (Sem alterações, mantido o original) */
	.players-online {
		text-align: center;
		padding: 10px;
		background-color: var(--neutral-bg-dark);
		border-radius: 6px;
		font-size: 14px;
		color: var(--neutral-text);
		font-weight: 600;
		margin-top: 10px;
		width: 100%; /* Ocupa a linha toda em telas pequenas */
	}
	
	.players-online i {
		color: var(--success-color);
		margin-right: 5px;
	}

	.game-info {
		text-align: center;
		color: var(--neutral-text); /* Texto escuro */
	}

	.game-info h3 {
		color: var(--primary-color);
		font-size: 16px;
		margin-bottom: 5px;
	}

	.countdown, .game-status {
		font-size: 2rem;
		font-weight: bold;
		color: var(--secondary-color); /* Amarelo no countdown */
		margin-bottom: 5px;
	}

	.game-status {
		color: var(--success-color); /* Verde no status */
	}

	.game-number {
		color: var(--neutral-text);
		font-size: 13px;
	}

	.last-ball {
		width: 80px;
		height: 80px;
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 20px;
		position: relative;
	}

	.last-ball img {
		width: 100%;
		height: 100%;
		object-fit: contain;
		filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));
	}

	.volume-toggle {
		background-color: var(--neutral-bg-dark);
		border: 1px solid var(--neutral-border);
		color: var(--primary-color);
		border-radius: 50%;
		width: 40px;
		height: 40px;
		cursor: pointer;
		transition: background-color 0.3s ease;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.volume-toggle:hover {
		background-color: var(--neutral-border);
	}

	.prizes {
		text-align: right;
		color: var(--neutral-text);
	}

	.prizes h4 {
		color: var(--primary-color);
		font-size: 14px;
		margin-bottom: 10px;
	}

	.prize-item {
		display: flex;
		justify-content: space-between;
		margin-bottom: 5px;
		font-size: 13px;
	}

	.prize-label {
		color: var(--neutral-text);
	}

	.prize-value {
		color: var(--success-color);
		font-weight: bold;
	}

	/* GAME CONTENT (Sem alterações, mantido o original) */
	.main-content .game-content {
		display: grid;
		grid-template-columns: 1fr;
		gap: 20px;
		max-width: 1200px;
		width: 100%;
		margin: 0 auto;
	}
	
	.bingo-cartelas-area {
		display: grid;
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.bingo-area {
		background-color: var(--neutral-bg-light); /* Fundo claro para área de bingo */
		border-radius: 8px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		border: 1px solid var(--neutral-border);
	}

	.drawn-numbers h4 {
		color: var(--neutral-text); /* Título escuro */
		font-size: 18px;
		margin-bottom: 15px;
		text-align: center;
	}

	.last-four-balls {
		display: flex;
		justify-content: center;
		gap: 10px;
		margin-bottom: 20px;
		padding: 10px;
		background-color: var(--neutral-bg-dark); /* Fundo um pouco mais escuro para as bolas */
		border-radius: 8px;
		border: 1px solid var(--neutral-border);
	}

	.ball-item {
		text-align: center;
	}

	.ball-position {
		width: 60px;
		height: 60px;
		margin-bottom: 5px;
	}

	.ball-position img {
		width: 100%;
		height: 100%;
		object-fit: contain;
	}

	.ball-label {
		color: var(--neutral-text);
		font-size: 10px;
	}

	/* CARTELA DE BINGO 1-75 (Sem alterações, mantido o original) */
	.bingo-board {
		margin-bottom: 20px;
	}

	.bingo-board h4 {
		color: var(--neutral-text);
		font-size: 16px;
		margin-bottom: 10px;
		text-align: center;
	}

	.numbers-board {
		display: grid;
		grid-template-columns: repeat(15, 1fr);
		gap: 3px;
		padding: 10px;
		background-color: var(--neutral-bg-dark); /* Fundo um pouco mais escuro para a grade */
		border-radius: 8px;
		border: 1px solid var(--neutral-border);
	}

	.board-number {
		aspect-ratio: 1;
		background-color: var(--neutral-bg-light); /* Fundo branco/claro para números não sorteados */
		border: 1px solid var(--neutral-border);
		border-radius: 4px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 10px;
		font-weight: bold;
		color: var(--neutral-text);
		transition: background-color 0.3s ease, color 0.3s ease;
	}

	.board-number.drawn {
		background-color: var(--secondary-color); /* Amarelo para números sorteados */
		color: #333; /* Texto escuro na bola amarela */
		border-color: var(--secondary-dark);
	}

	/* RESULT SECTION (Sem alterações, mantido o original) */
	.result-section {
		background-color: var(--success-color); /* Verde para a seção de resultado */
		border-radius: 8px;
		padding: 20px;
		margin-top: 20px;
		text-align: center;
		color: white;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
	}

	.result-section h4 {
		color: white;
		font-size: 20px;
		margin-bottom: 10px;
	}

	.result-section .total-premio {
		font-size: 2rem;
		font-weight: bold;
		color: var(--secondary-color); /* Dourado para o valor do prêmio */
	}

	/* WAITING MESSAGE (Removido, a lógica final está na ending-message) */
	/* .waiting-message { ... } */

	/* MENSAGEM DE ENCERRAMENTO ESPECÍFICA (Ajustado) */
	.ending-message {
		background: linear-gradient(135deg, #dc3545, #e74c3c); /* Gradiente vermelho - BOT VENCEDOR */
		border-radius: 12px;
		padding: 25px;
		margin-bottom: 20px;
		text-align: center;
		color: white;
		box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
		border: 2px solid rgba(255, 255, 255, 0.2);
		animation: pulseGlow 2s infinite alternate;
		display: none; /* Inicialmente oculto */
	}

	.ending-message h3 {
		font-size: 1.8rem;
		margin-bottom: 15px;
		text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
	}

	.ending-message p {
		font-size: 1.1rem;
		margin-bottom: 10px;
		opacity: 0.95;
	}

	.ending-message.winner-user {
		background: linear-gradient(135deg, #28a745, #20c997); /* Verde para vitória do usuário */
		box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
	}

	@keyframes pulseGlow {
		0% {
			box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
		}
		100% {
			box-shadow: 0 12px 35px rgba(220, 53, 69, 0.6);
		}
	}

	/* Toast especial para bingo do usuário (Mantido) */
	.toast.user-bingo {
		background: linear-gradient(135deg, #28a745, #20c997);
		border-color: #1e7e34;
		color: white;
		font-size: 16px;
		padding: 15px 20px;
		box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
		animation: bounceIn 0.6s ease-out;
	}

	@keyframes bounceIn {
		0% {
			opacity: 0;
			transform: translateX(300px) scale(0.3);
		}
		50% {
			opacity: 1;
			transform: translateX(-10px) scale(1.05);
		}
		70% {
			transform: translateX(5px) scale(0.98);
		}
		100% {
			transform: translateX(0) scale(1);
		}
	}

	/* CARTELAS (Sem alterações, mantido o original) */
	.cartelas-section h4 {
		color: var(--neutral-text);
		font-size: 18px;
		margin-bottom: 15px;
		text-align: center;
	}

	/* CARTELAS MENORES - 3 POR COLUNA (Sem alterações, mantido o original) */
	.cartelas-container {
		display: grid;
		grid-template-columns: repeat(3, 1fr); /* Sempre 3 colunas */
		gap: 15px;
		max-width: 800px; /* Limita a largura para cartelas menores */
		margin: 0 auto;
	}

	.cartela {
		background-color: var(--neutral-bg-light); /* Fundo claro para cartelas */
		border: 1px solid var(--neutral-border);
		border-radius: 8px;
		padding: 10px; /* Padding menor */
		box-shadow: 0 2px 5px rgba(0,0,0,0.05);
		transition: transform 0.2s ease, border-color 0.2s ease;
	}

	.cartela:hover {
		transform: translateY(-3px);
		border-color: var(--primary-color);
	}

	.cartela-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px; /* Margin menor */
		font-size: 11px; /* Fonte menor */
		color: var(--neutral-text);
	}

	.cartela-grid {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 2px; /* Gap menor */
	}

	.cartela-number {
		aspect-ratio: 1;
		background-color: var(--neutral-bg-dark);
		border: 1px solid var(--neutral-border);
		border-radius: 3px; /* Border radius menor */
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 9px; /* Fonte menor */
		font-weight: bold;
		color: var(--neutral-text);
		transition: background-color 0.3s ease;
	}

	.cartela-number.marked {
		background-color: var(--secondary-color);
		color: #333;
		border-color: var(--secondary-dark);
	}

	/* PURCHASE PANEL (Sem alterações, mantido o original) */
	.purchase-panel {
		background-color: var(--neutral-bg-light); /* Fundo claro para painel de compra */
		border-radius: 8px;
		padding: 20px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		border: 1px solid var(--neutral-border);
		position: static;	
		width: 100%;
	}

	.balance-info {
		text-align: center;
		margin-bottom: 20px;
		padding: 15px;
		background-color: var(--primary-color); /* Fundo primário para info de saldo no painel de compra */
		border-radius: 10px;
		color: white;
	}

	.balance-amount {
		font-size: 1.8rem;
		font-weight: bold;
		color: var(--secondary-color); /* Dourado para o saldo */
		margin-bottom: 5px;
	}

	.cartelas-compradas {
		font-size: 13px;
		color: rgba(255,255,255,0.9);
	}

	.price-options h4 {
		color: var(--neutral-text);
		font-size: 15px;
		margin-bottom: 10px;
		text-align: center;
	}

	.price-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr); /* Aumentei para 4 colunas */
		gap: 10px;
		margin-bottom: 15px;
	}

	.price-btn {
		padding: 10px;
		border: 1px solid var(--neutral-border);
		border-radius: 8px;
		background-color: var(--neutral-bg-dark); /* Fundo claro para botões de preço */
		color: var(--neutral-text);
		font-size: 12px;
		font-weight: bold;
		cursor: pointer;
		transition: background-color 0.3s ease, border-color 0.3s ease;
	}

	.price-btn:hover:not(:disabled) {
		background-color: var(--neutral-border);
		border-color: var(--primary-color);
	}

	.price-btn.active {
		background-color: var(--primary-color); /* Azul para botão de preço ativo */
		color: white;
		border-color: var(--primary-color);
	}

	.price-btn:disabled {
		opacity: 0.6;
		cursor: not-allowed;
		background-color: #999;
	}

	.quantity-control {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 15px;
		margin-bottom: 20px;
	}

	.qty-btn {
		width: 40px;
		height: 40px;
		border: 1px solid var(--primary-color);
		border-radius: 50%;
		background-color: var(--neutral-bg-dark);
		color: var(--primary-color);
		font-size: 18px;
		font-weight: bold;
		cursor: pointer;
		transition: background-color 0.3s ease, color 0.3s ease;
	}

	.qty-btn:hover:not(:disabled) {
		background-color: var(--primary-color);
		color: white;
	}

	.qty-btn:disabled {
		opacity: 0.5;
		cursor: not-allowed;
		background-color: var(--neutral-bg-dark);
		color: #999;
		border-color: #999;
	}

	.qty-display {
		font-size: 2rem;
		font-weight: bold;
		color: var(--primary-color);
		min-width: 50px;
		text-align: center;
	}

	.buy-btn {
		width: 100%;
		padding: 15px;
		background-color: var(--success-color); /* Verde para botão de compra */
		border: none;
		border-radius: 10px;
		color: white;
		font-size: 15px;
		font-weight: bold;
		cursor: pointer;
		transition: background-color 0.3s ease;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
	}

	.buy-btn:hover:not(:disabled) {
		background-color: #059669; /* Verde mais escuro no hover */
	}

	.buy-btn:disabled {
		opacity: 0.6;
		cursor: not-allowed;
		background-color: #999;
	}

	/* STATUS MESSAGES (Sem alterações, mantido o original) */
	.status-message {
		padding: 12px 15px;
		border-radius: 8px;
		margin-bottom: 15px;
		text-align: center;
		font-weight: bold;
		border: 1px solid;
	}

	.status-message.game-running {
		background-color: rgba(255, 165, 0, 0.1); /* Laranja claro */
		border-color: var(--secondary-dark);
		color: #b36a00; /* Texto laranja escuro */
	}

	.status-message.game-finished {
		background-color: rgba(46, 213, 115, 0.1); /* Verde claro */
		border-color: var(--success-color);
		color: #0d7751; /* Texto verde escuro */
	}

	.status-message.limit-reached {
		background-color: rgba(239, 68, 68, 0.1); /* Vermelho claro */
		border-color: var(--error-color);
		color: #c22b2b; /* Texto vermelho escuro */
	}

	/* TOAST (Ajuste) */
	.toast-container {
		position: fixed;
		top: 20px;
		right: 20px;
		z-index: 1000;
	}
	
	.toast {
		padding: 12px 18px;
		border-radius: 8px;
		color: white;
		font-weight: bold;
		box-shadow: 0 5px 15px rgba(0,0,0,0.1);
		transform: translateX(300px);
		transition: all 0.3s ease-in-out;
		z-index: 1000;
		max-width: 300px;
		border: 1px solid;
		margin-bottom: 10px; /* Garante que os toasts não se sobreponham (embora só deva haver 1) */
	}

	.toast.show {
		transform: translateX(0);
	}

	.toast.success {
		background-color: var(--success-color);
		border-color: #059669;
	}

	.toast.error {
		background-color: var(--error-color);
		border-color: #b91c1c;
	}

	.toast.info {
		background-color: var(--primary-color);
		border-color: #2563eb;
	}
	
	/* NOVO ESTILO: Toast para Feedback de Bots (Cor Vermelha/Diferente) */
	.toast.bot-feedback {
		background-color: #dc3545; /* Vermelho mais suave */
		border-color: #c82333;
		color: white;
		box-shadow: 0 5px 15px rgba(220, 53, 69, 0.5);
	}

	/* OVERLAY (Sem alterações, mantido o original) */
	.overlay {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0,0,0,0.4); /* Overlay semi-transparente escuro */
		z-index: 99;
		opacity: 0;
		visibility: hidden;
		transition: opacity 0.3s ease, visibility 0.3s ease;
		pointer-events: none;
	}

	.overlay.show {
		opacity: 1;
		visibility: visible;
		pointer-events: auto;
	}

	/* RESPONSIVE MOBILE (Sem alterações, mantido o original) */
	@media (max-width: 768px) {
		body {
			flex-direction: column;
		}
		.sidebar {
			transform: translateX(-100%);
			width: 100%;
			max-width: 250px;
			box-shadow: 2px 0 5px rgba(0,0,0,0.2);
		}

		.sidebar.open {
			transform: translateX(0);
		}

		.main-content {
			margin-left: 0;
			max-width: 100vw;
			padding: 15px;
			background-color: #f0f2f5;
		}

		.game-content {
			grid-template-columns: 1fr;
			gap: 15px;
		}

		.game-header {
			flex-direction: column;
			padding: 15px;
			margin-bottom: 15px;
		}

		.game-info, .prizes {
			width: 100%;
			margin-bottom: 10px;
			text-align: center;
		}
		.prizes { text-align: center; }

		.last-ball {
			margin: 10px auto;
		}

		.mobile-header {
			display: flex;
		}

		.banner-slider {
			height: 120px;
		}

		.banner-title {
			font-size: 1.5rem;
		}

		.banner-subtitle {
			font-size: 0.9rem;
		}

		.numbers-board {
			grid-template-columns: repeat(10, 1fr);
		}

		.purchase-panel {
			position: static;
			top: auto;
		}
		
		/* Garante que o painel de compra apareça como um bloco no mobile */
		.main-content .game-content .purchase-panel {
				grid-column: 1 / -1;
		}

		/* CARTELAS MOBILE - 2 POR COLUNA */
		.cartelas-container {
			grid-template-columns: repeat(2, 1fr); /* 2 colunas no mobile */
			max-width: none; /* Remove limite de largura no mobile */
		}
	}

	/* EMPTY STATE (Sem alterações, mantido o original) */
	.empty-cartelas {
		text-align: center;
		padding: 30px 20px;
		color: var(--neutral-text);
		font-size: 14px;
		background-color: var(--neutral-bg-dark);
		border-radius: 8px;
		border: 1px dashed var(--neutral-border);
		margin-top: 20px;
	}

	.empty-cartelas i {
		font-size: 3rem;
		margin-bottom: 15px;
		color: #ccc;
	}
</style>
</head>
<body>
	<div class="sidebar" id="sidebar">
		<div class="sidebar-header">
			<a href="main.php" class="logo">
				<img src="assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
			</a>
			<h2>BINGO ONLINE</h2>
			<div class="user-info">Olá, <?php echo htmlspecialchars($user_data['nome_completo']); ?></div>
			<div class="user-balance">Saldo: R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></div>
			<a href="depositar.php" class="deposit-btn">
				<i class="fas fa-plus"></i>
				DEPOSITAR
			</a>
			<?php if ($user_data['is_admin']): ?>
			<a href="dash/" class="menu-item">
				<img src="assets/icons/settings.png" alt="Administrativo" onerror="this.style.display='none'">
				Administrativo
			</a>
			<?php endif; ?>
		</div>
		<nav class="sidebar-menu">
			<a href="main.php" class="menu-item active">
				<img src="assets/icons/salas.png" alt="Salas" onerror="this.style.display='none'">
				Salas
			</a>
			<a href="perfil.php" class="menu-item">
				<img src="assets/icons/perfil.png" alt="Perfil" onerror="this.style.display='none'">
				Perfil
			</a>
			<a href="depositar.php" class="menu-item">
				<img src="assets/icons/depositar.png" alt="Depositar" onerror="this.style.display='none'">
				Depositar
			</a>
			<a href="sacar.php" class="menu-item">
				<img src="assets/icons/sacar.png" alt="Sacar" onerror="this.style.display='none'">
				Sacar
			</a>
			<a href="historico.php" class="menu-item">
				<img src="assets/icons/historico.png" alt="Histórico" onerror="this.style.display='none'">
				Histórico
			</a>
			<a href="<?php echo htmlspecialchars($url_suporte); ?>" class="menu-item">
				<img src="assets/icons/suporte.png" alt="Suporte" onerror="this.style.display='none'">
				Suporte
			</a>
			<button class="menu-item" onclick="logout()">
				<img src="assets/icons/sair.png" alt="Sair" onerror="this.style.display='none'">
				Sair
			</button>
		</nav>
	</div>
	
	<div class="overlay" id="overlay"></div>
	
	<div class="main-content">
		<div class="mobile-header">
			<button class="menu-toggle" id="menuToggle">☰</button>
			</div>

		<div class="banner-slider" id="bannerSlider">
			<?php if (empty($banners)): ?>
				<div class="banner-slide active">
					<div class="banner-content">
						<h1 class="banner-title">BINGO ONLINE</h1>
						<p class="banner-subtitle">Diversão e Prêmios te Aguardam!</p>
					</div>
				</div>
			<?php else: ?>
				<?php foreach ($banners as $index => $banner): ?>
					<div class="banner-slide <?php echo $index === 0 ? 'active' : ''; ?>"
						style="background-image: url('uploads/banners/<?php echo htmlspecialchars($banner['imagem']); ?>');">
						<div class="banner-content">
							<h1 class="banner-title"><?php echo htmlspecialchars($banner['titulo']); ?></h1>
							<p class="banner-subtitle"><?php echo htmlspecialchars($banner['subtitulo']); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
				
				<?php if (count($banners) > 1): ?>
				<div class="banner-dots" id="bannerDots">
					<?php foreach ($banners as $index => $banner): ?>
						<div class="banner-dot <?php echo $index === 0 ? 'active' : ''; ?>"
							onclick="goToSlide(<?php echo $index; ?>)"></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="game-header">
			<div class="game-info">
				<h3>
					<?php
					if ($jogo_terminado) {
						echo 'Sorteio Finalizado';
					} elseif ($sala && $sala['status'] === 'em_andamento') {
						echo 'Sorteio em Andamento';
					} elseif ($aguardando_proximo) {
						echo 'Aguarde Próximo Jogo';
					} else {
						echo 'Próximo Sorteio';
					}
					?>
				</h3>
				<?php if ($jogo_terminado): ?>
					<div class="game-status" id="game-status-message">
						Resultados!
					</div>
				<?php elseif ($aguardando_proximo): ?>
					<div class="game-status" id="game-status-message">
						Iniciando...
					</div>
				<?php elseif ($sala && $sala['status'] === 'em_andamento'): ?>
					<div class="game-status">Sorteando...</div>
				<?php else: ?>
					<div class="countdown" id="countdown">--:--</div>
				<?php endif; ?>
				<div class="game-number">Sorteio #<?php echo $sala['numero_sorteio'] ?? '000000'; ?></div>
				<div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 4px;">
					<?php
					if ($sala) {
						$data_brasilia = new DateTime($sala['inicio_previsto'] ?? 'now');
						echo "Início Previsto: " . $data_brasilia->format('d/m H:i');
					}
					?>
				</div>
			</div>
			
			<div class="game-info">
				<h3>Última Bola</h3>
				<div class="last-ball" id="lastBall">
					<?php
					if (!empty($numeros_sorteados)) {
						$last_number = end($numeros_sorteados);
						echo '<img src="assets/images/balls/' . $last_number . '.png" alt="Bola ' . $last_number . '" onerror="this.src=\'assets/images/balls/interrogacao.png\'">';
					} else {
						echo '<img src="assets/images/balls/interrogacao.png" alt="Bola" onerror="this.style.display=\'none\'">';
					}
					?>
				</div>
				<button id="volumeToggle" class="volume-toggle" aria-label="Toggle audio">
					<i class="fas fa-volume-up"></i>
				</button>
			</div>
			
			<div class="prizes">
				<h4>Prêmios</h4>
				<div class="prize-item">
					<span class="prize-label">Quadra:</span>
					<span class="prize-value">R$ <?php echo number_format($sala['premio_quadra'] ?? 20.00, 2, ',', '.'); ?></span>
				</div>
				<div class="prize-item">
					<span class="prize-label">Quina:</span>
					<span class="prize-value">R$ <?php echo number_format($sala['premio_quina'] ?? 30.00, 2, ',', '.'); ?></span>
				</div>
				<div class="prize-item">
					<span class="prize-label">Cartela Cheia:</span>
					<span class="prize-value">R$ <?php echo number_format($sala['premio_cartela_cheia'] ?? 60.00, 2, ',', '.'); ?></span>
				</div>
				<div style="font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 8px;">
					Taxa: R$ 0,10
				</div>
			</div>
			
			<div class="players-online">
				<i class="fas fa-users"></i> <span id="playersOnlineCount">0</span> Jogadores Online
			</div>
		</div>

		<div class="ending-message" id="endingMessage">
			</div>
		
		<div class="game-content">
			<div class="bingo-area">
				<div class="drawn-numbers">
					<h4>Últimas 4 Bolas Sorteadas</h4>
					<div class="last-four-balls">
						<?php
						$last_four = array_slice($numeros_sorteados, -4);
						$positions = ['4ª', '3ª', '2ª', 'Última'];
						for ($i = 0; $i < 4; $i++) {
							$ball_number = isset($last_four[$i]) ? $last_four[$i] : null;
							echo '<div class="ball-item">';
							echo '<div class="ball-position">';
							if ($ball_number) {
								echo '<img src="assets/images/balls/' . $ball_number . '.png" alt="Bola ' . $ball_number . '" onerror="this.style.display=\'none\'">';
							} else {
								echo '<img src="assets/images/balls/interrogacao.png" alt="Vazio" onerror="this.style.display=\'none\'">';
							}
							echo '</div>';
							echo '<div class="ball-label">' . $positions[$i] . '</div>';
							echo '</div>';
						}
						?>
					</div>
				</div>

				<div class="bingo-board">
					<h4>Cartela de Bingo (1-75)</h4>
					<div class="numbers-board" id="bingoBoard">
						<?php for ($i = 1; $i <= 75; $i++): ?>
							<div class="board-number <?php echo in_array($i, $numeros_sorteados) ? 'drawn' : ''; ?>"
								data-number="<?php echo $i; ?>">
								<?php echo $i; ?>
							</div>
						<?php endfor; ?>
					</div>
				</div>
				
				<?php if ($jogo_terminado && $total_premio_ganho > 0): ?>
					<div class="result-section">
						<h4>VOCÊ GANHOU!</h4>
						<div class="total-premio">
							R$ <?php echo number_format($total_premio_ganho, 2, ',', '.'); ?>
						</div>
						<p style="font-size: 14px; color: rgba(255,255,255,0.9); margin-top: 10px;">
							Seu saldo foi atualizado automaticamente.
						</p>
					</div>
				<?php endif; ?>

				<div class="cartelas-section">
					<h4>Suas Cartelas (<?php echo count($cartelas); ?>)</h4>
					<?php if (empty($cartelas)): ?>
						<div class="empty-cartelas">
							<i class="fas fa-th-large"></i>
							<p>Você ainda não possui cartelas para este sorteio.</p>
							<p>Compre suas cartelas e boa sorte!</p>
						</div>
					<?php else: ?>
						<div class="cartelas-container">
							<?php
							foreach ($cartelas as $index => $cartela):
								$numeros = json_decode($cartela['numeros'], true);
							?>
								<div class="cartela">
									<div class="cartela-header">
										<span>Cartela <?php echo $index + 1; ?></span>
										<span>R$ <?php echo number_format($cartela['valor_pago'], 2, ',', '.'); ?></span>
									</div>
									<div class="cartela-grid">
										<?php foreach ($numeros as $numero): ?>
											<div class="cartela-number <?php echo in_array($numero, $numeros_sorteados) ? 'marked' : ''; ?>" data-number="<?php echo $numero; ?>">
												<?php echo $numero; ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="purchase-panel">
				<div class="balance-info">
					<div class="balance-amount">
						R$ <?php echo number_format($total_gasto, 2, ',', '.'); ?>
					</div>
					<div class="cartelas-compradas">GASTO NESTE JOGO: <?php echo count($cartelas); ?> CARTELAS</div>
				</div>

				<form method="POST" id="compraForm">
					<?php
					$jogo_em_andamento_js = ($sala && $sala['status'] === 'em_andamento') || !empty($numeros_sorteados);
					$pode_comprar = !$jogo_em_andamento_js && !$jogo_terminado && !$aguardando_proximo && $pode_comprar_mais;
					$limite_atingido = $cartelas_compradas_agora >= $max_cartelas;
					?>
					
					<?php if ($jogo_em_andamento_js && !$jogo_terminado): ?>
						<div class="status-message game-running">
							<div style="font-weight: 700; margin-bottom: 4px;">Jogo em Andamento</div>
							<div style="font-size: 13px;">Não é possível comprar cartelas durante o sorteio</div>
						</div>
					<?php endif; ?>
					
					<?php if ($jogo_terminado || $aguardando_proximo): ?>
						<div class="status-message game-finished">
							<div style="font-weight: 700; margin-bottom: 4px;">Sorteio Finalizado</div>
							<div style="font-size: 13px;">Compre novas cartelas para o próximo jogo!</div>
						</div>
					<?php endif; ?>
					
					<?php if ($limite_atingido && !$jogo_em_andamento_js && !$jogo_terminado && !$aguardando_proximo): ?>
						<div class="status-message limit-reached">
							<div style="font-weight: 700; margin-bottom: 4px;">Limite de Cartelas Atingido</div>
							<div style="font-size: 13px;">Você já comprou o máximo de <?php echo $max_cartelas; ?> cartelas.</div>
						</div>
					<?php endif; ?>

					<div class="price-options">
						<h4>Valor da Cartela</h4>
						<div class="price-grid">
							<?php foreach ($valores_cartela as $index => $valor): ?>
								<button type="button" class="price-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-price="<?php echo $valor; ?>" <?php echo !$pode_comprar ? 'disabled' : ''; ?>>
									R$ <?php echo $valor; ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
					
					<div class="quantity-control">
						<button type="button" class="qty-btn" onclick="changeQuantity(-1)" <?php echo (!$pode_comprar || $cartelas_compradas_agora >= $max_cartelas) ? 'disabled' : ''; ?>>
							<i class="fas fa-minus"></i>
						</button>
						<span class="qty-display" id="quantity">1</span>
						<button type="button" class="qty-btn" onclick="changeQuantity(1)" <?php echo (!$pode_comprar || $cartelas_compradas_agora >= $max_cartelas) ? 'disabled' : ''; ?>>
							<i class="fas fa-plus"></i>
						</button>
					</div>
					
					<input type="hidden" name="preco" id="precoInput" value="<?php echo $valores_cartela[0]; ?>">
					<input type="hidden" name="quantidade" id="quantidadeInput" value="1">
					<button type="submit" name="comprar" class="buy-btn" id="buyBtn" <?php echo !$pode_comprar ? 'disabled' : ''; ?>>
						Comprar Cartelas
					</button>
				</form>
			</div>
		</div>
	</div>

	<div class="toast-container" id="toastContainer">
		<?php if (isset($_SESSION['success'])): ?>
			<div class="toast show success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
		<?php endif; ?>
		<?php if (isset($_SESSION['error'])): ?>
			<div class="toast show error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
		<?php endif; ?>
	</div>
	<audio id="audio3Minutos" src="assets/audio/audio_3_minutos.mp3"></audio>
	<audio id="audioApostasEncerradas" src="assets/audio/audio_apostas_encerradas.mp3"></audio>
	
	<audio id="audioQuadra" src="assets/audio/audio_quadra.mp3"></audio>
	<audio id="audioQuina" src="assets/audio/audio_quina.mp3"></audio>
	<audio id="audioBingo" src="assets/audio/audio_bingo.mp3"></audio>
	<audio id="audioBola"></audio> 

	<script>
		// Configurações de jogo
		let playersOnlineBase = <?php echo $players_online_base; ?>;
		let botNames = <?php echo json_encode($bots_nomes_sorteados); ?>;
		
		// Estados de vitória do bot para forçar a sequência Quadra -> Quina -> Bingo
		const salaKey = '<?php echo $sala['id'] ?? 0; ?>';
		let botQuadraAnnounced = localStorage.getItem(`botQuadraAnnounced_${salaKey}`) === 'true';
		let botQuinaAnnounced = localStorage.getItem(`botQuinaAnnounced_${salaKey}`) === 'true';
		
		let botBingoWinner = localStorage.getItem(`botBingoWinner_${salaKey}`) || botNames[Math.floor(Math.random() * botNames.length)];
		
		// Garante que o vencedor do Bingo esteja salvo
		localStorage.setItem(`botBingoWinner_${salaKey}`, botBingoWinner);
		
		// Se a sala é nova e não tem números, reseta os estados de vitória
		if (<?php echo empty($numeros_sorteados) ? 'true' : 'false'; ?>) {
			localStorage.removeItem(`botQuadraAnnounced_${salaKey}`);
			localStorage.removeItem(`botQuinaAnnounced_${salaKey}`);
			if (!localStorage.getItem(`botBingoWinner_${salaKey}`)) {
				botBingoWinner = botNames[Math.floor(Math.random() * botNames.length)];
				localStorage.setItem(`botBingoWinner_${salaKey}`, botBingoWinner);
			}
			botQuadraAnnounced = false;
			botQuinaAnnounced = false;
		}

		let tempoRestante = <?php echo $tempo_restante; ?>;
		let selectedPrice = <?php echo $valores_cartela[0]; ?>;
		let quantity = 1;
		let userBalance = <?php echo $user_data['saldo']; ?>;
		let cartelas = <?php echo json_encode(array_map(function($c) {
				$c['numeros'] = json_decode($c['numeros'], true);
				return $c;
			}, $cartelas)); ?>;
		let drawnNumbers = <?php echo json_encode($numeros_sorteados); ?>;
		let jogoEmAndamento = <?php echo ($sala && $sala['status'] === 'em_andamento') ? 'true' : 'false'; ?>;
		let jogoTerminado = <?php echo $jogo_terminado ? 'true' : 'false'; ?>;
		let aguardandoProximo = <?php echo $aguardando_proximo ? 'true' : 'false'; ?>;
		let cartelasCompradas = <?php echo $cartelas_compradas_agora; ?>;
		let maxCartelas = <?php echo $max_cartelas; ?>;
		let salaId = <?php echo $sala['id'] ?? 'null'; ?>;
		let velocidadeSorteio = <?php echo $velocidade_sorteio; ?>;
		let ativarBots = <?php echo $ativar_bots ? 'true' : 'false'; ?>;
		let ativarNarracao = <?php echo $ativar_narracao ? 'true' : 'false'; ?>;
		
		// Rastreio avançado de Quadra/Quina por linha/coluna/diagonal
		// Estrutura: { cartelaId_db: { "L0": "Quina", "D1": "Quadra" } }
		// L0-L4: Linhas | C0-C4: Colunas | D0-D1: Diagonais
		let userPrizesAnnounced = JSON.parse(localStorage.getItem(`userPrizesAnnounced_${salaKey}`)) || {};

		// Inicializa o rastreio para as cartelas atuais
		cartelas.forEach(cartela => {
			if (!userPrizesAnnounced[cartela.id]) {
				userPrizesAnnounced[cartela.id] = {};
			}
		});

		// Persiste o estado atualizado no localStorage
		function saveUserPrizesAnnounced() {
			localStorage.setItem(`userPrizesAnnounced_${salaKey}`, JSON.stringify(userPrizesAnnounced));
		}

		// Banner Slider e Menu Mobile (código omitido para brevidade, mas deve estar no código final)
		let currentSlide = 0;
		const slides = document.querySelectorAll('.banner-slide');
		const dots = document.querySelectorAll('.banner-dot');

		function goToSlide(slideIndex) {
			if (slides.length <= 1) return;
			slides[currentSlide].classList.remove('active');
			if (dots.length > 0) dots[currentSlide].classList.remove('active');
			currentSlide = slideIndex;
			slides[currentSlide].classList.add('active');
			if (dots.length > 0) dots[currentSlide].classList.add('active');
		}

		function nextSlide() {
			if (slides.length <= 1) return;
			const nextIndex = (currentSlide + 1) % slides.length;
			goToSlide(nextIndex);
		}

		if (slides.length > 1) {
			setInterval(nextSlide, 5000);
		}

		// Áudios
		const audio3Minutos = document.getElementById('audio3Minutos');
		const audioApostasEncerradas = document.getElementById('audioApostasEncerradas');	
		const audioQuadra = document.getElementById('audioQuadra');
		const audioQuina = document.getElementById('audioQuina');
		const audioBingo = document.getElementById('audioBingo');
		const audioBola = document.getElementById('audioBola');
		
		let isMuted = localStorage.getItem('isMuted') === 'true';

		function playAudio(audioElement) {
			if (isMuted || !ativarNarracao || !audioElement) {
				return;
			}
			audioElement.currentTime = 0;
			audioElement.play()
				.then(() => {})
				.catch(e => { console.error("Erro ao tocar áudio:", e); });
		}
		
		function playBallAudio(numero) {
			if (isMuted || !ativarNarracao) return;

			audioBola.src = `assets/audio/bolas/${numero}.mp3`;
			
			audioBola.load();
			audioBola.oncanplaythrough = function() {
				audioBola.play().catch(e => console.error("Erro ao tocar áudio da bola:", e));
			};
		}


		const toastContainer = document.getElementById('toastContainer');
		
		function showToast(message, type = 'success', duration = 3000) {
			const toast = document.createElement('div');
			toast.className = `toast ${type}`;
			toast.textContent = message;
			
			if (toastContainer) {
				const existingToasts = toastContainer.querySelectorAll('.toast');
				existingToasts.forEach(t => t.remove());
				
				toastContainer.appendChild(toast);
				
				setTimeout(() => {
					toast.classList.add('show');
				}, 50);
			}

			setTimeout(() => {
				toast.classList.remove('show');
				setTimeout(() => {
					if (toast.parentNode === toastContainer) {
						toastContainer.removeChild(toast);
					}
				}, 400);
			}, duration);
		}

		document.querySelectorAll('.toast-container .toast').forEach(toast => {
			setTimeout(() => {
				toast.classList.remove('show');
				setTimeout(() => {
					toast.remove();
				}, 400);
			}, 4000);
		});

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

		document.querySelectorAll('.menu-item').forEach(item => {
			item.addEventListener('click', () => {
				if (window.innerWidth <= 768) {
					sidebar.classList.remove('open');
					overlay.classList.remove('show');
				}
			});
		});

		document.querySelectorAll('.price-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				document.querySelectorAll('.price-btn').forEach(b => b.classList.remove('active'));
				this.classList.add('active');
				selectedPrice = parseFloat(this.dataset.price);
				document.getElementById('precoInput').value = selectedPrice;
				updateButton();
			});
		});

		function changeQuantity(delta) {
			const newQty = quantity + delta;
			const maxAllowed = maxCartelas - cartelasCompradas;
			if (newQty >= <?php echo $min_cartelas; ?> && newQty <= maxAllowed) {
				quantity = newQty;
				document.getElementById('quantity').textContent = quantity;
				document.getElementById('quantidadeInput').value = quantity;
				updateButton();
			}
		}

		function updateButton() {
			const total = selectedPrice * quantity;
			const btn = document.getElementById('buyBtn');
			const maxAllowed = maxCartelas - cartelasCompradas;
			const canBuy = !jogoEmAndamento && !jogoTerminado && !aguardandoProximo && cartelasCompradas < maxCartelas;
			
			const qtyMinusBtn = document.querySelector('.qty-btn:first-child');
			const qtyPlusBtn = document.querySelector('.qty-btn:last-child');
			
			if (qtyMinusBtn) qtyMinusBtn.disabled = !canBuy || quantity <= 1;
			if (qtyPlusBtn) qtyPlusBtn.disabled = !canBuy || quantity >= maxAllowed;
			
			document.querySelectorAll('.price-btn').forEach(b => b.disabled = !canBuy);
			
			if (!canBuy) {
				btn.disabled = true;
				if(jogoEmAndamento) {
					btn.textContent = 'Jogo em Andamento';
				} else if (jogoTerminado || aguardandoProximo) {
					btn.textContent = 'Sorteio Finalizado';
				} else if (cartelasCompradas >= maxCartelas) {
					btn.textContent = `Limite de Cartelas Atingido`;
				} else {
					btn.textContent = 'Comprar Cartelas';
				}
				return;
			}
			
			btn.textContent = `Comprar ${quantity} Cartela${quantity > 1 ? 's' : ''} - R$ ${total.toFixed(2)}`;
			
			if (total > userBalance) {
				btn.disabled = true;
				btn.textContent = 'Saldo Insuficiente';
			} else {
				btn.disabled = false;
			}
		}

		let audio3MinutosPlayed = false;
		let audioApostasEncerradasPlayed = false;

		function updateCountdown() {
			if (tempoRestante > 0) {
				const minutes = Math.floor(tempoRestante / 60);
				const seconds = tempoRestante % 60;
				const countdownEl = document.getElementById('countdown');
				
				if (tempoRestante === 180 && !audio3MinutosPlayed) {
					playAudio(audio3Minutos);
					showToast('3 minutos para o sorteio! Compre suas cartelas!', 'info');
					audio3MinutosPlayed = true;
				}
				
				if (tempoRestante === 15 && !audioApostasEncerradasPlayed) {
					playAudio(audioApostasEncerradas);
					// Toast de encerramento de apostas
					showToast('Apostas encerradas! Preparando o sorteio.', 'error', 5000);
					audioApostasEncerradasPlayed = true;
					
					document.querySelectorAll('.price-btn').forEach(b => b.disabled = true);
					document.querySelectorAll('.qty-btn').forEach(b => b.disabled = true);
					document.getElementById('buyBtn').disabled = true;
					document.getElementById('buyBtn').textContent = 'Apostas Encerradas';
				}

				if (countdownEl) {
					countdownEl.textContent =
						`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
				}
				
				tempoRestante--;
			} else {
				const countdownEl = document.getElementById('countdown');
				if (countdownEl) {
					countdownEl.textContent = 'Sorteando...';
				}
				jogoEmAndamento = true;
				updateButton();
				window.location.reload(); // Força o reload para começar o polling
			}
		}

		function updateBingoBoard(numero) {
			const boardNumber = document.querySelector(`.board-number[data-number="${numero}"]`);
			if (boardNumber) {
				boardNumber.classList.add('drawn');
			}
		}

		function updateLastFourBalls() {
			const lastFour = drawnNumbers.slice(-4);
			const ballItems = document.querySelectorAll('.last-four-balls .ball-position');
			
			for (let i = 0; i < 4; i++) {
				const ballImg = ballItems[i].querySelector('img');
				if (lastFour[i]) {
					ballImg.src = `assets/images/balls/${lastFour[i]}.png`;
					ballImg.alt = `Bola ${lastFour[i]}`;
				} else {
					ballImg.src = 'assets/images/balls/interrogacao.png';
					ballImg.alt = 'Vazio';
				}
			}
		}
		
		/**
		 * checkUserWins: Verifica se o usuário fez Quadra ou Quina e garante que o feedback
		 * seja dado apenas uma vez por linha/coluna/diagonal.
		 */
		function checkUserWins() {
			if (jogoEncerrado) return;
			const currentDrawnSet = new Set(drawnNumbers);
			
			cartelas.forEach((cartela, index) => {
				const cartelaNumbers = cartela.numeros;
				const cartelaId = cartela.id; // ID real do DB
				let prizes = userPrizesAnnounced[cartelaId]; // Rastreador específico para esta cartela
				
				// Definir os padrões (L=Linha, C=Coluna, D=Diagonal)
				const patterns = [];
				// Linhas (L0 a L4)
				for (let i = 0; i < 5; i++) {
					patterns.push({
						id: `L${i}`, 
						indices: [i * 5, i * 5 + 1, i * 5 + 2, i * 5 + 3, i * 5 + 4]
					});
				}
				// Colunas (C0 a C4)
				for (let i = 0; i < 5; i++) {
					patterns.push({
						id: `C${i}`, 
						indices: [i, i + 5, i + 10, i + 15, i + 20]
					});
				}
				// Diagonais (D0 e D1)
				patterns.push({ id: 'D0', indices: [0, 6, 12, 18, 24] });
				patterns.push({ id: 'D1', indices: [4, 8, 12, 16, 20] });
				
				
				patterns.forEach(pattern => {
					// Pular se já tivermos alcançado Quina neste padrão
					if (prizes[pattern.id] === 'Quina') return;
					
					const matches = pattern.indices.filter(index => currentDrawnSet.has(cartelaNumbers[index])).length;
					
					// 1. Prioriza Quina
					if (matches === 5) {
						if (prizes[pattern.id] !== 'Quina') {
							playAudio(audioQuina);
							showToast(`QUINA na Cartela ${index + 1}!`, 'success');
							prizes[pattern.id] = 'Quina'; // Trava no prêmio máximo
						}
					} 
					// 2. Se não é Quina, verifica Quadra
					else if (matches === 4) {
						if (prizes[pattern.id] !== 'Quina' && prizes[pattern.id] !== 'Quadra') {
							playAudio(audioQuadra);
							showToast(`QUADRA na Cartela ${index + 1}!`, 'info');
							prizes[pattern.id] = 'Quadra'; // Trava em Quadra
						}
					}
					// Se for menos de 4, limpa o estado (embora não devesse acontecer com a adição de bolas)
					// else if (matches < 4) {
					// 	delete prizes[pattern.id];
					// }
				});

			});
			saveUserPrizesAnnounced();
		}

		// Sistema de sorteio
		let sorteioInterval;
		let playersOnlineInterval;
		let playersOnlineDirection = (Math.random() > 0.5) ? 1 : -1;
		let jogoEncerrado = false;
		let usuarioFezBingo = false;

		function verificarBingoUsuarioFinal() {
			const currentDrawnSet = new Set(drawnNumbers);

			for (const cartela of cartelas) {
				const cartelaNumbers = cartela.numeros;
				const matchedCount = cartelaNumbers.filter(num => currentDrawnSet.has(num)).length;

				if (matchedCount >= 24) {	
					return true;
				}
			}
			return false;
		}

		function getNextBallFromServer() {
			if (!salaId || jogoEncerrado) {
				clearInterval(sorteioInterval);
				return;
			}
			
			fetch('ajax/sorteio_server.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ sala_id: salaId })
			})
			.then(response => {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(data => {
				// TRATAMENTO PARA ENCERRAMENTO DEFINITIVO (Bingo detectado pelo servidor)
				if (data.status === 'bingo_fechado' || data.status === 'finalizado') {
					console.log("🎯 BINGO FECHADO OU FINALIZADO DETECTADO!");
					
					// 1. Define jogoEncerrado imediatamente para parar o polling
					jogoEncerrado = true;
					clearInterval(sorteioInterval);
					clearInterval(playersOnlineInterval);

					
					// 2. PROCESSA A ÚLTIMA BOLA SE HOUVER (A última bola que fechou o bingo)
					if (data.numero && !drawnNumbers.includes(data.numero)) {
						drawnNumbers.push(data.numero);
						
						updateBingoBoard(data.numero);
						// Marca nas cartelas do usuário
						const cartelaNumbersEl = document.querySelectorAll('.cartela-number');
						cartelaNumbersEl.forEach(cell => {
							if (parseInt(cell.dataset.number) === data.numero) {
								cell.classList.add('marked');
							}
						});
						updateLastFourBalls();
						
						// Atualiza a última bola
						const lastBallContainer = document.getElementById('lastBall');
						lastBallContainer.innerHTML = `<img src="assets/images/balls/${data.numero}.png" alt="Bola ${data.numero}" onerror="this.style.display='none'">`;
						
						// Narra a última bola
						playBallAudio(data.numero);
					}
					
					// 3. LÓGICA DE FEEDBACK DO BINGO (Atraso de 1s para a última bola aparecer)
					setTimeout(() => {
						
						usuarioFezBingo = verificarBingoUsuarioFinal();
						
						// Toca o áudio e exibe o 1º toast (Bingo)
						playAudio(audioBingo);
						
						let endingMessageEl = document.getElementById('endingMessage');
						const nomeVencedorBingo = botBingoWinner;
						const DURATION = 6000; // Duração base do toast

						if (usuarioFezBingo) {
							endingMessageEl.className = 'ending-message winner-user';
							endingMessageEl.innerHTML = `<h3><i class="fas fa-trophy"></i> VOCÊ FEZ BINGO!</h3><p>Jogo finalizado. Você ganhou o prêmio da cartela cheia!</p>`;
							endingMessageEl.style.display = 'block';
							showToast('🎉 BINGO! Você fez a cartela cheia!', 'user-bingo', DURATION);
							
						} else {
							endingMessageEl.className = 'ending-message'; // Padrão (vermelho)
							endingMessageEl.innerHTML = `<h3><i class="fas fa-trophy"></i> BINGO!</h3><p>O jogador <b>${nomeVencedorBingo}</b> fez Bingo, jogo finalizado.</p>`;
							endingMessageEl.style.display = 'block';
							showToast(`O jogador ${nomeVencedorBingo} fez BINGO!`, 'bot-feedback', DURATION);
						}
						
						// Exibe o 2º toast de finalização 
						setTimeout(() => {
							showToast(`Jogo finalizado. Recarregando em ${7 - 2} segundos...`, 'info', DURATION - 2000); 
						}, 2000); // 2 segundos após o primeiro toast

						// 4. RECARREGA A PÁGINA APÓS 7 SEGUNDOS (AJUSTE PRINCIPAL)
						localStorage.removeItem(`botQuadraAnnounced_${salaKey}`);
						localStorage.removeItem(`botQuinaAnnounced_${salaKey}`);
						localStorage.removeItem(`botBingoWinner_${salaKey}`);
						localStorage.removeItem(`userPrizesAnnounced_${salaKey}`); // Limpa rastreio de prêmios

						
						setTimeout(() => {
							window.location.reload();
						}, 7000);
						
					}, 1000); // 1 segundo de atraso para a última bola aparecer na tela
					
					return;
				}

				// LÓGICA NORMAL DO SORTEIO (QUANDO NÃO É BINGO_FECHADO)
				if (data.numero && !jogoEncerrado) {
					
					// Evita números duplicados
					if (!drawnNumbers.includes(data.numero)) {
						drawnNumbers.push(data.numero);
					} else {
						return;
					}
					
					// Atualiza a interface com a nova bola
					const lastBallContainer = document.getElementById('lastBall');
					lastBallContainer.innerHTML = `<img src="assets/images/balls/${data.numero}.png" alt="Bola ${data.numero}" onerror="this.style.display='none'">`;
					
					updateBingoBoard(data.numero);
					updateLastFourBalls();
					
					// Marca nas cartelas do usuário
					const cartelaNumbersEl = document.querySelectorAll('.cartela-number');
					cartelaNumbersEl.forEach(cell => {
						if (parseInt(cell.dataset.number) === data.numero) {
							cell.classList.add('marked');
						}
					});
					
					// Feedback da bola sorteada
					showToast(`Número sorteado: ${data.numero}`, 'info');	
					playBallAudio(data.numero); // Chama a narração por arquivo
					
					// Verifica vitórias do usuário
					checkUserWins();
					
					// === SIMULAÇÃO DE VITÓRIAS DE OUTROS JOGADORES EM SEQUÊNCIA CONTROLADA ===
					if (ativarBots) {
						const bolasSorteadas = drawnNumbers.length;
						
						// 1. QUADRA: Entre a 15ª e 25ª bola, chance de 20%
						if (bolasSorteadas >= 15 && bolasSorteadas <= 25 && !botQuadraAnnounced && Math.random() < 0.2) {
							const nomeJogador = botNames[Math.floor(Math.random() * botNames.length)];
							
							playAudio(audioQuadra);
							showToast(`${nomeJogador} fez a Quadra!`, 'bot-feedback', 3000);
							
							botQuadraAnnounced = true;
							localStorage.setItem(`botQuadraAnnounced_${salaKey}`, 'true');
						}	
						// 2. QUINA: Entre a 30ª e 40ª bola, após quadra, chance de 25%
						else if (bolasSorteadas >= 30 && bolasSorteadas <= 40 && botQuadraAnnounced && !botQuinaAnnounced && Math.random() < 0.25) {
							const nomeJogador = botNames[Math.floor(Math.random() * botNames.length)];
							
							playAudio(audioQuina);
							showToast(`${nomeJogador} fez a Quina!`, 'bot-feedback', 3000);
							
							botQuinaAnnounced = true;
							localStorage.setItem(`botQuinaAnnounced_${salaKey}`, 'true');
						}
					}
				}
			})
			.catch(error => {
				console.error('Erro ao buscar números do servidor:', error);
				clearInterval(sorteioInterval);
				// Tenta recarregar após um tempo se houver erro de rede.
				setTimeout(() => window.location.reload(), 5000);	
			});
		}
		
		function startSorteioPolling() {
			if (sorteioInterval) {
				clearInterval(sorteioInterval);
			}
			getNextBallFromServer();
			sorteioInterval = setInterval(getNextBallFromServer, velocidadeSorteio * 1000);
		}
		
		function updatePlayersOnline() {
			let currentBase = playersOnlineBase;
			const MIN_PLAYERS = 133;
			const MAX_PLAYERS = 350;
			let variation = Math.floor(Math.random() * 5) + 1;
			
			if (currentBase + variation >= MAX_PLAYERS) {
				playersOnlineDirection = -1;
			} else if (currentBase - variation <= MIN_PLAYERS) {
				playersOnlineDirection = 1;
			} else if (Math.random() < 0.1) {
				playersOnlineDirection *= -1;
			}
			
			currentBase += (variation * playersOnlineDirection);
			currentBase = Math.max(MIN_PLAYERS, Math.min(MAX_PLAYERS, currentBase));
			playersOnlineBase = currentBase;

			const playersEl = document.getElementById('playersOnlineCount');
			if (playersEl) {
				playersEl.textContent = currentBase;
			}
		}

		const volumeToggleBtn = document.getElementById('volumeToggle');
		const volumeIcon = document.querySelector('#volumeToggle i');
		
		if (isMuted) {
			volumeIcon.classList.remove('fa-volume-up');
			volumeIcon.classList.add('fa-volume-mute');
		} else {
			volumeIcon.classList.remove('fa-volume-mute');
			volumeIcon.classList.add('fa-volume-up');
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

		function logout() {
			if (confirm('Tem certeza que deseja sair?')) {
				window.location.href = 'libs/logout.php';
			}
		}
		
		// Inicialização
		updateButton();
		
		updatePlayersOnline();
		playersOnlineInterval = setInterval(updatePlayersOnline, 5000);	

		if (tempoRestante > 0 && !jogoEmAndamento && !jogoTerminado && !aguardandoProximo) {
			setInterval(updateCountdown, 1000);
		}
		
		if (jogoEmAndamento && !jogoTerminado && !aguardandoProximo) {
			startSorteioPolling();
		}
		
		// Lógica de feedback na carga da página quando o jogo já foi marcado como FINALIZADO no PHP
		if (jogoTerminado || aguardandoProximo) {
			clearInterval(playersOnlineInterval);
			
			const statusMessageEl = document.getElementById('game-status-message');
			if (jogoTerminado && statusMessageEl) {
				statusMessageEl.textContent = "Resultados Processados!";
			} else if (aguardandoProximo && statusMessageEl) {
				statusMessageEl.textContent = "Novo Jogo em 7s..."; 
			}
			
			// Se o jogo terminou, garante o feedback e o delay antes do reload
			if (jogoTerminado) {
				setTimeout(() => {
					// 1. Dispara o feedback final (garante que o toast de bingo apareça)
					const usuarioGanhou = verificarBingoUsuarioFinal();
					let endingMessageEl = document.getElementById('endingMessage');
					
					// Toca o som de bingo e exibe o 1º toast
					playAudio(audioBingo); 
					if (usuarioGanhou) {
						showToast('🎉 BINGO! Você fez a cartela cheia!', 'user-bingo', 6000);
						endingMessageEl.className = 'ending-message winner-user';
						endingMessageEl.innerHTML = `<h3><i class="fas fa-trophy"></i> VOCÊ FEZ BINGO!</h3><p>Jogo finalizado. Você ganhou o prêmio da cartela cheia!</p>`;
					} else {
						const nomeVencedorBingo = botBingoWinner;
						showToast(`O jogador ${nomeVencedorBingo} fez BINGO!`, 'bot-feedback', 6000);
						endingMessageEl.className = 'ending-message';
						endingMessageEl.innerHTML = `<h3><i class="fas fa-trophy"></i> BINGO!</h3><p>O jogador <b>${nomeVencedorBingo}</b> fez Bingo, jogo finalizado.</p>`;
					}
					endingMessageEl.style.display = 'block';

					// 2. Exibe o 2º toast de finalização após um pequeno atraso
					setTimeout(() => {
						showToast(`Jogo finalizado. Recarregando em ${7 - 2} segundos...`, 'info', 5000);
					}, 2000); // 2 segundos após o primeiro para não sumir rápido demais
					
				}, 100);

				// 3. RECARREGA A PÁGINA APÓS 7 SEGUNDOS (AJUSTE PRINCIPAL)
				setTimeout(() => {
					window.location.reload();
				}, 7000);
			}
		}
	</script>
</body>
</html>