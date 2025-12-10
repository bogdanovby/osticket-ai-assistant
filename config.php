<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

/**
 * Custom field for Model selection that switches between dropdown and textbox
 */
class AIAssistantModelField extends TextboxField {
    static $widget = 'AIAssistantModelWidget';
}

class AIAssistantModelWidget extends Widget {
    function render($options=array()) {
        $name = $this->name;
        $value = $this->value;
        $config = $this->field->getConfiguration();
        
        // OpenAI models list
        $models = array(
            'gpt-4o' => 'GPT-4o (Most capable, expensive)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest)'
        );
        ?>
        <input type="hidden" name="<?php echo $name; ?>" id="model_value" value="<?php echo Format::htmlchars($value); ?>" />
        
        <!-- Dropdown for OpenAI -->
        <select id="model_select" class="model-select-dropdown" style="width: 350px;">
            <?php foreach ($models as $model_id => $model_name): ?>
                <option value="<?php echo $model_id; ?>" <?php if ($value === $model_id) echo 'selected="selected"'; ?>>
                    <?php echo Format::htmlchars($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Textbox for Custom -->
        <input type="text" id="model_text" class="model-text-input" 
               value="<?php echo Format::htmlchars($value); ?>" 
               placeholder="Enter model name (e.g., gpt-4o-mini)"
               style="width: 350px; padding: 5px;" />

        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config.js'); ?></script>
        <?php
    }
}

class AiAssistantConfig extends PluginConfig {
    
    function getOptions() {
        return array(
            'api_provider' => new ChoiceField(array(
                'label' => __('API Provider'),
                'default' => 'openai',
                'choices' => array(
                    'openai' => 'Open AI',
                    'custom' => 'Custom'
                ),
                'hint' => __('Choose API provider type')
            )),
            'api_key' => new TextboxField(array(
                'label' => __('API Key'),
                'required' => true,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-...'
                ),
                'hint' => __('Your API key')
            )),
            'api_url' => new TextboxField(array(
                'label' => __('API URL'),
                'required' => false,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'https://api.example.com/v1/chat/completions'
                ),
                'hint' => __('Custom API endpoint URL (compatible with OpenAI)')
            )),
            'model' => new AIAssistantModelField(array(
                'label' => __('Model Name'),
                'default' => 'gpt-4o-mini',
                'required' => true,
                'hint' => __('Select or enter the model name to use for analysis')
            )),
            'auto_suggest' => new BooleanField(array(
                'label' => __('Auto-suggest on ticket view'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Automatically suggest response when viewing a ticket (may increase API costs)')
                )
            )),
            'min_confidence' => new TextboxField(array(
                'label' => __('Minimum Confidence Score'),
                'default' => '70',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Only show suggestions with confidence above this score (0-100)')
            )),
            'max_templates' => new TextboxField(array(
                'label' => __('Max Templates to Analyze'),
                'default' => '10',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Maximum number of canned responses to send to AI for analysis')
            )),
            'timeout' => new TextboxField(array(
                'label' => __('API Timeout (seconds)'),
                'default' => '30',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Maximum time to wait for OpenAI response')
            )),
            'enable_logging' => new BooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Log AI requests and responses for debugging')
                )
            ))
        );
    }
    
    function getFormOptions() {
        return array(
            'title' => __('AI Assistant Configuration'),
            'instructions' => __('Configure AI API integration for intelligent ticket response suggestions.')
        );
    }
    
    function pre_save(&$config, &$errors) {

        $result = true;
        if ('openai' === $config['api_provider']) {
            // For OpenAI provider, set default API URL
            $config['api_url'] = 'https://api.openai.com/v1/chat/completions';
        }

        // Validate API URL
        if (empty($config['api_url'])) {
            $errors['api_url'] = __('API URL is required for Custom provider');
            $result = false;
        }

        return $result;
    }
}
