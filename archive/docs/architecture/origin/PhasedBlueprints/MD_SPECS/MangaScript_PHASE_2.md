# MangaScript - Phase 2: Multi-Modal AI Orchestration

**Status**: IN_PROGRESS
**Source**: `Blueprint/MangaScript/PHASE_2_AI_ORCHESTRATION.md`

## Objectives
- [ ] Modal AI Orchestration
- [ ] step generation tasks and multi-modal inputs, fully integrated with the unified LLM config.
- [ ] Modal Vision Integration
- [ ] Based Prompts
- [ ] Script generation from text + reference images.
- [ ] Streaming script output visible in the SuperPHP workspace.
- [ ] Automatic routing based on model capabilities.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.

### Technical Spec: AI Orchestration (Streaming)
Use `openai-php/client` for streaming completions. This enables real-time UI updates in the Superpowers SPA.

```php
$stream = $client->chat()->createStreamed([
    'model' => 'gpt-4',
    'messages' => [...],
]);

foreach($stream as $response) {
    $text = $response->choices[0]->delta->content;
    if ($text) {
        // Broadcast via Nexus or return chunk
    }
}
```
