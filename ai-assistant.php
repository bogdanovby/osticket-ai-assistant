<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.dispatcher.php');
require_once(INCLUDE_DIR . 'class.json.php');
require_once('config.php');
require_once('class.api-client.php');

// --- ГЛОБАЛЬНЫЕ ФУНКЦИИ-ОБРАБОТЧИКИ ---

function ai_assistant_handle_suggest() {
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
            Http::response(200, json_encode(array(
                'success' => false,
                'error' => 'FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
            )), 'application/json');
        }
    });

    global $thisstaff;
    
    if (!$thisstaff) {
        Http::response(403, 'Access Denied');
        return;
    }
    
    $ticket_id = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
    
    if (!$ticket_id) {
        Http::response(200, json_encode(array('success' => false, 'error' => 'Ticket ID required')), 'application/json');
        return;
    }
    
    try {
        $plugin = null;
        $installed_plugins = PluginManager::allInstalled();
        
        foreach ($installed_plugins as $path => $info) {
            if (is_object($info)) {
                $manifest = isset($info->info) ? $info->info : array();
                if (isset($manifest['id']) && $manifest['id'] == 'osticket:ai-assistant') {
                    $plugin = $info;
                    break;
                }
            } elseif (is_array($info)) {
                if (isset($info['id']) && $info['id'] == 'osticket:ai-assistant') {
                    $plugin = PluginManager::getInstance($path);
                    break;
                }
            }
        }
        
        if (!$plugin) {
             $plugin = PluginManager::getInstance('plugins/ai-assistant');
        }
        
        if (!$plugin || !is_a($plugin, 'Plugin')) {
             throw new Exception('Plugin instance not found');
        }
        
        // Получаем активный инстанс плагина, чтобы считать настройки
        $config = null;
        $instances = $plugin->getInstances();
        foreach ($instances as $instance) {
            if ($instance->isEnabled()) { // <--- ИСПРАВЛЕНО: было isActive()
                $config = $instance->getConfig();
                break;
            }
        }
        
        if (!$config) {
             $config = $plugin->getConfig();
        }
        
        if (!class_exists('Ticket')) {
             require_once(INCLUDE_DIR . 'class.ticket.php');
        }
        
        $analyzer_path = dirname(__FILE__) . '/class.analyzer.php';
        if (!file_exists($analyzer_path)) {
            throw new Exception('Analyzer file not found');
        }
        require_once($analyzer_path);
        
        if (!class_exists('TicketAnalyzer')) {
            throw new Exception('Class TicketAnalyzer not found');
        }
        
        $analyzer = new TicketAnalyzer($config);
        $result = $analyzer->analyzeTicket($ticket_id);
        
        Http::response(200, json_encode($result), 'application/json');
        
    } catch (Throwable $e) {
        Http::response(200, json_encode(array(
            'success' => false, 
            'error' => 'EXCEPTION: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        )), 'application/json');
    }
}

function ai_assistant_handle_template() {
    global $thisstaff;
    
    $template_id = $_POST['template_id'] ?? $_GET['template_id'] ?? null;
    
    if (!$template_id) {
        Http::response(200, json_encode(array('success' => false, 'error' => 'Template ID required')), 'application/json');
        return;
    }
    
    try {
        $plugin = null;
        $installed_plugins = PluginManager::allInstalled();
        foreach ($installed_plugins as $path => $info) {
             if (is_object($info)) {
                $manifest = isset($info->info) ? $info->info : array();
                if (isset($manifest['id']) && $manifest['id'] == 'osticket:ai-assistant') {
                    $plugin = $info;
                    break;
                }
            } elseif (is_array($info)) {
                if (isset($info['id']) && $info['id'] == 'osticket:ai-assistant') {
                    $plugin = PluginManager::getInstance($path);
                    break;
                }
            }
        }
        
        if (!$plugin) {
             $plugin = PluginManager::getInstance('plugins/ai-assistant');
        }
        
        if (!$plugin) throw new Exception('Plugin instance not found');
        
        $config = null;
        $instances = $plugin->getInstances();
        foreach ($instances as $instance) {
            if ($instance->isEnabled()) { // <--- ИСПРАВЛЕНО: было isActive()
                $config = $instance->getConfig();
                break;
            }
        }
        if (!$config) $config = $plugin->getConfig();
        
        require_once(dirname(__FILE__) . '/class.analyzer.php');
        $analyzer = new TicketAnalyzer($config);
        $template = $analyzer->getTemplate($template_id);
        
        if (!$template) {
             Http::response(200, json_encode(array('success' => false, 'error' => 'Template not found')), 'application/json');
             return;
        }
        
        Http::response(200, json_encode(array('success' => true, 'template' => $template)), 'application/json');
        
    } catch (Exception $e) {
        Http::response(200, json_encode(array('success' => false, 'error' => $e->getMessage())), 'application/json');
    }
}

// --- КЛАСС ПЛАГИНА ---

class AiAssistantPlugin extends Plugin {
    var $config_class = 'AiAssistantConfig';
    
    function bootstrap() {
        Signal::connect('object.view', array($this, 'onObjectView'));
        Signal::connect('ajax.scp', array($this, 'registerAjax'));
    }
    
    function registerAjax($dispatcher, $data=null) {
        $dispatcher->append(
            url_post('^/ai-assistant/suggest', 'ai_assistant_handle_suggest')
        );
        $dispatcher->append(
            url_post('^/ai-assistant/get-template', 'ai_assistant_handle_template')
        );
    }
    
    function onObjectView($object, $type=null) {
        if ($object && is_a($object, 'Ticket')) {
            $this->loadAssets($object);
        }
    }
    
    function loadAssets($object) {
        $config = $this->getConfig();
        $path = dirname(__FILE__);
        
        echo '<style type="text/css">';
        @readfile($path . '/css/ai-assistant.css');
        echo '</style>';
        
        echo '<script type="text/javascript">
            var AI_ASSISTANT_CONFIG = {
                ajax_url: "ajax.php/ai-assistant",
                auto_suggest: ' . ($config->get('auto_suggest') ? 'true' : 'false') . ',
                min_confidence: ' . intval($config->get('min_confidence')) . '
            };
        </script>';
        
        echo '<script type="text/javascript">';
        @readfile($path . '/js/ai-assistant.js');
        echo '</script>';
    }
}
