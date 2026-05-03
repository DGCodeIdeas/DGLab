<?php
$providers = [
    'app/Services/MangaScript/AI/Providers/AzureOpenAiProvider.php' => ['id' => 'azure_openai', 'name' => 'Azure OpenAI', 'models' => 'availableModels', 'default' => 'gpt-4o'],
    'app/Services/MangaScript/AI/Providers/BedrockProvider.php' => ['id' => 'bedrock', 'name' => 'Amazon Bedrock', 'models' => 'availableModels', 'default' => 'anthropic.claude-3-haiku'],
    'app/Services/MangaScript/AI/Providers/CohereProvider.php' => ['id' => 'cohere', 'name' => 'Cohere', 'models' => 'availableModels', 'default' => 'command-r-plus'],
    'app/Services/MangaScript/AI/Providers/GroqProvider.php' => ['id' => 'groq', 'name' => 'Groq', 'models' => 'availableModels', 'default' => 'llama-3.3-70b-versatile'],
    'app/Services/MangaScript/AI/Providers/XaiProvider.php' => ['id' => 'xai', 'name' => 'xAI', 'models' => 'availableModels', 'default' => 'grok-2-1212'],
];

foreach ($providers as $path => $data) {
    $content = file_get_contents($path);
    if (strpos($content, 'public function chat(') !== false) {
        echo "Skipping $path\n";
        continue;
    }

    $methods = "\n    public function getId(): string { return '{$data['id']}'; }\n";
    $methods .= "    public function getName(): string { return '{$data['name']}'; }\n";
    $methods .= "    public function getModels(): array { return $this->{$data['models']}; }\n";
    $methods .= "    protected function getDefaultModel(): string { return '{$data['default']}'; }\n";
    $methods .= "    public function chat(string $model, array $messages, array $options = []): \DGLab\Services\MangaScript\AI\LLMResponse { return $this->sendWithHistory($messages, null, array_merge($options, ['model' => $model])); }\n";
    $methods .= "    public function chatStream(string $model, array $messages, array $options = []): \Generator { if (false) yield; throw new \RuntimeException('Not implemented'); }\n";

    $pos = strrpos($content, '}');
    $newContent = substr($content, 0, $pos) . $methods . "}\n";
    file_put_contents($path, $newContent);
    echo "Fixed $path\n";
}
