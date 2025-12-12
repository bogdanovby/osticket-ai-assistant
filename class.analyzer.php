<?php


/**
 * Ticket Analyzer
 * Analyzes tickets and finds best matching canned responses using AI
 */
class AIAssistantTicketAnalyzer {
    
    private ?AIAssistantAPIClient $apiClient = null;
    private AiAssistantConfig $config;
    
    public function __construct(\AiAssistantConfig  $config) {
        $this->config = $config;
        
        $api_key = $config->get('api_key');
        $model = $config->get('model');
        $api_url = $config->get('api_url');
        
        if ($api_key && $model && $api_url) {
            $temperature = $config->get('temperature');
            if ('' === trim((string)$temperature)) {
                $temperature = 0.3;
            }
            $this->apiClient = new AIAssistantAPIClient(
                $api_key,
                $model,
                $api_url,
                (int) $config->get('timeout', 30),
                (bool) $config->get('enable_logging', false),
                (float) $temperature
            );
        }
    }
    
    /**
     * Analyze ticket and find best response
     *
     * @param int $ticket_id Ticket ID
     * @return array Result with template suggestion
     */
    public function analyzeTicket($ticket_id) {
        // Get ticket data
        $ticket_data = $this->getTicketContext($ticket_id);
        
        if (!$ticket_data) {
            return array(
                'success' => false,
                'error' => 'Ticket not found'
            );
        }
        
        // Get available canned responses
        $templates = $this->getCannedResponses($ticket_data['dept_id']);
        
        if (empty($templates)) {
            return array(
                'success' => false,
                'error' => 'No canned responses available'
            );
        }
        
        // Limit templates if configured
        $max_templates = (int) $this->config->get('max_templates', 0);
        if ($max_templates > 0 && count($templates) > $max_templates) {
            $templates = array_slice($templates, 0, $max_templates);
        }

        if (!$this->apiClient) {
            return array(
                'success' => false,
                'error' => 'API client not configured. Please check plugin settings (API Key, Model, API URL).'
            );
        }

        // Use AI to find best match
        $result = $this->apiClient->findBestTemplate($ticket_data, $templates);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Check confidence threshold
        $min_confidence = (int)$this->config->get('min_confidence', 0);
        if ($result['confidence'] < $min_confidence) {
            return array(
                'success' => false,
                'error' => 'No template found with sufficient confidence',
                'confidence' => $result['confidence'],
                'min_required' => $min_confidence
            );
        }
        
        return $result;
    }
    
    /**
     * Extract ticket context and content
     *
     * @param int $ticket_id
     * @return array|null Ticket data
     */
    private function getTicketContext($ticket_id) {
        $ticket = Ticket::lookup($ticket_id);
        
        if (!$ticket) {
            return null;
        }
        
        // Get basic ticket info
        $data = array(
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'dept_id' => $ticket->getDeptId(),
            'department' => $ticket->getDept()->getName(),
            'priority' => $ticket->getPriority(),
            'content' => ''
        );
        
        // Get ticket thread (messages)
        $thread = $ticket->getThread();
        if ($thread) {
            $entries = $thread->getEntries();
            $messages = array();
            
            foreach ($entries as $entry) {
                $body = $entry->getBody();
                if ($body) {
                    $messages[] = strip_tags($body->getClean());
                }
            }

            // First message is the main content
            if (!empty($messages)) {
                $data['content'] = $messages[0];
                
                // Add history if there are replies
                if (count($messages) > 1) {
                    $data['history'] = implode("\n\n--- Reply ---\n\n", array_slice($messages, 1));
                }
            }
        }

        return $data;
    }

    /**
     * Get all canned responses for a department
     *
     * @param int|null $dept_id Department ID
     * @return array Array of canned responses
     */
    private function getCannedResponses($dept_id = null) {
        $templates = array();
        
        // Get all active canned responses
        $query = Canned::objects()->filter(array(
            'isenabled' => 1
        ));
        
        foreach ($query as $canned) {
            // Filter by department in PHP (OR logic: matches dept OR global)
            if ($dept_id !== null && $canned->getDeptId() != 0 && $canned->getDeptId() != $dept_id) {
                continue;
            }
            
            $templates[] = array(
                'id' => $canned->getId(),
                'title' => $canned->getTitle(),
                'content' => $this->cleanContent($canned->getResponse()),
                'dept_id' => $canned->getDeptId()
            );
        }
        
        return $templates;
    }

    /**
     * Clean HTML content and extract text
     */
    private function cleanContent($html) {
        // Remove HTML tags but preserve some formatting
        $text = strip_tags($html);
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }
    
    /**
     * Get template by ID
     */
    public function getTemplate($template_id) {
        $canned = Canned::lookup($template_id);
        
        if (!$canned) {
            return null;
        }
        
        return array(
            'id' => $canned->getId(),
            'title' => $canned->getTitle(),
            'content' => $canned->getResponse(),
            'notes' => $canned->getNotes()
        );
    }
}
