<?php

require_once(INCLUDE_DIR . 'class.ajax.php');
require_once(INCLUDE_DIR . 'class.json.php');
require_once(dirname(__FILE__) . '/class.analyzer.php');

class AiAssistantAjaxController extends AjaxController {
    
    function access() {
        global $thisstaff;
        if (!$thisstaff)
            Http::response(403, 'Access Denied');
        return true;
    }
    
    private function getConfig() {
        $plugin = PluginManager::getInstance()->getPlugin('osticket:ai-assistant');
        if (!$plugin)
            throw new Exception('Plugin not found');
        return $plugin->getConfig();
    }
    
    function suggest() {
        $ticket_id = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
        if (!$ticket_id)
            return $this->jsonResponse(array('success' => false, 'error' => 'Ticket ID required'));
        
        try {
            $analyzer = new TicketAnalyzer($this->getConfig());
            $result = $analyzer->analyzeTicket($ticket_id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(array('success' => false, 'error' => $e->getMessage()));
        }
    }
    
    function getTemplate() {
        $template_id = $_POST['template_id'] ?? $_GET['template_id'] ?? null;
        if (!$template_id)
            return $this->jsonResponse(array('success' => false, 'error' => 'Template ID required'));
        
        try {
            $analyzer = new TicketAnalyzer($this->getConfig());
            $template = $analyzer->getTemplate($template_id);
            if (!$template)
                return $this->jsonResponse(array('success' => false, 'error' => 'Template not found'));
            return $this->jsonResponse(array('success' => true, 'template' => $template));
        } catch (Exception $e) {
            return $this->jsonResponse(array('success' => false, 'error' => $e->getMessage())) ;
        }
    }
    
    private function jsonResponse($data) {
        Http::response(200, json_encode($data), 'application/json');
    }
}
