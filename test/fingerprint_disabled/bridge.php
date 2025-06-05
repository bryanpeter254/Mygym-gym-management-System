<?php
/**
 * ZKT4500 Fingerprint Bridge for PHP
 * 
 * This class provides an interface to communicate with the Java-based ZKT4500 fingerprint scanner bridge.
 * It uses a WebSocket client to connect to the Java bridge service.
 */
class FingerprintBridge {
    // Bridge service connection details
    private $host;
    private $port;
    private $path;
    
    // Connection status
    private $connected = false;
    
    // Socket handle
    private $socket = null;
    
    /**
     * Constructor
     */
    public function __construct($host = 'localhost', $port = 8099, $path = '/') {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
    }
    
    /**
     * Connect to the fingerprint bridge service
     */
    public function connect() {
        // Check if already connected
        if ($this->connected && $this->socket) {
            return true;
        }
        
        // Create a socket connection
        $address = "tcp://{$this->host}:{$this->port}";
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$this->socket) {
            $this->logError("Failed to create socket");
            return false;
        }
        
        // Set timeout for socket operations
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        
        // Connect to the server
        $result = @socket_connect($this->socket, $this->host, $this->port);
        
        if (!$result) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            $this->logError("Could not connect to bridge service: [$errorCode] $errorMsg");
            socket_close($this->socket);
            $this->socket = null;
            return false;
        }
        
        // Perform WebSocket handshake
        if (!$this->performHandshake()) {
            socket_close($this->socket);
            $this->socket = null;
            return false;
        }
        
        $this->connected = true;
        return true;
    }
    
    /**
     * Perform WebSocket handshake
     */
    private function performHandshake() {
        // Generate WebSocket key
        $key = base64_encode(openssl_random_pseudo_bytes(16));
        
        // Create handshake headers
        $headers = "GET {$this->path} HTTP/1.1\r\n";
        $headers .= "Host: {$this->host}:{$this->port}\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Key: $key\r\n";
        $headers .= "Sec-WebSocket-Version: 13\r\n";
        $headers .= "Origin: http://{$this->host}:{$this->port}\r\n\r\n";
        
        // Send handshake request
        $sent = socket_write($this->socket, $headers, strlen($headers));
        
        if (!$sent) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            $this->logError("Handshake failed: [$errorCode] $errorMsg");
            return false;
        }
        
        // Read handshake response
        $response = socket_read($this->socket, 2048);
        
        if (!$response) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            $this->logError("Failed to read handshake response: [$errorCode] $errorMsg");
            return false;
        }
        
        // Check if handshake was successful
        if (strpos($response, "HTTP/1.1 101") === false) {
            $this->logError("Handshake failed: Unexpected response");
            return false;
        }
        
        return true;
    }
    
    /**
     * Send a WebSocket frame
     */
    private function sendFrame($payload) {
        if (!$this->connected || !$this->socket) {
            $this->logError("Cannot send frame: Not connected");
            return false;
        }
        
        // Convert payload to JSON if it's an array or object
        if (is_array($payload) || is_object($payload)) {
            $payload = json_encode($payload);
        }
        
        // Create WebSocket frame
        $frameHead = [];
        $payloadLength = strlen($payload);
        
        $frameHead[0] = 129; // FIN + text frame
        
        // Set payload length bits
        if ($payloadLength <= 125) {
            $frameHead[1] = $payloadLength;
        } elseif ($payloadLength <= 65535) {
            $frameHead[1] = 126;
            $frameHead[2] = ($payloadLength >> 8) & 255;
            $frameHead[3] = $payloadLength & 255;
        } else {
            $frameHead[1] = 127;
            $frameHead[2] = ($payloadLength >> 56) & 255;
            $frameHead[3] = ($payloadLength >> 48) & 255;
            $frameHead[4] = ($payloadLength >> 40) & 255;
            $frameHead[5] = ($payloadLength >> 32) & 255;
            $frameHead[6] = ($payloadLength >> 24) & 255;
            $frameHead[7] = ($payloadLength >> 16) & 255;
            $frameHead[8] = ($payloadLength >> 8) & 255;
            $frameHead[9] = $payloadLength & 255;
        }
        
        // Convert to string
        $header = '';
        foreach ($frameHead as $byte) {
            $header .= chr($byte);
        }
        
        // Append payload to header
        $frame = $header . $payload;
        
        // Send frame
        $sent = socket_write($this->socket, $frame, strlen($frame));
        
        if (!$sent) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            $this->logError("Failed to send frame: [$errorCode] $errorMsg");
            return false;
        }
        
        return true;
    }
    
    /**
     * Read a WebSocket frame
     */
    private function readFrame() {
        if (!$this->connected || !$this->socket) {
            $this->logError("Cannot read frame: Not connected");
            return false;
        }
        
        // Read first two bytes (header)
        $header = socket_read($this->socket, 2);
        
        if (!$header) {
            $errorCode = socket_last_error($this->socket);
            $errorMsg = socket_strerror($errorCode);
            $this->logError("Failed to read frame header: [$errorCode] $errorMsg");
            return false;
        }
        
        $headerData = unpack('C*', $header);
        $fin = ($headerData[1] & 0x80) == 0x80;
        $opcode = $headerData[1] & 0x0F;
        $masked = ($headerData[2] & 0x80) == 0x80;
        $payloadLength = $headerData[2] & 0x7F;
        
        // Handle extended payload length
        if ($payloadLength == 126) {
            $extendedPayloadLength = socket_read($this->socket, 2);
            if (!$extendedPayloadLength) {
                $this->logError("Failed to read extended payload length");
                return false;
            }
            $payloadLength = unpack('n', $extendedPayloadLength)[1];
        } elseif ($payloadLength == 127) {
            $extendedPayloadLength = socket_read($this->socket, 8);
            if (!$extendedPayloadLength) {
                $this->logError("Failed to read extended payload length");
                return false;
            }
            $payloadLength = unpack('N2', $extendedPayloadLength);
            $payloadLength = ($payloadLength[1] << 32) | $payloadLength[2];
        }
        
        // Read mask if present
        $mask = [];
        if ($masked) {
            $maskBytes = socket_read($this->socket, 4);
            if (!$maskBytes) {
                $this->logError("Failed to read mask");
                return false;
            }
            $mask = array_values(unpack('C*', $maskBytes));
        }
        
        // Read payload
        $payload = '';
        $remainingLength = $payloadLength;
        
        while ($remainingLength > 0) {
            $data = socket_read($this->socket, $remainingLength);
            if (!$data) {
                $this->logError("Failed to read payload");
                return false;
            }
            $payload .= $data;
            $remainingLength -= strlen($data);
        }
        
        // Unmask payload if masked
        if ($masked) {
            $unmaskedPayload = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $unmaskedPayload .= chr(ord($payload[$i]) ^ $mask[$i % 4]);
            }
            $payload = $unmaskedPayload;
        }
        
        // Handle different opcodes
        switch ($opcode) {
            case 0x1: // Text frame
                // Try to decode as JSON
                $json = json_decode($payload, true);
                return $json !== null ? $json : $payload;
                
            case 0x8: // Close frame
                $this->connected = false;
                socket_close($this->socket);
                $this->socket = null;
                return false;
                
            case 0x9: // Ping frame
                // Send pong
                $this->sendFrame(''); // Opcode 0xA Pong
                return $this->readFrame(); // Read next frame
                
            default:
                return $payload;
        }
    }
    
    /**
     * Initialize the fingerprint scanner
     */
    public function initializeScanner() {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Failed to connect to bridge service'
            ];
        }
        
        // Send initialization command
        $message = [
            'type' => 'init'
        ];
        
        if (!$this->sendFrame($message)) {
            return [
                'success' => false,
                'message' => 'Failed to send initialization command'
            ];
        }
        
        // Read response
        $response = $this->readFrame();
        
        if (!$response || !isset($response['type'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from bridge service'
            ];
        }
        
        if ($response['type'] == 'status') {
            $data = $response['data'] ?? [];
            $ready = isset($data['status']) && $data['status'] == 'ready';
            
            return [
                'success' => $ready,
                'status' => $data['status'] ?? 'unknown',
                'deviceInfo' => $data['deviceInfo'] ?? 'No device info available',
                'message' => $data['message'] ?? ($ready ? 'Scanner initialized successfully' : 'Failed to initialize scanner')
            ];
        } else if ($response['type'] == 'error') {
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Error initializing scanner'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unexpected response type: ' . $response['type']
            ];
        }
    }
    
    /**
     * Capture a fingerprint
     */
    public function captureFingerprint($memberId = null) {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Failed to connect to bridge service'
            ];
        }
        
        // Send capture command
        $message = [
            'type' => 'scan'
        ];
        
        if ($memberId !== null) {
            $message['data'] = ['memberId' => $memberId];
        }
        
        if (!$this->sendFrame($message)) {
            return [
                'success' => false,
                'message' => 'Failed to send capture command'
            ];
        }
        
        // Read response
        $response = $this->readFrame();
        
        if (!$response || !isset($response['type'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from bridge service'
            ];
        }
        
        if ($response['type'] == 'scan_result') {
            $data = $response['data'] ?? [];
            
            return [
                'success' => $data['success'] ?? false,
                'template' => $data['template'] ?? null,
                'quality' => $data['quality'] ?? 0,
                'message' => $data['message'] ?? 'Fingerprint captured'
            ];
        } else if ($response['type'] == 'error') {
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Error capturing fingerprint'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unexpected response type: ' . $response['type']
            ];
        }
    }
    
    /**
     * Verify a fingerprint
     */
    /**
     * Verify a fingerprint against a stored template
     * 
     * @param string $template The template to verify, or null to capture a new template
     * @param string $storedTemplate The stored template to verify against
     * @param array $options Optional configuration settings
     * @return array Result of verification operation
     */
    public function verifyFingerprint($template = null, $storedTemplate = null, $options = []) {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Failed to connect to bridge service'
            ];
        }
        
        // Default options
        $defaultOptions = [
            'threshold' => 60,   // Default match threshold
            'timeout' => 5000    // Default timeout in milliseconds
        ];
        
        // Merge with user-provided options
        $options = array_merge($defaultOptions, $options);
        
        // Prepare message data
        $messageData = [
            'threshold' => $options['threshold'],
            'timeout' => $options['timeout']
        ];
        
        if ($template) {
            $messageData['template'] = $template;
        }
        
        if ($storedTemplate) {
            $messageData['storedTemplate'] = $storedTemplate;
        }
        
        // Send verify command
        $message = [
            'type' => 'verify',
            'data' => $messageData
        ];
        
        if (!$this->sendFrame($message)) {
            return [
                'success' => false,
                'message' => 'Failed to send verification command'
            ];
        }
        
        // Read response
        $response = $this->readFrame();
        
        if (!$response || !isset($response['type'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from bridge service'
            ];
        }
        
        if ($response['type'] == 'verify_result') {
            $data = $response['data'] ?? [];
            
            return [
                'success' => $data['success'] ?? false,
                'score' => $data['score'] ?? 0,
                'threshold' => $data['threshold'] ?? 0,
                'message' => $data['message'] ?? 'Fingerprint verification completed'
            ];
        } else if ($response['type'] == 'error') {
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Error verifying fingerprint'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unexpected response type: ' . $response['type']
            ];
        }
    }
    
    /**
     * Get scanner status
     */
    public function getStatus() {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Failed to connect to bridge service'
            ];
        }
        
        // Send status command
        $message = [
            'type' => 'status'
        ];
        
        if (!$this->sendFrame($message)) {
            return [
                'success' => false,
                'message' => 'Failed to send status command'
            ];
        }
        
        // Read response
        $response = $this->readFrame();
        
        if (!$response || !isset($response['type'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from bridge service'
            ];
        }
        
        if ($response['type'] == 'status') {
            $data = $response['data'] ?? [];
            
            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'deviceInfo' => $data['deviceInfo'] ?? 'No device info available',
                'cachedTemplates' => $data['cachedTemplates'] ?? 0
            ];
        } else if ($response['type'] == 'error') {
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Error getting status'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unexpected response type: ' . $response['type']
            ];
        }
    }
    
    /**
     * Close the connection
     */
    public function close() {
        if ($this->connected && $this->socket) {
            // Send close frame
            $frame = chr(0x88) . chr(0x00);
            socket_write($this->socket, $frame, 2);
            
            socket_close($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }
    
    /**
     * Log error message
     */
    private function logError($message) {
        // Log error to file
        $logFile = __DIR__ . '/fingerprint-bridge.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}