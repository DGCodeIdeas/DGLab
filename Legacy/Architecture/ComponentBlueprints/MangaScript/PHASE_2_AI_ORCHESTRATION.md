# Phase 2: Multi-Modal AI Orchestration

## Goal
To evolve the AI integration layer into a sophisticated orchestration engine capable of handling complex, multi-step generation tasks and multi-modal inputs, fully integrated with the unified LLM config.

## Key Components

### 1. Multi-Modal Vision Integration
- **Vision Support**: Accept images as part of `source_material`.
- **Model Analysis**: Use Vision models (e.g., Claude 3.5 Sonnet, GPT-4o) to analyze character sheets or sketches.
- **Prompt Logic**: Inject visual analysis into generated panel descriptions.

### 2. Intelligent Routing Engine 3.0
- **Context Routing**: Route large novels to "Massive" tier models (Gemini 1.5 Pro).
- **Vision Routing**: Automatically select Vision-capable models for image inputs.
- **Cost Balancing**: Choose providers based on user-configured `quality` vs. `cost` preferences.

### 3. AIStreamingResponse via SuperPHP Bridge
- **Streaming API**: Implement `AIStreamingResponse` implementing `Iterator`.
- **Reactive UI**: Update SuperPHP reactive state (`@persist($scriptContent)`) as chunks arrive.
- **JSON Parsing**: Support incremental parsing of streaming JSON objects for UI structured feedback.

### 4. Advanced Template-Based Prompts
- **Prompt Files**: Move hardcoded strings to `resources/prompts/mangascript/*.latte`.
- **Dynamic Style**: Inject Shonen/Shojo/Seinen style guidelines based on user selection.

## Technical Requirements
- **Providers**: Refactor to use unified providers from `llm_unified.php`.
- **Latency Tracking**: Record start/stop times and cost per token using `AuditService`.

## Success Criteria
- Script generation from text + reference images.
- Streaming script output visible in the SuperPHP workspace.
- Automatic routing based on model capabilities.
