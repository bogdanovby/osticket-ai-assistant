/**
 * AI Assistant Frontend JavaScript
 */

(function($) {
    'use strict';
    
    var AiAssistant = {
        
        config: window.AI_ASSISTANT_CONFIG || {},
        
        init: function() {
            this.injectButton();
            this.bindEvents();
            
            if (this.config.auto_suggest) {
                this.autoSuggest();
            }
        },
        
        injectButton: function() {
            // Check if button already exists
            if ($('#ai-suggest-btn').length > 0) return;
            
            // Find target to insert button (Canned Response select)
            var $target = $('#cannedResp');
            
            // If canned response dropdown not found, try finding the response textarea label
            if ($target.length === 0) {
                $target = $('label[for="response"]');
            }
            
            if ($target.length > 0) {
                var btnHtml = '<span id="ai-assistant-container" style="margin-left: 10px; vertical-align: middle;">' +
                    '<button type="button" id="ai-suggest-btn" class="btn btn-primary ai-suggest-btn">' +
                    '<i class="icon-magic"></i> AI Suggest Response' +
                    '</button>' +
                    '<span id="ai-loading" style="display:none; margin-left: 10px;">' +
                    '<i class="icon-spinner icon-spin"></i> Analyzing...' +
                    '</span>' +
                    '</span>' +
                    '<div id="ai-suggestions" style="display:none; margin-top: 10px;" class="ai-suggestions-panel"></div>';
                
                // Insert after the target
                $target.after(btnHtml);
                console.log('AI Assistant button injected');
            } else {
                console.log('AI Assistant: Target for button not found');
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            $(document).on('click', '#ai-suggest-btn', function(e) {
                e.preventDefault();
                self.suggestResponse();
            });
            
            $(document).on('click', '.ai-use-template', function(e) {
                e.preventDefault();
                var templateId = $(this).data('template-id');
                self.loadTemplate(templateId);
            });
            
            $(document).on('click', '.ai-alternative-template', function(e) {
                e.preventDefault();
                var templateId = $(this).data('template-id');
                self.loadTemplate(templateId);
            });
        },
        
        suggestResponse: function() {
            var self = this;
            var ticketId = this.getTicketId();
            
            if (!ticketId) {
                alert('Error: Could not determine Ticket ID');
                return;
            }
            
            $('#ai-suggest-btn').prop('disabled', true);
            $('#ai-loading').show();
            $('#ai-suggestions').hide();
            
            $.ajax({
                url: this.config.ajax_url + '/suggest',
                type: 'POST',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    self.handleSuggestionResponse(response);
                },
                error: function(xhr, status, error) {
                    console.error('AI Error:', xhr.responseText);
                    self.showError('Analysis failed: ' + error);
                },
                complete: function() {
                    $('#ai-suggest-btn').prop('disabled', false);
                    $('#ai-loading').hide();
                }
            });
        },
        
        handleSuggestionResponse: function(response) {
            if (!response.success) {
                this.showError(response.error || 'Unknown error from AI');
                return;
            }
            this.displaySuggestion(response);
        },
        
        displaySuggestion: function(data) {
            var html = '<div class="ai-suggestion-box">';
            
            var confidenceClass = data.confidence >= 80 ? 'high' : (data.confidence >= 60 ? 'medium' : 'low');
            
            html += '<div class="ai-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">';
            html += '<span class="ai-confidence ai-confidence-' + confidenceClass + '">Confidence: ' + data.confidence + '%</span>';
            html += '<a href="#" onclick="$(\'#ai-suggestions\').slideUp(); return false;" style="color:#999;">&times; Close</a>';
            html += '</div>';
            
            html += '<div class="ai-template-info">';
            html += '<h3 style="margin:0 0 10px 0;">' + this.escapeHtml(data.template.title) + '</h3>';
            
            if (data.reasoning) {
                html += '<div class="ai-reasoning">' + this.escapeHtml(data.reasoning) + '</div>';
            }
            
            html += '<div class="ai-template-preview">' + this.escapeHtml(data.template.content).replace(/\n/g, '<br>') + '</div>';
            
            html += '<div class="ai-actions" style="margin-top:15px;">';
            html += '<button type="button" class="btn btn-green ai-use-template" data-template-id="' + data.template.id + '">Use This Template</button>';
            html += '</div>';
            
            if (data.alternatives && data.alternatives.length > 0) {
                html += '<div class="ai-alternatives">';
                html += '<strong>Alternatives:</strong> ';
                var links = [];
                data.alternatives.forEach(function(altId) {
                    links.push('<a href="#" class="ai-alternative-template" data-template-id="' + altId + '">Template #' + altId + '</a>');
                });
                html += links.join(', ');
                html += '</div>';
            }
            
            html += '</div></div>';
            
            $('#ai-suggestions').html(html).slideDown();
        },
        
        loadTemplate: function(templateId) {
            var self = this;
            
            // Use osTicket's built-in canned response loader if possible, 
            // but here we do it manually to ensure compatibility
            $.ajax({
                url: this.config.ajax_url + '/get-template',
                type: 'POST',
                data: { template_id: templateId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.insertContent(response.template.content);
                    } else {
                        self.showError(response.error);
                    }
                }
            });
        },
        
        insertContent: function(content) {
            // Find textarea with Redactor instance
            var $textarea = $('#response');
            
            if ($textarea.length > 0 && typeof $.fn.redactor !== 'undefined' && $textarea.data('redactor')) {
                // Use Redactor 3.x API
                try {
                    var redactor = $textarea.data('redactor');
                    
                    // Use insertHtml to append content at cursor position (like native canned responses)
                    if (redactor && redactor.insertion && typeof redactor.insertion.insertHtml === 'function') {
                        redactor.insertion.insertHtml(content);
                        console.log('AI Assistant: Content inserted via Redactor insertion.insertHtml()');
                    }
                    // Fallback: append to existing content
                    else if (redactor && redactor.source && typeof redactor.source.get === 'function' && typeof redactor.source.set === 'function') {
                        var existing = redactor.source.get();
                        redactor.source.set(existing + content);
                        console.log('AI Assistant: Content appended via Redactor source.set()');
                    }
                    // Direct textarea fallback - append
                    else {
                        var existing = $textarea.val();
                        $textarea.val(existing + content);
                        $textarea.trigger('change');
                        console.log('AI Assistant: Content appended via textarea');
                    }
                } catch(e) {
                    console.error('AI Assistant: Redactor API error', e);
                    // Direct textarea fallback - append
                    var existing = $textarea.val();
                    $textarea.val(existing + content);
                    $textarea.trigger('change');
                }
            } else {
                // Plain textarea fallback - append
                if ($textarea.length > 0) {
                    var existing = $textarea.val();
                    $textarea.val(existing + content);
                    $textarea.trigger('change');
                    console.log('AI Assistant: Content appended via textarea');
                } else {
                    alert('Could not find response editor');
                }
            }
            
            // Закрываем блок без прокрутки - фиксируем позицию сразу
            var $suggestions = $('#ai-suggestions');
            var scrollPos = $(window).scrollTop();
            
            // Используем простое скрытие без анимации чтобы избежать прокрутки
            $suggestions.hide();
            $(window).scrollTop(scrollPos);
        },
        
        getTicketId: function() {
            // Extract from URL
            var match = window.location.search.match(/id=(\d+)/);
            if (match) return match[1];
            
            // Extract from hidden input
            return $('input[name="id"]').val();
        },
        
        showError: function(msg) {
            alert('AI Assistant: ' + msg);
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };
    
    $(document).ready(function() {
        // Wait a bit for other scripts to load
        setTimeout(function() {
            AiAssistant.init();
        }, 500);
    });
    
})(jQuery);
