<?php

class CodexPayAPI {
    private $base_url = 'https://api.codexpay.app';
    private $client_id;
    private $client_secret;
    private $access_token;
    
    public function __construct($client_id, $client_secret) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        // Log da instanciação da classe com validação
        $this->log("CodexPayAPI constructor called", [
            "client_id_length" => strlen($client_id),
            "client_id_preview" => substr($client_id, 0, 8) . "...",
            "client_secret_length" => strlen($client_secret),
            "client_secret_preview" => substr($client_secret, 0, 4) . "...",
            "credentials_valid" => !empty($client_id) && !empty($client_secret)
        ]);
        
        // Validação básica das credenciais
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new Exception('Client ID e Client Secret são obrigatórios para CodexPay API');
        }
    }
    
    /**
     * Autentica e retorna o token JWT
     */
    public function authenticate() {
        $url = $this->base_url . '/api/auth/login';
        
        $this->log("Starting authentication process", ["url" => $url]);
        
        $postData = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        ];
        
        $this->log("Sending authentication request", [
            "client_id_preview" => substr($this->client_id, 0, 8) . "...",
            "client_secret_length" => strlen($this->client_secret),
            "post_data_keys" => array_keys($postData)
        ]);
        
        try {
            $response = $this->makeRequest($url, $postData);
            
            if (isset($response['token'])) {
                $this->access_token = $response['token'];
                $this->log("Authentication successful", ["token_length" => strlen($response['token'])]);
                return $response;
            }
            
            $this->log("Authentication failed - no token in response", ["response" => $response]);
            throw new Exception('Falha na autenticação: ' . ($response['message'] ?? 'Erro desconhecido'));
            
        } catch (Exception $e) {
            $this->log("Authentication error", ["error" => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Cria um depósito PIX
     */
    public function createDeposit($amount, $external_id, $clientCallbackUrl, $payer, $split = null) {
        $this->log("Creating deposit request", [
            "amount" => $amount,
            "external_id" => $external_id,
            "client_callback_url" => $clientCallbackUrl,
            "payer_info" => [
                "name" => $payer['name'] ?? 'N/A',
                "email" => $payer['email'] ?? 'N/A',
                "document" => substr($payer['document'] ?? 'N/A', 0, 3) . "..."
			],
            "has_split" => $split !== null
        ]);
        
        $this->ensureAuthenticated();
        
        $url = $this->base_url . '/api/payments/deposit';
        
        $postData = [
            'amount' => $amount,
            'external_id' => $external_id,
            'clientCallbackUrl' => $clientCallbackUrl,
            'payer' => $payer
        ];
        
        if ($split !== null) {
            $postData['split'] = $split;
            $this->log("Split information added to deposit", ["split" => $split]);
        }
        
        // Validação de campos obrigatórios para depósito
        $required_fields = ['amount', 'external_id', 'clientCallbackUrl', 'payer'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($postData[$field]) || empty($postData[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        // Validação específica do objeto payer
        if (isset($postData['payer']) && is_array($postData['payer'])) {
            $payer_required = ['name', 'email', 'document'];
            foreach ($payer_required as $payer_field) {
                if (!isset($postData['payer'][$payer_field]) || empty($postData['payer'][$payer_field])) {
                    $missing_fields[] = "payer.$payer_field";
                }
            }
        }
        
        if (!empty($missing_fields)) {
            $this->log("Missing required fields for deposit", [
                "missing_fields" => $missing_fields,
                "current_post_data" => $postData
            ]);
            throw new Exception('Campos obrigatórios faltando: ' . implode(', ', $missing_fields));
        }
        
        $this->log("Deposit payload validated successfully", [
            "has_amount" => isset($postData['amount']),
            "has_external_id" => isset($postData['external_id']),
            "has_client_callback" => isset($postData['clientCallbackUrl']),
            "has_payer" => isset($postData['payer']),
            "payer_fields" => isset($postData['payer']) ? array_keys($postData['payer']) : []
        ]);
        
        try {
            $response = $this->makeRequest($url, $postData, [
                'Authorization: Bearer ' . $this->access_token
            ]);
            
            if (isset($response['qrCodeResponse'])) {
                $this->log("Deposit created successfully", [
                    "transaction_id" => $response['qrCodeResponse']['transactionId'] ?? 'N/A',
                    "status" => $response['qrCodeResponse']['status'] ?? 'N/A',
                    "qr_code_generated" => !empty($response['qrCodeResponse']['qrcode'])
                ]);
                return $response;
            }
            
            $this->log("Deposit creation failed - no qrCodeResponse", ["response" => $response]);
            throw new Exception('Falha ao criar depósito: ' . ($response['message'] ?? 'Erro desconhecido'));
            
        } catch (Exception $e) {
            $this->log("Deposit creation error", ["error" => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Cria um saque PIX
     */
    public function createWithdrawal($amount, $name, $document, $external_id, $pix_key, $key_type, $description, $clientCallbackUrl) {
        $this->log("Creating withdrawal request", [
            "amount" => $amount,
            "name" => $name,
            "document_preview" => substr($document, 0, 3) . "...",
            "external_id" => $external_id,
            "pix_key_preview" => substr($pix_key, 0, 4) . "...",
            "key_type" => $key_type,
            "description" => $description,
            "client_callback_url" => $clientCallbackUrl
        ]);
        
        $this->ensureAuthenticated();
        
        $url = $this->base_url . '/api/withdrawals/withdraw';
        
        $postData = [
            'amount' => $amount,
            'name' => $name,
            'document' => $document,
            'external_id' => $external_id,
            'pix_key' => $pix_key,
            'key_type' => $key_type,
            'description' => $description,
            'clientCallbackUrl' => $clientCallbackUrl
        ];
        
        try {
            $response = $this->makeRequest($url, $postData, [
                'Authorization: Bearer ' . $this->access_token
            ]);
            
            if (isset($response['withdrawal'])) {
                $this->log("Withdrawal created successfully", [
                    "transaction_id" => $response['withdrawal']['transaction_id'] ?? 'N/A',
                    "status" => $response['withdrawal']['status'] ?? 'N/A',
                    "amount" => $response['withdrawal']['amount'] ?? 'N/A',
                    "fee" => $response['withdrawal']['fee'] ?? 'N/A'
                ]);
                return $response;
            }
            
            $this->log("Withdrawal creation failed - no withdrawal response", ["response" => $response]);
            throw new Exception('Falha ao criar saque: ' . ($response['message'] ?? 'Erro desconhecido'));
            
        } catch (Exception $e) {
            $this->log("Withdrawal creation error", ["error" => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Garante que temos um token válido
     */
    private function ensureAuthenticated() {
        if (empty($this->access_token)) {
            $this->log("No access token found, triggering authentication");
            $this->authenticate();
        } else {
            $this->log("Using existing access token", ["token_length" => strlen($this->access_token)]);
        }
    }
    
    /**
     * Faz requisição HTTP para a API
     */
    private function makeRequest($url, $data, $headers = []) {
        // Cria preview dos dados enviados
        $preview_data = $data;
        if (isset($preview_data['client_secret'])) {
            $preview_data['client_secret'] = substr($preview_data['client_secret'], 0, 4) . '...';
        }
        
        $this->log("Making HTTP request", [
            "url" => $url,
            "method" => "POST",
            "data_preview" => json_encode($preview_data)
        ]);
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de conexão de 10 segundos
        
        // Headers padrão
        $default_headers = [
            'User-Agent: BingoApp/1.0'
        ];
        
        $final_headers = array_merge($default_headers, $headers);
        
        // Adiciona Content-Type se não existir
        $has_content_type = false;
        foreach ($final_headers as $header) {
            if (strpos($header, 'Content-Type:') === 0) {
                $has_content_type = true;
                break;
            }
        }
        
        if (!$has_content_type) {
            $final_headers[] = 'Content-Type: application/json';
        }
        
        // Log dos headers (sem revelar token completo)
        $headers_preview = [];
        foreach ($final_headers as $header) {
            if (strpos($header, 'Authorization:') === 0) {
                $headers_preview[] = 'Authorization: Bearer ' . substr($header, 16, 10) . '...';
            } else {
                $headers_preview[] = $header;
            }
        }
        $this->log("Request headers", ["headers" => $headers_preview]);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $final_headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desenvolvimento - remover em produção
        
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);
        $request_duration = round(($end_time - $start_time) * 1000, 2); // em milissegundos
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        // Log informações de conectividade
        $curl_info = [
            "http_code" => $httpCode,
            "total_time" => $request_duration . "ms",
            "effective_url" => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            "response_size" => strlen($response) . " bytes"
        ];
        
        if (curl_error($ch)) {
            $curl_info["error"] = $curl_error;
            $this->log("CURL error occurred", $curl_info);
            curl_close($ch);
            throw new Exception('Erro de conexão: ' . $curl_error);
        }
        
        curl_close($ch);
        
        $decoded_response = json_decode($response, true);
        
        $this->log("HTTP request completed", array_merge($curl_info, [
            "response_decoded" => is_array($decoded_response)
        ]));
        
        // HTTP 200 = OK, HTTP 201 = Created (sucesso)
        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->log("HTTP error response", [
                "status_code" => $httpCode,
                "raw_response" => substr($response, 0, 500), // Primeiros 500 chars
                "decoded_error" => $decoded_response['message'] ?? 'No error message',
                "sent_data_keys" => array_keys($data),
                "sent_data_preview" => json_encode($data)
            ]);
            throw new Exception('Erro HTTP ' . $httpCode . ': ' . ($decoded_response['message'] ?? $response));
        }
        
        // Log de sucesso diferenciado por status HTTP
        if ($httpCode === 201) {
            $this->log("Resource created successfully", [
                "status_code" => $httpCode,
                "response_keys" => is_array($decoded_response) ? array_keys($decoded_response) : ['raw_response']
            ]);
        } else {
            $this->log("Request successful", [
                "status_code" => $httpCode,
                "response_keys" => is_array($decoded_response) ? array_keys($decoded_response) : ['raw_response']
            ]);
        }
        
        return $decoded_response;
    }
    
    /**
     * Testa conectividade e credenciais
     */
    public function testConnection() {
        $this->log("Testing CodexPay API connection");
        
        try {
            $response = $this->authenticate();
            $this->log("Connection test successful", ["response" => $response]);
            return true;
        } catch (Exception $e) {
            $this->log("Connection test failed", ["error" => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Método para logging centralizado
     */
    private function log($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = [
            'timestamp' => $timestamp,
            'level' => 'INFO',
            'message' => $message,
            'context' => $context
        ];
        
        // Log estruturado em JSON
        $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        // Escreve no arquivo de log da CodexPayAPI
        file_put_contents('codexpay_api.log', $log_line, FILE_APPEND | LOCK_EX);
        
        // Também escreve no PHP error log para debugging local
        error_log("CODEXPAY: $message " . json_encode($context));
    }
}
