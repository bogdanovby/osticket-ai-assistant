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
            // GPT-5 series (latest)
            'gpt-5.2' => 'GPT-5.2 (Latest, improved reasoning)',
            'gpt-5.1' => 'GPT-5.1 (Coding & agentic tasks)',
            'gpt-5.1-codex' => 'GPT-5.1 Codex (Optimized for code)',
            'gpt-5.1-codex-mini' => 'GPT-5.1 Codex Mini',
            'gpt-5.1-codex-max' => 'GPT-5.1 Codex Max (Project-scale coding)',
            'gpt-5-mini' => 'GPT-5 Mini (Fast, 400K context)',
            'gpt-5-nano' => 'GPT-5 Nano (Fastest, cheapest)',
            // Reasoning models (o-series) - think longer before responding
            'o3' => 'o3 (Most advanced reasoning)',
            'o3-mini' => 'o3-mini (Cost-efficient reasoning)',
            'o4-mini' => 'o4-mini (Latest compact reasoning)',
            'o1' => 'o1 (Extended reasoning)',
            'o1-mini' => 'o1-mini (Compact reasoning)',
            // GPT-4.1 series - improved coding & long context
            'gpt-4.1' => 'GPT-4.1 (Best for coding, 1M context)',
            'gpt-4.1-mini' => 'GPT-4.1 Mini (Balanced)',
            'gpt-4.1-nano' => 'GPT-4.1 Nano (Fastest)',
            // GPT-4o series - multimodal
            'gpt-4o' => 'GPT-4o (Multimodal, capable)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
            // Legacy models
            'gpt-4-turbo' => 'GPT-4 Turbo (Legacy)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest, legacy)'
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

        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-api-provider.js'); ?></script>
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
                'hint' => __('Your API key. Get it for example from https://platform.openai.com/api-keys')
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
            'temperature' => new TextboxField(array(
                'label' => __('Temperature'),
                'default' => '0.3',
                'required' => false,
                'configuration' => array(
                    'size' => 10,
                    'length' => 4
                ),
                'hint' => __('Advanced: Controls response randomness (0.0-2.0). Lower = more deterministic. Default: 0.3')
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

        if (isset($config['temperature'])) {
            $config['temperature'] = (float) $config['temperature'];
            if (0.0 > $config['temperature'] || $config['temperature'] > 2.0) {
                $errors['temperature'] = __('Value is out of range');
                $result = false;
            }
        }

        return $result;
    }
}
