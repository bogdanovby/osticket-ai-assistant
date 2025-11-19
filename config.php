<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

class AiAssistantConfig extends PluginConfig {
    
    function getOptions() {
        return array(
            'api_key' => new TextboxField(array(
                'label' => __('OpenAI API Key'),
                'required' => true,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-...'
                ),
                'hint' => __('Your OpenAI API key. Get it from https://platform.openai.com/api-keys')
            )),
            'model' => new ChoiceField(array(
                'label' => __('OpenAI Model'),
                'default' => 'gpt-4o-mini',
                'choices' => array(
                    'gpt-4o' => 'GPT-4o (Most capable, expensive)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest)'
                ),
                'hint' => __('Choose the model to use for analysis')
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
            'instructions' => __('Configure OpenAI integration for intelligent ticket response suggestions.')
        );
    }
}

?>
