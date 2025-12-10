<?php

/**
 * AI API Client
 * Handles communication with OpenAI-compatible API
 */
class OsticketAIAssistantAPIClient {
    
    private string $api_key;
    private string $api_url;
    private bool $enable_logging;
    private string $model;
    private float $temperature;
    private int $timeout;
    
    public function __construct(string $api_key, string $model, string $api_url, int $timeout, bool $enable_logging, float $temperature) {
        $this->api_key = trim($api_key);
        $this->model = $model;
        $this->api_url = $api_url;
        $this->timeout = $timeout;
        $this->enable_logging = $enable_logging;
        $this->temperature = $temperature;
    }
    
    /**
     * Analyze ticket and find best matching canned response
     *
     * @param array $ticket_data Ticket information (subject, content, dept, etc)
     * @param array $templates Array of canned response templates
     * @return array Result with best_template_id, confidence, reasoning
     */
    public function findBestTemplate($ticket_data, $templates) {
        if (empty($templates)) {
            return array(
                'success' => false,
                'error' => 'No templates available'
            );
        }
        
        // Build the prompt
        $prompt = $this->buildAnalysisPrompt($ticket_data, $templates);
        
        // Prepare messages for ChatGPT
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are an expert customer support assistant. Analyze support tickets and suggest the most appropriate canned response template. Tickets and templates may be written in different languages (for example Russian, Ukrainian, English). First, detect the primary language of the customer\'s ticket text. Prefer templates written in the same language as the ticket. In particular, if the ticket is in Russian, do not choose Ukrainian templates unless there are absolutely no reasonable Russian options, and vice versa. Always respond with valid JSON only, with no natural-language text outside of the JSON object.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        // Make API request
        $response = $this->makeRequest($messages);
        
        if (!$response['success']) {
            return $response;
        }
        
        // Parse and validate response
        return $this->parseAnalysisResponse($response['data'], $templates);
    }
    
    /**
     * Build the analysis prompt
     */
    private function buildAnalysisPrompt($ticket_data, $templates) {
        $prompt = "Analyze the following support ticket and determine which canned response template is most suitable.\n\n";
        
        $prompt .= "TICKET INFORMATION:\n";
        $prompt .= "Subject: " . $ticket_data['subject'] . "\n";
        $prompt .= "Content: " . $ticket_data['content'] . "\n";
        
        if (!empty($ticket_data['department'])) {
            $prompt .= "Department: " . $ticket_data['department'] . "\n";
        }
        
        if (!empty($ticket_data['priority'])) {
            $prompt .= "Priority: " . $ticket_data['priority'] . "\n";
        }
        
        if (!empty($ticket_data['history'])) {
            $prompt .= "\nPREVIOUS MESSAGES:\n" . $ticket_data['history'] . "\n";
        }
        
        $prompt .= "\n\nAVAILABLE CANNED RESPONSE TEMPLATES:\n";
        foreach ($templates as $template) {
            $prompt .= "\nTemplate ID: " . $template['id'] . "\n";
            $prompt .= "Title: " . $template['title'] . "\n";
            $prompt .= "Content: " . substr($template['content'], 0, 500) . (strlen($template['content']) > 500 ? '...' : '') . "\n";
            $prompt .= "---\n";
        }
        
        $prompt .= "\n\nTASK:\n";
        $prompt .= "First, determine the primary language of the ticket text (for example: \"ru\" for Russian, \"uk\" for Ukrainian, \"en\" for English). When selecting the best_template_id, strongly prefer templates written in the same language as the ticket. In particular, avoid choosing Ukrainian templates for clearly Russian tickets and vice versa, unless there are no reasonable templates in the same language.\n";
        $prompt .= "Analyze the ticket and return JSON with:\n";
        $prompt .= "{\n";
        $prompt .= '  "best_template_id": <ID of most suitable template>,'. "\n";
        $prompt .= '  "confidence_score": <0-100>,'. "\n";
        $prompt .= '  "detected_language": "<ticket language code such as ru, uk, en>",'. "\n";
        $prompt .= '  "reasoning": "<brief explanation>",'. "\n";
        $prompt .= '  "suggested_modifications": "<optional customizations>",'. "\n";
        $prompt .= '  "alternatives": [<array of alternative template IDs if applicable>]'. "\n";
        $prompt .= "}\n";
        
        return $prompt;
    }
    
    /**
     * Make HTTP request to AI API
     */
    private function makeRequest($messages) {
        $data = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'response_format' => array('type' => 'json_object')
        );
        
        // Encode payload as JSON, handling invalid UTF-8 safely
        $json_options = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json_data = json_encode($data, $json_options);
        
        if ($json_data === false || json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Failed to encode request as JSON: ' . json_last_error_msg();
            if ($this->enable_logging) {
                error_log('AI Assistant - JSON encode error: ' . $error_msg . ' | Data snapshot: ' . print_r($data, true));
            }
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }
        
        if ($this->enable_logging) {
            error_log("AI Assistant - API Request: " . $json_data);
        }
        
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            if ($this->enable_logging) {
                error_log('AI Assistant - CURL Error: ' . $curl_error);
            }
            return array(
                'success' => false,
                'error' => 'CURL Error: ' . $curl_error
            );
        }
        
        if ($http_code !== 200) {
            if ($this->enable_logging) {
                error_log('AI Assistant - API Error HTTP ' . $http_code . ': ' . $response);
            }
            $error_data = json_decode($response, true);
            $error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            return array(
                'success' => false,
                'error' => 'OpenAI API Error ('. $http_code .'): ' . $error_msg
            );
        }
        
        $result = json_decode($response, true);
        
        if ($this->enable_logging) {
            error_log("AI Assistant - API Response: " . $response);
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }
        
        return array(
            'success' => true,
            'data' => $result['choices'][0]['message']['content']
        );
    }
    
    /**
     * Parse and validate the AI analysis response
     */
    private function parseAnalysisResponse($json_string, $templates) {
        $analysis = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to parse AI response: ' . json_last_error_msg()
            );
        }
        
        // Validate required fields
        if (!isset($analysis['best_template_id']) || !isset($analysis['confidence_score'])) {
            return array(
                'success' => false,
                'error' => 'AI response missing required fields'
            );
        }
        
        // Find the template
        $best_template = null;
        foreach ($templates as $template) {
            if ($template['id'] == $analysis['best_template_id']) {
                $best_template = $template;
                break;
            }
        }
        
        if (!$best_template) {
            return array(
                'success' => false,
                'error' => 'AI suggested invalid template ID'
            );
        }
        
        return array(
            'success' => true,
            'template' => $best_template,
            'confidence' => intval($analysis['confidence_score']),
            'reasoning' => $analysis['reasoning'] ?? '',
            'suggested_modifications' => $analysis['suggested_modifications'] ?? '',
            'alternatives' => $analysis['alternatives'] ?? array()
        );
    }
}
