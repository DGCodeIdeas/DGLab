# Phase 2: AI Orchestration & Multi-Modal Support

## Goal
To evolve the AI integration layer into a sophisticated orchestration engine capable of handling complex, multi-step generation tasks and multi-modal inputs.

## Key Components

### 1. Multi-Modal Vision Integration
- Enable the service to accept images as part of the `source_material`.
- Use Vision models (e.g., Claude 3.5 Sonnet, GPT-4o) to analyze character reference sheets or setting sketches.
- Integrate visual analysis into the prompt generation logic for more consistent panel descriptions.

### 2. Intelligent Routing Engine 2.0
- Enhanced logic for selecting providers based on:
    - **Context Window**: Automatically routing large novels to models with 128k+ tokens.
    - **Visual Capabilities**: Routing requests with image inputs to Vision-capable models.
    - **Cost/Performance Ratio**: Balancing quality and speed based on user preferences.

### 3. Streaming Response Support
- Implementation of a streaming interface for real-time script generation.
- Integration with Server-Sent Events (SSE) or WebSockets to provide a "typing" effect in the UI.
- Support for "incremental" JSON parsing of streaming AI outputs.

### 4. Advanced Prompt Engineering System
- Move prompts from hardcoded strings to template-based files (`resources/prompts/mangascript/*.latte` or similar).
- Support for dynamic "few-shot" examples based on the selected manga style (e.g., Shonen vs. Shojo).

## Technical Requirements
- **Providers**: Update `AnthropicProvider`, `OpenAiProvider`, and `GoogleProvider` to support multi-modal payloads.
- **Streaming**: Implementation of an `AIStreamingResponse` object that implements `Iterator`.

## Success Criteria
- Ability to generate a script based on a combination of text and reference images.
- Successful streaming of a script generation process to the frontend.
- Routing engine correctly selects a Vision model when an image is provided.
