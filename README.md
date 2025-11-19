# AI Assistant Plugin for osTicket

## Overview
The AI Assistant plugin integrates OpenAI's GPT models into osTicket to assist support agents. It analyzes incoming ticket content and suggests the most appropriate "Canned Response" from the system's database, significantly reducing response time.

## Features
- **Automated Analysis**: Analyzes ticket subject, body, and thread history using OpenAI.
- **Smart Matching**: Matches tickets with existing Canned Responses based on semantic meaning, not just keywords.
- **Context Aware**: Considers ticket priority and department.
- **One-Click Insertion**: Agents can preview the suggested response and insert it into the reply editor with a single click.
- **Configurable**: Adjustable confidence threshold, max templates limit, and model selection (GPT-4o, GPT-3.5, etc.).

## Installation

1. **Upload**: Copy the `ai-assistant` folder into your osTicket `include/plugins/` directory.
2. **Permissions**: Ensure the web server user (e.g., `www-data`) has read access to the files.
3. **Install**: 
   - Log in to osTicket Admin Panel.
   - Go to **Manage -> Plugins**.
   - Click **Add New Plugin**.
   - Click **Install** next to "AI Assistant".
4. **Enable**: Click on the installed plugin and change "Status" to **Active**.

## Configuration

Go to **Manage -> Plugins -> AI Assistant -> Config**.

- **OpenAI API Key**: Your secret key starting with `sk-...` (required).
- **OpenAI Model**: Select the model (e.g., `gpt-4o-mini` for speed/cost or `gpt-4o` for quality).
- **Auto-suggest**: If enabled, analysis starts automatically when viewing a ticket.
- **Minimum Confidence Score**: Threshold (0-100) to filter weak suggestions.
- **Max Templates**: How many canned responses to send to AI for comparison (affects token usage).

## Architecture

### Files
- `plugin.php`: Entry point and metadata.
- `ai-assistant.php`: Main plugin class (`AiAssistantPlugin`). Handles hooks, asset injection, and AJAX routing.
- `config.php`: Configuration form definition (`AiAssistantConfig`).
- `class.analyzer.php`: Core logic (`TicketAnalyzer`). Orchestrates data gathering and AI analysis.
- `class.openai.php`: API client (`OpenAIClient`). Handles communication with OpenAI.
- `js/ai-assistant.js`: Frontend logic. Injects button, handles clicks, displays modal.
- `css/ai-assistant.css`: Styles for the suggestion modal.

### Logic Flow
1. **Injection**: On ticket view (`tickets.php`), `AiAssistantPlugin` injects JS/CSS.
2. **Trigger**: User clicks "AI Suggest Response".
3. **Request**: JS sends AJAX POST to `/scp/ajax.php/ai-assistant/suggest` with `ticket_id`.
4. **Dispatch**: osTicket dispatcher routes request to `ai_assistant_handle_suggest` global function.
5. **Analysis**:
   - `TicketAnalyzer` fetches ticket data (subject, body).
   - It fetches active Canned Responses (global + department specific).
   - `OpenAIClient` sends this data to ChatGPT with a prompt to find the best match.
6. **Response**: JSON with the best template ID and reasoning is returned.
7. **UI**: JS shows a modal with the suggested response. User confirms to insert into editor.

## Troubleshooting

- **500 Error**: Check Apache error logs. Ensure file permissions are correct.
- **401 Error**: Incorrect API Key. Check whitespace or validity of the key.
- **No canned responses**: Ensure you have active Canned Responses in the Knowledgebase.

## License
MIT License

## Authors
- **Pavel Bahdanau**
- **Anatoly Melnikov**

## License
This project is licensed under the GNU General Public License v2.0 - see the [LICENSE](LICENSE) file for details.
